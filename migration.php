<?php

namespace migration;

use ValidationQuery\ValidationQuery;

class migration
{
    /**
     * @var \PDO
     */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
        $this->checkIfMigrationTableExists();
    }

    public static function run(\PDO $connection){
        return (new self($connection))->migrate();
    }

    public function migrate(\PDO $connection){

        return true;
    }

    private function checkIfMigrationTableExists()
    {
        $result = $this->connection->query(ValidationQuery::MigrationExistsSQL());
        if($result){
            var_dump($result->fetch());
        }
    }
}
