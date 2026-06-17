<?php

declare(strict_types=1);

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
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(TranslatorInterface $translator): Response
    {
        return $this->render('admin/index.html.twig', [
            'page' => [
                'sidebar_title' => $translator->trans('admin.sidebar_title'),
                'dashboard_title' => $translator->trans('admin.dashboard_title'),
                'welcome_title' => $translator->trans('admin.welcome_title'),
                'welcome_text' => $translator->trans('admin.welcome_text'),
                'nav_dashboard' => $translator->trans('admin.nav_dashboard'),
                'nav_carousel' => $translator->trans('admin.nav_carousel'),
                'nav_users' => $translator->trans('admin.nav_users'),
                'nav_stripe' => $translator->trans('admin.nav_stripe'),
                'nav_translations' => $translator->trans('admin.nav_translations'),
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

    #[Route('/admin/order', name: 'app_admin_order')]
    public function orderRedirect(): Response
    {
        return $this->redirectToRoute('app_admin_purchases');
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
    public function purchaseDetail(string $id, StripePaymentService $stripePaymentService, TranslatorInterface $translator): Response
    {
        try {
            $paymentIntent = $stripePaymentService->getPaymentIntent($id);
        } catch (\Exception $e) {
            throw $this->createNotFoundException($translator->trans('admin.payment_not_found'));
        }

        return $this->render('admin/purchase_detail.html.twig', [
            'payment_intent' => $paymentIntent,
        ]);
    }

    /** @var int Number of users shown per page on the admin users list */
    private const USERS_PER_PAGE = 50;

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(
        UserRepository $userRepository,
        Request $request
    ): Response {
        $newsletterFilter = $request->query->get('newsletter');

        $newsletter = match ($newsletterFilter) {
            'subscribed' => true,
            'not_subscribed' => false,
            default => null,
        };

        $page = max(1, $request->query->getInt('page', 1));
        $total = $userRepository->countUsers($newsletter);
        $lastPage = max(1, (int) ceil($total / self::USERS_PER_PAGE));
        $page = min($page, $lastPage);

        $users = $userRepository->findPaginated($page, self::USERS_PER_PAGE, $newsletter);

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'newsletter_filter' => $newsletterFilter,
            'pagination' => [
                'page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
        ]);
    }

    #[Route('/admin/carousel/add', name: 'app_admin_carousel_add', methods: ['GET', 'POST'])]
    public function carouselAdd(
        Request $request,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator
    ): Response {
        $title = trim((string) $request->request->get('title', ''));
        $assetPath = trim((string) $request->request->get('asset_path', ''));
        $alt = trim((string) $request->request->get('alt', ''));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('carousel_manage', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($translator->trans('admin.csrf_invalid'));
            }

            if ($title === '' || $assetPath === '') {
                $this->addFlash('error', $translator->trans('admin.title_and_path_required'));

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
            'title' => $translator->trans('admin.add_image'),
        ]);
    }

    #[Route('/admin/carousel/edit/{id}', name: 'app_admin_carousel_edit', methods: ['GET', 'POST'])]
    public function carouselEdit(
        int $id,
        Request $request,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator
    ): Response {
        $mediaItem = $mediaItemRepository->find($id);

        if (!$mediaItem) {
            throw $this->createNotFoundException($translator->trans('admin.image_not_found'));
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('carousel_manage', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($translator->trans('admin.csrf_invalid'));
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
            'title' => $translator->trans('admin.edit_image'),
            'media_item' => $mediaItem,
        ]);
    }

    #[Route('/admin/carousel/delete/{id}', name: 'app_admin_carousel_delete', methods: ['POST'])]
    public function carouselDelete(
        int $id,
        Request $request,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator
    ): Response {
        if (!$this->isCsrfTokenValid('carousel_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('admin.csrf_invalid'));
        }

        $mediaItem = $mediaItemRepository->find($id);

        if (!$mediaItem) {
            throw $this->createNotFoundException($translator->trans('admin.image_not_found'));
        }

        $mediaItemRepository->remove($mediaItem, true);

        return $this->redirectToRoute('app_admin_carousel');
    }

    #[Route('/admin/carousel/reorder', name: 'app_admin_carousel_reorder', methods: ['POST'])]
    public function carouselReorder(
        Request $request,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('carousel_reorder', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['success' => false, 'error' => $translator->trans('admin.invalid_csrf_token')], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['orders']) || !is_array($data['orders'])) {
            return new JsonResponse(['success' => false, 'error' => $translator->trans('admin.invalid_data')], Response::HTTP_BAD_REQUEST);
        }

        $idToPosition = [];
        foreach ($data['orders'] as $orderData) {
            if (!isset($orderData['id']) || !isset($orderData['sortOrder'])) {
                continue;
            }

            $id = (int) $orderData['id'];
            $sortOrder = (int) $orderData['sortOrder'];

            if ($id <= 0 || $sortOrder < 0) {
                continue;
            }

            $idToPosition[$id] = $sortOrder;
        }

        $mediaItemRepository->updateSortOrders($idToPosition);

        return new JsonResponse(['success' => true]);
    }
}
