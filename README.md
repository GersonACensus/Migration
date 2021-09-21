# Settings

To configure it you must add it to your composer. 

``composer require gersonalves/migration``

The project has the example files for easy understanding. 

#Implementation

```php
require 'migrationRequire.php';
use migration\migration;
$myConnection = new PDO("mysql:host=localhost:3307;dbname=docker", 'docker', 'docker', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
```
first, import the necessary files and start your PDO connection. 

then select the appropriate place in your code to call to run a migration. and Add:

```php
$migration = migration::run($myConnection, [
    'table' => 'migracoes',
    'migrations_dir' => 'migrations',
    'onlyJSON' => true,
    'continueWithErrors' => true
]);
$migration->getResponse();
```

The second parameter is the configuration, here are some important information. 

- table - This is the field to define the migration log table. 
- migrations_dir - This is the directory to save yours .sql files to migrate.
- onlyJSON - if is true, the response have a JSON header. If false
- continueWithErrors - If is true, when an error occurs, the other migrations will be persisted and it will return a list with successes and failures. if false, on error the interrupt the migration script and return a throw exception.

#Responses examples

On JSON Success
```JSON
  {
    "status": "success",
    "message": "Migração realizado com sucesso",
    "bag": [
      "YOUR SQL HERE"
    ]
  }
```

For more infomations, have images examples HERE:
