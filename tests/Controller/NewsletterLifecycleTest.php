<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\NewsletterConfirmation;
use App\Tests\Support\BackendWebTestCase;

final class NewsletterLifecycleTest extends BackendWebTestCase
{
    public function testConfirmReplayAndUnsubscribePreserveFinalChoice(): void
    {
        $user = $this->user();
        $token = new NewsletterConfirmation($user->getUserIdentifier(), 'audit-newsletter-token');
        $this->em->persist($token);
        $this->em->flush();
        $this->client->request('GET', '/fr/newsletter/confirmer/audit-newsletter-token');
        self::assertResponseStatusCodeSame(302);
        $user = $this->reload($user);
        self::assertTrue($user->isNewsletter());
        $token = $this->reload($token);
        $date = $token->getConfirmedAt();
        self::assertNotNull($date);
        $this->client->request('GET', '/fr/newsletter/desinscription/audit-newsletter-token');
        $user = $this->reload($user);
        self::assertFalse($user->isNewsletter());
        $this->client->request('GET', '/fr/newsletter/confirmer/audit-newsletter-token');
        $user = $this->reload($user);
        $token = $this->reload($token);
        self::assertFalse($user->isNewsletter());
        self::assertEquals($date, $token->getConfirmedAt());
    }

    public function testPendingTokensCannotReenableAfterUnsubscribe(): void
    {
        $user = $this->user();
        foreach (['pending-one', 'pending-two'] as $value) {
            $this->em->persist(new NewsletterConfirmation($user->getUserIdentifier(), $value));
        }
        $this->em->flush();
        $this->client->request('GET', '/fr/newsletter/desinscription/pending-one');
        self::assertResponseStatusCodeSame(302);
        foreach (['pending-one', 'pending-two', 'unknown'] as $value) {
            $this->client->request('GET', '/fr/newsletter/confirmer/'.$value);
            self::assertResponseStatusCodeSame(302);
            $user = $this->reload($user);
            self::assertFalse($user->isNewsletter());
        }
        foreach ($this->em->getRepository(NewsletterConfirmation::class)->findAll() as $token) {
            self::assertNull($token->getConfirmedAt(), 'Cancellation must not fabricate proof of consent.');
        }
    }

    public function testExpiredConfirmationAndUnknownUnsubscribeAreHarmless(): void
    {
        $user = $this->user();
        $token = new NewsletterConfirmation($user->getUserIdentifier(), 'expired-token');
        $this->em->persist($token);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('UPDATE newsletter_confirmation SET expires_at = ? WHERE token = ?', ['2000-01-01 00:00:00', 'expired-token']);
        $this->em->clear();
        $this->client->request('GET', '/fr/newsletter/confirmer/expired-token');
        self::assertResponseStatusCodeSame(302);
        $stored = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $user->getUserIdentifier()]);
        self::assertNotNull($stored);
        self::assertFalse($stored->isNewsletter());
        $this->client->request('GET', '/fr/newsletter/desinscription/unknown');
        self::assertResponseStatusCodeSame(302);
    }
}
