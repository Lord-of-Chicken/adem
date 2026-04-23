<?php

namespace App\EventSubscriber;

use App\Cart\CartService;
use App\Repository\SiteSettingRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Expose des globales Twig (strict_variables / tests fonctionnels).
 */
final class TwigGlobalsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SiteSettingRepository $siteSettings,
        private readonly CartService $cartService,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après SessionListener (priorité 128) pour que le panier puisse lire la session.
        return [KernelEvents::REQUEST => ['onKernelRequest', -100]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->twig->addGlobal('site_brand', [
            'title' => $this->siteSettings->get('brand.title') ?: 'La Ruelle d\'Adem',
            'tagline' => $this->siteSettings->get('brand.tagline') ?: 'Fais une fleur\nà La Ruelle d\'Adem',
            'logo_asset' => $this->siteSettings->get('brand.logo_asset'),
        ]);
        $this->twig->addGlobal('cart_line_count', $this->cartService->countLines());
        $this->twig->addGlobal('footer_line', $this->siteSettings->get('brand.title') ?: 'La Ruelle d\'Adem');
        $this->twig->addGlobal('medias_intro', $this->siteSettings->get('section.medias.intro')
            ?: 'Quelques images de la ruelle — le lieu du projet, tel qu\'on le vit au quotidien.');
        $this->twig->addGlobal('stripe_publishable_key', $this->params->get('stripe.publishable_key'));
    }
}
