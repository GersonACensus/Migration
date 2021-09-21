<?php

namespace migration;

use dictionaryClass;
use MigrationException;
use PDO;
use PDOException;
use ValidationQuery\MigrationQueries;

class migration
{
    private $response;
    /**
     * @var PDO
     */
    private $connection;
    /**
     * @var array|null
     */
    private $config = [
        'table' => null,
        'migrations_dir' => null,
        'continueWithErrors' => false,
        'onlyJSON' => false
    ];
    /**
     * @var array
     */
    private $sqlBag = [];

    private $failedBag = [];
    /**
     * @var bool|void
     */
    private $sendMail = false;

    /**
     * @param PDO $connection
     * @param array|null $config
     * @throws MigrationException
     */
    public function __construct(PDO $connection, array $config = null)
    {
        if (!isset($config['migrations_dir']) || !$config['migrations_dir'])
            throw new MigrationException(dictionaryClass::dictionary('error.directory', ['migration_dir']));
        $this->config = array_merge($this->config, $config);
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        $this->checkIfMigrationTableExists();
    }

    /**
     * @param PDO $connection
     * @param array|null $config
     * @return migration
     * @throws MigrationException
     */
    public static function run(PDO $connection, array $config = null)
    {
        return (new self($connection, $config))->migrate();
    }

    /**
     * @return migration
     * @throws MigrationException
     */
    private function migrate()
    {
        try {
            $migrated = $this->getMigrated();
            $migrations = $this->getFilesMigration();
            $files = $this->compareMigrationWithMigratedAndClear($migrated, $migrations);
            $this->response = $this->runMigration($files);
        } catch (MigrationException $e) {
            $this->sendMail = $this->checkAndSendNotify($e->getMessage());
            if ($this->isJson()) {
                $this->response = ['status' => 'error', 'message' => $e->getMessage(), 'runned' => $this->sqlBag];

            } else {
                throw new MigrationException($e->getMessage());
            }
        }
        return $this;
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
     * @return array|false
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
        throw new MigrationException(dictionaryClass::dictionary('error.directory', ['migration_dir']));
    }

    /**
     * @param array $files
     * @return array
     * @throws MigrationException
     */
    private function runMigration(array $files)
    {
        if (empty($files))
            return ['status' => 'success', 'message' => dictionaryClass::dictionary('msg.empty'), 'email_sent' => $this->sendMail ?: false];
        $batch = $this->getLastBathMoreOne();
        $this->sqlBag = [];
        $starttime = microtime(true);
        foreach ($files as $index => $file) {
            try {
                $this->connection->beginTransaction();
                ob_start();
                readfile($this->config['migrations_dir'] . "/" . $file);
                $sql = ob_get_clean();
                $this->executeMigrationFile($sql);
                $this->registerMigration($batch, $file);
                $this->connection->commit();
                $this->sqlBag[] = $sql;
            } catch (PDOException $e) {
                if ($this->config['continueWithErrors'] === false)
                    throw new MigrationException(dictionaryClass::dictionary("error.persistence", [$file, $sql]));

                $this->failedBag[] = $sql;
                continue;
            }
        }
        if (!count($this->sqlBag)) {
            $this->sendMail = $this->checkAndSendNotify(dictionaryClass::dictionary("msg.onlyFailed"));
            return [
                'status' => 'success',
                'message' => dictionaryClass::dictionary('msg.onlyFailed'),
                'failed' => array_unique($this->failedBag),
                'email_sent' => $this->sendMail ?: false
            ];
        }

        $endtime = microtime(true);
        return [
            'status' => 'success',
            'message' => dictionaryClass::dictionary('msg.success', ['migração']),
            'finish' => $this->sqlBag,
            'failed' => array_unique($this->failedBag),
            'email_sent' => $this->sendMail ?: false,
            'informations' => [
                'quantity' => count($this->sqlBag),
                'duration' => round($this->microtime_float($endtime - $starttime), 2) . ' seconds'
            ]
        ];
    }

    private function getLastBathMoreOne()
    {
        $query = $this->connection->prepare(MigrationQueries::LastBatchSQL($this->config['table']));
        $query->execute();
        return ($query->fetch()['batch'] ?: 0) + 1;
    }

    /**
     * @param $sql
     * @throws MigrationException
     */
    private function executeMigrationFile($sql)
    {
        $this->security($sql);
        $query = $this->connection->prepare($sql);
        $query->execute();
    }

    private function registerMigration($batch, $file)
    {
        $query = $this->connection->prepare(MigrationQueries::registerQuerySQL($this->config['table'], $file, $batch));
        $query->execute();
    }

    /**
     * @param $sql
     * @throws MigrationException
     */
    private function security($sql)
    {
        $badStrings = ['DROP', 'TRUNCATE', 'DELETE'];
        foreach ($badStrings as $index => $badString) {
            if (strpos(strtoupper($sql), strtoupper($badString)) !== false) {
                throw new MigrationException(dictionaryClass::dictionary('error.notAuthorized', [$sql]));
            }
        }

        if (strpos(strtoupper($sql), 'UPDATE') !== false && strpos(strtoupper($sql), 'WHERE') === false)
            throw new MigrationException(dictionaryClass::dictionary('error.notAuthorized', [$sql]));

    }

    public function getJson($isReturn = false)
    {
        if ($isReturn)
            return (object)$this->response;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
        return null;
    }

    public function getArray()
    {
        return $this->response;
    }

    /**
     * @throws MigrationException
     */
    public function __get($name)
    {
        return $this->response ?: ['status' => 'error', 'message' => dictionaryClass::dictionary('msg.unprocessed')];
    }

    function microtime_float($time)
    {
        list($sec) = explode(" ", $time);
        return (float)$sec;
    }

    public function getResponse()
    {
        return ($this->isJson() ? $this->getJson(true) : $this->getArray());
    }

    private function checkAndSendNotify($msg)
    {
        if (isset($this->config['mailTo']) && $this->config['mailTo'])
            return (notify::init($this->config['mailTo']))
                ->sendNotify($msg, ['errors' => array_unique($this->failedBag), 'success' => $this->sqlBag]);
    }

    private function isJson()
    {
        return isset($this->config['onlyJSON']) && $this->config['onlyJSON'];
    }


}
