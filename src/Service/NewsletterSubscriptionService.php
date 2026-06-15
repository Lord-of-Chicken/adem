<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsletterConfirmation;
use App\Repository\NewsletterConfirmationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Drives the GDPR double opt-in newsletter flow: token creation and confirmation
 * email dispatch. The newsletter flag is only enabled once the user confirms.
 */
final readonly class NewsletterSubscriptionService
{
    public function __construct(
        private NewsletterConfirmationRepository $confirmationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.contact_email')]
        private string $senderEmail,
    ) {
    }

    /**
     * Starts the double opt-in flow for an email: creates a confirmation token and
     * sends the confirmation email. Failures are logged, never thrown to the caller
     * (registration/subscription must not break on a transient mail error).
     *
     * @param string $email The subscriber email
     * @return void
     */
    public function startDoubleOptIn(string $email): void
    {
        $token = bin2hex(random_bytes(32));
        $confirmation = new NewsletterConfirmation($email, $token);
        $this->confirmationRepository->save($confirmation, flush: true);

        try {
            $confirmUrl = $this->urlGenerator->generate(
                'app_newsletter_confirm',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $unsubscribeUrl = $this->urlGenerator->generate(
                'app_newsletter_unsubscribe',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $message = (new Email())
                ->from($this->senderEmail)
                ->to($email)
                ->subject($this->translator->trans('newsletter.email_subject'))
                ->text($this->translator->trans('newsletter.email_text', ['%url%' => $confirmUrl]))
                ->html($this->twig->render('emails/newsletter_confirm.html.twig', [
                    'confirm_url' => $confirmUrl,
                    'unsubscribe_url' => $unsubscribeUrl,
                ]));

            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Newsletter confirmation email delivery failed', [
                'subscriber_email' => PiiMasker::maskEmail($email),
                'error_class' => $e::class,
            ]);
        }
    }
}
