<?php
namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        error_log('Contact form submitted: ' . ($form->isSubmitted() ? 'yes' : 'no'));

        if ($form->isSubmitted()) {
            error_log('Contact form valid: ' . ($form->isValid() ? 'yes' : 'no'));
            if ($form->isValid()) {
                $data = $form->getData();
                error_log('Form data: ' . json_encode($data));

                try {
                    $contactEmail = $this->getParameter('app.contact_email');
                    error_log('Contact email: ' . $contactEmail);

                    $email = (new Email())
                        ->from($contactEmail)
                        ->to($contactEmail)
                        ->replyTo($data['email'])
                        ->subject($translator->trans('contact.email_subject') . ' - ' . $data['name'])
                        ->text($data['name'] . ' (' . $data['email'] . '):' . "\n\n" . $data['message'])
                        ->html('<h3>' . htmlspecialchars($data['name']) . ' (' . htmlspecialchars($data['email']) . ')</h3>' .
                               '<p>' . nl2br(htmlspecialchars($data['message'])) . '</p>');

                    $mailer->send($email);
                    error_log('Email sent successfully');

                    $this->addFlash('success', $translator->trans('contact.success'));

                    return $this->redirectToRoute('app_contact');

                } catch (\Exception $e) {
                    $this->addFlash('error', $translator->trans('contact.error', ['%error%' => $e->getMessage()]));
                    error_log('Mailer error: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', $translator->trans('contact.validation_error'));
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                error_log('Form validation errors: ' . implode(', ', $errors));
            }
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