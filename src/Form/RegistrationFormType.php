<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['autocomplete' => 'street-address', 'rows' => 3],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('newsletter', CheckboxType::class, [
                'label' => 'Voulez-vous être tenu informé des événements à venir ?',
                'required' => false,
                'mapped' => true,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Choisis un mot de passe.'),
                    new Length(min: 8, max: 4096, minMessage: 'Au moins 8 caractères.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
