<?php

namespace ValidationQuery;

class ValidationQuery
{
    private static $migrationTable = 'migration';

    public static function MigrationExistsSQL()
    {
        return "SELECT count(1)  as qtd
                    FROM information_schema.tables 
                WHERE table_name = '" . self::$migrationTable . "' LIMIT 1;";
    }

    public static function createMigrationTableSQL()
    {
        return "CREATE TABLE docker.migration (
            id_migration INT UNSIGNED auto_increment NOT NULL,
            `sql` TEXT NOT NULL,
            batch varchar(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NULL
        );";
    }

}
