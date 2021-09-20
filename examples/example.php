<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'migrationRequire.php';
use migration\migration;

$myConnection = new PDO("mysql:host=localhost:3307;dbname=docker", 'docker', 'docker', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$migration = migration::run($myConnection, [
    'table' => 'migracoes',
    'migrations_dir' => 'migrations',
    'onlyJSON' => true,
    'continueWithErrors' => true
]);
$migration->getResponse();
