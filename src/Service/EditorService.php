<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Upload;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

class EditorService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
        private readonly UploadProfilesService $uploadProfiles,
        private readonly UploadService $uploadService,
        private readonly Utils $utils,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
    ) {}

    private function validateFile($file, array $rules): void
    {
        if (isset($rules['maxSize']) && $file['size'] > $rules['maxSize']) {
            throw new BadRequestHttpException(
                '檔案過大，請上傳不超過 ' . ($rules['maxSize'] / 1024 / 1024) . ' MB 的檔案。'
            );
        }

        if (isset($rules['mime']) && !in_array($file['mime'], $rules['mime'], true)) {
            throw new BadRequestHttpException(
                '檔案類型不正確，允許的格式: ' . implode(', ', $rules['mime'])
            );
        }
    }

    // Summernote / editor 上傳：存檔 + 資料庫
    public function uploadImage(string $profileKey, UploadedFile $uploadedFile)
    {

        $file = [
            'size' => $uploadedFile->getSize(),
            'mime' => $uploadedFile->getMimeType(),
            'clientOriginalName' => $uploadedFile->getClientOriginalName()
        ];

        $profile = $this->uploadProfiles->getProfile($profileKey);

        // 驗證權限
        // $user = $this->getUser();
        // if (!$user || !$user->hasRole('ROLE_CAN_UPLOAD')) {
        //     throw new BadRequestHttpException(['error' => '沒有上傳權限'], 403);
        // }

        // Step 1: 驗證檔案
        $this->validateFile($file, $profile['rules']);

        // Step 2: 存檔到檔案系統
        $storedFilename = $this->uploadService->storeFile($uploadedFile, $profile['path']);

        // Step 3: 寫入資料庫，回傳 紀錄
        return $this->uploadService->createUploadRecord($file, $storedFilename, $profile);
    }

    public function generateAccessUrl(string $profileKey, Upload $upload): string
    {
        $profile = $this->uploadProfiles->getProfile($profileKey);

        if ($profile['visibility'] === 'public') {
            $request = $this->requestStack->getCurrentRequest();

            if (!$request) {
                throw new \RuntimeException('無法取得當前 Request');
            }

            return $request->getSchemeAndHttpHost() . $profile['publicUrlBase'] . '/' . $upload->getStoredFilename();
        }

        return $this->router->generate(
            'app_editor_image_show',  // 路由名稱
            ['uuid' => $upload->getUuid()], // 路由參數
            RouterInterface::ABSOLUTE_URL  // 生成完整 URL，例如 https://example.com/path
        );
    }

    /**
     * 根據 UUID 取得檔案完整路徑
     *
     * @param string $uuid
     * @return string
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function getFilePathByUuid(string $uuid): string
    {
        $upload = $this->em->getRepository(Upload::class)->findOneBy(['uuid' => $uuid]);

        if (!$upload) {
            throw new NotFoundHttpException('檔案不存在');
        }

        // 取得 profile key
        $profileKey = $upload->getEntityType() . '_' . $upload->getFieldName();
        $profile = $this->uploadProfiles->getProfile($profileKey);

        if (!$profile) {
            throw new NotFoundHttpException('上傳設定不存在');
        }

        // 權限判斷，如果 private，需要檢查使用者
        if ($profile['visibility'] === 'private') {
            // 例如你有 Security Service
            // if (!$this->security->isGranted('ROLE_ADMIN')) {
            //     throw new AccessDeniedHttpException('沒有權限存取此檔案');
            // }
        }

        $filePath = $profile['path'] . '/' . $upload->getStoredFilename();

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('檔案不存在於伺服器');
        }

        return $filePath;
    }

    // 傳統表單上傳：不驗證(formType 以驗證)，只存檔，不寫入資料庫
    public function uploadFormFile(string $profileKey, UploadedFile $file): string
    {
        $profile = $this->uploadProfiles->getProfile($profileKey);

        // 回傳 實際檔名
        return $this->uploadService->storeFile($file, $profile['path']);
    }

    private function deleteFile(Upload $upload): void
    {
        $path = $this->params->get('upload.public_dir') . '/' . $upload->getStoredFilename();

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function deleteImage(string $uuid)
    {
        $upload = $this->em->getRepository(Upload::class)
            ->findOneBy(['uuid' => $uuid]);

        // if ($upload->getUser() !== $currentUser) {
        //     throw new AccessDeniedHttpException('無權刪除此圖片');
        // }

        dd($upload);

        if (!$upload) {
            throw new NotFoundHttpException('圖片不存在');
        }

        $this->deleteFile($upload);
        $this->em->remove($upload);
        $this->em->flush();
    }

    public function extractImageUuidsFromHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $uuids = [];

        foreach ($dom->getElementsByTagName('img') as $img) {
            if ($img->hasAttribute('data-uuid')) {
                $uuids[] = $img->getAttribute('data-uuid');
            }
        }

        return array_unique($uuids);
    }

    public function attachImagesToEntity(
        string $entityType,
        string $fieldName,
        int $entityId,
        string $html
    ) {
        $uuids = $this->extractImageUuidsFromHtml($html);

        foreach ($uuids as $uuid) {
            $upload = $this->em->getRepository(Upload::class)
                ->findOneBy(['uuid' => $uuid]);

            if (!$upload) {
                continue; // 防止亂傳 uuid
            }

            $upload->attachToEntity(
                entityType: $entityType,
                fieldName: $fieldName,
                entityId: $entityId
            );

            $upload->markAsUsed();

            $this->em->persist($upload);
        }

        $this->em->flush();
    }

    public function syncImagesWithEntity(string $entityType, string $fieldName, int $entityId, string $html)
    {
        $currentUuids = $this->extractImageUuidsFromHtml($html);

        $uploads = $this->em->getRepository(Upload::class)->findBy([
            'entityType' => $entityType,
            'fieldName' => $fieldName,
            'entityId' => $entityId
        ]);

        foreach ($uploads as $upload) {
            if (!in_array($upload->getUuid(), $currentUuids)) {
                $upload->delete(); // soft delete
            }
        }

        foreach ($currentUuids as $uuid) {
            $upload = $this->em->getRepository(Upload::class)
                ->findOneBy(['uuid' => $uuid]);

            if (!$upload || $upload->isAttached()) {
                continue;
            }

            $upload->attachToEntity($entityType, $fieldName, $entityId);
            $upload->markAsUsed();
        }

        $this->em->flush();
    }
}
