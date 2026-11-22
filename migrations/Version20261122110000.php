<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track creation and update timestamps for documents and localized content.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE documents
                ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
            SQL);
        if (!$schema->getTable('documents_content')->hasColumn('updated_at')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE documents_content
                    ADD created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    ADD updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
                SQL);

            return;
        }

        $this->addSql('ALTER TABLE documents_content ADD created_at DATETIME(6) NULL');
        $this->addSql(<<<'SQL'
            UPDATE documents_content
            SET created_at = COALESCE(updated_at, CURRENT_TIMESTAMP(6)),
                updated_at = COALESCE(updated_at, created_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents_content
                MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents_content DROP created_at, MODIFY updated_at DATETIME(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE documents DROP created_at, DROP updated_at');
    }
}
