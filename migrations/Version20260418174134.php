<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418174134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add firstName and lastName fields to User entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP first_name, DROP last_name');
    }
}
