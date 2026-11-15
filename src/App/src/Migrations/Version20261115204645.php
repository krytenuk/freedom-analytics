<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the index the live page reads recent visits by
 */
final class Version20261115204645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indexes visits by site and last activity, for the live page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_visits_site_activity ON visits (site_id, last_activity_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_visits_site_activity ON visits');
    }
}
