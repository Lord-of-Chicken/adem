<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return $this->redirectToRoute('admin_media_item_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('home.title'))
            ->renderContentMaximized()
            ->renderSidebarMinimized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard($this->translator->trans('admin.dashboard'), 'fa fa-home');
        yield MenuItem::linkToRoute($this->translator->trans('admin.carousel'), 'fa fa-image', 'admin_media_item_index');
        yield MenuItem::linkToRoute($this->translator->trans('admin.users'), 'fa fa-user', 'admin_user_index');
        yield MenuItem::linkToRoute($this->translator->trans('admin.purchases'), 'fa fa-shopping-cart', 'admin_order_index');
    }
}
