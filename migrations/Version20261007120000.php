<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add durable site payment receipt notifications (one per order)'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payment_receipt (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, attempts INT DEFAULT 0 NOT NULL, last_error_class VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_4AD0B8AF8D9F6D38 (order_id), INDEX idx_payment_receipt_pending (sent_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment_receipt ADD CONSTRAINT FK_PAYMENT_RECEIPT_ORDER FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preserve receipt delivery history; archive it explicitly before rollback.');
    }
}
