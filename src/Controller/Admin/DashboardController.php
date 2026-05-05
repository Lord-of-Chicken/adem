<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
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
        yield MenuItem::linkToRoute($this->translator->trans('admin.nav_translations'), 'fa fa-language', 'admin_translations', ['locale' => 'fr']);
    }

    #[AdminRoute(path: '/translations/{locale}', name: 'translations', options: ['requirements' => ['locale' => 'fr|en'], 'methods' => ['GET', 'POST']])]
    public function translations(string $locale, Request $request, KernelInterface $kernel): Response
    {
        $filePath = $kernel->getProjectDir() . '/translations/messages.' . $locale . '.yaml';

        if (!is_file($filePath)) {
            throw $this->createNotFoundException($this->translator->trans('admin.translation_file_not_found'));
        }

        $content = (string) file_get_contents($filePath);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_translations_' . $locale, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->translator->trans('admin.csrf_invalid'));
            }

            $newContent = (string) $request->request->get('content', '');

            try {
                Yaml::parse($newContent);
            } catch (ParseException $exception) {
                $this->addFlash('danger', $this->translator->trans('admin.translation_yaml_error') . ' ' . $exception->getMessage());

                return $this->render('admin/translations.html.twig', [
                    'selected_locale' => $locale,
                    'content' => $newContent,
                ]);
            }

            file_put_contents($filePath, rtrim($newContent) . "\n");
            $this->addFlash('success', $this->translator->trans('admin.translation_saved'));

            return $this->redirectToRoute('admin_translations', ['locale' => $locale]);
        }

        return $this->render('admin/translations.html.twig', [
            'selected_locale' => $locale,
            'content' => $content,
        ]);
    }
}
