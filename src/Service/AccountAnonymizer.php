<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Anonymises a user account in place of a hard delete (GDPR right to erasure).
 *
 * Orders are preserved to satisfy the 7-year fiscal retention obligation, but any
 * personal data they duplicate (donor name) is scrubbed, and all personal data on
 * the User entity is erased while keeping the row (and its order links) intact.
 */
final readonly class AccountAnonymizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Anonymises the given user and its related orders.
     *
     * Idempotent: a user already anonymised is left untouched.
     *
     * @param User $user The user to anonymise
     * @return void
     */
    public function anonymize(User $user): void
    {
        if ($user->isAnonymized()) {
            return;
        }

        $id = $user->getId();

        $user->setEmail(sprintf('deleted_%s@deleted.local', $id ?? bin2hex(random_bytes(8))));
        $user->setFirstName('Supprimé');
        $user->setLastName('Supprimé');
        $user->setAddress(null);
        $user->setNewsletter(false);
        $user->setRoles([]);
        // Unusable random password: account can no longer authenticate.
        $user->setPassword(bin2hex(random_bytes(32)));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $user->setAnonymizedAt(new \DateTimeImmutable());

        foreach ($user->getOrders() as $order) {
            $this->anonymizeOrderCartData($order);
        }

        $this->entityManager->flush();
    }

    /**
     * Scrubs the donor name duplicated inside an order's cart data snapshot.
     *
     * @param Order $order The order whose cart data must be anonymised
     * @return void
     */
    private function anonymizeOrderCartData(Order $order): void
    {
        $cartData = $order->getCartData();
        $changed = false;

        foreach ($cartData as $key => $line) {
            if (is_array($line) && array_key_exists('donor_name', $line) && $line['donor_name'] !== null) {
                $cartData[$key]['donor_name'] = null;
                $changed = true;
            }
        }

        if ($changed) {
            $order->setCartData($cartData);
        }
    }
}
