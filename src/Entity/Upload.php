<?php

namespace App\Entity;

use App\Repository\UploadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
class Upload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 對外使用
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $uuid = null;

    // 實際儲存檔名
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storedFilename = null;

    // 使用者上傳的原始名稱
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalFilename = null;

    // MIME type
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityUuid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fieldName = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdByUserId = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdByUnitId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    // 軟刪除
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getStoredFilename(): ?string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): static
    {
        $this->storedFilename = $storedFilename;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(int $createdByUserId): static
    {
        $this->createdByUserId = $createdByUserId;

        return $this;
    }

    public function getCreatedByUnitId(): ?int
    {
        return $this->createdByUnitId;
    }

    public function setCreatedByUnitId(int $createdByUnitId): static
    {
        $this->createdByUnitId = $createdByUnitId;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityUuid(): ?string
    {
        return $this->entityUuid;
    }

    public function setEntityUuid(string $entityUuid): static
    {
        $this->entityUuid = $entityUuid;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public const STATUS_TEMP   = 0;
    public const STATUS_USED   = 1;
    public const STATUS_DELETED = 9;

    public function attachToEntity(string $entityType, string $fieldName, int $entityId): void
    {
        $this->entityType = $entityType;
        $this->fieldName  = $fieldName;
        $this->entityId   = $entityId;
    }

    public function markAsUsed(): void
    {
        $this->status = self::STATUS_USED;
    }

    public function softDelete(): void
    {
        $this->status = self::STATUS_DELETED;
    }

    public function isAttached(): bool
    {
        return $this->entityId !== null;
    }
}
