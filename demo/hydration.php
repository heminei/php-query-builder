<?php

require_once __DIR__.'/../vendor/autoload.php';

class User
{
    /**
     * @var int Description
     */
    private $id;
    /**
     * @var string
     */
    private $email;
    /**
     * @var string|null
     */
    private $name;
    /**
     * @var float
     */
    private $randomProperty;
    /**
     * @var DateTime|null
     */
    private $createDate;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return self
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return self
     */
    public function setEmail(string $email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }
}

$pdo = new PDO('sqlite:'.__DIR__.'/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

HemiFrame\Lib\SQLBuilder\Query::$global['pdo'] = $pdo;

$st = $pdo->prepare('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, name TEXT, email TEXT NOT NULL, createDate DATETIME NOT NULL);');
$st->execute();

$queryInsert = new HemiFrame\Lib\SQLBuilder\Query();
$queryInsert->insertInto('users')->values(['id', 'email', 'name', 'createDate'], [
    [
        rand(1, 100000),
        'test1@example.com',
        'Test',
        '2022-06-28 18:00:00',
    ],
    [
        rand(1, 100000),
        'test2@example.com',
        null,
        '2022-06-28 19:00:00',
    ],
    [
        rand(1, 100000),
        'test3@example.com',
        'Test3',
        '2022-06-28 20:00:00',
    ],
]);
$queryInsert->execute();

echo PHP_EOL.'Select query: '.PHP_EOL.PHP_EOL;
$query = new HemiFrame\Lib\SQLBuilder\Query();
$query->select([
    'u.id',
    'u.email',
    'u.name',
    'u.createDate',
])->from('users', 'u');
$query->limit(10);

$rows = $query->fetchObjects(User::class);
var_dump($rows);

$row = $query->fetchFirstObject(User::class);
var_dump($row);
