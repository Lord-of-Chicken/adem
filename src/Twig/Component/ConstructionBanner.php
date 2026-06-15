<?php

declare(strict_types=1);

namespace App\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'ConstructionBanner')]
class ConstructionBanner
{
    public string $message = 'Chantier en cours';
    public bool $visible = true;
}
