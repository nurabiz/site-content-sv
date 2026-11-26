<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000006 extends AbstractMigration
{
    private const array INDEXES = [
        'idx_news_start_id' => ['start_at', 'id'],
        'idx_news_valid_start_id' => ['valid', 'start_at', 'id'],
        'idx_news_client_type_start_id' => ['client_type', 'start_at', 'id'],
        'idx_news_valid_client_type_start_id' => ['valid', 'client_type', 'start_at', 'id'],
    ];

    public function getDescription(): string
    {
        return 'Index news list ordering and optional filters.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $news = $schema->getTable('news');
        foreach (self::INDEXES as $name => $columns) {
            $news->addIndex($columns, $name);
        }
    }

    public function down(Schema $schema): void
    {
        $news = $schema->getTable('news');
        foreach (array_keys(self::INDEXES) as $name) {
            $news->dropIndex($name);
        }
    }
}
