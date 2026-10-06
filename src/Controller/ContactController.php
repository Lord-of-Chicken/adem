<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ContactFormType;
use App\Service\PiiMasker;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the contact form page and email submission.
 */
final class ContactController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.contact_form')]
        private readonly RateLimiterFactory $contactFormLimiter,
    ) {}

    /**
     * Displays and processes the contact form.
     *
     * @param Request $request The HTTP request
     * @param MailerInterface $mailer The mailer service
     * @param TranslatorInterface $translator The translator service
     * @param LoggerInterface $logger The logger service
     * @return Response The contact form page response
     */
    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        LoggerInterface $logger,
    ): Response {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Rate limiting : 5 messages / heure par IP
            $limiter = $this->contactFormLimiter->create($request->getClientIp() ?? 'anonymous');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', $translator->trans('contact.rate_limited'));
                return $this->redirectToRoute('app_contact');
            }

            $data = $form->getData();
            if (!is_array($data) || !isset($data['name'], $data['email'], $data['message'])
                || !is_string($data['name']) || !is_string($data['email']) || !is_string($data['message'])) {
                throw new \UnexpectedValueException('Invalid contact form data.');
            }

            try {
                $contactEmail = $this->getParameter('app.contact_email');
                if (!is_string($contactEmail)) {
                    throw new \LogicException('Contact sender must be a string.');
                }

                $email = (new Email())
                    ->from($contactEmail)
                    ->to($contactEmail)
                    ->replyTo($data['email'])
                    ->subject($translator->trans('contact.email_subject') . ' - ' . $data['name'])
                    ->text($data['name'] . ' (' . $data['email'] . '):' . "\n\n" . $data['message'])
                    ->html('<h3>' . htmlspecialchars($data['name']) . ' (' . htmlspecialchars($data['email']) . ')</h3>' .
                           '<p>' . nl2br(htmlspecialchars($data['message'])) . '</p>');

                $mailer->send($email);

                $this->addFlash('success', $translator->trans('contact.success'));

                return $this->redirectToRoute('app_contact');

            } catch (\Exception $e) {
                // Never leak internal exception details to the user, and never log
                // the sender's email/name in clear text (PII minimisation).
                $logger->error('Contact form mail failure', [
                    'sender_email' => PiiMasker::maskEmail(\is_string($data['email'] ?? null) ? $data['email'] : null),
                    'error_class'  => $e::class,
                ]);
                $this->addFlash('error', $translator->trans('contact.error', ['%error%' => '']));
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', $translator->trans('contact.validation_error'));
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'page' => [
                'title' => $translator->trans('contact.title'),
                'intro' => $translator->trans('contact.intro'),
                'submit' => $translator->trans('contact.submit'),
            ],
        ]);
    }
}