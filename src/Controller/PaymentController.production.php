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

final class PaymentController extends AbstractController
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
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $lines = $this->cartService->getLines();
        if (empty($lines)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $lineItems = [];
        foreach ($lines as $line) {
            $tier = $this->catalog->require($line['tier_id']);
            
            // On récupère le prix unitaire directement (en centimes) 
            // pour éviter les calculs d'arrondi complexes
            $unitAmount = (int)($tier['unit_price_eur'] * 100);

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $tier['title'],
                        'description' => $tier['detail'] ?? null,
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => $line['quantity'],
            ];
        }

        try {
            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                // ✅ Utilisation du générateur d'URL au lieu de liens en dur
                'success_url' => $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->generateUrl('app_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'customer_email' => $user->getUserIdentifier(),
                'client_reference_id' => 'user_' . $user->getId(),
                'locale' => 'fr',
            ]);

            // Création de la commande en base (statut non payé par défaut)
            $order = new Order();
            $order->setTotalCents($this->cartService->totalCents($this->catalog));
            $order->setCartData($lines);
            $order->setStripeCheckoutSessionId($checkoutSession->id);
            $order->setUser($user);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->redirect($checkoutSession->url, 303);

        } catch (\Exception $e) {
            error_log('Stripe Error: ' . $e->getMessage());
            $this->addFlash('error', 'Impossible de contacter le service de paiement (Stripe).');
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

        $order = $this->orderRepository->findOneBy(['stripeCheckoutSessionId' => $sessionId]);

        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Si déjà marqué payé (via Webhook), on redirige simplement
        if ($order->isPaid()) {
            $this->cartService->clear(); // Sécurité
            return $this->redirectToRoute('app_home');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $order->markAsPaid();
                $this->entityManager->flush();
                $this->cartService->clear();

                $this->addFlash('success', 'Merci ! Votre participation a bien été enregistrée.');
            }
        } catch (\Exception $e) {
            error_log('Stripe Verification Error: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la confirmation du paiement.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Le paiement a été annulé.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            return new Response('Invalid webhook payload', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $order = $this->orderRepository->findOneBy(['stripeCheckoutSessionId' => $session->id]);

            if ($order && !$order->isPaid()) {
                $order->markAsPaid();
                $this->entityManager->flush();
            }
        }

        return new Response('Webhook Handled', 200);
    }
}