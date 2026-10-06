<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\BackendWebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationFlowTest extends BackendWebTestCase
{
    public function testLoginAcceptsCorrectPasswordAndRejectsWrongPassword(): void
    {
        $this->user();
        $crawler = $this->client->request('GET', '/fr/connexion');
        $form = $crawler->filter('form')->form(['_username' => 'backend-audit@example.test', '_password' => 'wrong']);
        $this->client->submit($form);
        self::assertResponseRedirects('http://localhost/fr/connexion');
        $crawler = $this->client->request('GET', '/fr/connexion');
        $this->client->submit($crawler->filter('form')->form(['_username' => 'backend-audit@example.test', '_password' => 'Audit-password-123']));
        self::assertResponseRedirects('http://localhost/fr/');
        $this->client->request('GET', '/fr/profil');
        self::assertResponseStatusCodeSame(200);
    }

    public function testRegistrationValidatesAgeEmailAndDatabaseLengths(): void
    {
        $crawler = $this->client->request('GET', '/fr/inscription');
        $form = $crawler->filter('form')->form([
            'registration_form[email]' => 'not-an-email',
            'registration_form[firstName]' => str_repeat('a', 101),
        ]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
        $crawler = $this->client->request('GET', '/fr/inscription');
        $this->client->submit($crawler->filter('form')->form([
            'registration_form[email]' => 'registered@example.test', 'registration_form[ageConfirmed]' => '1',
            'registration_form[newsletter]' => '1',
        ]));
        self::assertResponseRedirects('/fr/connexion');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'registered@example.test']);
        self::assertNotNull($user);
        self::assertFalse($user->isNewsletter());
        self::assertNotNull($user->getPassword());
        self::assertSame(1, $this->em->getRepository(\App\Entity\NewsletterConfirmation::class)->count(['email' => 'registered@example.test']));
    }

    public function testResetRequiresValidTokenAndConsumesIt(): void
    {
        $user = $this->user();
        $user->setResetToken(hash('sha256', 'valid-fixture'))->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();
        $crawler = $this->client->request('GET', '/fr/reset-password/valid-fixture');
        self::assertResponseStatusCodeSame(200);
        $this->client->submit($crawler->filter('form')->form([
            'reset_password_form[plainPassword][first]' => 'New-password-123',
            'reset_password_form[plainPassword][second]' => 'New-password-123',
        ]));
        self::assertResponseRedirects('/fr/connexion');
        $user = $this->reload($user);
        self::assertNull($user->getResetToken());
        self::assertNull($user->getResetTokenExpiresAt());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'New-password-123'));
        $this->client->request('GET', '/fr/reset-password/valid-fixture');
        self::assertResponseRedirects('/fr/reset-password');
    }

    public function testExpiredTokenAndInvalidResetEmailDoNotChangeAccount(): void
    {
        $user = $this->user();
        $password = $user->getPassword();
        $user->setResetToken(hash('sha256', 'expired-fixture'))->setResetTokenExpiresAt(new \DateTimeImmutable('-1 second'));
        $this->em->flush();
        $this->client->request('GET', '/fr/reset-password/expired-fixture');
        self::assertResponseRedirects('/fr/reset-password');
        $crawler = $this->client->request('GET', '/fr/reset-password');
        $this->client->submit($crawler->filter('form')->form(['reset_password_request_form[email]' => 'invalid']));
        self::assertResponseStatusCodeSame(200);
        $user = $this->reload($user);
        self::assertSame($password, $user->getPassword());
        self::assertSame(hash('sha256', 'expired-fixture'), $user->getResetToken());
    }

    public function testProfileCannotChangeAnotherAccountOrEscalateRole(): void
    {
        $user = $this->user();
        $other = $this->user('other@example.test');
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/fr/profil/modifier');
        $form = $crawler->filter('form')->form(['profile_form[firstName]' => 'Edited', 'profile_form[email]' => $user->getUserIdentifier()]);
        $values = $form->getPhpValues();
        self::assertIsArray($values['profile_form']);
        $values['profile_form']['roles'] = ['ROLE_ADMIN'];
        $this->client->request('POST', '/fr/profil/modifier', $values);
        self::assertResponseStatusCodeSame(200);
        $user = $this->reload($user);
        $other = $this->reload($other);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('Audit', $other->getFirstName());
    }

    public function testProfileDeleteRequiresCsrfAndPreservesOrders(): void
    {
        $user = $this->user();
        $order = (new \App\Entity\Order())->setStripeCheckoutSessionId('cs_erasure')->setTotalCents(2000)->setCartData([
            ['donor_name' => 'Private name'],
        ])->setUser($user);
        $confirmation = new \App\Entity\NewsletterConfirmation($user->getUserIdentifier(), hash('sha256', 'erasure-token'));
        $this->em->persist($confirmation);
        $this->em->persist($order);
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('POST', '/fr/profil/supprimer');
        self::assertResponseRedirects('/fr/profil');
        $user = $this->reload($user);
        self::assertFalse($user->isAnonymized());
        $this->client->request('POST', '/fr/profil/supprimer', ['_token' => $this->csrf('delete_profile')]);
        self::assertResponseRedirects('/fr/');
        $user = $this->reload($user);
        $order = $this->reload($order);
        self::assertTrue($user->isAnonymized());
        $confirmation = $this->reload($confirmation);
        self::assertSame($user->getEmail(), $confirmation->getEmail());
        self::assertTrue($confirmation->isExpired());
        self::assertFalse($confirmation->isConfirmed());
        self::assertSame(0, $this->em->getRepository(\App\Entity\NewsletterConfirmation::class)->count(['email' => 'backend-audit@example.test']));
        self::assertSame([['donor_name' => null]], $order->getCartData());
        self::assertSame(2000, $order->getTotalCents());
        $this->client->request('GET', '/fr/profil');
        self::assertResponseRedirects('/fr/connexion');
    }
    public function testPasswordChangeRequiresCurrentPassword(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);
        foreach (['wrong', 'Audit-password-123'] as $current) {
            $crawler = $this->client->request('GET', '/fr/profil/changer-mot-de-passe');
            $this->client->submit($crawler->filter('form')->form([
                'change_password_form[currentPassword]' => $current,
                'change_password_form[newPassword][first]' => 'Changed-password-123',
                'change_password_form[newPassword][second]' => 'Changed-password-123',
            ]));
            self::assertResponseStatusCodeSame(302);
            $user = $this->reload($user);
            self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, $current === 'wrong' ? 'Audit-password-123' : 'Changed-password-123'));
        }
    }

    public function testProfileUnsubscribeRevokesPendingConfirmation(): void
    {
        $user = $this->user();
        $user->setNewsletter(true);
        $confirmation = new \App\Entity\NewsletterConfirmation($user->getUserIdentifier(), hash('sha256', 'profile-pending'));
        $this->em->persist($confirmation);
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('POST', '/fr/profil/newsletter', ['_token' => $this->csrf('toggle_newsletter')]);
        self::assertResponseRedirects('/fr/profil');
        self::assertFalse($this->reload($user)->isNewsletter());
        self::assertTrue($this->reload($confirmation)->isExpired());
    }

}
