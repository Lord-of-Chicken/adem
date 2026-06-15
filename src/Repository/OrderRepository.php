<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Order entity.
 *
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Saves an order to the database.
     *
     * @param Order $entity The order to save
     * @param bool $flush Whether to flush the entity manager
     * @return void
     */
    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Removes an order from the database.
     *
     * @param Order $entity The order to remove
     * @param bool $flush Whether to flush the entity manager
     * @return void
     */
    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Finds an order by Stripe checkout session ID.
     *
     * @param string $sessionId The Stripe session ID
     * @return Order|null The order or null if not found
     */
    public function findByStripeCheckoutSessionId(string $sessionId): ?Order
    {
        return $this->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
    }

    /**
     * Finds an order by Stripe payment intent ID.
     *
     * @param string $paymentIntentId The Stripe payment intent ID
     * @return Order|null The order or null if not found
     */
    public function findByStripePaymentIntentId(string $paymentIntentId): ?Order
    {
        return $this->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);
    }

    /**
     * Finds an unpaid order for a specific user.
     *
     * @param User $user The user to search for
     * @return Order|null The unpaid order or null if not found
     */
    public function findUnpaidOrderByUser(User $user): ?Order
    {
        // 'status' est le vrai champ Doctrine — isPaid() n'est qu'une méthode PHP.
        // Doctrine convertit l'enum vers sa valeur string ('pending') côté DBAL.
        return $this->findOneBy([
            'user'   => $user,
            'status' => OrderStatus::Pending,
        ]);
    }
}