<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2 schema: add reservation relations, cancellation fields, and make event image nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ALTER image DROP NOT NULL');

        $this->addSql('ALTER TABLE reservation ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('UPDATE reservation SET user_id = (SELECT id FROM "user" ORDER BY id ASC LIMIT 1) WHERE user_id IS NULL');
        $this->addSql('UPDATE reservation SET event_id = (SELECT id FROM event ORDER BY id ASC LIMIT 1) WHERE event_id IS NULL');

        $this->addSql('DELETE FROM reservation WHERE user_id IS NULL OR event_id IS NULL');

        $this->addSql('ALTER TABLE reservation DROP name');
        $this->addSql('ALTER TABLE reservation DROP email');
        $this->addSql('ALTER TABLE reservation DROP phone');

        $this->addSql('ALTER TABLE reservation ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE reservation ALTER event_id SET NOT NULL');

        $this->addSql('CREATE INDEX IDX_42C84955A76ED395 ON reservation (user_id)');
        $this->addSql('CREATE INDEX IDX_42C8495571F7E88B ON reservation (event_id)');

        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT FK_42C8495571F7E88B');
        $this->addSql('DROP INDEX IDX_42C84955A76ED395');
        $this->addSql('DROP INDEX IDX_42C8495571F7E88B');

        $this->addSql("ALTER TABLE reservation ADD name VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE reservation ADD email VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE reservation ADD phone VARCHAR(255) DEFAULT '' NOT NULL");

        $this->addSql('ALTER TABLE reservation DROP user_id');
        $this->addSql('ALTER TABLE reservation DROP event_id');
        $this->addSql('ALTER TABLE reservation DROP cancelled_at');

        $this->addSql('ALTER TABLE event ALTER image SET NOT NULL');
    }
}
