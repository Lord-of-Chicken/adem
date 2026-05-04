<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use App\Service\StripePaymentService;
use App\Entity\User;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ParticipationCatalog $catalog,
        private readonly StripePaymentService $stripePaymentService,
    ) {
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $lines = $this->cartService->getLines();
        if (empty($lines)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $lineItems = [];
        $metadata = [];
        
        foreach ($lines as $line) {
            $tier = $this->catalog->require($line['tier_id']);

            if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                $unitAmount = $line['custom_price_cents'];
                $description = 'Don libre de ' . ($line['custom_price_cents'] / 100) . '€';
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
                $description .= ' - Don de: ' . $line['donor_name'];
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
            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $this->generateUrl(
                    'app_payment_success',
                    ['session_id' => '{CHECKOUT_SESSION_ID}'],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'cancel_url' => $this->generateUrl(
                    'app_payment_cancel',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'metadata' => $metadata,
            ];

            if ($user) {
                $sessionData['customer_email'] = $user->getEmail();
            }

            $checkoutSession = $this->stripePaymentService->createCheckoutSession(
                $lineItems,
                $user ? $user->getEmail() : null,
                $metadata
            );

            return $this->redirect($checkoutSession->url, 303);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');
        }
    }

    #[Route('/payment/success', name: 'app_payment_success')]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            return $this->redirectToRoute('app_cart_index');
        }

        try {
            $isPaid = $this->stripePaymentService->isSessionPaid($sessionId);

            if ($isPaid) {
                $this->cartService->clear();
                $this->addFlash('success', 'Merci ! Votre participation a bien été enregistrée.');
            } else {
                $this->addFlash('warning', 'Le paiement est en cours de traitement ou a échoué.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de vérifier le paiement.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Le paiement a été annulé. Ton panier est toujours là !');
        return $this->redirectToRoute('app_cart_index');
    }

}