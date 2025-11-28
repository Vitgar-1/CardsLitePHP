<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$config = require __DIR__ . '/../config/database.php';
$connection = $config['connections'][$config['default']];

$capsule->addConnection($connection);

// Make this Capsule instance available globally via static methods
$capsule->setAsGlobal();

// Setup the Eloquent ORM
$capsule->bootEloquent();

return $capsule;