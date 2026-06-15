<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the newsletter_confirmation table for the GDPR double opt-in flow.
 */
final class Version20260616093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GDPR: newsletter double opt-in (newsletter_confirmation table)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_confirmation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_43D023425F37A13B (token), INDEX idx_newsletter_confirmation_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_confirmation');
    }
}
