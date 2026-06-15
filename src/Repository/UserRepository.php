<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository for User entity.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     *
     * @param PasswordAuthenticatedUserInterface $user The user to upgrade
     * @param string $newHashedPassword The new hashed password
     * @return void
     * @throws UnsupportedUserException If user is not supported
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Finds a user by email address.
     *
     * @param string $email The email address
     * @return User|null The user or null if not found
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Returns a paginated slice of users, optionally filtered by newsletter status.
     *
     * @param int $page The 1-based page number
     * @param int $perPage The number of users per page
     * @param bool|null $newsletter Newsletter filter, or null for all users
     * @return list<User> The users on the requested page
     */
    public function findPaginated(int $page, int $perPage, ?bool $newsletter = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($newsletter !== null) {
            $qb->andWhere('u.newsletter = :subscribed')
                ->setParameter('subscribed', $newsletter);
        }

        /** @var list<User> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Counts users, optionally filtered by newsletter status.
     *
     * @param bool|null $newsletter Newsletter filter, or null for all users
     * @return int The total number of matching users
     */
    public function countUsers(?bool $newsletter = null): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        if ($newsletter !== null) {
            $qb->andWhere('u.newsletter = :subscribed')
                ->setParameter('subscribed', $newsletter);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
