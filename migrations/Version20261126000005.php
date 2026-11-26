<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional image to news.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('news')->addColumn('image', Types::STRING, ['length' => 100, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('news')->dropColumn('image');
    }
}
