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

        try {
            $title = $this->siteSettings->get('brand.title') ?: 'La Ruelle d\'Adem';
            $tagline = $this->siteSettings->get('brand.tagline') ?: 'Fais une fleur à La Ruelle d\'Adem';
            $logoAsset = $this->siteSettings->get('brand.logo_asset');
            $mediasIntro = $this->siteSettings->get('section.medias.intro')
                ?: 'Quelques images de la ruelle — le lieu du projet, tel qu\'on le vit au quotidien.';
            $cartCount = $this->cartService->countLines();
        } catch (\Throwable) {
            $title = 'La Ruelle d\'Adem';
            $tagline = 'Fais une fleur à La Ruelle d\'Adem';
            $logoAsset = null;
            $mediasIntro = 'Quelques images de la ruelle — le lieu du projet, tel qu\'on le vit au quotidien.';
            $cartCount = 0;
        }

        $this->twig->addGlobal('site_brand', [
            'title' => $title,
            'tagline' => $tagline,
            'logo_asset' => $logoAsset,
        ]);
        $this->twig->addGlobal('cart_line_count', $cartCount);
        $this->twig->addGlobal('footer_line', $title);
        $this->twig->addGlobal('medias_intro', $mediasIntro);
        $this->twig->addGlobal('stripe_publishable_key', $this->params->get('stripe.publishable_key'));
    }
}
