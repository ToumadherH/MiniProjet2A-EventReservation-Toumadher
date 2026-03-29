<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329221303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD first_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ALTER username TYPE VARCHAR(180)');
        $this->addSql('ALTER TABLE "user" ALTER roles DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_identifier_username RENAME TO UNIQ_8D93D649F85E0677');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP first_name');
        $this->addSql('ALTER TABLE "user" DROP last_name');
        $this->addSql('ALTER TABLE "user" ALTER username TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "user" ALTER roles SET DEFAULT \'[]\'');
        $this->addSql('ALTER INDEX uniq_8d93d649f85e0677 RENAME TO uniq_identifier_username');
    }
}
