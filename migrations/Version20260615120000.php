<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds Stripe webhook idempotency table and order payment intent tracking.
 */
final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stripe security hardening: idempotency table + order payment_intent column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stripe_processed_event (id INT AUTO_INCREMENT NOT NULL, stripe_event_id VARCHAR(255) NOT NULL, processed_at DATETIME NOT NULL, UNIQUE INDEX uniq_stripe_event_id (stripe_event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `order` ADD stripe_payment_intent_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_order_stripe_payment_intent ON `order` (stripe_payment_intent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_stripe_payment_intent ON `order`');
        $this->addSql('ALTER TABLE `order` DROP stripe_payment_intent_id');
        $this->addSql('DROP TABLE stripe_processed_event');
    }
}
