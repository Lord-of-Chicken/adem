<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartService $cart, ParticipationCatalog $catalog): Response
    {
        $lines = [];
        foreach ($cart->getLines() as $line) {
            $tier    = $catalog->require($line['tier_id']);
            $lines[] = [
                'line'             => $line,
                'tier'             => $tier,
                'line_total_cents' => $catalog->lineTotalCents($tier, $line['quantity']),
            ];
        }

        return $this->render('cart/index.html.twig', [
            'lines'       => $lines,
            'total_cents' => $cart->totalCents($catalog),
            'catalog'     => $catalog,
        ]);
    }

    #[Route('/panier/ajouter', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, CartService $cart, ParticipationCatalog $catalog): Response
    {
        if (!$this->isCsrfTokenValid('cart_add', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $tierId    = (string) $request->request->get('tier_id');
        $quantity  = (int) $request->request->get('quantity', 1);
        $donorRaw  = $request->request->get('donor_name');
        $donorName = \is_string($donorRaw) ? $donorRaw : null;

        try {
            $cart->addLine($tierId, $quantity, $donorName, $catalog);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', 'Formule introuvable.');
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', 'Participation ajoutée au panier.');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/panier/ligne/{lineId}/supprimer', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(string $lineId, Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $cart->removeLine($lineId);

        $this->addFlash('success', 'Ligne retirée du panier.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/panier/ligne/{lineId}/quantite', name: 'app_cart_quantity', methods: ['POST'])]
    public function setQuantity(string $lineId, Request $request, CartService $cart, ParticipationCatalog $catalog): Response
    {
        if (!$this->isCsrfTokenValid('cart_quantity', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $cart->updateQuantity($lineId, $quantity, $catalog);

        return $this->redirectToRoute('app_cart_index');
    }
}