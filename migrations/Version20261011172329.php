<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011172329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, claim_id VARCHAR(40) DEFAULT NULL, name VARCHAR(160) NOT NULL, category VARCHAR(60) NOT NULL, serial_number VARCHAR(120) DEFAULT NULL, item_condition VARCHAR(30) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_E11EE94D7096A49F (claim_id), INDEX IDX_E11EE94D7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offers (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, seller_confirmed TINYINT NOT NULL, buyer_confirmed TINYINT NOT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL, INDEX IDX_DA460427126F525E (item_id), INDEX IDX_DA4604276C755722 (buyer_id), INDEX IDX_DA4604278DE820D9 (seller_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA460427126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA4604276C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA4604278DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD business VARCHAR(160) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL, ADD avatar VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D7E3C61F9');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA460427126F525E');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA4604276C755722');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA4604278DE820D9');
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE offers');
        $this->addSql('ALTER TABLE users DROP business, DROP bio, DROP avatar');
    }
}
