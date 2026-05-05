<?php

namespace App\Controller;

use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private MediaItemRepository $mediaItemRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $urls = [];
        $hostname = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Pages statiques principales
        $staticPages = [
            '' => ['priority' => '1.0', 'changefreq' => 'daily'],
            'offres' => ['priority' => '0.9', 'changefreq' => 'weekly'],
            'medias' => ['priority' => '0.8', 'changefreq' => 'weekly'],
            'a-propos' => ['priority' => '0.7', 'changefreq' => 'monthly'],
            'faq' => ['priority' => '0.8', 'changefreq' => 'monthly'],
        ];

        foreach ($staticPages as $path => $meta) {
            $urls[] = [
                'loc' => $path ? $hostname . '#' . $path : $hostname,
                'priority' => $meta['priority'],
                'changefreq' => $meta['changefreq'],
                'lastmod' => (new \DateTime())->format('Y-m-d'),
            ];
        }

        // Pages dynamiques - médias publiés
        $mediaItems = $this->mediaItemRepository->findPublishedOrdered();
        foreach ($mediaItems as $media) {
            $urls[] = [
                'loc' => $hostname . '#medias',
                'priority' => '0.6',
                'changefreq' => 'monthly',
                'lastmod' => $media->getUpdatedAt()->format('Y-m-d'),
            ];
        }

        return $this->render('sitemap.xml.twig', [
            'urls' => $urls,
            'hostname' => $hostname,
        ]);
    }
}
