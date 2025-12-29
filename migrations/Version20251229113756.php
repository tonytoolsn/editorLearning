<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229113756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__upload AS SELECT id, uuid, stored_filename, original_filename, mime_type, created_at, deleted_at, field_name, entity_id, created_by_user_id, created_by_unit_id, entity_type, entity_uuid, status FROM upload');
        $this->addSql('DROP TABLE upload');
        $this->addSql('CREATE TABLE upload (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uuid CLOB DEFAULT NULL, stored_filename VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, created_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , deleted_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , field_name VARCHAR(255) DEFAULT NULL, entity_id INTEGER DEFAULT NULL, created_by_user_id INTEGER DEFAULT NULL, created_by_unit_id INTEGER DEFAULT NULL, entity_type VARCHAR(255) DEFAULT NULL, entity_uuid VARCHAR(255) DEFAULT NULL, status SMALLINT DEFAULT NULL)');
        $this->addSql('INSERT INTO upload (id, uuid, stored_filename, original_filename, mime_type, created_at, deleted_at, field_name, entity_id, created_by_user_id, created_by_unit_id, entity_type, entity_uuid, status) SELECT id, uuid, stored_filename, original_filename, mime_type, created_at, deleted_at, field_name, entity_id, created_by_user_id, created_by_unit_id, entity_type, entity_uuid, status FROM __temp__upload');
        $this->addSql('DROP TABLE __temp__upload');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__upload AS SELECT id, uuid, stored_filename, original_filename, mime_type, entity_type, entity_id, entity_uuid, field_name, status, created_by_user_id, created_by_unit_id, created_at, deleted_at FROM upload');
        $this->addSql('DROP TABLE upload');
        $this->addSql('CREATE TABLE upload (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uuid CLOB NOT NULL, stored_filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, entity_type VARCHAR(255) NOT NULL, entity_id INTEGER NOT NULL, entity_uuid VARCHAR(255) NOT NULL, field_name VARCHAR(255) NOT NULL, status SMALLINT NOT NULL, created_by_user_id INTEGER NOT NULL, created_by_unit_id INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , deleted_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('INSERT INTO upload (id, uuid, stored_filename, original_filename, mime_type, entity_type, entity_id, entity_uuid, field_name, status, created_by_user_id, created_by_unit_id, created_at, deleted_at) SELECT id, uuid, stored_filename, original_filename, mime_type, entity_type, entity_id, entity_uuid, field_name, status, created_by_user_id, created_by_unit_id, created_at, deleted_at FROM __temp__upload');
        $this->addSql('DROP TABLE __temp__upload');
    }
}
