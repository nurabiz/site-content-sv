<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create documents and their localized content.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (
                id INT NOT NULL AUTO_INCREMENT,
                type_id INT NOT NULL DEFAULT 0,
                translit VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documents_content (
                id INT NOT NULL AUTO_INCREMENT,
                doc_id INT NOT NULL,
                locale VARCHAR(2) NOT NULL,
                doc_content TEXT NOT NULL,
                updated_at DATETIME(6) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_document_locale (doc_id, locale),
                CONSTRAINT fk_documents_content_document
                    FOREIGN KEY (doc_id) REFERENCES documents (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE documents_content');
        $this->addSql('DROP TABLE documents');
    }
}
