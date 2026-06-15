<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515074544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_media_item_published_sort ON media_item (published, sort_order)');
        $this->addSql('CREATE INDEX idx_order_stripe_session ON `order` (stripe_checkout_session_id)');
        $this->addSql('CREATE INDEX idx_order_status ON `order` (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_media_item_published_sort ON media_item');
        $this->addSql('DROP INDEX idx_order_stripe_session ON `order`');
        $this->addSql('DROP INDEX idx_order_status ON `order`');
    }
}
