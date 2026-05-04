<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', $this->translator->trans('admin.client'))->hideOnForm(),
            TextField::new('stripeCheckoutSessionId', $this->translator->trans('admin.order_stripe_session'))->hideOnIndex(),
            ChoiceField::new('status', $this->translator->trans('admin.order_status'))
                ->setChoices([
                    $this->translator->trans('admin.order_status_pending') => 'pending',
                    $this->translator->trans('admin.order_status_paid') => 'paid',
                    $this->translator->trans('admin.order_status_cancelled') => 'cancelled',
                ]),
            IntegerField::new('totalCents', $this->translator->trans('admin.order_amount_cents')),
            DateTimeField::new('createdAt', $this->translator->trans('admin.order_created_at'))->hideOnForm(),
            DateTimeField::new('paidAt', $this->translator->trans('admin.order_paid_at'))->hideOnForm(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('admin.order_label_singular'))
            ->setEntityLabelInPlural($this->translator->trans('admin.order_label_plural'))
            ->setDefaultSort(['paidAt' => 'DESC'])
            ->setSearchFields(['id', 'status', 'stripeCheckoutSessionId']);
    }
}
