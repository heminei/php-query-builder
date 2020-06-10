<?php

require_once __DIR__ . "/../vendor/autoload.php";

// $pdo = new \PDO("mysql:dbname=testdb;host=127.0.0.1");
// \HemiFrame\Lib\SQLBuilder\Query::$global['pdo'] = $pdo;

echo PHP_EOL . "Select query: " . PHP_EOL . PHP_EOL;
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.email",
    "u.name",
])->from("users", "u");
$query->leftJoin("details", "d", "d.userId=u.id");
$query->andWhere("u.status", 0);
$query->andWhere("u.id", [1, 2, 3]);
$query->andWhere("u.age", null);
$query->andWhere("u.gender", null, '!=');
$query->orderBy("u.id DESC");
$query->groupBy("u.id");
$query->paginationLimit(1, 10);

var_dump($query->getQueryString(true));

echo PHP_EOL . "Insert query: " . PHP_EOL . PHP_EOL;
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->insertInto("users")->set([
    "name" => 'Test',
    "email" => 'test@test.com',
]);
$query->onDuplicateKeyUpdate("email=:testVar")->setVar('testVar', 'testemail@test.com');

var_dump($query->getQueryString(true));

echo PHP_EOL . "Insert values query: " . PHP_EOL . PHP_EOL;
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->insertInto("users")->values([
    'name',
    'email',
    'age',
], [
    [
        'name 1',
        'email 1',
        '15',
    ],
    [
        'name 2',
        'email 2',
        '20',
    ],
]);
$query->onDuplicateKeyUpdate("email=:testVar")->setVar('testVar', 'testemail@test.com');

var_dump($query->getQueryString(true));

echo PHP_EOL . "Update query: " . PHP_EOL . PHP_EOL;
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->update("users")->set([
    "name" => 'Test',
    "email" => 'test@test.com',
]);
$query->set('totalViews = totalViews + 1');
$query->andWhere("status", 2, '!=');
$query->andWhere("id", [1, 2, 3]);
$query->andWhere("id", [10, 20, 30], '!=');

// $query->execute();
var_dump($query->getQueryString(true));


echo PHP_EOL . "Delete query: " . PHP_EOL . PHP_EOL;
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->delete()->from("users");
$query->andWhere("status", 2, '!=');
$query->andWhere("id", [1, 2, 3]);
$query->andWhere("id", [10, 20, 30], '!=');
$query->limit("1000");

// $query->execute();
var_dump($query->getQueryString(true));

echo PHP_EOL . "Sub query: " . PHP_EOL . PHP_EOL;
$queryInner = new \HemiFrame\Lib\SQLBuilder\Query();
$queryInner->select()->from("user");
$queryInner->andWhere("isActive", 1);

$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.name",
])->from($queryInner, "u");
$query->andWhere("status", 2, '!=');
$query->limit("1000");

// $query->execute();
var_dump($query->getQueryString(true));
