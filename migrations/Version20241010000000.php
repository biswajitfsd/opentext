<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241010000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add debricked_upload_id, repository_id, and commit_id columns to upload table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE upload ADD debricked_upload_id VARCHAR(255) DEFAULT NULL, ADD repository_id VARCHAR(255) DEFAULT NULL, ADD commit_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE upload DROP debricked_upload_id, DROP repository_id, DROP commit_id');
    }
}