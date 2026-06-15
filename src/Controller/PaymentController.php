<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Order;
use App\Entity\User;
use App\Participation\ParticipationCatalog;
use App\Repository\OrderRepository;
use App\Service\StripePaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles payment processing via Stripe checkout sessions.
 */
final class PaymentController extends AbstractController
{
    /** @var int Maximum length Stripe allows for a metadata value. */
    private const STRIPE_METADATA_MAX_LENGTH = 500;

    public function __construct(
        private readonly CartService $cartService,
        private readonly ParticipationCatalog $catalog,
        private readonly StripePaymentService $stripePaymentService,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Initiates Stripe checkout session for cart items.
     *
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to Stripe checkout or cart page
     */
    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(TranslatorInterface $translator): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $lines = $this->cartService->getLines();
        if (empty($lines)) {
            $this->addFlash('warning', $translator->trans('payment.cart_empty'));
            return $this->redirectToRoute('app_cart_index');
        }

        try {
            $lineItems = [];
            $metadata = [];

            foreach ($lines as $line) {
                $tier = $this->catalog->require($line['tier_id']);

                if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                    $cents = (int) $line['custom_price_cents'];
                    if ($cents < CartService::MIN_CUSTOM_PRICE_CENTS || $cents > CartService::MAX_CUSTOM_PRICE_CENTS) {
                        $this->logger->warning('Checkout rejected: custom price out of range', [
                            'tier_id' => $line['tier_id'],
                            'cents'   => $cents,
                        ]);
                        $this->addFlash('error', $translator->trans('payment.amount_invalid'));
                        return $this->redirectToRoute('app_cart_index');
                    }
                }

                if (!empty($line['donor_name']) && mb_strlen((string) $line['donor_name']) > CartService::MAX_DONOR_NAME_LENGTH) {
                    $this->addFlash('error', $translator->trans('payment.donor_name_invalid'));
                    return $this->redirectToRoute('app_cart_index');
                }

                $tier = $this->catalog->translateTier($tier, $translator);

                if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                    $unitAmount = $line['custom_price_cents'];
                    $description = $translator->trans('payment.free_donation') . ($line['custom_price_cents'] / 100) . $translator->trans('cart.currency_symbol');
                } else {
                    $unitAmount = ParticipationCatalog::eurToCents($tier['unit_price_eur']);
                    $description = $tier['detail'] ?? '';
                }

                $itemMetadata = [
                    'tier_id' => $line['tier_id'],
                    'tier_title' => $tier['title'],
                ];

                if (!empty($line['donor_name'])) {
                    $itemMetadata['donor_name'] = $line['donor_name'];
                    $description .= $translator->trans('payment.donation_of') . $line['donor_name'];
                }

                if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                    $itemMetadata['custom_price_eur'] = number_format($line['custom_price_cents'] / 100, 2, '.', '');
                }

                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'eur',
                        'product_data' => [
                            'name' => $tier['title'],
                            'description' => $description ?: null,
                            'metadata' => $itemMetadata,
                        ],
                        'unit_amount'  => $unitAmount,
                    ],
                    'quantity' => $line['quantity'],
                ];
            }

            if ($user) {
                $metadata['user_id'] = (string) $user->getId();
                $metadata['user_email'] = (string) $user->getEmail();
            }

            // Stripe rejects metadata values longer than 500 characters. Guard
            // defensively so an oversized value can never reach the API.
            foreach ($metadata as $key => $value) {
                if (mb_strlen((string) $value) > self::STRIPE_METADATA_MAX_LENGTH) {
                    $this->logger->warning('Checkout rejected: Stripe metadata value too long', [
                        'key'    => $key,
                        'length' => mb_strlen((string) $value),
                    ]);
                    $this->addFlash('error', $translator->trans('payment.stripe_error'));
                    return $this->redirectToRoute('app_cart_index');
                }
            }

            $successUrl = $this->generateUrl(
                'app_payment_success',
                ['session_id' => '{CHECKOUT_SESSION_ID}'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $cancelUrl = $this->generateUrl(
                'app_payment_cancel',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $checkoutSession = $this->stripePaymentService->createCheckoutSession(
                $lineItems,
                $user ? $user->getEmail() : null,
                $metadata,
                $successUrl,
                $cancelUrl
            );

            $order = (new Order())
                ->setStripeCheckoutSessionId($checkoutSession->id)
                ->setTotalCents($this->cartService->totalCents($this->catalog))
                ->setCartData($this->cartService->getLines())
                ->setUser($user);

            $this->orderRepository->save($order, flush: true);

            return $this->redirect($checkoutSession->url, 303);

        } catch (\Exception $e) {
            $this->logger->error('Stripe checkout error', ['exception' => $e]);
            $this->addFlash('error', $translator->trans('payment.stripe_error'));
            return $this->redirectToRoute('app_cart_index');
        }
    }

    /**
     * Handles successful payment return from Stripe.
     *
     * @param Request $request The HTTP request
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to home page
     */
    #[Route('/payment/success', name: 'app_payment_success')]
    public function success(Request $request, TranslatorInterface $translator): Response
    {
        $sessionId = $request->query->get('session_id');
        if (!$sessionId || !is_string($sessionId)) {
            return $this->redirectToRoute('app_cart_index');
        }

        // Only clear the cart if we actually have an order matching this session
        // (prevents manual URL access from wiping a user's cart).
        $order = $this->orderRepository->findByStripeCheckoutSessionId($sessionId);
        if (!$order) {
            return $this->redirectToRoute('app_cart_index');
        }

        $this->cartService->clear();
        $this->addFlash('success', $translator->trans('payment.success'));

        return $this->redirectToRoute('app_home');
    }

    /**
     * Handles cancelled payment return from Stripe.
     *
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to cart page
     */
    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(TranslatorInterface $translator): Response
    {
        $this->addFlash('info', $translator->trans('payment.cancel'));
        return $this->redirectToRoute('app_cart_index');
    }

}