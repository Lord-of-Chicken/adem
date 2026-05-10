<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $passwordField = TextField::new('password', $this->translator->trans('profile.password'))
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'first_options'  => ['label' => $this->translator->trans('profile.password')],
                'second_options' => ['label' => $this->translator->trans('profile.password')],
                'mapped' => false,
                'required' => false,
                'constraints' => [new Length(min: 8, max: 4096)],
            ])
            ->onlyOnForms();

        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('email', $this->translator->trans('profile.email')),
            TextField::new('firstName', $this->translator->trans('profile.first_name')),
            TextField::new('lastName', $this->translator->trans('profile.last_name')),
            TextField::new('address', $this->translator->trans('profile.address'))->hideOnIndex(),
            BooleanField::new('newsletter', $this->translator->trans('profile.newsletter_status')),
            ChoiceField::new('roles', $this->translator->trans('admin.roles'))
                ->allowMultipleChoices()
                ->setChoices([
                    $this->translator->trans('admin.role_admin') => 'ROLE_ADMIN',
                    $this->translator->trans('admin.role_user') => 'ROLE_USER',
                ])
                ->renderExpanded(),
            $passwordField,
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPasswordFromForm($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPasswordFromForm($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPasswordFromForm(User $user): void
    {
        $context = $this->getContext();
        if (!$context) {
            return;
        }
        $request = $context->getRequest();
        // EasyAdmin form name is "User"; pull the unmapped password field directly.
        $payload = $request->request->all('User');
        $plain = $payload['password']['first'] ?? null;
        if (\is_string($plain) && $plain !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        }
    }
}
