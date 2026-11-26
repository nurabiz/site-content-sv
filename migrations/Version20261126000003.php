<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert news.valid from integer to boolean.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            false !== $this->connection->fetchOne('SELECT 1 FROM news WHERE valid NOT IN (0, 1) LIMIT 1'),
            'news.valid contains values other than 0 and 1.',
        );

        $schema->getTable('news')->getColumn('valid')->setType(Type::getType(Types::BOOLEAN));
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('news')->getColumn('valid')->setType(Type::getType(Types::INTEGER));
    }
}
