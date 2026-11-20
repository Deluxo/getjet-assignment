<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the shifts table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shifts (id VARCHAR(36) NOT NULL, employee_id VARCHAR(255) NOT NULL, date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, country_code VARCHAR(2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_SHIFTS_EMPLOYEE_DATE ON shifts (employee_id, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shifts');
    }
}
