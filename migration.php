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
        /**
     * @var array|null
     */
    private $config = [
        'table' => null
    ];

    public function __construct(PDO $connection, array $config = null)
    {
        $this->config = array_merge($this->config, $config);
        $this->connection = $connection;
        $this->checkIfMigrationTableExists();
    }

    public static function run(PDO $connection, array $config = null){
        return (new self($connection, $config))->migrate();
    }

    public function migrate(){

        return true;
    }

    private function checkIfMigrationTableExists()
    {
        $query = $this->connection->prepare(ValidationQuery::MigrationExistsSQL($this->config['table']));
        $result = $query->execute();
        if(!$result || $result['qtd'] < 1){
            $this->createMigrationTable();
        }
    }

    private function createMigrationTable()
    {
        $this->connection->exec(ValidationQuery::createMigrationTableSQL($this->config['table']));
    }
}
