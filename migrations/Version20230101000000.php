<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration';
    }

    public function up(Schema $schema): void
    {
        // This empty up() method represents that
        // the current database state is the desired state
    }

    public function down(Schema $schema): void
    {
        // This empty down() method means there's nothing to revert
    }
}