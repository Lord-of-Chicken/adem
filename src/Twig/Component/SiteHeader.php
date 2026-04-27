<?php

namespace App\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'SiteHeader')]
class SiteHeader
{
    public string $logoAsset;
    public string $title;
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
