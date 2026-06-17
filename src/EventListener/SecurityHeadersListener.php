<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds HTTP security headers to every HTML response.
 *
 * The Content-Security-Policy is intentionally permissive on script-src/style-src
 * ('unsafe-inline') because the site relies on inline scripts (Google Analytics
 * loader, cookie consent banner, inline onclick handlers) and inline styles
 * (critical CSS, style attributes). Removing 'unsafe-inline' would require a
 * nonce/hash refactor of base.html.twig and the EasyAdmin layer.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -128)]
final class SecurityHeadersListener
{
    /**
     * Content-Security-Policy retenue.
     *
     * Compromis : 'unsafe-inline' conservé sur script-src et style-src à cause
     * des scripts/styles inline existants (GA, cookie consent, critical CSS,
     * EasyAdmin). Les domaines tiers nécessaires sont explicitement autorisés.
     */
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://js.stripe.com https://*.googletagmanager.com https://www.googletagmanager.com https://*.google-analytics.com https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: https: blob:; "
        . "connect-src 'self' https://*.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com https://api.stripe.com; "
        . "frame-src 'self' https://js.stripe.com https://hooks.stripe.com; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self' https://checkout.stripe.com; "
        . "frame-ancestors 'none'";

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = (string) $response->headers->get('Content-Type', '');

        // Apply HTML-oriented headers only to HTML responses.
        $isHtml = $contentType === '' || str_contains($contentType, 'text/html');

        if ($isHtml) {
            $headers = $response->headers;
            $headers->set('X-Frame-Options', 'DENY');
            $headers->set('X-Content-Type-Options', 'nosniff');
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            if (!$headers->has('Content-Security-Policy')) {
                $headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
            }
        }

        // HSTS is only meaningful (and only sent) over HTTPS.
        if ($event->getRequest()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
