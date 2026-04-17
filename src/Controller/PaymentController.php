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

        $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$stripeKey) {
            throw new \RuntimeException('La clé secrète Stripe n\'est pas configurée.');
        }
        Stripe::setApiKey($stripeKey);

        // ✅ Gestion des commandes orphelines (nettoyage avant nouvelle tentative)
        $existingOrder = $this->orderRepository->findUnpaidOrderByUser($user);
        if ($existingOrder) {
            try {
                $oldSession = Session::retrieve($existingOrder->getStripeCheckoutSessionId());
                if ($oldSession->status === 'open') {
                    $oldSession->expire();
                }
            } catch (\Exception) {
                // Session déjà expirée ou inexistante chez Stripe
            }
            $this->entityManager->remove($existingOrder);
            $this->entityManager->flush();
        }

        // Préparation des articles pour Stripe
        $lineItems = [];
        foreach ($lines as $line) {
            $tier = $this->catalog->require($line['tier_id']);
            
            // Gestion des dons libres avec prix personnalisé
            if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                $unitAmount = $line['custom_price_cents']; // Prix personnalisé en cents
                $description = 'Don libre de ' . ($line['custom_price_cents'] / 100) . '€';
            } else {
                // Conversion unit_price_eur (ex: "20.00") -> cents (2000)
                $unitAmount = (int) ((float)$tier['unit_price_eur'] * 100);
                $description = $tier['detail'] ?? null;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name' => $tier['title'],
                        'description' => $description,
                    ],
                    'unit_amount'  => $unitAmount,
                ],
                'quantity' => $line['quantity'],
            ];
        }

        try {
            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $lineItems,
                'mode'                 => 'payment',
                'customer_email'       => $user->getEmail(),
                'metadata'             => [
                    'order_user_id' => $user->getId(),
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

            // Création de la commande en base de données (statut non payé par défaut)
            $order = new Order();
            $order->setTotalCents($this->cartService->totalCents($this->catalog));
            $order->setCartData($lines);
            $order->setStripeCheckoutSessionId($checkoutSession->id);
            $order->setUser($user);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

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

        $order = $this->orderRepository->findOneBy(['stripeCheckoutSessionId' => $sessionId]);

        if (!$order || $order->isPaid()) {
            return $this->redirectToRoute('app_home');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $order->markAsPaid(); // Assurez-vous que cette méthode existe dans l'entité Order
                $this->entityManager->flush();
                
                // On vide le panier en session
                $this->cartService->clear();

                $this->addFlash('success', 'Merci ! Votre participation a bien été enregistrée.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de vérifier le paiement.');
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * ✅ ROUTE AJOUTÉE : Gestion de l'annulation par l'utilisateur
     */
    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Le paiement a été annulé. Ton panier est toujours là !');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            return new Response('Invalid webhook', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $order = $this->orderRepository->findOneBy(['stripeCheckoutSessionId' => $session->id]);

            if ($order && !$order->isPaid()) {
                $order->markAsPaid();
                $this->entityManager->flush();
            }
        }

        return new Response('Event handled', 200);
    }
}