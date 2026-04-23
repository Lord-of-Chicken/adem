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

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                try {
                    $contactEmail = $this->getParameter('app.contact_email');
                    $email = (new Email())
                        ->from($contactEmail)
                        ->to($contactEmail)
                        ->replyTo($data['email'])
                        ->subject('Contact - ' . $data['name'])
                        ->text($data['name'] . ' (' . $data['email'] . '):' . "\n\n" . $data['message'])
                        ->html('<h3>' . htmlspecialchars($data['name']) . ' (' . htmlspecialchars($data['email']) . ')</h3>' .
                               '<p>' . nl2br(htmlspecialchars($data['message'])) . '</p>');

                    $mailer->send($email);

                    $this->addFlash('success', 'Votre message a été envoyé avec succès. Nous vous répondrons bientôt.');

                    return $this->redirectToRoute('app_contact');

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'envoi du message : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Erreur lors de la validation du formulaire.');
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'page' => [
                'title' => 'Contactez-nous',
                'intro' => 'Une question ? Une suggestion ? N\'hésitez pas à nous envoyer un message.',
                'submit' => 'Envoyer le message',
            ],
        ]);
    }
}