<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113013553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contributor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE copy (id INT AUTO_INCREMENT NOT NULL, barcode VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, edition_id INT NOT NULL, site_id INT NOT NULL, destination_site_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4DBABB8297AE0266 (barcode), INDEX IDX_4DBABB8274281A5E (edition_id), INDEX IDX_4DBABB82F6BD1646 (site_id), INDEX IDX_4DBABB829884293E (destination_site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, isbn VARCHAR(20) DEFAULT NULL, type VARCHAR(20) NOT NULL, publisher VARCHAR(150) DEFAULT NULL, publication_year SMALLINT DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, work_id INT NOT NULL, UNIQUE INDEX UNIQ_A891181FCC1CF4E6 (isbn), INDEX IDX_A891181FBB3453DB (work_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition_contributor (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, edition_id INT NOT NULL, contributor_id INT NOT NULL, UNIQUE INDEX uniq_edition_contributor_role (edition_id, contributor_id, role), INDEX IDX_9F7EB14D74281A5E (edition_id), INDEX IDX_9F7EB14D7A19A357 (contributor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_694309E45E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE work (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE copy ADD CONSTRAINT FK_4DBABB8274281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE copy ADD CONSTRAINT FK_4DBABB82F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE copy ADD CONSTRAINT FK_4DBABB829884293E FOREIGN KEY (destination_site_id) REFERENCES site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE edition ADD CONSTRAINT FK_A891181FBB3453DB FOREIGN KEY (work_id) REFERENCES work (id)');
        $this->addSql('ALTER TABLE edition_contributor ADD CONSTRAINT FK_9F7EB14D74281A5E FOREIGN KEY (edition_id) REFERENCES edition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE edition_contributor ADD CONSTRAINT FK_9F7EB14D7A19A357 FOREIGN KEY (contributor_id) REFERENCES contributor (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE copy DROP FOREIGN KEY FK_4DBABB8274281A5E');
        $this->addSql('ALTER TABLE copy DROP FOREIGN KEY FK_4DBABB82F6BD1646');
        $this->addSql('ALTER TABLE copy DROP FOREIGN KEY FK_4DBABB829884293E');
        $this->addSql('ALTER TABLE edition DROP FOREIGN KEY FK_A891181FBB3453DB');
        $this->addSql('ALTER TABLE edition_contributor DROP FOREIGN KEY FK_9F7EB14D74281A5E');
        $this->addSql('ALTER TABLE edition_contributor DROP FOREIGN KEY FK_9F7EB14D7A19A357');
        $this->addSql('DROP TABLE contributor');
        $this->addSql('DROP TABLE copy');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE edition_contributor');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE work');
    }
}
