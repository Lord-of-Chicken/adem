<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for the contact page.
 */
final class ContactFormType extends AbstractType
{
    /**
     * Builds the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options The options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre nom.'),
                    new Length(max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre e-mail.'),
                    new Email(message: 'Veuillez entrer une adresse e-mail valide.'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 6],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre message.'),
                    new Length(min: 10, max: 1000),
                ],
            ]);
    }

    /**
     * Configures the default options.
     *
     * @param OptionsResolver $resolver The options resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // SÉCURITÉ : Protection CSRF activée
            'csrf_protection' => true,
        ]);
    }
}