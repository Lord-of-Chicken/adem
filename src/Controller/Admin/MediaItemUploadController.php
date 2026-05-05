<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
class MediaItemUploadController extends AbstractController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_MIME_TYPES  = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_FILE_SIZE       = 5 * 1024 * 1024; // 5 MB

    #[Route('/admin/media-item/upload', name: 'admin_media_item_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, SluggerInterface $slugger, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('media_upload', $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => $translator->trans('admin.csrf_invalid')], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => $translator->trans('admin.upload_no_file')], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(['error' => $translator->trans('admin.upload_file_too_large')], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->guessExtension() ?? '');
        if (
            !in_array($extension, self::ALLOWED_EXTENSIONS, true) ||
            !in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)
        ) {
            return new JsonResponse(['error' => $translator->trans('admin.upload_invalid_type')], Response::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName  = $slugger->slug($originalName);
        $fileName  = $safeName . '-' . uniqid() . '.' . $extension;

        $destination = $request->server->get('DOCUMENT_ROOT') . '/../assets/img/ruelle';
        $file->move($destination, $fileName);

        return new JsonResponse([
            'path'     => 'img/ruelle/' . $fileName,
            'filename' => $fileName,
        ]);
    }
}
