<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable email field to user for reservation confirmations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE "user" DROP email');
    }
}
