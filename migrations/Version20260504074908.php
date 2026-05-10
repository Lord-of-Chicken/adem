<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504074908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE nom_entite (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participation_tier (id VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, detail LONGTEXT NOT NULL, price_label VARCHAR(16) NOT NULL, price_unit VARCHAR(8) NOT NULL, price_suffix VARCHAR(32) DEFAULT NULL, unit_price_eur NUMERIC(10, 2) NOT NULL, priced_per_unit TINYINT NOT NULL, min_qty INT NOT NULL, max_qty INT NOT NULL, tier_group VARCHAR(16) NOT NULL, donor_field TINYINT NOT NULL, sort_order INT NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        // Skip DROP currency as it doesn't exist in fresh database
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE nom_entite');
        $this->addSql('DROP TABLE participation_tier');
        $this->addSql('ALTER TABLE `order` ADD currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL');
    }
}
