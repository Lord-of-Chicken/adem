<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ParticipationTier;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * EasyAdmin CRUD for participation tiers.
 *
 * Lets the family edit price, display order, min/max quantities, group and flags
 * without a redeploy. Title/detail/price hold translation KEYS (e.g.
 * "home.tier_begonia_title"); the actual texts stay editable via the translations
 * editor. The price actually charged is driven by "unit_price_eur".
 *
 * @extends AbstractCrudController<ParticipationTier>
 */
class ParticipationTierCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParticipationTier::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Formule de participation')
            ->setEntityLabelInPlural('Formules de participation')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setSearchFields(['id', 'title', 'tierGroup']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('id', 'Identifiant')
            ->setHelp('Code technique unique (ex. begonia_unit). Ne pas modifier après création.')
            ->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT);

        yield NumberField::new('unitPriceEur', 'Prix unitaire (€)')
            ->setNumDecimals(2)
            ->setHelp('Prix réellement facturé. 0 pour un don libre.');

        yield BooleanField::new('pricedPerUnit', 'Prix × quantité')
            ->setHelp('Coché : le prix est multiplié par la quantité. Décoché : prix forfaitaire.')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield ChoiceField::new('tierGroup', 'Groupe')
            ->setChoices(['Standard' => 'standard', 'VIP' => 'vip'])
            ->renderExpanded(false);

        yield IntegerField::new('sortOrder', 'Ordre d\'affichage')
            ->setHelp('Ordre croissant dans sa colonne.');

        yield IntegerField::new('minQty', 'Quantité min')->hideOnIndex();
        yield IntegerField::new('maxQty', 'Quantité max')->hideOnIndex();

        yield BooleanField::new('donorField', 'Champ "nom du donateur"')
            ->setHelp('Affiche un champ nom personnalisé (formules VIP).')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield BooleanField::new('active', 'Active')
            ->setHelp('Décoché : la formule est masquée du site.')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield TextField::new('title', 'Clé de titre')
            ->setHelp('Clé de traduction (ex. home.tier_begonia_title).')
            ->hideOnIndex();
        yield TextareaField::new('detail', 'Clé de description')
            ->setHelp('Clé de traduction (ex. home.tier_begonia_detail).')
            ->hideOnIndex();
        yield TextField::new('priceLabel', 'Libellé / clé de prix')
            ->setHelp('Libellé affiché ou clé de traduction (ex. 6, ou home.tier_free_price).')
            ->hideOnIndex();
        yield TextField::new('priceUnit', 'Symbole monétaire')->hideOnIndex();
        yield TextField::new('priceSuffix', 'Suffixe de prix (clé)')
            ->setHelp('Optionnel. Clé de traduction (ex. home.tier_begonia_price_unit).')
            ->hideOnIndex();
    }
}
