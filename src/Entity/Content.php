<?php

namespace App\Entity;

use App\Repository\ContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentRepository::class)]
class Content
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 400)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    private ?string $rocCreatedAt = null;

    private ?string $rocUpdatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreateAt(): ?\DateTimeImmutable
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeImmutable $createAt): static
    {
        $this->createAt = $createAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRocCreatedAt(): ?string
    {
        if ($this->createAt) {
            $year = (int)$this->createAt->format('Y') - 1911;
            $month = $this->createAt->format('m');
            $day = $this->createAt->format('d');
            return sprintf('%03d/%02d/%02d', $year, $month, $day);
        }

        return $this->rocCreatedAt;
    }

    public function setRocCreatedAt(string $rocCreatedAt): static
    {
        if ($rocCreatedAt) {
            $rocCreatedAt = (int)$rocCreatedAt + 19110000; // 转回西元
            $this->createAt = new \DateTimeImmutable("$rocCreatedAt");
        }

        $this->rocCreatedAt = $rocCreatedAt;

        return $this;
    }

    public function getRocUpdatedAt(): ?string
    {
        if ($this->updatedAt) {
            $year = (int)$this->updatedAt->format('Y') - 1911;
            $month = $this->updatedAt->format('m');
            $day = $this->updatedAt->format('d');
            return sprintf('%03d/%02d/%02d', $year, $month, $day);
        }

        return $this->rocUpdatedAt;
    }

    public function setRocUpdatedAt(string $rocUpdatedAt): static
    {
        if ($rocUpdatedAt) {
            $rocUpdatedAt = (int)$rocUpdatedAt + 19110000; // 转回西元
            $this->updatedAt = new \DateTimeImmutable("$rocUpdatedAt");
        }
        $this->rocUpdatedAt = $rocUpdatedAt;

        return $this;
    }
}
