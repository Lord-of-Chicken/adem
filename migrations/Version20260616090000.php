<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the anonymized_at column to user (GDPR right to erasure via anonymisation).
 */
final class Version20260616090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GDPR: add user.anonymized_at for account anonymisation (right to erasure)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD anonymized_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP anonymized_at');
    }
}
