<?php

declare(strict_types=1);

namespace App\Tests\Participation;

use App\Entity\ParticipationTier;
use App\Participation\ParticipationCatalog;
use App\Repository\ParticipationTierRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;

final class CatalogUnitTest extends TestCase
{
    private function tier(): ParticipationTier
    {
        return (new ParticipationTier())->setId('fixture')->setTitle('Literal title')->setDetail('home.fixture_detail')
            ->setPriceLabel('20')->setPriceSuffix('home.suffix')->setUnitPriceEur('20.00')->setMaxQty(5)->setTierGroup('vip');
    }

    public function testLiteralStringsTranslationsGroupingAndUnknownTier(): void
    {
        $repo = $this->createMock(ParticipationTierRepository::class);
        $repo->expects(self::once())->method('findAllActiveOrdered')->willReturn([$this->tier()]);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'Translated '.$key);
        $catalog = new ParticipationCatalog($repo);
        self::assertSame('Literal title', $catalog->require('fixture')['title']);
        self::assertNull($catalog->get('unknown'));
        $groups = $catalog->getTranslatedTiers($translator);
        self::assertSame([], $groups['standard']);
        self::assertSame('Translated home.fixture_detail', $groups['vip'][0]['detail']);
        self::assertSame('Translated home.suffix', $groups['vip'][0]['price_suffix']);
        self::assertSame(2000, $catalog->lineTotalCents($catalog->require('fixture'), 5));
        self::assertSame('1 234,56', $catalog->formatEurosFromCents(123456));
        self::assertSame(1999, ParticipationCatalog::eurToCents('19.99'));
        self::assertSame(101, ParticipationCatalog::eurToCents(1.005));
        $this->expectException(\InvalidArgumentException::class);
        $catalog->require('unknown');
    }

    public function testTierRejectsNegativePriceInvalidBoundsAndUnknownGroup(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $tier = $this->tier();
        self::assertCount(0, $validator->validate($tier));
        $tier->setUnitPriceEur('-1.00')->setMinQty(6)->setMaxQty(2)->setTierGroup('unrecognized');
        $errors = $validator->validate($tier);
        self::assertGreaterThanOrEqual(3, count($errors));
        $properties = [];
        foreach ($errors as $error) { $properties[] = $error->getPropertyPath(); }
        self::assertContains('unitPriceEur', $properties);
        self::assertContains('maxQty', $properties);
        self::assertContains('tierGroup', $properties);
    }
}
