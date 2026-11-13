<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113114828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blocks (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, blocker_id INT NOT NULL, blocked_id INT NOT NULL, UNIQUE INDEX uniq_block (blocker_id, blocked_id), INDEX IDX_CEED9578548D5975 (blocker_id), INDEX IDX_CEED957821FF5136 (blocked_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversations (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL, UNIQUE INDEX uniq_conversation (item_id, buyer_id), INDEX IDX_C2521BF1126F525E (item_id), INDEX IDX_C2521BF16C755722 (buyer_id), INDEX IDX_C2521BF18DE820D9 (seller_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, claim_id VARCHAR(40) DEFAULT NULL, name VARCHAR(160) NOT NULL, category VARCHAR(60) NOT NULL, serial_number VARCHAR(120) DEFAULT NULL, item_condition VARCHAR(30) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, is_featured TINYINT DEFAULT 0 NOT NULL, featured_until DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_E11EE94D7096A49F (claim_id), INDEX IDX_E11EE94D7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_DB021E969AC0396 (conversation_id), INDEX IDX_DB021E96F624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offers (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(12) DEFAULT \'cash\' NOT NULL, amount NUMERIC(10, 2) NOT NULL, message LONGTEXT DEFAULT NULL, meetup_note VARCHAR(300) DEFAULT NULL, status VARCHAR(20) NOT NULL, seller_confirmed TINYINT NOT NULL, buyer_confirmed TINYINT NOT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL, trade_item_id INT DEFAULT NULL, INDEX IDX_DA460427126F525E (item_id), INDEX IDX_DA4604276C755722 (buyer_id), INDEX IDX_DA4604278DE820D9 (seller_id), INDEX IDX_DA460427E1EF0AA8 (trade_item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ownership_history (id INT AUTO_INCREMENT NOT NULL, transferred_at DATETIME NOT NULL, item_id INT NOT NULL, previous_owner_id INT NOT NULL, new_owner_id INT NOT NULL, offer_id INT DEFAULT NULL, INDEX IDX_71CE2D5E126F525E (item_id), INDEX IDX_71CE2D5E61D779FD (previous_owner_id), INDEX IDX_71CE2D5E3955EDD5 (new_owner_id), INDEX IDX_71CE2D5E53C674EE (offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE password_resets (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_9EDAFEA15F37A13B (token), INDEX IDX_9EDAFEA1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE platform_revenue (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(30) NOT NULL, amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, item_id INT DEFAULT NULL, offer_id INT DEFAULT NULL, INDEX IDX_FC21A6D7A76ED395 (user_id), INDEX IDX_FC21A6D7126F525E (item_id), INDEX IDX_FC21A6D753C674EE (offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, item_label VARCHAR(160) DEFAULT NULL, reason VARCHAR(500) NOT NULL, status VARCHAR(12) NOT NULL, created_at DATETIME NOT NULL, reporter_id INT NOT NULL, reported_user_id INT DEFAULT NULL, reported_item_id INT DEFAULT NULL, INDEX IDX_F11FA745E1CFE6F5 (reporter_id), INDEX IDX_F11FA745E7566E (reported_user_id), INDEX IDX_F11FA745B5E6D7A5 (reported_item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reviews (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, comment VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, offer_id INT NOT NULL, reviewer_id INT NOT NULL, reviewee_id INT NOT NULL, UNIQUE INDEX uniq_review (offer_id, reviewer_id), INDEX IDX_6970EB0F53C674EE (offer_id), INDEX IDX_6970EB0F70574616 (reviewer_id), INDEX IDX_6970EB0FBD992930 (reviewee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, business VARCHAR(160) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, is_suspended TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blocks ADD CONSTRAINT FK_CEED9578548D5975 FOREIGN KEY (blocker_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blocks ADD CONSTRAINT FK_CEED957821FF5136 FOREIGN KEY (blocked_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF16C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF18DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA460427126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA4604276C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA4604278DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offers ADD CONSTRAINT FK_DA460427E1EF0AA8 FOREIGN KEY (trade_item_id) REFERENCES items (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ownership_history ADD CONSTRAINT FK_71CE2D5E126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ownership_history ADD CONSTRAINT FK_71CE2D5E61D779FD FOREIGN KEY (previous_owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ownership_history ADD CONSTRAINT FK_71CE2D5E3955EDD5 FOREIGN KEY (new_owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ownership_history ADD CONSTRAINT FK_71CE2D5E53C674EE FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE password_resets ADD CONSTRAINT FK_9EDAFEA1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_revenue ADD CONSTRAINT FK_FC21A6D7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE platform_revenue ADD CONSTRAINT FK_FC21A6D7126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE platform_revenue ADD CONSTRAINT FK_FC21A6D753C674EE FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745E7566E FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745B5E6D7A5 FOREIGN KEY (reported_item_id) REFERENCES items (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F53C674EE FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F70574616 FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FBD992930 FOREIGN KEY (reviewee_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blocks DROP FOREIGN KEY FK_CEED9578548D5975');
        $this->addSql('ALTER TABLE blocks DROP FOREIGN KEY FK_CEED957821FF5136');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF1126F525E');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF16C755722');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF18DE820D9');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D7E3C61F9');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E969AC0396');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA460427126F525E');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA4604276C755722');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA4604278DE820D9');
        $this->addSql('ALTER TABLE offers DROP FOREIGN KEY FK_DA460427E1EF0AA8');
        $this->addSql('ALTER TABLE ownership_history DROP FOREIGN KEY FK_71CE2D5E126F525E');
        $this->addSql('ALTER TABLE ownership_history DROP FOREIGN KEY FK_71CE2D5E61D779FD');
        $this->addSql('ALTER TABLE ownership_history DROP FOREIGN KEY FK_71CE2D5E3955EDD5');
        $this->addSql('ALTER TABLE ownership_history DROP FOREIGN KEY FK_71CE2D5E53C674EE');
        $this->addSql('ALTER TABLE password_resets DROP FOREIGN KEY FK_9EDAFEA1A76ED395');
        $this->addSql('ALTER TABLE platform_revenue DROP FOREIGN KEY FK_FC21A6D7A76ED395');
        $this->addSql('ALTER TABLE platform_revenue DROP FOREIGN KEY FK_FC21A6D7126F525E');
        $this->addSql('ALTER TABLE platform_revenue DROP FOREIGN KEY FK_FC21A6D753C674EE');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745E1CFE6F5');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745E7566E');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745B5E6D7A5');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F53C674EE');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F70574616');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0FBD992930');
        $this->addSql('DROP TABLE blocks');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE offers');
        $this->addSql('DROP TABLE ownership_history');
        $this->addSql('DROP TABLE password_resets');
        $this->addSql('DROP TABLE platform_revenue');
        $this->addSql('DROP TABLE reports');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('DROP TABLE users');
    }
}
