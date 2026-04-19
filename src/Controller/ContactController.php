<?php

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                // Send email
                $email = (new Email())
                    ->from('info@ruelledadem.com')
                    ->to('info@ruelledadem.com')
                    ->replyTo($data['email'])
                    ->subject('Contact depuis La Ruelle d\'Adem - ' . $data['name'])
                    ->text($data['message'])
                    ->html('<p><strong>Nom:</strong> ' . $data['name'] . '</p>' .
                           '<p><strong>Email:</strong> ' . $data['email'] . '</p>' .
                           '<p><strong>Message:</strong></p>' .
                           '<p>' . nl2br($data['message']) . '</p>');

                $mailer->send($email);

                $this->addFlash('success', 'Votre message a été envoyé avec succès. Nous vous répondrons bientôt.');

                return $this->redirectToRoute('app_contact');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi du message : ' . $e->getMessage());
                // Log the error
                error_log('Email sending error: ' . $e->getMessage());
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
