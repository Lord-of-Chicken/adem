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

    #[Route('/admin/carousel/reorder', name: 'app_admin_carousel_reorder', methods: ['POST'])]
    public function carouselReorder(
        Request $request,
        MediaItemRepository $mediaItemRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['orders']) || !is_array($data['orders'])) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data['orders'] as $orderData) {
            if (!isset($orderData['id']) || !isset($orderData['sortOrder'])) {
                continue;
            }

            $mediaItem = $mediaItemRepository->find($orderData['id']);
            if ($mediaItem) {
                $mediaItem->setSortOrder($orderData['sortOrder']);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
