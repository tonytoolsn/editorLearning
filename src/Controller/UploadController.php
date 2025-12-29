<?php

namespace App\Controller;

use App\Entity\Upload;
use App\Entity\Post;
use App\Entity\User;
use App\Form\UploadType;
use App\Repository\UploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Service\UploadService;
use Symfony\Component\Routing\RouterInterface;
use Psr\Log\LoggerInterface;

// #[Route('/upload')]
final class UploadController extends AbstractController
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
        private readonly UploadService $uploadService,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/api/upload/new', name: 'app_upload_new', methods: ['POST'])]
    // #[Route('/api/upload_image', name: 'api_upload_image', methods: ['POST'])]
    public function new(Request $request): Response
    {
        $profileKey = $request->request->get('profile');
        $file = $request->files->get('file');

        dd($$profileKey, $file);

        if (!$profileKey) {
            return $this->json(['error' => '缺少上傳配置參數'], 400);
        }

        if (!$file) {
            return $this->json(['error' => '沒有上傳檔案'], 400);
        }

        try {
            $upload = $this->uploadService->uploadEditorImage($profileKey, $file);
            $url = $this->router->generate(
                'app_upload_show',  // 路由名稱
                ['uuid' => $upload->getUuid()], // 路由參數
                RouterInterface::ABSOLUTE_URL  // 生成完整 URL，例如 https://example.com/path
            );

            return $this->json([
                'url' => $url,
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logger->error('Upload failed: ' . $e->getMessage());
            return $this->json(['error' => '系統錯誤'], 500);
        }
    }

    #[Route('/{id}', name: 'app_upload_delete', methods: ['POST'])]
    // #[Route('/api/delete_image', name: 'api_delete_image', methods: ['POST'])]
    public function deleteImage(Request $request): Response
    {
        // $user = $this->getUser();
        // if (!$user || !$user->hasRole('ROLE_CAN_DELETE')) {
        //     return new JsonResponse(['error' => '沒有刪除權限'], 403);
        // }

        $uuid = $request->request->get('uuid');
        $image = $this->em->getRepository(Upload::class)->find($uuid);

        if (!$image) {
            return new JsonResponse(['error' => '圖片不存在'], 404);
        }

        // 軟刪除
        $image->setDeleted(true);
        $this->em->flush();

        return new JsonResponse(['success' => true]);

        // 刪除資料庫中的記錄
        // $em->remove($image);
        // $em->flush();
        // return new JsonResponse(['success' => true]);

        // 刪除實體檔案
        // $src = $request->request->get('src');
        // $filePath = $this->getParameter('kernel.project_dir') . '/public' . parse_url($src, PHP_URL_PATH);

        // if (file_exists($filePath)) {
        //     unlink($filePath);
        //     return new JsonResponse(['success' => true]);
        // }

        // return new JsonResponse(['error' => '檔案不存在'], 404);
    }

    #[Route('/api/image/{uuid}', name: 'app_upload_show', methods: ['GET'])]
    // #[Route('/api/image/{uuid}/{originalName}', name: 'api_image', methods: ['GET'])]
    public function getImage(string $uuid): BinaryFileResponse
    {
        $upload = $this->em->getRepository(Upload::class)->findOneBy(['uuid' => $uuid]);

        if (!$upload) {
            throw $this->createNotFoundException('圖片不存在或已刪除');
        }

        // 權限檢查
        // $user = $this->getUser();
        // if (!$user || !$user->hasRole('ROLE_CAN_VIEW_IMAGE')) {
        //     throw $this->createAccessDeniedException('沒有權限觀看圖片');
        // }

        $uploadsDir = $this->getParameter('uploads_directory');
        $filePath = $uploadsDir . '/' . $upload->getFilename();

        $path = $this->getParameter('uploads_directory') . '/' . $upload->getStoredFilename();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('檔案不存在');
        }

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => $upload->getMimeType(),
        ]);

        // $response = new BinaryFileResponse($filePath);
        // $response->setContentDisposition(
        //     ResponseHeaderBag::DISPOSITION_INLINE,
        //     $image->getOriginalFilename()   // 使用上傳原始名稱
        // );

        // return $response;
    }

    #[Route('/upload', name: 'app_upload_index', methods: ['GET'])]
    public function index(UploadRepository $uploadRepository): Response
    {
        return $this->render('upload/index.html.twig', [
            'uploads' => $uploadRepository->findAll(),
        ]);
    }

    // #[Route('/new', name: 'app_upload_new', methods: ['GET', 'POST'])]
    // public function new(Request $request, EntityManagerInterface $entityManager): Response
    // {
    //     $upload = new Upload();
    //     $form = $this->createForm(UploadType::class, $upload);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $entityManager->persist($upload);
    //         $entityManager->flush();

    //         return $this->redirectToRoute('app_upload_index', [], Response::HTTP_SEE_OTHER);
    //     }

    //     return $this->render('upload/new.html.twig', [
    //         'upload' => $upload,
    //         'form' => $form,
    //     ]);
    // }

    // #[Route('/{id}', name: 'app_upload_show', methods: ['GET'])]
    // public function show(Upload $upload): Response
    // {
    //     return $this->render('upload/show.html.twig', [
    //         'upload' => $upload,
    //     ]);
    // }

    // #[Route('/{id}/edit', name: 'app_upload_edit', methods: ['GET', 'POST'])]
    // public function edit(Request $request, Upload $upload, EntityManagerInterface $entityManager): Response
    // {
    //     $form = $this->createForm(UploadType::class, $upload);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $entityManager->flush();

    //         return $this->redirectToRoute('app_upload_index', [], Response::HTTP_SEE_OTHER);
    //     }

    //     return $this->render('upload/edit.html.twig', [
    //         'upload' => $upload,
    //         'form' => $form,
    //     ]);
    // }

    // #[Route('/{id}', name: 'app_upload_delete', methods: ['POST'])]
    // public function delete(Request $request, Upload $upload, EntityManagerInterface $entityManager): Response
    // {
    //     if ($this->isCsrfTokenValid('delete'.$upload->getId(), $request->getPayload()->getString('_token'))) {
    //         $entityManager->remove($upload);
    //         $entityManager->flush();
    //     }

    //     return $this->redirectToRoute('app_upload_index', [], Response::HTTP_SEE_OTHER);
    // }
}
