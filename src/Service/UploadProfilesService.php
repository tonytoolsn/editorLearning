<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UploadProfilesService
{
    // 個人設定
    private array $profiles = [];

    // 快取
    private array $profileCache = [];

    private const DEFAULT_MAX_SIZE = 20 * 1024 * 1024;

    private const MIME_TYPES = [
        'editorImage' => [    // 影像 / 圖片 (類型)
            'image/jpeg',   // JPEG
            'image/png', // PNG
            'image/gif', // GIF
        ],
        'image' => [    // 影像 / 圖片 (類型)
            'image/jpeg',   // JPEG
            'image/png', // PNG
            'image/gif', // GIF
            'image/webp', // WebP
            'image/svg+xml', // SVG
            'image/bmp', // BMP
            'image/tiff', // TIFF
            'image/vnd.microsoft.icon', // ICO
            'image/heif', // HEIF / HEIC
            'image/avif', // AVIF
        ],
        'video' => [    // 視頻 / 影音 (類型)
            'video/mp4',    // MP4
            'video/webm', // WebM
            'video/ogg', // Ogg
            'video/x-msvideo', // AVI
            'video/quicktime', // MOV / QuickTime
            'video/mpeg', // MPEG
        ],
        'audio' => [    // 音訊 (類型)
            'audio/mpeg',   // MP3
            'audio/mpeg', // WAV
            'audio/ogg', // Ogg
            'audio/aac', // Ogg
            'audio/aac' // AAC
        ],
        'text' => [ // 文件 / 文本 (類型)
            'application/pdf',  // PDF
            'application/msword',   // DOC / DOCX
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', // XLS / XLSX
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',    // PPT / PPTX
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',   // TXT
            'text/csv', // CSV
            'text/html',    // HTML
            'application/json', // JSONs
            'application/xml'   // XML
        ],
        'zip' => [  // 壓縮檔 / 其他  (類型)
            'application/zip',  // ZIP
            'application/vnd.rar',  // RAR
            'application/x-7z-compressed',  // 7z
            'application/x-tar',    // TAR
            'application/x-tar' // GZ
        ]
    ];

    // 可以寫部分，會合併預設 rules + 覆蓋規則
    private const TYPE_RULES = [
        // 部分覆蓋預設規則
        'avatar' => [
            'maxSize' => 2 * 1024 * 1024,
        ],
        // 完全覆蓋預設規則
        'banner' => [
            'maxSize' => 5 * 1024 * 1024,
            'mime' => ['image/jpeg', 'image/png'],
        ],
        // summernote
        'editor' => [
            'maxSize' => self::DEFAULT_MAX_SIZE,
            'mime' => self::MIME_TYPES['editorImage'],
        ],
    ];

    public function __construct(private readonly ParameterBagInterface $params)
    {
        $this->initProfiles();
    }

    public function getDefaultRules(): array
    {
        return [
            'maxSize' => self::DEFAULT_MAX_SIZE,
            'mime'    => array_merge(...array_values(self::MIME_TYPES)),
        ];
    }

    private function makeProfile(
        string $entity,
        string $field,
        string $subPath,
        string $rulesType = '',
        string $visibility = 'private',
        string $system = 'new',
        ?string $status = null,
    ): array {
        $rules = $this->getDefaultRules();

        if (is_string($rulesType) && isset(self::TYPE_RULES[$rulesType])) {
            $rules = array_replace_recursive($rules, self::TYPE_RULES[$rulesType]);
        }

        // 1️⃣ 選 base dir
        if ($system === 'legacy') {
            $baseDir =  $visibility === 'public'
                ? $this->params->get('kernel.project_dir') . '/public'
                : $this->params->get('kernel.project_dir');

            $path = $baseDir . '/' . ltrim($subPath, '/');

            $publicUrlBase = $visibility === 'public'
                ? '/' . ltrim($subPath, '/')
                : null;
        } else {
            // new system
            $baseDir = $visibility === 'public'
                ? $this->params->get('kernel.project_dir') . '/public/uploads'
                : $this->params->get('kernel.project_dir') . '/uploads';

            $path = $baseDir . '/' . $subPath;

            $publicUrlBase = $visibility === 'public'
                ? '/uploads/' . $subPath
                : null;
        }

        return [
            'rules'      => $rules, // 驗證規則
            'path'       => $path,  // 上傳：存檔的目錄
            'entity'     => $entity,    // entity
            'field'      => $field,     // 對應的名稱
            'visibility' => $visibility,     //
            'publicUrlBase' => $publicUrlBase,
            'status' => $status,
        ];
    }

    private function initProfiles(): void
    {
        //
        $this->profiles = [
            // fn1401 使用預設 rules
            'fn1401_content' => fn() => $this->makeProfile(
                entity: 'Post',
                field: 'content',
                status: 0,
                subPath: 'fn1401',
                visibility: 'public',
            ),

            // avatar 使用 typeRules
            'User_avatar' => fn() => $this->makeProfile(
                entity: 'User',
                field: 'avatar',
                subPath: 'avatar',
                rulesType: 'avatar'
            ),

            // banner 使用 rules 覆蓋全部規則
            // 公開上傳，存到 upload.public_dir（例如 banner 或 editor）
            'Post_banner' => fn() => $this->makeProfile(
                entity: 'Post',         // 儲存完成時，判斷圖片用
                field: 'banner',        // 儲存完成時，判斷圖片用
                subPath: 'banner',      // 實際目錄
                rulesType: 'editor',    // 驗證規則
                visibility: 'public'    // 公有還是私有，目錄
            ),
        ];
    }

    public function getProfile(string $key): array
    {
        // 如果已經有快取，直接返回
        if (isset($this->profileCache[$key])) {
            return $this->profileCache[$key];
        }

        // 檢查 profiles map 是否存在 key
        if (!isset($this->profiles[$key])) {
            throw new BadRequestHttpException("無效的上傳設定檔：$key");
        }

        // 延遲生成 profile 並存入 cache
        $this->profileCache[$key] = ($this->profiles[$key])();

        return $this->profileCache[$key];
    }
}
