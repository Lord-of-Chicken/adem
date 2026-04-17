<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByStripeCheckoutSessionId(string $sessionId): ?Order
    {
        return $this->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
    }

    public function findUnpaidOrderByUser(User $user): ?Order
    {
        // ✅ 'status' est le vrai champ Doctrine — isPaid() n'est qu'une méthode PHP
        return $this->findOneBy([
            'user'   => $user,
            'status' => 'pending',
        ]);
    }
}