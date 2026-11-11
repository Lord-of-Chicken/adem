<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class BackendWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::service(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        parent::tearDown();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected static function service(string $class): object
    {
        $service = static::getContainer()->get($class);
        if (!$service instanceof $class) {
            throw new \LogicException('Unexpected test service.');
        }
        return $service;
    }

    protected function user(string $email = 'backend-audit@example.test', bool $admin = false): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Audit')->setLastName('Fixture')
            ->setRoles($admin ? ['ROLE_ADMIN'] : ['ROLE_USER']);
        $user->setPassword(self::service(UserPasswordHasherInterface::class)->hashPassword($user, 'Audit-password-123'));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    /** @param array<string, mixed> $values */
    protected function session(array $values): Session
    {
        $factory = static::getContainer()->get('session.factory');
        if (!$factory instanceof SessionFactoryInterface) {
            throw new \LogicException('Unexpected session factory.');
        }
        $session = $factory->createSession();
        if (!$session instanceof Session) {
            throw new \LogicException('Unexpected test session implementation.');
        }
        $cookie = $this->client->getCookieJar()->get($session->getName());
        if ($cookie !== null) {
            $session->setId($cookie->getValue());
        }
        $session->start();
        foreach ($values as $name => $value) {
            $session->set($name, $value);
        }
        $session->save();
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
        return $session;
    }

    /**
     * @template T of \App\Entity\Order|User|\App\Entity\MediaItem|\App\Entity\NewsletterConfirmation
     * @param T $entity
     * @return T
     */
    protected function reload(object $entity): object
    {
        $class = $entity::class;
        $fresh = $this->em->getRepository($class)->find($entity->getId());
        if (!$fresh instanceof $class) {
            throw new \LogicException('Missing fixture.');
        }
        $this->em->refresh($fresh);
        return $fresh;
    }

    protected function csrf(string $id): string
    {
        $this->session(['_csrf/'.$id => 'audit-csrf-fixture-with-at-least-thirty-two-characters']);
        return 'audit-csrf-fixture-with-at-least-thirty-two-characters';
    }
}
