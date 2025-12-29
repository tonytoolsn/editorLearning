<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\UploadService;
use App\Service\EditorService;
use Symfony\Component\Routing\RouterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class EditorController extends AbstractController
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
        private readonly UploadService $uploadService,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
        private readonly EditorService $service
    ) {}

    #[Route('/api/editor/images', name: 'api_editor_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        $profileKey = $request->request->get('profile');
        $file = $request->files->get('file');

        if (!$profileKey) {
            return $this->json(['error' => '缺少上傳配置參數'], 400);
        }

        if (!$file) {
            return $this->json(['error' => '沒有上傳檔案'], 400);
        }

        try {
            $upload = $this->service->uploadImage($profileKey, $file);

            return $this->json([
                'url' => $this->service->generateAccessUrl($profileKey, $upload),
                'uuid' => $upload->getUuid()
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logger->error('Upload failed: ' . $e->getMessage());
            return $this->json(['error' => '系統錯誤'], 500);
        }
    }

    #[Route('/editor/images/{uuid}', name: 'editor_image_show', methods: ['GET'])]
    public function showImage($uuid): Response
    {
        // 假設有 service 取得檔案完整路徑
        $filePath = $this->service->getFilePathByUuid($uuid);

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('檔案不存在');
        }

        $response = new BinaryFileResponse($filePath);

        // 可選：設定檔案下載檔名
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE, // 直接顯示，不下載
            basename($filePath)
        );

        return $response;
    }

    #[Route('/api/editor/images/{uuidOrFilename}', name: 'api_editor_image_delete', methods: ['DELETE'])]
    public function deleteImage(string $uuidOrFilename): Response
    {
        try {
            $this->service->deleteImage($uuidOrFilename);
            return $this->json([
                'status' => 'success',
                'message' => '圖片已成功刪除'
            ]);
        } catch (NotFoundHttpException | AccessDeniedHttpException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => '系統錯誤',
            ], 500);
        }
    }
}
