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

class PostService
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

    public function extractImageUuids(string $html): array
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

    public function newImage($id, $html)
    {
        $uuids = $this->extractImageUuids($html);

        foreach ($uuids as $uuid) {
            $upload = $this->em->getRepository(Upload::class)->findOneBy(['uuid' => $uuid]);
            $upload->setEntityId($id);
            $upload->setStatus(1);
            $this->em->persist($upload);
        }
        $this->em->flush();
    }

    public function editImage($id, $html)
    {
        $uuids = $this->extractImageUuids($html);

        foreach ($uuids as $uuid) {
            $upload = $this->em->getRepository(Upload::class)->findOneBy(['uuid' => $uuid]);
            $upload->setEntityId($id);
            $upload->setStatus(1);
            $this->em->persist($upload);
        }
        $this->em->flush();
    }
}
