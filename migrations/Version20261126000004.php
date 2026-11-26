<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the news publication dates to start_at and finish_at.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('news')->renameColumn('date_beg', 'start_at');
        $schema->getTable('news')->renameColumn('date_end', 'finish_at');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('news')->renameColumn('start_at', 'date_beg');
        $schema->getTable('news')->renameColumn('finish_at', 'date_end');
    }
}
