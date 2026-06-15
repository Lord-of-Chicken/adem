<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates XML sitemap for SEO.
 */
final class SitemapController extends AbstractController
{
    /** @var list<string> */
    private const LOCALES = ['fr', 'en', 'nl'];

    public function __construct(
        private readonly MediaItemRepository $mediaItemRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generates an XML sitemap with all public pages for the three locales,
     * including hreflang alternates (xhtml:link) for each URL.
     *
     * @return Response The XML sitemap response
     */
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        // Public, indexable pages with recalibrated priorities.
        $pages = [
            'app_home'    => ['priority' => '1.0',  'changefreq' => 'daily'],
            'app_about'   => ['priority' => '0.9',  'changefreq' => 'monthly'],
            'app_gallery' => ['priority' => '0.85', 'changefreq' => 'weekly'],
            'app_faq'     => ['priority' => '0.8',  'changefreq' => 'monthly'],
            'app_contact' => ['priority' => '0.7',  'changefreq' => 'monthly'],
            'app_press'   => ['priority' => '0.6',  'changefreq' => 'monthly'],
        ];

        $urls = [];

        // Root entry point (/) redirects to the default locale — list it once.
        $urls[] = [
            'loc'        => $this->urlGenerator->generate('app_root', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'priority'   => '1.0',
            'changefreq' => 'daily',
            'lastmod'    => $today,
            'alternates' => [],
        ];

        foreach ($pages as $route => $meta) {
            // Pre-compute the hreflang alternates shared by every locale variant.
            $alternates = [];
            foreach (self::LOCALES as $altLocale) {
                $alternates[$altLocale] = $this->urlGenerator->generate(
                    $route,
                    ['_locale' => $altLocale],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }
            // x-default points to the French (default) locale.
            $alternates['x-default'] = $alternates['fr'];

            foreach (self::LOCALES as $locale) {
                $urls[] = [
                    'loc'        => $alternates[$locale],
                    'priority'   => $meta['priority'],
                    'changefreq' => $meta['changefreq'],
                    'lastmod'    => $today,
                    'alternates' => $alternates,
                ];
            }
        }

        $response = $this->render('sitemap.xml.twig', [
            'urls' => $urls,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
