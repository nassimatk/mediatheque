<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113140921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan (id INT AUTO_INCREMENT NOT NULL, borrowed_at DATETIME NOT NULL, due_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, renewal_count SMALLINT NOT NULL, copy_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_C5D30D03A8752772 (copy_id), INDEX IDX_C5D30D037597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE member (id INT AUTO_INCREMENT NOT NULL, card_number VARCHAR(30) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, plan_id INT NOT NULL, UNIQUE INDEX UNIQ_70E4FA78E4AF4C20 (card_number), INDEX IDX_70E4FA78E899029B (plan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE penalty (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, amount_cents INT NOT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, loan_id INT NOT NULL, INDEX IDX_AFE28FD8CE73868F (loan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, hold_until DATETIME DEFAULT NULL, member_id INT NOT NULL, edition_id INT NOT NULL, pickup_site_id INT NOT NULL, copy_id INT DEFAULT NULL, INDEX IDX_42C849557597D3FE (member_id), INDEX IDX_42C8495574281A5E (edition_id), INDEX IDX_42C84955999F10EB (pickup_site_id), INDEX IDX_42C84955A8752772 (copy_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscription_plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, max_loans SMALLINT NOT NULL, loan_days SMALLINT NOT NULL, max_renewals SMALLINT NOT NULL, penalty_cents_per_day INT NOT NULL, UNIQUE INDEX UNIQ_EA664B635E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D03A8752772 FOREIGN KEY (copy_id) REFERENCES copy (id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plan (id)');
        $this->addSql('ALTER TABLE penalty ADD CONSTRAINT FK_AFE28FD8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849557597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495574281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955999F10EB FOREIGN KEY (pickup_site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A8752772 FOREIGN KEY (copy_id) REFERENCES copy (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D03A8752772');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA78E899029B');
        $this->addSql('ALTER TABLE penalty DROP FOREIGN KEY FK_AFE28FD8CE73868F');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849557597D3FE');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495574281A5E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955999F10EB');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A8752772');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE penalty');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE subscription_plan');
    }
}
