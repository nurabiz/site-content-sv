<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move existing Russian and English news text to news_content.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $news = $schema->getTable('news');
        if (!$news->hasColumn('header_ru')) {
            return;
        }

        $this->addSql(<<<'SQL'
            INSERT INTO news_content (news_id, locale, header, text, created_at, updated_at)
            SELECT id, 'ru', header_ru, text_ru, COALESCE(created_at, date_beg), COALESCE(created_at, date_beg)
            FROM news
            ON DUPLICATE KEY UPDATE news_id = news_content.news_id
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO news_content (news_id, locale, header, text, created_at, updated_at)
            SELECT id, 'en', header_en, text_en, COALESCE(created_at, date_beg), COALESCE(created_at, date_beg)
            FROM news
            WHERE header_en IS NOT NULL OR text_en IS NOT NULL
            ON DUPLICATE KEY UPDATE news_id = news_content.news_id
            SQL);

        foreach (['header_ru', 'text_ru', 'header_en', 'text_en'] as $column) {
            $news->dropColumn($column);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigration('The legacy news table cannot represent all localized content.');
    }
}
