<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227034336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upload (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uuid CLOB NOT NULL, stored_filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , deleted_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , field_name VARCHAR(255) NOT NULL, entity_id INTEGER NOT NULL, created_by_user_id INTEGER NOT NULL, created_by_unit_id INTEGER NOT NULL, entity_type VARCHAR(255) NOT NULL, entity_uuid VARCHAR(255) NOT NULL, status SMALLINT NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE upload');
    }
}
