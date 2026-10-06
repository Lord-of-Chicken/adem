<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\NewsletterConfirmation;
use App\Repository\NewsletterConfirmationRepository;
use App\Service\NewsletterSubscriptionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class NewsletterSubscriptionServiceTest extends TestCase
{
    /** @return iterable<array{bool}> */
    public static function deliveries(): iterable { yield [false]; yield [true]; }

    #[DataProvider('deliveries')]
    public function testPersistsPendingTokenAndHandlesDeliveryFailure(bool $fails): void
    {
        $repository = $this->createMock(NewsletterConfirmationRepository::class);
        $repository->expects(self::once())->method('save')->willReturnCallback(function (NewsletterConfirmation $token, bool $flush): void {
            self::assertTrue($flush);
            self::assertSame('person@example.test', $token->getEmail());
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token->getToken());
            self::assertFalse($token->isConfirmed());
            self::assertFalse($token->isExpired());
        });
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (RawMessage $message) use ($fails): void {
            self::assertInstanceOf(Email::class, $message);
            self::assertSame('person@example.test', $message->getTo()[0]->getAddress());
            $body = $message->getHtmlBody();
            self::assertIsString($body);
            self::assertStringContainsString('https://example.test/app_newsletter_confirm/', $body);
            if ($fails) { throw new TransportException('fixture SMTP outage'); }
        });
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            /** @param array<string, mixed> $parameters */
            static function (string $name, array $parameters): string {
                self::assertIsString($parameters['token']);
                return 'https://example.test/'.$name.'/'.$parameters['token'];
            }
        );
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Newsletter fixture');
        $logger = $this->createMock(LoggerInterface::class);
        if ($fails) {
            $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
                /** @param array<string, mixed> $context */
                static fn (array $context): bool => ($context['subscriber_email'] ?? null) === 'p***@example.test' && !isset($context['exception'])
            ));
        } else { $logger->expects(self::never())->method('error'); }
        $twig = new Environment(new ArrayLoader(['emails/newsletter_confirm.html.twig' => '{{ confirm_url }} {{ unsubscribe_url }}']));
        (new NewsletterSubscriptionService($repository, $mailer, $generator, $translator, $twig, $logger, 'sender@example.test'))->startDoubleOptIn('person@example.test');
    }
}
