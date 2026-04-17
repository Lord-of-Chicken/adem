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
            $tier = $catalog->require($line['tier_id']);
            
            // Calcul du total de la ligne (Don Libre ou Standard)
            if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                $lineTotal = $line['custom_price_cents'] * $line['quantity'];
            } else {
                $lineTotal = $catalog->lineTotalCents($tier, $line['quantity']);
            }

            $lines[] = [
                'line'             => $line,
                'tier'             => $tier,
                'line_total_cents' => $lineTotal,
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
        $isAjax = $request->isXmlHttpRequest();
        
        if (!$this->isCsrfTokenValid('cart_add', (string) $request->request->get('_token'))) {
            return $isAjax 
                ? $this->json(['success' => false, 'message' => 'Jeton CSRF invalide.'], 403)
                : throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $tierId    = (string) $request->request->get('tier_id');
        $quantity  = (int) $request->request->get('quantity', 1);
        $donorRaw  = $request->request->get('donor_name');
        $donorName = \is_string($donorRaw) ? $donorRaw : null;

        $customPriceCents = null;
        if ($tierId === 'free_donation') {
            $amount = (float) $request->request->get('amount');
            if ($amount <= 0) {
                return $isAjax 
                    ? $this->json(['success' => false, 'message' => 'Montant invalide.'])
                    : $this->redirectToRoute('app_home');
            }
            $customPriceCents = (int) round($amount * 100);
            $quantity = 1;
        }

        try {
            $cart->addLine($tierId, $quantity, $donorName, $catalog, $customPriceCents);
        } catch (\InvalidArgumentException) {
            return $isAjax 
                ? $this->json(['success' => false, 'message' => 'Formule introuvable.'])
                : $this->redirectToRoute('app_home');
        }

        if ($isAjax) {
            return $this->json([
                'success'   => true,
                'cartCount' => $cart->countLines(),
            ]);
        }

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

    /**
     * ✅ CORRIGÉ : Renvoie newLineTotal et newTotalHtml pour Stimulus
     */
    #[Route('/panier/ligne/{lineId}/quantite', name: 'app_cart_quantity', methods: ['POST'])]
    public function setQuantity(string $lineId, Request $request, CartService $cart, ParticipationCatalog $catalog): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('cart_quantity', (string) $request->request->get('_token'))) {
            return $isAjax 
                ? $this->json(['success' => false, 'message' => 'CSRF Invalid'], 403)
                : throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $cart->updateQuantity($lineId, $quantity, $catalog);

        if ($isAjax) {
            // On calcule le nouveau prix de la ligne spécifique pour le renvoyer au JS
            $newLineTotalFormatted = '0,00';
            foreach ($cart->getLines() as $line) {
                if ($line['line_id'] === $lineId) {
                    $tier = $catalog->require($line['tier_id']);
                    $price = $line['custom_price_cents'] ?? (int)round((float)$tier['unit_price_eur'] * 100);
                    $newLineTotalFormatted = $catalog->formatEurosFromCents($price * $line['quantity']);
                    break;
                }
            }

            return $this->json([
                'success' => true,
                'newLineTotal' => $newLineTotalFormatted, // Mis à jour dans la ligne
                'newTotalHtml' => $catalog->formatEurosFromCents($cart->totalCents($catalog)), // Mis à jour en bas de page
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }
}