<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418180715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address field to User entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP address');
    }
}
