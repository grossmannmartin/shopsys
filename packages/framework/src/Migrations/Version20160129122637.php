<?php

namespace Shopsys\FrameworkBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Shopsys\MigrationBundle\Component\Doctrine\Migrations\AbstractMigration;

class Version20160129122637 extends AbstractMigration
{
    /**
     * @param \Doctrine\DBAL\Schema\Schema $schema
     */
    public function up(Schema $schema): void
    {
        $sql = 'INSERT INTO setting_values (name, domain_id, value, type) VALUES
            (\'baseUrl\', 1, \'http://localhost:8080\', \'string\')
        ';
        $this->sql($sql);

        $this->sql('CREATE OR REPLACE FUNCTION get_domain_ids_by_locale(locale text) RETURNS SETOF integer AS $$
            BEGIN
                CASE
                    WHEN locale = \'cs\' THEN RETURN NEXT 1;
                    ELSE RAISE EXCEPTION \'Locale % does not exists\', locale;
                END CASE;
            END
            $$ LANGUAGE plpgsql IMMUTABLE;');

        $this->sql('CREATE OR REPLACE FUNCTION get_domain_locale(domain_id integer) RETURNS text AS $$
            BEGIN
                CASE
                    WHEN domain_id = 1 THEN RETURN \'cs\';
                    ELSE RAISE EXCEPTION \'Domain with ID % does not exists\', domain_id;
                END CASE;
            END
            $$ LANGUAGE plpgsql IMMUTABLE;');
    }

    /**
     * @param \Doctrine\DBAL\Schema\Schema $schema
     */
    public function down(Schema $schema): void
    {
    }
}
