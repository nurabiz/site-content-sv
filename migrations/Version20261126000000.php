<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the ID of the last user to update each localized document content.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('documents_content')->addColumn('updated_by', 'integer', ['default' => 0]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('documents_content')->dropColumn('updated_by');
    }
}
