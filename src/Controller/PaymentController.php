<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Order;
use App\Entity\User;
use App\Participation\ParticipationCatalog;
use App\Repository\OrderRepository;
use App\Service\StripePaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ParticipationCatalog $catalog,
        private readonly StripePaymentService $stripePaymentService,
        private readonly OrderRepository $orderRepository,
    ) {
    }

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

        $lineItems = [];
        $metadata = [];

        foreach ($lines as $line) {
            $tier = $this->catalog->require($line['tier_id']);

            // Traduire title_key et detail_key en title et detail
            $tier['title']  = isset($tier['title_key'])  ? $translator->trans($tier['title_key'])  : ($tier['title']  ?? '');
            $tier['detail'] = isset($tier['detail_key']) ? $translator->trans($tier['detail_key']) : ($tier['detail'] ?? null);
            if (isset($tier['price_key'])) {
                $tier['price'] = $translator->trans($tier['price_key']);
            }
            if (isset($tier['price_suffix_key'])) {
                $tier['price_suffix'] = $translator->trans($tier['price_suffix_key']);
            }

            if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                $unitAmount = $line['custom_price_cents'];
                $description = $translator->trans('payment.free_donation') . ($line['custom_price_cents'] / 100) . $translator->trans('cart.currency_symbol');
            } else {
                $unitAmount = (int) ((float)$tier['unit_price_eur'] * 100);
                $description = $tier['detail'] ?? null;
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
                        'description' => $description,
                        'metadata' => $itemMetadata,
                    ],
                    'unit_amount'  => $unitAmount,
                ],
                'quantity' => $line['quantity'],
            ];
        }

        if ($user) {
            $metadata['user_id'] = $user->getId();
            $metadata['user_email'] = $user->getEmail();
        }

        try {
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
            $this->addFlash('error', $translator->trans('payment.stripe_error') . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');
        }
    }

    #[Route('/payment/success', name: 'app_payment_success')]
    public function success(Request $request, TranslatorInterface $translator): Response
    {
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            return $this->redirectToRoute('app_cart_index');
        }

        // Stripe only redirects here after a confirmed payment — no API check needed.
        // Order fulfillment (marking as paid) is handled by the webhook.
        $this->cartService->clear();
        $this->addFlash('success', $translator->trans('payment.success'));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(TranslatorInterface $translator): Response
    {
        $this->addFlash('info', $translator->trans('payment.cancel'));
        return $this->redirectToRoute('app_cart_index');
    }

}