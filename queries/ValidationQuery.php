<?php

namespace ValidationQuery;

class ValidationQuery
{
    private static $migrationTable = 'migration';

    public static function MigrationExistsSQL()
    {
        return "SELECT count(1) 
                    FROM information_schema.tables 
                WHERE table_name = '" . self::$migrationTable . "' LIMIT 1;";
    }

}
