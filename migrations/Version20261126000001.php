<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the tables for news and localized news content.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('news')) {
            $news = $schema->createTable('news');
            $news->addOption('engine', 'InnoDB');
            $news->addColumn('id', 'integer', ['autoincrement' => true]);
            $news->addColumn('valid', 'integer', ['notnull' => false]);
            $news->addColumn('userid', 'integer');
            $news->addColumn('client_type', 'integer', ['default' => 0]);
            $news->addColumn('created_at', 'datetime', ['notnull' => false]);
            $news->addColumn('date_beg', 'datetime');
            $news->addColumn('date_end', 'datetime', ['notnull' => false]);
            $news->setPrimaryKey(['id']);
            $news->addIndex(['date_beg'], 'idx_news_date_beg');
        }

        $content = $schema->createTable('news_content');
        $content->addOption('engine', 'InnoDB');
        $content->addOption('charset', 'utf8mb4');
        $content->addOption('collation', 'utf8mb4_unicode_ci');
        $content->addColumn('news_id', 'integer');
        $content->addColumn('locale', 'string', ['length' => 2]);
        $content->addColumn('header', 'string', ['length' => 250, 'notnull' => false]);
        $content->addColumn('text', 'text', ['length' => 65535, 'notnull' => false]);
        $content->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $content->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $content->addColumn('updated_by', 'integer', ['default' => 0]);
        $content->setPrimaryKey(['news_id', 'locale']);
        $content->addForeignKeyConstraint('news', ['news_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_news_content_news');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigration('Removing news content can discard localized text.');
    }
}
