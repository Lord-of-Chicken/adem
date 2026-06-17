<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widen participation_tier.price_label and price_suffix so they can hold translation
 * keys (e.g. "home.tier_free_price") now that the DB is the catalog source of truth.
 */
final class Version20260616180507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen participation_tier price_label/price_suffix to 64 chars (hold translation keys)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_tier CHANGE price_label price_label VARCHAR(64) NOT NULL, CHANGE price_suffix price_suffix VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_tier CHANGE price_label price_label VARCHAR(16) NOT NULL, CHANGE price_suffix price_suffix VARCHAR(32) DEFAULT NULL');
    }
}
