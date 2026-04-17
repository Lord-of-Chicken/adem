<?php

namespace App\Cart;

use App\Participation\ParticipationCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'participation_cart_v1';

    public function __construct(private readonly RequestStack $requestStack) {}

    /**
     * Récupère la session de manière sécurisée
     */
    private function session(): ?SessionInterface
    {
        return $this->requestStack->getCurrentRequest()?->getSession();
    }

    /**
     * Récupère toutes les lignes du panier
     */
    public function getLines(): array
    {
        return $this->session()?->get(self::SESSION_KEY) ?? [];
    }

    /**
     * Calcule le total en centimes (basé sur unit_price_eur du Seeder)
     */
    public function totalCents(ParticipationCatalog $catalog): int
    {
        $total = 0;
        foreach ($this->getLines() as $line) {
            $tier = $catalog->require($line['tier_id']);
            // Conversion forcée : "1.00" (string) -> 1.0 (float) -> 100 (int cents)
            $priceCents = (int) (round((float)$tier['unit_price_eur'] * 100));
            $total += $priceCents * $line['quantity'];
        }
        return $total;
    }

    /**
     * Ajoute une ligne au panier
     */
    public function addLine(string $tierId, int $quantity, ?string $donorName, ParticipationCatalog $catalog): void
    {
        $session = $this->session();
        if (!$session) return;

        $tier = $catalog->require($tierId);
        $lines = $this->getLines();

        // On génère un ID unique pour cette ligne (pour pouvoir la modifier/supprimer plus tard)
        $lineId = bin2hex(random_bytes(8));

        $lines[] = [
            'line_id'    => $lineId,
            'tier_id'    => $tierId,
            'quantity'   => max($tier['min_qty'], min($tier['max_qty'], $quantity)),
            'donor_name' => $this->normalizeDonor($donorName, $tier['donor_field'] ?? false),
        ];

        $session->set(self::SESSION_KEY, $lines);
    }

    /**
     * Met à jour la quantité d'une ligne spécifique via son line_id
     */
    public function updateQuantity(string $lineId, int $quantity, ParticipationCatalog $catalog): void
    {
        $session = $this->session();
        if (!$session) return;

        $lines = $this->getLines();
        foreach ($lines as $i => $line) {
            if ($line['line_id'] === $lineId) {
                $tier = $catalog->require($line['tier_id']);
                $lines[$i]['quantity'] = max($tier['min_qty'], min($tier['max_qty'], $quantity));
                $session->set(self::SESSION_KEY, $lines);
                return;
            }
        }
    }

    /**
     * Supprime une ligne du panier
     */
    public function removeLine(string $lineId): void
    {
        $session = $this->session();
        if (!$session) return;

        $lines = array_filter($this->getLines(), fn($l) => $l['line_id'] !== $lineId);
        
        // array_values est important pour réinitialiser les index du tableau [0, 1, 2...]
        $session->set(self::SESSION_KEY, array_values($lines));
    }

    /**
     * ✅ MÉTHODE AJOUTÉE : Vide complètement le panier (après paiement réussi)
     */
    public function clear(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    /**
     * Nettoie le nom du donateur ou renvoie null si non autorisé/vide
     */
    private function normalizeDonor(?string $donorName, bool $allowed): ?string
    {
        if (!$allowed) return null;
        $trimmed = trim($donorName ?? '');
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Compte le nombre d'articles (lignes) dans le panier
     */
    public function countLines(): int 
    { 
        return count($this->getLines()); 
    }
}