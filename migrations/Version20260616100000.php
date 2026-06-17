<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the orphan `nom_entite` table. The `NomEntite` entity was removed from the
 * codebase, but the table was recreated by migration Version20260504074908 and never
 * dropped again. This migration removes it so the schema matches the mapping.
 */
final class Version20260616100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphan table nom_entite (entity removed from code)';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$schema->hasTable('nom_entite'),
            'Table nom_entite does not exist, nothing to drop.'
        );

        $this->addSql('DROP TABLE nom_entite');
    }

    public function down(Schema $schema): void
    {
        // Recreate the table from its last known definition (Version20260504074908).
        $this->addSql('CREATE TABLE nom_entite (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
