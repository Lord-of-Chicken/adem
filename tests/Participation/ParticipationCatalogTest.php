<?php

declare(strict_types=1);

namespace App\Tests\Participation;

use App\Participation\ParticipationCatalog;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verifies the catalog reads its tiers from the database (participation_tier table)
 * and exposes the same shape/values the site relied on when they came from YAML.
 *
 * Requires the seeded test database (php bin/console app:seed-participation-tiers
 * --env=test). Skipped cleanly when the DB is unreachable — see docs/TESTING.md.
 */
final class ParticipationCatalogTest extends KernelTestCase
{
    private function catalog(): ParticipationCatalog
    {
        /** @var ParticipationCatalog $catalog */
        $catalog = static::getContainer()->get(ParticipationCatalog::class);

        return $catalog;
    }

    private function translator(): TranslatorInterface
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);

        return $translator;
    }

    private function ensureDatabaseSeeded(): void
    {
        try {
            /** @var ManagerRegistry $registry */
            $registry = static::getContainer()->get('doctrine');
            /** @var EntityManagerInterface $em */
            $em = $registry->getManager();
            $em->getConnection()->executeQuery('SELECT 1');
        } catch (ConnectionException | DriverException | \Doctrine\DBAL\Exception $e) {
            self::markTestSkipped('Test database unavailable: '.$e->getMessage());
        }

        if ($this->catalog()->get('begonia_unit') === null) {
            self::markTestSkipped('participation_tier table not seeded (run app:seed-participation-tiers --env=test).');
        }
    }

    public function testReadsTiersFromDatabase(): void
    {
        self::bootKernel();
        $this->ensureDatabaseSeeded();

        $all = $this->catalog()->all();

        self::assertArrayHasKey('begonia_unit', $all);
        self::assertArrayHasKey('free_donation', $all);
        self::assertSame('begonia_unit', $all['begonia_unit']['id']);
    }

    public function testPricesMatchHistoricalYamlValues(): void
    {
        self::bootKernel();
        $this->ensureDatabaseSeeded();

        $catalog = $this->catalog();

        self::assertSame(1.0, $catalog->require('begonia_unit')['unit_price_eur']);
        self::assertTrue($catalog->require('begonia_unit')['priced_per_unit']);
        self::assertSame(20.0, $catalog->require('vip_20')['unit_price_eur']);
        self::assertSame('vip', $catalog->require('vip_20')['group']);
        self::assertSame(0.0, $catalog->require('free_donation')['unit_price_eur']);
        self::assertTrue($catalog->require('free_donation')['donor_field']);
    }

    public function testLineTotalCentsUsesDatabasePricing(): void
    {
        self::bootKernel();
        $this->ensureDatabaseSeeded();

        $catalog = $this->catalog();

        // begonia_unit is priced per unit (1.00 € => 100 cents) * qty 3 = 300.
        self::assertSame(300, $catalog->lineTotalCents($catalog->require('begonia_unit'), 3));
        // vip_20 is a flat price (20.00 €), quantity ignored.
        self::assertSame(2000, $catalog->lineTotalCents($catalog->require('vip_20'), 5));
    }

    public function testGetTranslatedTiersSplitsByGroupAndResolvesKeys(): void
    {
        self::bootKernel();
        $this->ensureDatabaseSeeded();

        $translated = $this->catalog()->getTranslatedTiers($this->translator());

        self::assertArrayHasKey('standard', $translated);
        self::assertArrayHasKey('vip', $translated);
        self::assertNotEmpty($translated['standard']);
        self::assertNotEmpty($translated['vip']);

        // Titles must be resolved (no leftover "home." translation key).
        foreach ([...$translated['standard'], ...$translated['vip']] as $tier) {
            self::assertStringStartsNotWith('home.', $tier['title']);
        }

        // Every VIP tier carries the vip group.
        foreach ($translated['vip'] as $tier) {
            self::assertSame('vip', $tier['group']);
        }
    }
}
