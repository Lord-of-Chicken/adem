<?php
namespace App\Controller;

use App\Form\ContactFormType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactController extends AbstractController
{
    // TODO: installer symfony/rate-limiter pour activer une limitation par IP
    // (5 messages / heure recommandé) afin d'endiguer le spam.
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
            $data = $form->getData();

            try {
                $contactEmail = $this->getParameter('app.contact_email');

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
                // Never leak internal exception details to the user.
                $logger->error('Contact form mail failure', ['exception' => $e]);
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