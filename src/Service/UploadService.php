<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Upload;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Filesystem\Filesystem;

class UploadService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
        private readonly UploadProfilesService $profiles,
        private readonly Utils $utils
    ) {}

    private function validateFile(UploadedFile $file, array $rules): void
    {
        if (isset($rules['maxSize']) && $file->getSize() > $rules['maxSize']) {
            throw new BadRequestHttpException(
                '檔案過大，請上傳不超過 ' . ($rules['maxSize'] / 1024 / 1024) . ' MB 的檔案。'
            );
        }

        if (isset($rules['mime']) && !in_array($file->getMimeType(), $rules['mime'], true)) {
            throw new BadRequestHttpException(
                '檔案類型不正確，允許的格式: ' . implode(', ', $rules['mime'])
            );
        }
    }

    private function generateSafeFilename(UploadedFile $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName);
        return $safeName . '-' . uniqid() . '.' . $file->guessExtension();
    }

    /**
     * 將檔案存到指定路徑，回傳實際儲存的檔名
     */
    public function storeFile(UploadedFile $file, string $path): string
    {
        $filename = $this->generateSafeFilename($file);

        $fs = new Filesystem();

        // 確保資料夾存在
        if (!$fs->exists($path)) {
            $fs->mkdir($path, 0755);
        }


        // if (!is_dir($path)) {
        //     mkdir($path, 0755, true);
        // }

        $file->move($path, $filename);

        return $filename;
    }

    /**
     * 將上傳記錄寫入資料庫
     */
    public function createUploadRecord(
        array $file,
        string $storedFilename,
        array $profile,
    ): Upload {
        // 寫入資料庫
        $upload = new Upload();
        $upload->setUuid($this->utils->generateUuid());
        $upload->setOriginalFilename($file['clientOriginalName']);
        $upload->setStoredFilename($storedFilename);
        $upload->setMimeType($file['mime']);
        $upload->setEntityType($profile['entity']);
        $upload->setFieldName($profile['field']);
        $upload->setStatus($profile['status']);  // 0.暫存 1.儲存完成

        $this->em->persist($upload);
        $this->em->flush();

        return $upload;
    }

    // Summernote / editor 上傳：存檔 + 資料庫
    public function uploadEditorImage(string $profileKey, UploadedFile $file)
    {
        $profile = $this->profiles->getProfile($profileKey);

        // 驗證權限
        // $user = $this->getUser();
        // if (!$user || !$user->hasRole('ROLE_CAN_UPLOAD')) {
        //     throw new BadRequestHttpException(['error' => '沒有上傳權限'], 403);
        // }

        // Step 1: 驗證檔案
        $this->validateFile($file, $profile['rules']);

        // Step 2: 存檔到檔案系統
        $storedFilename = $this->storeFile($file, $profile['path']);

        // Step 3: 寫入資料庫，回傳 紀錄
        return $this->createUploadRecord($file, $storedFilename, $profile['entity'], $profile['field']);
    }

    // 傳統表單上傳：不驗證(formType 以驗證)，只存檔，不寫入資料庫
    public function uploadFormFile(string $profileKey, UploadedFile $file): string
    {
        $profile = $this->profiles->getProfile($profileKey);

        // 回傳 實際檔名
        return $this->storeFile($file, $profile['path']);
    }
}
