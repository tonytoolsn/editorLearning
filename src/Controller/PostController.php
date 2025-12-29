<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Upload;
use App\Form\PostType;
use App\Service\PostService;
use App\Service\EditorService;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use HTMLPurifier;
use HTMLPurifier_Config;

#[Route('/post')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly HtmlSanitizerInterface $htmlSanitizer,
        private readonly EntityManagerInterface $em,
        private readonly PostService $service,
        private readonly EditorService $editorService,
    ) {}

    #[Route(name: 'app_post_index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        return $this->render('post/index.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 內容 過濾
            // 圖片 處存檢查
            // $config = HTMLPurifier_Config::createDefault();
            // $purifier = new HTMLPurifier($config);
            // $cleanHtml = $purifier->purify($post->getContent());

            $cleanHtml = $this->htmlSanitizer->sanitize($post->getContent());
            $post->setContent($cleanHtml);

            $this->em->persist($post);
            $this->em->flush();

            $this->editorService->attachImagesToEntity('Post', 'content', $post->getId(), $post->getContent());

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 內容 過濾
            // 圖片 處存檢查
            // $config = HTMLPurifier_Config::createDefault();
            // $purifier = new HTMLPurifier($config);
            // $cleanHtml = $purifier->purify($post->getContent());
            $content = $post->getContent();
            $cleanHtml = $this->htmlSanitizer->sanitize($post->getContent());
            $post->setContent($cleanHtml);

            $this->editorService->syncImagesWithEntity('Post', 'content', $post->getId(), $content);

            $this->em->persist($post);
            $this->em->flush();

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    // #[Route('/{id}', name: 'app_post_show', methods: ['GET'])]
    // public function show(Post $post): Response
    // {
    //     return $this->render('post/show.html.twig', [
    //         'post' => $post,
    //     ]);
    // }

    // #[Route('/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    // public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    // {
    //     $form = $this->createForm(PostType::class, $post);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $entityManager->flush();

    //         return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    //     }

    //     return $this->render('post/edit.html.twig', [
    //         'post' => $post,
    //         'form' => $form,
    //     ]);
    // }

    // #[Route('/{id}', name: 'app_post_delete', methods: ['POST'])]
    // public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    // {
    //     if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->getPayload()->getString('_token'))) {
    //         $entityManager->remove($post);
    //         $entityManager->flush();
    //     }

    //     return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    // }
}
