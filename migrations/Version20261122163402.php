<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122163402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce uniqueness of the document translit and locale pair.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(false !== $this->connection->fetchOne(<<<'SQL'
            SELECT 1
            FROM documents_content c
            JOIN documents d ON d.id = c.doc_id
            GROUP BY d.translit, c.locale
            HAVING COUNT(*) > 1
            LIMIT 1
            SQL), 'Resolve duplicate document translit and locale pairs before migrating.');

        if (!$schema->getTable('documents')->hasIndex('uniq_documents_id_translit')) {
            $this->addSql('ALTER TABLE documents ADD UNIQUE INDEX uniq_documents_id_translit (id, translit)');
        }
        if (!$schema->getTable('documents_content')->hasColumn('translit')) {
            $this->addSql('ALTER TABLE documents_content ADD translit VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL');
        }
        $this->addSql(<<<'SQL'
            UPDATE documents_content c
            JOIN documents d ON d.id = c.doc_id
            SET c.translit = d.translit
            SQL);
        $content = $schema->getTable('documents_content');
        if (!$content->hasForeignKey('fk_documents_content_translit')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE documents_content
                    MODIFY translit VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    ADD UNIQUE INDEX uniq_documents_content_translit_locale (translit, locale),
                    ADD CONSTRAINT fk_documents_content_translit
                        FOREIGN KEY (doc_id, translit) REFERENCES documents (id, translit)
                        ON UPDATE CASCADE ON DELETE CASCADE
                SQL);
        }
        if ($content->hasForeignKey('fk_documents_content_document')) {
            $this->addSql('ALTER TABLE documents_content DROP FOREIGN KEY fk_documents_content_document');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE documents_content
                ADD CONSTRAINT fk_documents_content_document
                    FOREIGN KEY (doc_id) REFERENCES documents (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents_content
                DROP FOREIGN KEY fk_documents_content_translit,
                DROP INDEX uniq_documents_content_translit_locale,
                DROP COLUMN translit
            SQL);
        $this->addSql('ALTER TABLE documents DROP INDEX uniq_documents_id_translit');
    }
}
