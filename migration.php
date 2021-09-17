<?php

namespace migration;

use PDO;
use ValidationQuery\ValidationQuery;

class migration
{
    /**
     * @var PDO
     */
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->checkIfMigrationTableExists();
    }

    public static function run(PDO $connection){
        return (new self($connection))->migrate();
    }

    public function migrate(){

        return true;
    }

    private function checkIfMigrationTableExists()
    {
        $query = $this->connection->prepare(ValidationQuery::MigrationExistsSQL());
        $result = $query->execute();
        if(!$result || $result['qtd'] < 1){
            $this->createMigrationTable();
        }
    }

    private function createMigrationTable()
    {
        $this->connection->exec(ValidationQuery::createMigrationTableSQL());
    }
}
