<?php

namespace App\Controller;

use App\Repository\MediaItemRepository;
use App\Repository\UserRepository;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function purchases(): Response
    {
        return $this->render('admin/purchases.html.twig');
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
}
