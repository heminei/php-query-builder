# php-query-builder

Powerfull and lightweight PHP SQL Query Builder with fluid interface SQL syntax unsing bindings and complicated query generation

## Features

* multiple databases (multiple PDO instances)
* INSERT, INSERT IGNORE, INSERT DELAYED, UPDATE, SELECT, DELETE queries
* support LEFT JOIN, INNER JOIN, RIGHT JOIN, GROUP BY, LIMIT, HAVING and etc.
* =, !=, >, <, >=, <=, IN, NOT IN, IS NULL, NOT NULL operators
* support transactions and sub-queries
* support result caching (`\Psr\SimpleCache\CacheInterface`)
* multiple fetching data modes (fetch arrays, fetch objects, etc.)
* auto escape column names
* auto format queries
* auto bind variables

## Quick install

---

The recommended way to install the Query Builder is through Composer. Run the following command to install it:

``` cmd
composer require hemiframe/php-query-builder
```

Set default PDO instance:

``` php
<?php

//Create PDO instance
$pdo = new \PDO('mysql:host=localhoset;dbname=test', 'test', 'test', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("set names utf8");

// Set as default for all query instances
\HemiFrame\Lib\SQLBuilder\Query::$global['pdo'] = $pdo;

$query = new \HemiFrame\Lib\SQLBuilder\Query(); //Your query
$query->execute();
```

## Documentation

---

## Select query

```php
<?php

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
```

Output:

```sql
SELECT
  u.id
  ,u.email
  ,u.name
FROM
  users AS u
LEFT JOIN details AS d
  ON d.userId=u.id
WHERE
  u.status=0
  AND u.id IN (1,2,3)
  AND u.age IS NULL
  AND u.gender IS NOT NULL
GROUP BY u.id
ORDER BY u.id DESC
LIMIT 0, 10
```

## Select query from sub query

```php
<?php

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
```

Output:

```sql
SELECT
  u.id
  ,u.name
FROM
  (SELECT
  *
FROM
  user
WHERE
  isActive=1) AS u
WHERE
  `status`!=2
LIMIT 1000
```

## Insert query

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->insertInto("users")->set([
    "name" => 'Test',
    "email" => 'test@test.com',
]);
$query->onDuplicateKeyUpdate("`email`=:testVar")->setVar('testVar', 'testemail@test.com');
```

Output:

```sql
INSERT INTO
  users
SET
  `name`="Test"
  ,`email`="test@test.com"
ON DUPLICATE KEY UPDATE
  `email`="testemail@test.com"
```

## Insert query with VALUES

```php
<?php
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
```

Output:

```sql
INSERT INTO
  users
  (`name`,`email`,`age`)
VALUES
  ("name 1","email 1","15")
  ,("name 2","email 2","20")
ON DUPLICATE KEY UPDATE
  `email`="testemail@test.com"
```

## Update query

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->update("users")->set([
    "name" => 'Test',
    "email" => 'test@test.com',
]);
$query->set('totalViews = totalViews + 1');
$query->andWhere("status", 2, '!=');
$query->andWhere("id", [1, 2, 3]);
$query->andWhere("id", [10, 20, 30], '!=');
```

Output:

```sql
UPDATE
  users
SET
  `name`="Test"
  ,`email`="test@test.com"
  ,totalViews = totalViews + 1
WHERE
  `status`!=2
  AND `id` IN (1,2,3)
  AND `id` NOT IN (10,20,30)
```

## Delete query

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->delete()->from("users");
$query->andWhere("status", 2, '!=');
$query->andWhere("id", [1, 2, 3]);
$query->andWhere("id", [10, 20, 30], '!=');
$query->limit("1000");
```

Output:

```sql
DELETE FROM
  users
WHERE
  `status`!=2
  AND `id` IN (1,2,3)
  AND `id` NOT IN (10,20,30)
LIMIT 1000
```

## Fetching data

Fetch data as array of arrays

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.email",
    "u.name",
])->from("users", "u");
$query->orderBy("u.id DESC");
$rows = $query->fetchArrays();
```

Fetch data as array of objects

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.email",
    "u.name",
])->from("users", "u");
$query->orderBy("u.id DESC");
$rows = $query->fetchObjects();
```

Fetch first result as array

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.email",
    "u.name",
])->from("users", "u");
$query->orderBy("u.id DESC");
$row = $query->fetchFirstArray();
```

Fetch first result as object

```php
<?php
$query = new \HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    "u.id",
    "u.email",
    "u.name",
])->from("users", "u");
$query->orderBy("u.id DESC");
$row = $query->fetchFirstObject();
```

## Using multiple databases

```php
<?php
$pdo1 = new \PDO('mysql:host=localhoset;dbname=test', 'test', 'test');
$pdo2 = new \PDO('mysql:host=localhoset;dbname=test', 'test', 'test');

$query = new \HemiFrame\Lib\SQLBuilder\Query([
    'pdo' => $pdo1,
]);
$query->select([
    "u.id",
])->from("users", "u");

$queryArticles = new \HemiFrame\Lib\SQLBuilder\Query([
    'pdo' => $pdo2,
]);
$queryArticles->select([
    "a.id",
])->from("articles", "a");
```
