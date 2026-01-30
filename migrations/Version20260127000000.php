<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `game` (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            `character` VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            solution VARCHAR(255) DEFAULT NULL,
            history LONGTEXT DEFAULT NULL,
            INDEX IDX_232B318CA76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE `game` ADD CONSTRAINT FK_232B318CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `game` DROP FOREIGN KEY FK_232B318CA76ED395');
        $this->addSql('DROP TABLE `game`');
    }
}
