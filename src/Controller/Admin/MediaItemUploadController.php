<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class MediaItemUploadController
{
    #[Route('/admin/media-item/upload', name: 'admin_media_item_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, SluggerInterface $slugger): Response
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        // Validate file type
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])) {
            return new JsonResponse(['error' => 'Invalid file type'], Response::HTTP_BAD_REQUEST);
        }

        // Generate unique filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName);
        $fileName = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        // Move file to assets/img/ruelle
        $destination = $request->server->get('DOCUMENT_ROOT') . '/../assets/img/ruelle';
        $file->move($destination, $fileName);

        // Return the asset path
        return new JsonResponse([
            'path' => 'img/ruelle/' . $fileName,
            'filename' => $fileName
        ]);
    }
}
