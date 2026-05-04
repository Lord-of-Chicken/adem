<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartService $cart, ParticipationCatalog $catalog, TranslatorInterface $translator): Response
    {
        $lines = [];
        foreach ($cart->getLines() as $line) {
            $tier = $catalog->require($line['tier_id']);

            // Traduire title_key et detail_key en title et detail
            if (isset($tier['title_key'])) {
                $tier['title'] = $translator->trans($tier['title_key']);
            }
            if (isset($tier['detail_key'])) {
                $tier['detail'] = $translator->trans($tier['detail_key']);
            }
            if (isset($tier['price_key'])) {
                $tier['price'] = $translator->trans($tier['price_key']);
            }
            if (isset($tier['price_suffix_key'])) {
                $tier['price_suffix'] = $translator->trans($tier['price_suffix_key']);
            }

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
            'page' => [
                'title' => $translator->trans('cart.title'),
                'empty_hint' => $translator->trans('cart.empty_hint'),
                'empty_link' => $translator->trans('cart.empty_link'),
                'total_label' => $translator->trans('cart.total_label'),
                'clear_btn' => $translator->trans('cart.clear_btn'),
                'checkout_btn' => $translator->trans('cart.checkout_btn'),
                'remove_btn' => $translator->trans('cart.remove_btn'),
            ],
        ]);
    }

    #[Route('/panier/ajouter', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, CartService $cart, ParticipationCatalog $catalog, TranslatorInterface $translator): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('cart_add', (string) $request->request->get('_token'))) {
            return $isAjax
                ? $this->json(['success' => false, 'message' => $translator->trans('cart.csrf_invalid')], 403)
                : throw $this->createAccessDeniedException($translator->trans('cart.csrf_invalid'));
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
                    ? $this->json(['success' => false, 'message' => $translator->trans('cart.amount_invalid')])
                    : $this->redirectToRoute('app_home');
            }
            $customPriceCents = (int) round($amount * 100);
            $quantity = 1;
        }

        try {
            $cart->addLine($tierId, $quantity, $donorName, $catalog, $customPriceCents);
        } catch (\InvalidArgumentException) {
            return $isAjax
                ? $this->json(['success' => false, 'message' => $translator->trans('cart.formula_not_found')])
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
    public function remove(string $lineId, Request $request, CartService $cart, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('cart.csrf_invalid'));
        }

        $cart->removeLine($lineId);
        $this->addFlash('success', $translator->trans('cart.line_removed'));

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/panier/ligne/{lineId}/quantite', name: 'app_cart_quantity', methods: ['POST'])]
    public function setQuantity(string $lineId, Request $request, CartService $cart, ParticipationCatalog $catalog, TranslatorInterface $translator): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('cart_quantity', (string) $request->request->get('_token'))) {
            return $isAjax
                ? $this->json(['success' => false, 'message' => $translator->trans('cart.csrf_invalid')], 403)
                : throw $this->createAccessDeniedException($translator->trans('cart.csrf_invalid'));
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

    #[Route('/panier/vider', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Request $request, CartService $cart, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('cart_clear', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('cart.csrf_invalid'));
        }

        $cart->clear();
        $this->addFlash('success', $translator->trans('cart.cart_cleared'));

        return $this->redirectToRoute('app_cart_index');
    }
}