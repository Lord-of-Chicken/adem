<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\MediaUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class MediaItemUploadController extends AbstractController
{
    #[Route('/admin/media-item/upload', name: 'admin_media_item_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, MediaUploadService $uploads, TranslatorInterface $translator): JsonResponse
    {
        if (!$this->isCsrfTokenValid('media_upload', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => $translator->trans('admin.csrf_invalid')], 403);
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => $translator->trans('admin.upload_no_file')], 400);
        }
        try {
            $path = $uploads->store($file);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => $translator->trans('admin.upload_invalid_type')], 400);
        }
        return new JsonResponse(['path' => $path, 'filename' => basename($path)]);
    }
}
