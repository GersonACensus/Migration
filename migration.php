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
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
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
     * @throws MigrationException
     */
    public function migrate()
    {
        $migrated = $this->getMigrated();
        $migrations = $this->getFilesMigration();
        $files = $this->compareMigrationWithMigratedAndClear($migrated, $migrations);
        return $this->runMigration($files);
    }

    private function checkIfMigrationTableExists()
    {
        $query = $this->connection->prepare(MigrationQueries::MigrationExistsSQL($this->config['table']));
        $query->execute();
        $result = $query->fetch();
        if (!$result || $result['qtd'] < 1) {
            $this->createMigrationTable();
        }
    }


    private function createMigrationTable()
    {
        $query = $this->connection->prepare(MigrationQueries::createMigrationTableSQL($this->config['table']));
        $query->execute();
    }

    /**
     * @param $migrated
     * @param $migrations
     * @return array
     */
    private function compareMigrationWithMigratedAndClear($migrated, $migrations)
    {
        $migrated = array_map(function ($item) {
            return $item['file'];
        }, $migrated);
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
        return $query->fetchAll();
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
        foreach ($files as $index => $file) {
            try {
                $this->connection->beginTransaction();
                ob_start();
                readfile($this->config['migrations_dir'] . "/" . $file);
                $sql = ob_get_clean();
                $this->executeMigrationFile($sql);
                $this->registerMigration($batch, $file);
                $this->connection->commit();
            } catch (\PDOException $e) {
                throw new MigrationException("Não foi possível persistir a migration {$file}, SQL: {$sql}");
            }
        }
    }

    private function getLastBathMoreOne()
    {
        $query = $this->connection->prepare(MigrationQueries::LastBatchSQL($this->config['table']));
        $query->execute();
        return ($query->fetch()['batch'] ?: 0) + 1;
    }

    private function executeMigrationFile($sql)
    {
        $query = $this->connection->prepare($sql);
        $query->execute();
    }

    private function registerMigration($batch, $file)
    {
        $query = $this->connection->prepare(MigrationQueries::registerQuerySQL($this->config['table'], $file, $batch));
        $query->execute();
    }
}
