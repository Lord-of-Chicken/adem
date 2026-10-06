<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Form type for requesting password reset.
 */
/** @extends AbstractType<array{email: string}> */
final class ResetPasswordRequestFormType extends AbstractType
{
    /**
     * Builds the form.
     *
     * @param FormBuilderInterface<array{email: string}|null> $builder The form builder
     * @param array<string, mixed> $options The options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Entrez votre adresse e-mail.'),
                    new Email(),
                    new Length(max: 180),
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
        $resolver->setDefaults([]);
    }
}
