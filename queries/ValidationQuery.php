<?php

namespace ValidationQuery;

class ValidationQuery
{
    private static $migrationTable = 'migration';

    public static function MigrationExistsSQL($migrationTable = null)
    {
        $migrationTable = $migrationTable ?: self::$migrationTable;
        return "SELECT count(1)  as qtd
                    FROM information_schema.tables 
                WHERE table_name = '" . $migrationTable . "' LIMIT 1;";
    }

    public static function createMigrationTableSQL($migrationTable = null)
    {
        $migrationTable = $migrationTable ?: self::$migrationTable;
        return "CREATE TABLE ".$migrationTable." (
            id_migration INT UNSIGNED auto_increment NOT NULL PRIMARY KEY,
            `sql` TEXT NOT NULL,
            batch varchar(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NULL
        );";
    }

}
