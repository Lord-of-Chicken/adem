<?php

namespace App\EventSubscriber;

use App\Cart\CartService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Expose des globales Twig (strict_variables / tests fonctionnels).
 */
final class TwigGlobalsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CartService $cartService,
        private readonly ParameterBagInterface $params,
        private readonly TranslatorInterface $translator,
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

        $title = 'La Ruelle d\'Adem';
        $tagline = $this->translator->trans('site.tagline');
        $logoAsset = 'img/Panneau/IMG_0197.png';
        $ogImageAsset = 'img/Social/og-home-preview.png';
        $cartCount = $this->cartService->countLines();

        $this->twig->addGlobal('site_brand', [
            'title' => $title,
            'tagline' => $tagline,
            'logo_asset' => $logoAsset,
            'og_image_asset' => $ogImageAsset,
        ]);
        $this->twig->addGlobal('cart_line_count', $cartCount);
        $this->twig->addGlobal('footer_line', $title);
        $this->twig->addGlobal('stripe_publishable_key', $this->params->get('stripe.publishable_key'));
    }
}
