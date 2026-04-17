<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ParticipationCatalog $catalog,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(): Response
    {
        $lines = $this->cartService->getLines();

        if (empty($lines)) {
            return $this->redirectToRoute('app_cart_index');
        }

        $totalCents = $this->cartService->totalCents($this->catalog);

        /** @var User $user */
        $user = $this->getUser();

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // ✅ Annuler toute commande non payée existante pour éviter les sessions Stripe orphelines
        $existingOrder = $this->orderRepository->findUnpaidOrderByUser($user);
        if ($existingOrder) {
            try {
                // ✅ retrieve() d'abord, puis expire() sur l'objet — pas en statique
                $oldSession = Session::retrieve($existingOrder->getStripeCheckoutSessionId());
                $oldSession->expire();
            } catch (\Exception) {
                // Session déjà expirée ou inconnue de Stripe, on ignore
            }
            $this->entityManager->remove($existingOrder);
            $this->entityManager->flush();
        }

        $lineItems = [];
        foreach ($lines as $line) {
            $tier      = $this->catalog->require($line['tier_id']);
            $lineTotal = $this->catalog->lineTotalCents($tier, $line['quantity']);

            $productData = [
                'name' => $tier['title'],
            ];

            if (!empty($tier['description'])) {
                $productData['description'] = $tier['description'];
            }

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => $productData,
                    'unit_amount'  => (int) round($lineTotal / $line['quantity']),
                ],
                'quantity' => $line['quantity'],
            ];
        }

        try {
            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $lineItems,
                'mode'                 => 'payment',
                'customer_email'       => $user->getUserIdentifier(),
                'metadata'             => [
                    'donor_name' => $user->getUserIdentifier(),
                ],
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
            ]);

            $order = new Order();
            $order->setTotalCents($totalCents);
            $order->setCartData($lines);
            $order->setStripeCheckoutSessionId($checkoutSession->id);

            if ($user instanceof User) {
                $order->setUser($user);
            }

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->redirect($checkoutSession->url, 303);

        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->addFlash('error', 'Erreur d\'authentification Stripe : clé API invalide.');
            error_log('Stripe Authentication Error: ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $this->addFlash('error', 'Requête Stripe invalide : ' . $e->getMessage());
            error_log('Stripe Invalid Request Error: ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $this->addFlash('error', 'Erreur de connexion à l\'API Stripe.');
            error_log('Stripe Connection Error: ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->addFlash('error', 'Erreur API Stripe : ' . $e->getMessage());
            error_log('Stripe API Error: ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur inattendue : ' . $e->getMessage());
            error_log('Unexpected Error in Payment: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
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

        $order = $this->orderRepository->findByStripeCheckoutSessionId($sessionId);

        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Éviter de retraiter une commande déjà payée
        if ($order->isPaid()) {
            $this->addFlash('success', 'Votre commande a déjà été confirmée.');
            return $this->redirectToRoute('app_home');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $order->markAsPaid();
                $this->entityManager->flush();

                $this->cartService->clear();

                $this->addFlash('success', 'Paiement réussi ! Merci pour votre participation.');
                return $this->redirectToRoute('app_home');
            }
        } catch (\Exception $e) {
            error_log('Stripe success verification error: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la vérification du paiement.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Le paiement a été annulé. Vous pouvez modifier votre panier et réessayer.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook')]
    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $_ENV['STRIPE_WEBHOOK_SECRET']
            );
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $order   = $this->orderRepository->findByStripeCheckoutSessionId($session->id);

                if ($order && !$order->isPaid()) {
                    $order->markAsPaid();
                    $this->entityManager->flush();
                }
                break;
        }

        return new Response('Webhook received', 200);
    }
}