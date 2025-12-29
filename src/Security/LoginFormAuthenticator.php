<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class LoginFormAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        // TODO: Implement supports() method.
        // return $request->isMethod('POST') && $request->getPathInfo() === '/login';
        return $request->isMethod('POST') && $request->attributes->get('_route') === 'app_login';
    }

    public function authenticate(Request $request): Passport
    {
        // TODO: Implement authenticate() method.

        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');
        $recaptcha = $request->request->get('g-recaptcha-response', '');

        // 驗證 Google reCAPTCHA
        $response = $this->client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $_ENV['GOOGLE_RECAPTCHA_SECRET_KEY'],
                'response' => $recaptcha,
                'remoteip' => $request->getClientIp(),
            ],
        ]);
        $result = json_decode($response->getContent(), true);

        if (!$result['success']) {
            $this->logger->warning('Login failed due to invalid captcha', [
                'username' => $username,
                'ip' => $request->getClientIp(),
            ]);
            throw new CustomUserMessageAuthenticationException('驗證碼錯誤');
        }

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // TODO: Implement onAuthenticationSuccess() method.
        $user = $token->getUser();

        // 登录成功记录日志
        $this->logger->info('User logged in successfully', [
            'email' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        // 如果用户之前访问受保护页面 → 跳回
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // 默认跳转到 dashboard
        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // TODO: Implement onAuthenticationFailure() method.
        $this->logger->warning('Login failed', [
            'username' => $request->request->get('_username'),
            'ip' => $request->getClientIp(),
            'message' => $exception->getMessage(),
        ]);

        return parent::onAuthenticationFailure($request, $exception);
    }

    //    public function start(Request $request, ?AuthenticationException $authException = null): Response
    //    {
    //        /*
    //         * If you would like this class to control what happens when an anonymous user accesses a
    //         * protected page (e.g. redirect to /login), uncomment this method and make this class
    //         * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
    //         *
    //         * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
    //         */
    //    }
}
