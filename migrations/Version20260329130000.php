<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles column and username unique index on user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD roles JSON DEFAULT '[]' NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "user" (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_USERNAME');
        $this->addSql('ALTER TABLE "user" DROP roles');
    }
}
