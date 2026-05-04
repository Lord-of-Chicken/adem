<?php

namespace App\Controller;

use App\Entity\MediaItem;
use App\Repository\MediaItemRepository;
use App\Repository\UserRepository;
use App\Service\StripePaymentService;
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
        return $this->render('admin/index.html.twig', [
            'page' => [
                'sidebar_title' => 'Admin',
                'dashboard_title' => 'Dashboard Admin',
                'welcome_title' => 'Bienvenue',
                'welcome_text' => 'Utilisez le menu de gauche pour naviguer dans les différentes sections d\'administration.',
                'nav_dashboard' => 'Dashboard',
                'nav_carousel' => 'Carousel',
                'nav_users' => 'Utilisateurs',
                'nav_stripe' => 'Paiements Stripe',
            ],
        ]);
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
    public function purchases(StripePaymentService $stripePaymentService): Response
    {
        $paymentIntents = $stripePaymentService->listAllPaymentIntents(100);

        return $this->render('admin/purchases.html.twig', [
            'payment_intents' => $paymentIntents,
        ]);
    }

    #[Route('/admin/purchases/{id}', name: 'app_admin_purchase_detail')]
    public function purchaseDetail(string $id, StripePaymentService $stripePaymentService): Response
    {
        try {
            $paymentIntent = $stripePaymentService->getPaymentIntent($id);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Paiement non trouvé');
        }

        return $this->render('admin/purchase_detail.html.twig', [
            'payment_intent' => $paymentIntent,
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
        $title = trim((string) $request->request->get('title', ''));
        $assetPath = trim((string) $request->request->get('asset_path', ''));
        $alt = trim((string) $request->request->get('alt', ''));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('carousel_manage', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            if ($title === '' || $assetPath === '') {
                $this->addFlash('error', 'Le titre et le chemin de l\'image sont obligatoires.');

                return $this->redirectToRoute('app_admin_carousel_add');
            }

            $mediaItem = new MediaItem();
            $mediaItem->setTitle($title);
            $mediaItem->setAssetPath($assetPath);
            $mediaItem->setAlt($alt !== '' ? $alt : null);
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
            if (!$this->isCsrfTokenValid('carousel_manage', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $title = trim((string) $request->request->get('title', ''));
            $assetPath = trim((string) $request->request->get('asset_path', ''));
            $alt = trim((string) $request->request->get('alt', ''));

            if ($title !== '') {
                $mediaItem->setTitle($title);
            }
            if ($assetPath !== '') {
                $mediaItem->setAssetPath($assetPath);
            }
            $mediaItem->setAlt($alt !== '' ? $alt : null);

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
        Request $request,
        MediaItemRepository $mediaItemRepository
    ): Response {
        if (!$this->isCsrfTokenValid('carousel_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

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
        if (!$this->isCsrfTokenValid('carousel_reorder', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

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
