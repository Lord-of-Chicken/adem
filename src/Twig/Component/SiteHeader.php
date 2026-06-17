<?php

declare(strict_types=1);

namespace App\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'SiteHeader')]
class SiteHeader
{
    public string $logoAsset;
    public string $title;
    /** @var array<int, array{label: string, url: string, active?: bool}> */
    public array $navLinks;
    public ?int $cartLineCount = null;
    public bool $isAuthenticated = false;
    public bool $isAdmin = false;
    public string $profileUrl = '';
    public string $loginUrl = '';
    public string $registerUrl = '';
    public string $cartUrl = '';
    public string $adminUrl = '';
}
