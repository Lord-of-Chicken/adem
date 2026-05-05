<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
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
            TextField::new('password', $this->translator->trans('profile.password'))
                ->onlyOnForms()
                ->setRequired(false),
        ];
    }
}
