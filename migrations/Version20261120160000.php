<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the shift notifications table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shift_notifications (
                id VARCHAR(64) NOT NULL,
                shift_id VARCHAR(36) NOT NULL,
                employee_id VARCHAR(255) NOT NULL,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at DATETIME NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_attempt_at DATETIME NULL,
                last_error VARCHAR(500) NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_SHIFT_NOTIFICATIONS_PENDING ON shift_notifications (sent_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shift_notifications');
    }
}
