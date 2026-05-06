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
        $locales = ['fr', 'en', 'nl'];
        $today = (new \DateTime())->format('Y-m-d');

        // Pages publiques indexables par locale
        $pages = [
            'app_home'    => ['priority' => '1.0', 'changefreq' => 'daily'],
            'app_about'   => ['priority' => '0.7', 'changefreq' => 'monthly'],
            'app_faq'     => ['priority' => '0.8', 'changefreq' => 'monthly'],
            'app_contact' => ['priority' => '0.6', 'changefreq' => 'monthly'],
            'app_gallery' => ['priority' => '0.7', 'changefreq' => 'weekly'],
        ];

        foreach ($locales as $locale) {
            foreach ($pages as $route => $meta) {
                $urls[] = [
                    'loc'        => $this->urlGenerator->generate($route, ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
                    'priority'   => $meta['priority'],
                    'changefreq' => $meta['changefreq'],
                    'lastmod'    => $today,
                ];
            }
        }

        return $this->render('sitemap.xml.twig', [
            'urls' => $urls,
            'hostname' => $hostname,
        ]);
    }
}
