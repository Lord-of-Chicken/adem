<?php
namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        $logger->info('Contact page accessed');

        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $logger->info('Contact form submitted');

            if ($form->isValid()) {
                $data = $form->getData();

                $logger->info('Form data received', [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'message_length' => strlen($data['message'])
                ]);

                // Validation supplémentaire de l'email (sécurité)
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $logger->warning('Invalid email format', ['email' => $data['email']]);
                    $this->addFlash('error', 'Adresse email invalide.');
                    return $this->redirectToRoute('app_contact');
                }

                try {
                    $logger->info('Starting email construction', [
                        'from' => 'info@ruelledadem.com',
                        'to' => 'info@ruelledadem.com',
                        'reply_to' => $data['email'],
                        'subject' => 'Contact depuis La Ruelle d\'Adem - ' . $data['name']
                    ]);

                    $email = (new Email())
                        ->from('info@ruelledadem.com')
                        ->to('info@ruelledadem.com')
                        ->replyTo($data['email'])
                        ->subject('Contact depuis La Ruelle d\'Adem - ' . $data['name'])
                        ->text($data['message'])
                        ->html(
                            '<p><strong>Nom:</strong> ' . htmlspecialchars($data['name']) . '</p>' .
                            '<p><strong>Email:</strong> ' . htmlspecialchars($data['email']) . '</p>' .
                            '<p><strong>Message:</strong></p>' .
                            '<p>' . nl2br(htmlspecialchars($data['message'])) . '</p>'
                        );

                    $logger->info('Email object created, attempting to send');

                    $mailer->send($email);

                    $logger->info('Email sent successfully', [
                        'to' => 'info@ruelledadem.com',
                        'subject' => 'Contact depuis La Ruelle d\'Adem - ' . $data['name']
                    ]);

                    $this->addFlash('success', 'Votre message a été envoyé avec succès. Nous vous répondrons bientôt.');

                    return $this->redirectToRoute('app_contact');

                } catch (\Exception $e) {
                    $logger->error('Email sending failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Erreur lors de l\'envoi du message : ' . $e->getMessage());
                    error_log('Email sending error: ' . $e->getMessage());
                }
            } else {
                $logger->warning('Form validation failed', [
                    'errors' => (string) $form->getErrors(true, false)
                ]);
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}