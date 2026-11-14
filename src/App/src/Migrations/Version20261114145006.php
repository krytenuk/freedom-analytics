<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114145006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the analytics tables (users, sites, visitors, visits, pages, page views, events) and doctrine-auth\'s login tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE events (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, site_id INT UNSIGNED NOT NULL, visit_id BIGINT UNSIGNED NOT NULL, visitor_id BIGINT UNSIGNED NOT NULL, page_id BIGINT UNSIGNED DEFAULT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', visit_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', category VARCHAR(100) NOT NULL, action VARCHAR(100) NOT NULL, label VARCHAR(255) DEFAULT NULL, value NUMERIC(14, 2) DEFAULT NULL, INDEX IDX_5387574AF6BD1646 (site_id), INDEX IDX_5387574A75FA0FF2 (visit_id), INDEX IDX_5387574A70BEE6D (visitor_id), INDEX IDX_5387574AC4663E4 (page_id), INDEX idx_events_site_date (site_id, visit_date), INDEX idx_events_visit (visit_id, occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE failed_login_attempts_log (id INT AUTO_INCREMENT NOT NULL, identity_hash VARCHAR(64) NOT NULL, ip_address VARCHAR(45) NOT NULL, attempted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX failed_login_identity (identity_hash, attempted_at), INDEX failed_login_ip (ip_address, attempted_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ip_blocked (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, blocked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX ip_blocked_ip (ip_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE login_log (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(64) NOT NULL, ip_address VARCHAR(45) NOT NULL, logged_in_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX login_log_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pages (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, site_id INT UNSIGNED NOT NULL, url_hash CHAR(40) NOT NULL, url LONGTEXT NOT NULL, path VARCHAR(768) NOT NULL, title VARCHAR(255) DEFAULT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2074E575F6BD1646 (site_id), UNIQUE INDEX uniq_pages_site_hash (site_id, url_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pageviews (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, site_id INT UNSIGNED NOT NULL, visit_id BIGINT UNSIGNED NOT NULL, visitor_id BIGINT UNSIGNED NOT NULL, page_id BIGINT UNSIGNED NOT NULL, viewed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', visit_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', time_on_page INT UNSIGNED DEFAULT NULL, INDEX IDX_CB11F046F6BD1646 (site_id), INDEX IDX_CB11F04675FA0FF2 (visit_id), INDEX IDX_CB11F04670BEE6D (visitor_id), INDEX IDX_CB11F046C4663E4 (page_id), INDEX idx_pageviews_site_date (site_id, visit_date), INDEX idx_pageviews_visit (visit_id, viewed_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE password_reminder (id INT AUTO_INCREMENT NOT NULL, consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', token_hash VARCHAR(64) NOT NULL, user_id VARCHAR(64) NOT NULL, ip_address VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX password_reminder_user (user_id, created_at), INDEX password_reminder_ip (ip_address, created_at), UNIQUE INDEX password_reminder_token (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sites (id INT UNSIGNED AUTO_INCREMENT NOT NULL, site_key VARCHAR(32) NOT NULL, name VARCHAR(100) NOT NULL, domain VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, check_domain TINYINT(1) DEFAULT 1 NOT NULL, respect_dnt TINYINT(1) DEFAULT 1 NOT NULL, excluded_ips LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_sites_site_key (site_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE two_factor_app (user_id VARCHAR(64) NOT NULL, secret VARCHAR(255) NOT NULL, last_timestep INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE two_factor_recovery_code (id INT AUTO_INCREMENT NOT NULL, used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_id VARCHAR(64) NOT NULL, code_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX two_factor_recovery_code_hash (user_id, code_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT NOT NULL, email VARCHAR(254) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(100) NOT NULL, role VARCHAR(20) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_users_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_sites (user_id INT UNSIGNED NOT NULL, site_id INT UNSIGNED NOT NULL, INDEX IDX_5EC2513BA76ED395 (user_id), INDEX IDX_5EC2513BF6BD1646 (site_id), PRIMARY KEY(user_id, site_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visitors (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, site_id INT UNSIGNED NOT NULL, visitor_key VARCHAR(64) NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', num_visits INT UNSIGNED DEFAULT 0 NOT NULL, INDEX IDX_7B74A43FF6BD1646 (site_id), UNIQUE INDEX uniq_visitors_site_key (site_id, visitor_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visits (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, site_id INT UNSIGNED NOT NULL, visitor_id BIGINT UNSIGNED NOT NULL, entry_page_id BIGINT UNSIGNED DEFAULT NULL, exit_page_id BIGINT UNSIGNED DEFAULT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_activity_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', visit_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', visit_hour SMALLINT UNSIGNED NOT NULL, duration INT UNSIGNED DEFAULT 0 NOT NULL, num_pageviews INT UNSIGNED DEFAULT 0 NOT NULL, num_events INT UNSIGNED DEFAULT 0 NOT NULL, is_bounce TINYINT(1) DEFAULT 1 NOT NULL, is_new_visitor TINYINT(1) DEFAULT 1 NOT NULL, visit_number INT UNSIGNED DEFAULT 1 NOT NULL, days_since_last_visit INT UNSIGNED DEFAULT NULL, referrer_url LONGTEXT DEFAULT NULL, referrer_host VARCHAR(255) DEFAULT NULL, medium VARCHAR(20) NOT NULL, source VARCHAR(255) DEFAULT NULL, campaign VARCHAR(255) DEFAULT NULL, campaign_content VARCHAR(255) DEFAULT NULL, search_terms VARCHAR(255) DEFAULT NULL, browser VARCHAR(50) DEFAULT NULL, browser_version VARCHAR(20) DEFAULT NULL, os VARCHAR(50) DEFAULT NULL, os_version VARCHAR(20) DEFAULT NULL, device_type VARCHAR(20) DEFAULT NULL, screen_resolution VARCHAR(20) DEFAULT NULL, language VARCHAR(20) DEFAULT NULL, country CHAR(2) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, INDEX IDX_444839EAF6BD1646 (site_id), INDEX IDX_444839EA70BEE6D (visitor_id), INDEX IDX_444839EA20A0CC73 (entry_page_id), INDEX IDX_444839EA12548E52 (exit_page_id), INDEX idx_visits_site_date (site_id, visit_date), INDEX idx_visits_visitor_activity (visitor_id, last_activity_at), INDEX idx_visits_site_started (site_id, started_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AF6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A75FA0FF2 FOREIGN KEY (visit_id) REFERENCES visits (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A70BEE6D FOREIGN KEY (visitor_id) REFERENCES visitors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AC4663E4 FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pages ADD CONSTRAINT FK_2074E575F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pageviews ADD CONSTRAINT FK_CB11F046F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pageviews ADD CONSTRAINT FK_CB11F04675FA0FF2 FOREIGN KEY (visit_id) REFERENCES visits (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pageviews ADD CONSTRAINT FK_CB11F04670BEE6D FOREIGN KEY (visitor_id) REFERENCES visitors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pageviews ADD CONSTRAINT FK_CB11F046C4663E4 FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_sites ADD CONSTRAINT FK_5EC2513BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_sites ADD CONSTRAINT FK_5EC2513BF6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visitors ADD CONSTRAINT FK_7B74A43FF6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visits ADD CONSTRAINT FK_444839EAF6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visits ADD CONSTRAINT FK_444839EA70BEE6D FOREIGN KEY (visitor_id) REFERENCES visitors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visits ADD CONSTRAINT FK_444839EA20A0CC73 FOREIGN KEY (entry_page_id) REFERENCES pages (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visits ADD CONSTRAINT FK_444839EA12548E52 FOREIGN KEY (exit_page_id) REFERENCES pages (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574AF6BD1646');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574A75FA0FF2');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574A70BEE6D');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574AC4663E4');
        $this->addSql('ALTER TABLE pages DROP FOREIGN KEY FK_2074E575F6BD1646');
        $this->addSql('ALTER TABLE pageviews DROP FOREIGN KEY FK_CB11F046F6BD1646');
        $this->addSql('ALTER TABLE pageviews DROP FOREIGN KEY FK_CB11F04675FA0FF2');
        $this->addSql('ALTER TABLE pageviews DROP FOREIGN KEY FK_CB11F04670BEE6D');
        $this->addSql('ALTER TABLE pageviews DROP FOREIGN KEY FK_CB11F046C4663E4');
        $this->addSql('ALTER TABLE user_sites DROP FOREIGN KEY FK_5EC2513BA76ED395');
        $this->addSql('ALTER TABLE user_sites DROP FOREIGN KEY FK_5EC2513BF6BD1646');
        $this->addSql('ALTER TABLE visitors DROP FOREIGN KEY FK_7B74A43FF6BD1646');
        $this->addSql('ALTER TABLE visits DROP FOREIGN KEY FK_444839EAF6BD1646');
        $this->addSql('ALTER TABLE visits DROP FOREIGN KEY FK_444839EA70BEE6D');
        $this->addSql('ALTER TABLE visits DROP FOREIGN KEY FK_444839EA20A0CC73');
        $this->addSql('ALTER TABLE visits DROP FOREIGN KEY FK_444839EA12548E52');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE failed_login_attempts_log');
        $this->addSql('DROP TABLE ip_blocked');
        $this->addSql('DROP TABLE login_log');
        $this->addSql('DROP TABLE pages');
        $this->addSql('DROP TABLE pageviews');
        $this->addSql('DROP TABLE password_reminder');
        $this->addSql('DROP TABLE sites');
        $this->addSql('DROP TABLE two_factor_app');
        $this->addSql('DROP TABLE two_factor_recovery_code');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE user_sites');
        $this->addSql('DROP TABLE visitors');
        $this->addSql('DROP TABLE visits');
    }
}
