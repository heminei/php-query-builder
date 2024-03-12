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
    "MATCH (u.name) AGAINST (:name IN NATURAL LANGUAGE MODE) AS score",
])->from("users", "u");
$query->leftJoin("details", "d", "d.userId=u.id");
$query->andWhere("u.status", 0);
$query->andWhere("u.name", "testname", "fulltext");
$query->andWhere("u.name", "'testname' IN BOOLEAN MODE", "fulltext");
$query->andWhere("u.name,u.email", ":name IN BOOLEAN MODE", "fulltext");
$query->andWhere("u.email", ["asd", "zxc"], "fulltext");

$query->andWhere("MATCH(copy) AGAINST(:name IN BOOLEAN MODE)");

$query->setVar("name", "testname");

var_dump($query->getQueryString(true));
