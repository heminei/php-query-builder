<?php

use Demo\ORM\Entities\User;

require_once __DIR__.'/../../vendor/autoload.php';

$files = scandir(__DIR__.'/Entities');
foreach ($files as $file) {
    if ('.' === $file || '..' === $file) {
        continue;
    }
    require_once __DIR__.'/Entities/'.$file;
}

$dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__.'/../../');
$dotenv->load();

$pdo = new PDO('mysql:dbname='.$_ENV['DB_NAME'].';host='.$_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
HemiFrame\Lib\SQLBuilder\Query::$global['pdo'] = $pdo;

echo PHP_EOL.'Select query: '.PHP_EOL.PHP_EOL;
$query = new HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    'u.id',
    'u.email',
    'u.name',
    'u.addressId',
])->from('users', 'u');
$query->leftJoin('addresses', 'a', 'a.id=u.addressId');
$query->paginationLimit(1, 10);

var_dump($query->fetchObjects(User::class));
