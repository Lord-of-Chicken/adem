<?php

namespace App\Controller;

use App\Repository\MediaItemRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/admin/carousel', name: 'app_admin_carousel')]
    public function carousel(MediaItemRepository $mediaItemRepository): Response
    {
        $mediaItems = $mediaItemRepository->findAll();

        return $this->render('admin/carousel.html.twig', [
            'media_items' => $mediaItems,
        ]);
    }

    #[Route('/admin/purchases', name: 'app_admin_purchases')]
    public function purchases(OrderRepository $orderRepository): Response
    {
        $purchases = $orderRepository->findBy(['status' => 'paid'], ['paidAt' => 'DESC']);

        return $this->render('admin/purchases.html.twig', [
            'purchases' => $purchases,
        ]);
    }

    #[Route('/admin/purchases/{id}', name: 'app_admin_purchase_detail')]
    public function purchaseDetail(int $id, OrderRepository $orderRepository): Response
    {
        $purchase = $orderRepository->find($id);

        if (!$purchase) {
            throw $this->createNotFoundException('Achat non trouvé');
        }

        return $this->render('admin/purchase_detail.html.twig', [
            'purchase' => $purchase,
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(
        UserRepository $userRepository,
        Request $request
    ): Response {
        $newsletterFilter = $request->query->get('newsletter');
        
        if ($newsletterFilter === 'subscribed') {
            $users = $userRepository->findByNewsletterSubscription(true);
        } elseif ($newsletterFilter === 'not_subscribed') {
            $users = $userRepository->findByNewsletterSubscription(false);
        } else {
            $users = $userRepository->findAll();
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'newsletter_filter' => $newsletterFilter,
        ]);
    }

    #[Route('/admin/carousel/add', name: 'app_admin_carousel_add', methods: ['GET', 'POST'])]
    public function carouselAdd(
        Request $request,
        MediaItemRepository $mediaItemRepository
    ): Response {
        $title = $request->request->get('title');
        $assetPath = $request->request->get('asset_path');
        $alt = $request->request->get('alt');

        if ($request->isMethod('POST') && $title && $assetPath) {
            $mediaItem = new \App\Entity\MediaItem();
            $mediaItem->setTitle($title);
            $mediaItem->setAssetPath($assetPath);
            $mediaItem->setAlt($alt);
            $mediaItem->setPublished(true);
            $mediaItem->setSortOrder(0);

            $mediaItemRepository->save($mediaItem, true);

            return $this->redirectToRoute('app_admin_carousel');
        }

        return $this->render('admin/carousel_form.html.twig', [
            'title' => 'Ajouter une image',
        ]);
    }

    #[Route('/admin/carousel/edit/{id}', name: 'app_admin_carousel_edit', methods: ['GET', 'POST'])]
    public function carouselEdit(
        int $id,
        Request $request,
        MediaItemRepository $mediaItemRepository
    ): Response {
        $mediaItem = $mediaItemRepository->find($id);

        if (!$mediaItem) {
            throw $this->createNotFoundException('Image non trouvée');
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $assetPath = $request->request->get('asset_path');
            $alt = $request->request->get('alt');

            if ($title) {
                $mediaItem->setTitle($title);
            }
            if ($assetPath) {
                $mediaItem->setAssetPath($assetPath);
            }
            if ($alt) {
                $mediaItem->setAlt($alt);
            }

            $mediaItemRepository->save($mediaItem, true);

            return $this->redirectToRoute('app_admin_carousel');
        }

        return $this->render('admin/carousel_form.html.twig', [
            'title' => 'Modifier l\'image',
            'media_item' => $mediaItem,
        ]);
    }

    #[Route('/admin/carousel/delete/{id}', name: 'app_admin_carousel_delete', methods: ['POST'])]
    public function carouselDelete(
        int $id,
        MediaItemRepository $mediaItemRepository
    ): Response {
        $mediaItem = $mediaItemRepository->find($id);

        if (!$mediaItem) {
            throw $this->createNotFoundException('Image non trouvée');
        }

        $mediaItemRepository->remove($mediaItem, true);

        return $this->redirectToRoute('app_admin_carousel');
    }

    #[Route('/admin/media-item/{entityId}/upload-image', name: 'admin_media_item_upload_image', methods: ['POST'])]
    public function uploadEditedImage(Request $request, EntityManagerInterface $entityManager, $entityId): JsonResponse
    {
        $mediaItem = $entityManager->getRepository(\App\Entity\MediaItem::class)->find($entityId);
        
        if (!$mediaItem) {
            return new JsonResponse(['success' => false, 'message' => 'MediaItem not found'], 404);
        }

        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        try {
            // Générer un nom de fichier unique
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $originalFilename . '-' . uniqid() . '.' . $file->guessExtension();

            // Déplacer le fichier vers le répertoire public
            $destination = $this->getParameter('kernel.project_dir') . '/public/img/ruelle';
            $file->move($destination, $newFilename);

            // Mettre à jour le chemin de l'asset
            $mediaItem->setAssetPath('img/ruelle/' . $newFilename);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Image updated successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
