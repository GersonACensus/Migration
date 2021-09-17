<?php

namespace migration;

use MigrationException;
use PDO;
use ValidationQuery\MigrationQueries;

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
        'table' => null,
        'migrations_dir' => null
    ];

    /**
     * @param PDO $connection
     * @param array|null $config
     * @throws MigrationException
     */
    public function __construct(PDO $connection, array $config = null)
    {
        if (!$config['migrations_dir'])
            throw new MigrationException("O diretório das migrations é obrigatório");
        $this->config = array_merge($this->config, $config);
        $this->connection = $connection;
        $this->checkIfMigrationTableExists();
    }

    /**
     * @param PDO $connection
     * @param array|null $config
     * @return bool
     * @throws MigrationException
     */
    public static function run(PDO $connection, array $config = null)
    {
        return (new self($connection, $config))->migrate();
    }

    /**
     * @return bool
     */
    public function migrate()
    {

        return true;
    }

    /**
     * @throws MigrationException
     */
    private function checkIfMigrationTableExists()
    {
        $query = $this->connection->prepare(MigrationQueries::MigrationExistsSQL($this->config['table']));
        $query->execute();
        $result = $query->fetch();
        if (!$result || $result['qtd'] < 1) {
            $this->createMigrationTable();
        }

        $migrated = $this->getMigrated();
        $migrations = $this->getFilesMigration();
        $files = $this->compareMigrationWithMigratedAndClear($migrated, $migrations);
        $this->runMigration($files);
    }


    private function createMigrationTable()
    {
        $this->connection->exec(MigrationQueries::createMigrationTableSQL($this->config['table']));
    }

    /**
     * @param $migrated
     * @param $migrations
     * @return array
     */
    private function compareMigrationWithMigratedAndClear($migrated, $migrations)
    {
        $toMigrate = array_diff($migrations, $migrated);
        $newArrayToMigrate = [];
        foreach ($toMigrate as $index => $item) {
            if (strpos($item, '.sql'))
                $newArrayToMigrate[] = $item;
        }

        return $newArrayToMigrate;
    }

    /**
     * @return mixed
     */
    private function getMigrated()
    {
        $query = $this->connection->prepare(MigrationQueries::getMigrated($this->config['table']));
        $query->execute();
        return $query->fetch();
    }

    /**
     * @throws MigrationException
     */
    private function getFilesMigration()
    {
        $this->checkIfDirExists();
        return scandir($this->config['migrations_dir']);
    }

    /**
     * @return void
     * @throws MigrationException
     */
    private function checkIfDirExists()
    {
        if (is_dir($this->config['migrations_dir'])) {
            return;
        }
        throw new MigrationException("O diretório informado em 'migration_dir' não é um diretório válido.");
    }

    private function runMigration(array $files)
    {
        $batch = $this->getLastBathMoreOne();
        $this->connection->beginTransaction();
        foreach ($files as $index => $file) {
            $sql = readfile($this->config['migrations_dir']."/".$file);
            $this->executeMigrationFile($sql);
            $this->registerMigration($batch, $file);
        }
        $this->connection->commit();
    }

    private function getLastBathMoreOne()
    {
        $query = $this->connection->prepare(MigrationQueries::LastBatchSQL($this->config['table']));
        $query->execute();
        return $query->fetch() + 1;
    }

    private function executeMigrationFile($sql)
    {
        $this->connection->exec($sql);
    }

    private function registerMigration($batch, $file)
    {
        $this->connection->exec(MigrationQueries::registerQuerySQL($this->config['table'], $file, $batch));
    }
}
