<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles shopping cart operations including adding, removing, updating quantities, and clearing the cart.
 */
final class CartController extends AbstractController
{
    /** @var array<string> Allowed CSRF token intents for cart operations */
    private const ALLOWED_CSRF_INTENTS = [
        'cart_add',
        'cart_remove',
        'cart_quantity',
        'cart_clear',
    ];

    /**
     * Returns a CSRF token for the specified intent.
     *
     * @param Request $request The HTTP request
     * @param CsrfTokenManagerInterface $csrfTokenManager The CSRF token manager
     * @return Response JSON response with the CSRF token
     */
    #[Route('/csrf-token', name: 'app_csrf_token', methods: ['GET'])]
    public function csrfToken(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $intent = (string) $request->query->get('intent', 'cart_add');
        if (!in_array($intent, self::ALLOWED_CSRF_INTENTS, true)) {
            return $this->json(['error' => 'invalid_intent'], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->json(['token' => $csrfTokenManager->getToken($intent)->getValue()]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * Displays the shopping cart with all items and totals.
     *
     * @param CartService $cart The cart service
     * @param ParticipationCatalog $catalog The participation catalog
     * @param TranslatorInterface $translator The translator service
     * @return Response The cart page response
     */
    #[Route('/panier', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartService $cart, ParticipationCatalog $catalog, TranslatorInterface $translator): Response
    {
        $lines = [];
        foreach ($cart->getLines() as $line) {
            $tier = $catalog->translateTier($catalog->require($line['tier_id']), $translator);

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

    /**
     * Adds an item to the shopping cart.
     *
     * @param Request $request The HTTP request
     * @param CartService $cart The cart service
     * @param ParticipationCatalog $catalog The participation catalog
     * @param TranslatorInterface $translator The translator service
     * @return Response JSON or redirect response
     */
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

        // GDPR: the donor name is transferred to Stripe (metadata, outside the EU)
        // and displayed publicly. It may only be kept with explicit consent.
        // Absent/unticked consent => drop the name rather than storing it.
        $donorConsent = $request->request->getBoolean('donor_consent');
        if (!$donorConsent) {
            $donorName = null;
        }

        $customPriceCents = null;
        if ($tierId === 'free_donation') {
            $amount = (float) $request->request->get('amount');
            $customPriceCents = ParticipationCatalog::eurToCents($amount);
            if ($customPriceCents < CartService::MIN_CUSTOM_PRICE_CENTS
                || $customPriceCents > CartService::MAX_CUSTOM_PRICE_CENTS) {
                return $isAjax
                    ? $this->json(['success' => false, 'message' => $translator->trans('cart.amount_invalid')])
                    : $this->redirectToRoute('app_home');
            }
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

    /**
     * Removes a specific line from the shopping cart.
     *
     * @param string $lineId The line ID to remove
     * @param Request $request The HTTP request
     * @param CartService $cart The cart service
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to cart page
     */
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

    /**
     * Updates the quantity of a specific line in the shopping cart.
     *
     * @param string $lineId The line ID to update
     * @param Request $request The HTTP request
     * @param CartService $cart The cart service
     * @param ParticipationCatalog $catalog The participation catalog
     * @param TranslatorInterface $translator The translator service
     * @return Response JSON or redirect response
     */
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
            $newLineTotalFormatted = $catalog->formatEurosFromCents($cart->getLineTotal($lineId, $catalog));

            return $this->json([
                'success' => true,
                'newLineTotal' => $newLineTotalFormatted, // Mis à jour dans la ligne
                'newTotalHtml' => $catalog->formatEurosFromCents($cart->totalCents($catalog)), // Mis à jour en bas de page
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    /**
     * Clears all items from the shopping cart.
     *
     * @param Request $request The HTTP request
     * @param CartService $cart The cart service
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to cart page
     */
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