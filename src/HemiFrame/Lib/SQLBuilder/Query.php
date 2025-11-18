<?php

namespace HemiFrame\Lib\SQLBuilder;

/**
 * @author heminei <heminei@heminei.com>
 */
class Query
{
    public const DEFAULT_VALUE = 'f056c6dc7b3fc49ea4655853bea9b1d4e637a52d3d4a582985175f513fb9589241e21657f686e633c36760416031546cfe283d9961b9ac19aa2c45175dbbc9dd';

    /**
     * @var array<mixed>
     */
    public static array $global = [
        'pdo' => null,
        'logs' => [
            'errors' => null,
        ],
        'executedQueries' => [],
        'resultCache' => [
            'implementation' => null,
            'prefix' => 'query-result-cache',
        ],
        'skipDuplicateJoins' => true,
    ];
    /**
     * @var array<mixed>
     */
    private array $config = [];
    private ?\PDO $pdo;
    /**
     * @var array<mixed>
     */
    private array $query = [
        'type' => null,
    ];
    /**
     * @var array<int, self>
     */
    private array $subQueries = [];
    private float $executionTime = 0;
    /**
     * @var array<mixed>
     */
    private array $vars = [];
    private mixed $tables = null;
    /**
     * @var array<mixed>
     */
    private array $columns = [];
    /**
     * @var array<mixed>
     */
    private array $joinTables = [];
    /**
     * @var array<mixed>
     */
    private array $whereConditions = [];
    /**
     * @var array<mixed>
     */
    private array $havingConditions = [];
    /**
     * @var array<mixed>
     */
    private array $groupByColumns = [];
    /**
     * @var array<mixed>
     */
    private array $orderByColumns = [];
    /**
     * @var array<mixed>
     */
    private array $setColumns = [];
    /**
     * @var array<mixed>
     */
    private array $values = [];

    /**
     * @var string|int
     */
    private $limit;

    private ?string $onDuplicateKeyUpdate = null;
    /**
     * @var array<mixed>
     */
    private array $unions = [];

    private ?string $plainQueryString = null;

    private bool $useResultCache = false;

    private ?\Psr\SimpleCache\CacheInterface $resultCacheImplementation = null;

    private int $resultCacheLifeTime = 0;

    private ?string $resultCacheKey = null;

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::$global);
        $this->config = array_merge($this->config, $config);

        if (!empty($this->config['pdo'])) {
            $this->pdo = $this->config['pdo'];
        }
        if (!empty($this->config['resultCache']['implementation'])) {
            if (!$this->config['resultCache']['implementation'] instanceof \Psr\SimpleCache\CacheInterface) {
                throw new QueryException("Result cache implementation must be implement \Psr\SimpleCache\CacheInterface");
            }
            $this->resultCacheImplementation = $this->config['resultCache']['implementation'];
        }
    }

    /**
     * Set PDO object.
     */
    public function setPdo(\PDO $pdo): self
    {
        $this->pdo = $pdo;

        return $this;
    }

    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    public function setVar(string $name, mixed $value): self
    {
        if (empty($name)) {
            throw new QueryException('Enter var name');
        }
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $this->vars[$name] = $value;

        return $this;
    }

    /**
     * @param array<mixed> $array
     */
    public function setVars(array $array): self
    {
        foreach ($array as $key => $value) {
            $this->setVar($key, $value);
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return array<mixed>
     */
    public function getVars(): array
    {
        $vars = [];
        foreach ($this->subQueries as $query) {
            /* @var $query self */
            foreach ($query->getVars() as $name => $var) {
                $vars[$name] = $var;
            }
        }
        foreach ($this->vars as $name => $var) {
            $vars[$name] = $var;
        }

        return $vars;
    }

    /**
     * @return self[]
     */
    public function getSubQueries(): array
    {
        return $this->subQueries;
    }

    public function getVar(string $name): mixed
    {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }

        return null;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getLastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Initiates a transaction
     * Returns TRUE on success or FALSE on failure.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     * Returns TRUE on success or FALSE on failure.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rolls back a transaction
     * Returns TRUE on success or FALSE on failure.
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Checks if inside a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     */
    public function prepare(string $query): \PDOStatement
    {
        return $this->pdo->prepare($query);
    }

    /**
     * @throws QueryException
     */
    public function execute(): \PDOStatement
    {
        $timeStart = microtime(true);
        $queryString = $this->getQueryString();
        $PDOStatement = $this->prepare($queryString);

        /*
         * bind vars
         */
        foreach ($this->getVars() as $key => $value) {
            if (is_int($value)) {
                $PDOStatement->bindValue(':'.$key, $value, \PDO::PARAM_INT);
            } elseif (is_null($value)) {
                $PDOStatement->bindValue(':'.$key, $value, \PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $PDOStatement->bindValue(':'.$key, $value, \PDO::PARAM_BOOL);
            } else {
                $PDOStatement->bindValue(':'.$key, $value, \PDO::PARAM_STR);
            }
        }

        $PDOStatement->execute();
        $this->executionTime = microtime(true) - $timeStart;

        $error = $PDOStatement->errorInfo();
        if (!empty($error[2])) {
            if (!empty($this->config['logs']['errors'])) {
                $fileErrors = fopen($this->config['logs']['errors'], 'a+');
                $text = $error[2].' - '.$queryString."\n\n";
                fwrite($fileErrors, $text);
                fclose($fileErrors);
            }
            throw new QueryException($error[2].' ==> '.$queryString);
        }

        self::$global['executedQueries'][] = [
            'query' => $queryString,
            'queryWithParams' => $this->getQueryString(true),
            'time' => $this->getExecutionTime(),
        ];

        return $PDOStatement;
    }

    /**
     * @template T
     *
     * @param class-string<T>|null $hydrationClass
     *
     * @return T[]|\stdClass[]
     */
    public function fetchObjects(?string $hydrationClass = null): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = $this->config['resultCache']['prefix'].'-'.__METHOD__.'-'.md5($this->getQueryString(true));
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                /** @var Cache\ResultData|null $resultData */
                $resultData = $this->resultCacheImplementation->get($cacheKey);
                if (!empty($resultData) && is_a($resultData, Cache\ResultData::class)) {
                    return $resultData->getData();
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_OBJ);
        if (!is_array($data)) {
            $data = [];
        }

        if (!empty($hydrationClass) && !empty($data)) {
            if (!class_exists($hydrationClass)) {
                throw new \InvalidArgumentException("Hydration class ($hydrationClass) not found");
            }
            $reflectionClass = new \ReflectionClass($hydrationClass);

            foreach ($data as $key => $value) {
                $data[$key] = $this->hydrateClass($reflectionClass, $value);
            }
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            $resultData = new Cache\ResultData();
            $resultData->setData($data);
            $this->resultCacheImplementation->set($cacheKey, $resultData, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     * @template T
     *
     * @param class-string<T>|null $hydrationClass
     *
     * @return T|\stdClass|null
     */
    public function fetchFirstObject(?string $hydrationClass = null)
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = $this->config['resultCache']['prefix'].'-'.__METHOD__.'-'.md5($this->getQueryString(true));
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                /** @var Cache\ResultData|null $resultData */
                $resultData = $this->resultCacheImplementation->get($cacheKey);
                if (!empty($resultData) && is_a($resultData, Cache\ResultData::class)) {
                    return $resultData->getData();
                }
            }
        }

        $data = $this->execute()->fetchObject();
        if (!is_object($data) && null !== $data) {
            $data = null;
        }

        if (!empty($hydrationClass) && !empty($data)) {
            if (!class_exists($hydrationClass)) {
                throw new \InvalidArgumentException("Hydration class ($hydrationClass) not found");
            }
            $reflectionClass = new \ReflectionClass($hydrationClass);
            $data = $this->hydrateClass($reflectionClass, $data);
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            $resultData = new Cache\ResultData();
            $resultData->setData($data);
            $this->resultCacheImplementation->set($cacheKey, $resultData, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    public function fetchArrays(): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = $this->config['resultCache']['prefix'].'-'.__METHOD__.'-'.md5($this->getQueryString(true));
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                /** @var Cache\ResultData|null $resultData */
                $resultData = $this->resultCacheImplementation->get($cacheKey);
                if (!empty($resultData) && is_a($resultData, 'HemiFrame\Lib\SQLBuilder\Cache\ResultData')) {
                    return $resultData->getData();
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($data)) {
            $data = [];
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            $resultData = new Cache\ResultData();
            $resultData->setData($data);
            $this->resultCacheImplementation->set($cacheKey, $resultData, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     * @throws QueryException
     */
    public function fetchColumn(): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = $this->config['resultCache']['prefix'].'-'.__METHOD__.'-'.md5($this->getQueryString(true));
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                /** @var Cache\ResultData|null $resultData */
                $resultData = $this->resultCacheImplementation->get($cacheKey);
                if (!empty($resultData) && is_a($resultData, 'HemiFrame\Lib\SQLBuilder\Cache\ResultData')) {
                    return $resultData->getData();
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_COLUMN);
        if (!is_array($data)) {
            $data = [];
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            $resultData = new Cache\ResultData();
            $resultData->setData($data);
            $this->resultCacheImplementation->set($cacheKey, $resultData, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     * @return array<mixed>|null
     */
    public function fetchFirstArray(): ?array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = $this->config['resultCache']['prefix'].'-'.__METHOD__.'-'.md5($this->getQueryString(true));
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                /** @var Cache\ResultData|null $resultData */
                $resultData = $this->resultCacheImplementation->get($cacheKey);
                if (!empty($resultData) && is_a($resultData, Cache\ResultData::class)) {
                    return $resultData->getData();
                }
            }
        }

        $data = $this->execute()->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($data) && null !== $data) {
            $data = null;
        }

        if (true == $this->useResultCache && $this->resultCacheLifeTime > 0) {
            $resultData = new Cache\ResultData();
            $resultData->setData($data);
            $this->resultCacheImplementation->set($cacheKey, $resultData, $this->resultCacheLifeTime);
        }

        return $data;
    }

    public function rowCount(): int
    {
        return $this->execute()->rowCount();
    }

    public function insertInto(mixed $tables): self
    {
        $this->setQueryType('insertInto');
        $this->setTables($tables);

        return $this;
    }

    public function insertIgnore(mixed $tables): self
    {
        $this->setQueryType('insertIgnore');
        $this->setTables($tables);

        return $this;
    }

    public function insertDelayed(mixed $tables): self
    {
        $this->setQueryType('insertDelayed');
        $this->setTables($tables);

        return $this;
    }

    public function select(mixed $columns = '*', $clear = false): self
    {
        $this->setQueryType('select');
        if ($clear) {
            $this->columns = [];
        }
        if (is_string($columns)) {
            $arrayColumns = explode(',', $columns);
            $this->columns = array_merge($this->columns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->columns = array_merge($this->columns, $columns);
        }

        return $this;
    }

    /**
     * @param string|array<mixed> $column
     */
    public function set(mixed $column, mixed $value = self::DEFAULT_VALUE): self
    {
        if (is_string($column)) {
            $column = trim($column);
            if (self::DEFAULT_VALUE === $value) {
                $this->setColumns[] = ['column' => $column, 'parameter' => false];
            } else {
                $parameterName = $this->generateParameterName($value);
                $this->setColumns[] = ['column' => $column, 'parameter' => ':'.$parameterName];
            }
        } elseif (is_array($column)) {
            foreach ($column as $k => $v) {
                $this->set($k, $v);
            }
        }

        return $this;
    }

    /**
     * @param string|array<mixed> $values
     */
    public function values(mixed $columns, $values): self
    {
        if (is_string($columns)) {
            $this->values['columns'] = explode(',', $columns);
        } elseif (is_array($columns)) {
            $this->values['columns'] = $columns;
        }

        if (empty($this->values['columns'])) {
            throw new QueryException('Columns are empty');
        }
        if (empty($values)) {
            throw new QueryException('Values are empty');
        }
        if (!is_array($values)) {
            throw new QueryException('Values is not array');
        }

        $this->values['columns'] = array_map(function ($item) {
            if (!strstr($item, '`') && !strstr($item, '.')) {
                $item = "`$item`";
            }

            return $item;
        }, $this->values['columns']);

        $this->values['values'] = array_map(function ($row) {
            foreach ($row as $keyValue => $value) {
                $parameterName = $this->generateParameterName($value);
                $row[$keyValue] = ':'.$parameterName;
            }

            return $row;
        }, $values);

        return $this;
    }

    public function update(mixed $table, string $alias = ''): self
    {
        if (empty($table)) {
            throw new QueryException('Enter table name');
        }
        $this->setTables($table, $alias);
        $this->setQueryType('update');

        return $this;
    }

    public function onDuplicateKeyUpdate(string $string): self
    {
        $this->onDuplicateKeyUpdate = $string;

        return $this;
    }

    public function delete(mixed $table = null): self
    {
        if (!empty($table)) {
            $this->setTables($table);
        }
        $this->setQueryType('delete');

        return $this;
    }

    public function from(mixed $table, string $alias = ''): self
    {
        if (empty($table)) {
            throw new QueryException('Enter table name');
        }
        $this->setTables($table, $alias);

        return $this;
    }

    public function leftJoin(mixed $table, string $alias, string $relation): self
    {
        $this->setJoinTable('LEFT JOIN', $table, $alias, $relation);

        return $this;
    }

    public function rightJoin(mixed $table, string $alias, string $relation): self
    {
        $this->setJoinTable('RIGHT JOIN', $table, $alias, $relation);

        return $this;
    }

    public function innerJoin(mixed $table, string $alias, string $relation): self
    {
        $this->setJoinTable('INNER JOIN', $table, $alias, $relation);

        return $this;
    }

    public function straightJoin(mixed $table, string $alias, string $relation): self
    {
        $this->setJoinTable('STRAIGHT_JOIN', $table, $alias, $relation);

        return $this;
    }

    public function where(string $column, mixed $value = self::DEFAULT_VALUE, string $operator = '='): self
    {
        return $this->setWhereCondition($column, $value, $operator);
    }

    public function having(string $string): self
    {
        $this->havingConditions = [];
        $this->havingConditions[] = ['operator' => '', 'condition' => $string];

        return $this;
    }

    public function andWhere(string $column, mixed $value = self::DEFAULT_VALUE, string $operator = '='): self
    {
        return $this->setWhereCondition($column, $value, $operator, 'AND');
    }

    public function orWhere(string $column, mixed $value = self::DEFAULT_VALUE, string $operator = '='): self
    {
        return $this->setWhereCondition($column, $value, $operator, 'OR');
    }

    /**
     * @return $this
     */
    public function groupBy(mixed $columns): self
    {
        if (is_string($columns)) {
            $arrayColumns = explode(',', $columns);
            $this->groupByColumns = array_merge($this->groupByColumns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->groupByColumns = array_merge($this->groupByColumns, $columns);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function orderBy(mixed $columns): self
    {
        if (is_string($columns)) {
            $arrayColumns = explode(',', $columns);
            $this->orderByColumns = array_merge($this->orderByColumns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->orderByColumns = array_merge($this->orderByColumns, $columns);
        }

        return $this;
    }

    /**
     * @param string|self $query
     *
     * @return $this
     */
    public function unionAll($query)
    {
        if (is_string($query)) {
            $this->unions[] = [
                'type' => 'ALL',
                'query' => $query,
            ];
        } elseif ($query instanceof self) {
            $this->subQueries[] = $query;
            $this->unions[] = [
                'type' => 'ALL',
                'query' => $query,
            ];
        }

        return $this;
    }

    /**
     * @param string|int $limit
     *
     * @return $this
     */
    public function limit($limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return $this
     */
    public function paginationLimit(int $page = 1, int $itemsPerPage = 10): self
    {
        $offset = ($page - 1) * $itemsPerPage;
        $this->limit("$offset, $itemsPerPage");

        return $this;
    }

    /**
     * @return $this
     */
    public function setQueryString(string $query): self
    {
        $this->plainQueryString = $query;

        return $this;
    }

    public function getQueryString(bool $replaceParameters = false): string
    {
        $newLine = PHP_EOL;
        $tab = '  ';
        if (null !== $this->plainQueryString) {
            $queryString = $this->plainQueryString;
        } else {
            if (null === $this->getQueryType()) {
                throw new QueryException('Set query type (insertInto, insertIgnore, insertDelayed, select, update, delete)');
            }

            if ($this->tables instanceof self) {
                $this->tables = ['('.$this->tables->getQueryString().')'];
            }

            $queryString = '';

            switch ($this->getQueryType()) {
                case 'insertInto':
                case 'insertIgnore':
                case 'insertDelayed':
                    if ('insertInto' == $this->getQueryType()) {
                        $queryString .= 'INSERT INTO'.$newLine;
                    } elseif ('insertIgnore' == $this->getQueryType()) {
                        $queryString .= 'INSERT IGNORE'.$newLine;
                    } elseif ('insertDelayed' == $this->getQueryType()) {
                        $queryString .= 'INSERT DELAYED'.$newLine;
                    }

                    /*
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    if (!empty($this->setColumns) && !empty($this->values)) {
                        throw new QueryException('Use only SET or VALUES');
                    }

                    if (!empty($this->setColumns)) {
                        /**
                         * ADD SET COLUMNS.
                         */
                        $setColumns = array_map(function ($array) use ($newLine) {
                            $column = $array['column'];
                            $parameter = $array['parameter'];
                            if (false === $parameter) {
                                return $column.$newLine;
                            } else {
                                return '`'.$column."`=$parameter".$newLine;
                            }
                        }, $this->setColumns);
                        $queryString .= 'SET'.$newLine.$tab.implode("$tab,", $setColumns);
                    }
                    if (!empty($this->values)) {
                        /*
                         * ADD VALUES COLUMNS
                         */
                        $queryString .= $tab.'('.implode(',', $this->values['columns']).')'.$newLine;

                        $values = array_map(function ($array) use ($newLine) {
                            return '('.implode(',', $array).')'.$newLine;
                        }, $this->values['values']);
                        $queryString .= 'VALUES'.$newLine.$tab.implode("$tab,", $values);
                    }

                    if (null !== $this->onDuplicateKeyUpdate) {
                        $queryString .= 'ON DUPLICATE KEY UPDATE '.$newLine.$tab.$this->onDuplicateKeyUpdate.$newLine;
                    }

                    break;
                case 'select':
                    $queryString .= 'SELECT '.$newLine;

                    /**
                     * ADD SELECTED COLUMNS.
                     */
                    $columns = array_map(function ($column) use ($newLine) {
                        return trim($column).$newLine;
                    }, $this->columns);
                    $queryString .= $tab.implode("$tab,", $columns);

                    $queryString .= 'FROM'.$newLine;

                    /*
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /*
                     * ADD JOIN TABLES
                     */
                    foreach ($this->joinTables as $joinTable) {
                        if ($joinTable['table'] instanceof self) {
                            $joinTable['table'] = '('.$joinTable['table']->getQueryString().')';
                        }
                        if (!empty($joinTable['alias'])) {
                            $joinTable['alias'] = ' AS '.$joinTable['alias'];
                        }
                        $queryString .= trim($joinTable['type'].' '.$joinTable['table'].$joinTable['alias'].$newLine.$tab.'ON '.$joinTable['relation']).' '.$newLine;
                    }

                    /*
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /*
                     * GROUP BY
                     */
                    if (count($this->groupByColumns) > 0) {
                        $queryString .= 'GROUP BY '.implode(',', $this->groupByColumns).$newLine;
                    }

                    /*
                     * HAVING CONDITIONS
                     */
                    if (count($this->havingConditions) > 0) {
                        $havingConditions = 'HAVING'.$newLine;
                        $i = 1;
                        foreach ($this->havingConditions as $value) {
                            if (1 == $i) {
                                $havingConditions .= $tab.$value['condition'].$newLine;
                            } else {
                                $havingConditions .= $tab.$value['operator'].' '.$value['condition'].$newLine;
                            }
                            ++$i;
                        }
                        $queryString .= $havingConditions;
                    }

                    /*
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= 'ORDER BY '.implode(',', $this->orderByColumns).$newLine;
                    }

                    /*
                     * LIMIT
                     */
                    if (null !== $this->limit) {
                        $queryString .= 'LIMIT '.$this->limit.$newLine;
                    }

                    /*
                     * UNION
                     */
                    foreach ($this->unions as $key => $value) {
                        if ($value['query'] instanceof self) {
                            $value['query'] = $value['query']->getQueryString();
                        }
                        $queryString .= 'UNION'.$newLine.$value['type'].' '.$value['query'].' '.$newLine;
                    }

                    break;
                case 'update':
                    $queryString .= 'UPDATE'.$newLine;

                    /*
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /*
                     * ADD JOIN TABLES
                     */
                    foreach ($this->joinTables as $joinTable) {
                        if ($joinTable['table'] instanceof self) {
                            $joinTable['table'] = '('.$joinTable['table']->getQueryString().')';
                        }
                        if (!empty($joinTable['alias'])) {
                            $joinTable['alias'] = ' AS '.$joinTable['alias'];
                        }
                        $queryString .= trim($joinTable['type'].' '.$joinTable['table'].$joinTable['alias'].$newLine.$tab.'ON '.$joinTable['relation']).' '.$newLine;
                    }

                    /**
                     * ADD SET COLUMNS.
                     */
                    $setColumns = array_map(function ($array) use ($newLine) {
                        $column = $this->escapeString($array['column']);
                        $parameter = $array['parameter'];
                        if (false === $parameter) {
                            return $column.$newLine;
                        } else {
                            return $column."=$parameter".$newLine;
                        }
                    }, $this->setColumns);
                    $queryString .= 'SET'.$newLine.$tab.implode("$tab,", $setColumns);

                    /*
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /*
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= 'ORDER BY '.implode(',', $this->orderByColumns).$newLine;
                    }

                    /*
                     * LIMIT
                     */
                    if (null !== $this->limit) {
                        $queryString .= 'LIMIT '.$this->limit.$newLine;
                    }

                    break;
                case 'delete':
                    $queryString .= 'DELETE '.$newLine;

                    /**
                     * ADD SELECTED COLUMNS.
                     */
                    $tables = array_map(function ($column) use ($newLine) {
                        return trim($column).$newLine;
                    }, $this->columns);
                    if (!empty($this->tables)) {
                        $queryString .= $tab.implode("$tab,", $tables);
                    } else {
                        $this->setTables(trim(reset($this->columns)));
                    }

                    $queryString .= 'FROM '.$newLine;

                    /*
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /*
                     * ADD JOIN TABLES
                     */
                    foreach ($this->joinTables as $joinTable) {
                        if ($joinTable['table'] instanceof self) {
                            $joinTable['table'] = '('.$joinTable['table']->getQueryString().')';
                        }
                        if (!empty($joinTable['alias'])) {
                            $joinTable['alias'] = ' AS '.$joinTable['alias'];
                        }
                        $queryString .= trim($joinTable['type'].' '.$joinTable['table'].$joinTable['alias'].$newLine.$tab.'ON '.$joinTable['relation']).' '.$newLine;
                    }

                    /*
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /*
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= 'ORDER BY '.implode(',', $this->orderByColumns).$newLine;
                    }

                    /*
                     * LIMIT
                     */
                    if (null !== $this->limit) {
                        $queryString .= 'LIMIT '.$this->limit.$newLine;
                    }

                    break;

                default:
                    throw new QueryException('Invalid query type');
            }
        }
        if (true === $replaceParameters) {
            foreach ($this->getVars() as $name => $value) {
                if (is_int($value)) {
                    $queryString = str_replace(':'.$name, (string) $value, $queryString);
                } else {
                    $queryString = str_replace(':'.$name, '"'.$value.'"', $queryString);
                }
            }
        }

        return trim($queryString);
    }

    /**
     * @return \stdClass[]
     */
    public function getDatabaseTables(): array
    {
        $query = new Query($this->config);
        $query->select([
            'table_name AS `name`',
            'table_schema AS `schema`',
            'engine AS `engine`',
            'table_rows AS `tableRows`',
            'data_length AS `dataLength`',
            'index_length AS `indexLength`',
            'create_time AS `createTime`',
            'update_time AS `updateTime`',
            'table_collation AS `tableCollation`',
        ])->from('information_schema.tables');
        $query->where('table_schema=DATABASE()');

        return $query->fetchObjects();
    }

    /**
     * @return array<mixed>
     */
    public static function getExecutedQueries(): array
    {
        return self::$global['executedQueries'];
    }

    /**
     * Enable result query cache.
     *
     * @deprecated 1.4.1 Please use 'enableResultCache' method
     */
    public function useResultCache(bool $bool = true, int $lifeTime = 300, ?string $key = null): self
    {
        $this->useResultCache = $bool;
        $this->resultCacheLifeTime = $lifeTime;
        $this->resultCacheKey = $key;

        return $this;
    }

    public function enableResultCache(int $lifeTime = 300, ?string $key = null): self
    {
        $this->useResultCache = true;
        $this->resultCacheLifeTime = $lifeTime;
        $this->resultCacheKey = $key;

        return $this;
    }

    public function disableResultCache(): self
    {
        $this->useResultCache = false;

        return $this;
    }

    /**
     * Generate array of parameters from array values. All parameters is safety set with setVar method (bind).
     *
     * @param array<mixed> $data
     *
     * @return string[] Example: [":param1", ":param2", ":param3"]
     */
    public function generateParametersFromArray(array $data): array
    {
        $parameters = [];
        foreach ($data as $value) {
            $parameters[] = ':'.$this->generateParameterName($value);
        }

        return $parameters;
    }

    private function setWhereCondition(mixed $column, mixed $value, string $operator = '', ?string $whereOperator = null): self
    {
        $condition = '';
        if (self::DEFAULT_VALUE === $value) {
            $condition = $column;
        } else {
            switch ($operator) {
                case 'fulltext':
                    if (is_array($column)) {
                        $column = implode(',', $column);
                    }
                    if (is_array($value)) {
                        if (count($value) > 0) {
                            $parameters = [];
                            foreach ($value as $v) {
                                $parameterName = $this->generateParameterName($v);
                                $parameters[] = ':'.$parameterName;
                            }
                            $condition = "MATCH($column) AGAINST(".implode(',', $parameters).')';
                        } else {
                            $condition = "MATCH($column) AGAINST('')";
                        }
                    } else {
                        if (strstr(strtoupper($value), 'WITH QUERY EXPANSION') || strstr(strtoupper($value), 'IN BOOLEAN MODE')) {
                            $condition = "MATCH($column) AGAINST($value)";
                        } else {
                            $parameterName = $this->generateParameterName($value);
                            $condition = "MATCH($column) AGAINST(:$parameterName)";
                        }
                    }
                    break;
                default:
                    $column = $this->escapeString($column);
                    if (is_null($value)) {
                        if ('!=' == $operator) {
                            $condition = $column.' IS NOT NULL';
                        } else {
                            $condition = $column.' IS NULL';
                        }
                    } elseif (is_array($value)) {
                        if (count($value) > 0) {
                            $parameters = [];
                            foreach ($value as $v) {
                                $parameterName = $this->generateParameterName($v);
                                $parameters[] = ':'.$parameterName;
                            }
                            if ('!=' == $operator) {
                                $operator = 'NOT IN';
                            } else {
                                $operator = 'IN';
                            }
                            $condition = $column." $operator (".implode(',', $parameters).')';
                        } else {
                            $condition = $column.' IS NULL';
                        }
                    } elseif ($value instanceof self) {
                        $this->subQueries[] = $value;
                        if ('!=' == $operator) {
                            $operator = 'NOT IN';
                        } else {
                            $operator = 'IN';
                        }
                        $condition = $column." $operator ";
                    } else {
                        $parameterName = $this->generateParameterName($value);
                        $condition = $column.$operator.':'.$parameterName;
                    }
                    break;
            }
        }
        $this->whereConditions[] = [
            'condition' => $condition,
            'column' => $column,
            'value' => $value,
            'operator' => $operator,
            'whereOperator' => $whereOperator,
        ];

        return $this;
    }

    private function setQueryType(string $type): self
    {
        $this->query['type'] = $type;

        return $this;
    }

    private function getQueryType(): ?string
    {
        return $this->query['type'];
    }

    /**
     * @param string|self $table
     */
    private function setTables($table, string $alias = ''): self
    {
        if ($table instanceof self) {
            $this->subQueries[] = $table;
        }
        $this->tables[] = [
            'table' => $table,
            'alias' => $alias,
        ];

        return $this;
    }

    private function setJoinTable(string $type, mixed $table, ?string $alias = null, ?string $relation = null): self
    {
        if (true == $this->config['skipDuplicateJoins']) {
            $check = array_filter($this->joinTables, function ($joinTable) use ($type, $table, $alias, $relation) {
                if ($joinTable['type'] != $type) {
                    return false;
                }
                if ($joinTable['table'] != $table) {
                    return false;
                }
                if ($joinTable['alias'] != $alias) {
                    return false;
                }
                if ($joinTable['relation'] != $relation) {
                    return false;
                }

                return true;
            });
            if (count($check) > 0) {
                return $this;
            }
        }

        if ($table instanceof self) {
            $this->subQueries[] = $table;
        }
        $this->joinTables[] = [
            'type' => $type,
            'table' => $table,
            'alias' => $alias,
            'relation' => $relation,
        ];

        return $this;
    }

    private function parseTables(string $newLine = PHP_EOL, string $tab = '  '): string
    {
        $tables = [];
        foreach ($this->tables as $table) {
            if ($table['table'] instanceof self) {
                $table['table'] = '('.$table['table']->getQueryString().')';
            }
            if (!empty($table['alias'])) {
                $table['alias'] = ' AS '.$table['alias'];
            }
            $tables[] = $table['table'].$table['alias'];
        }

        return $tab.implode("$tab,", $tables).' '.$newLine;
    }

    private function parseWhereConditions(string $newLine = PHP_EOL, string $tab = '  '): string
    {
        $queryString = '';
        if (!empty($this->whereConditions)) {
            $queryString .= 'WHERE '.$newLine;
        }
        foreach ($this->whereConditions as $key => $value) {
            if ($value['value'] instanceof self) {
                $value['condition'] .= '('.$value['value']->getQueryString().')';
            }
            if (0 == $key) {
                $queryString .= $tab.$value['condition'].' '.$newLine;
            } else {
                $queryString .= $tab.$value['whereOperator'].' '.$value['condition'].$newLine;
            }
        }

        return $queryString;
    }

    private function generateParameterName(mixed $value, bool $bindToQuery = true): string
    {
        $name = sha1(uniqid('param', true));
        if ($bindToQuery) {
            $this->setVar($name, $value);
        }

        return $name;
    }

    private function escapeString(string $string): string
    {
        if (
            !strstr($string, '.')
            && !strstr($string, '`')
            && !strstr($string, '<')
            && !strstr($string, ',')
            && !strstr($string, '>')
            && !strstr($string, '=')
            && !strstr($string, '(')
            && !strstr($string, ')')
            && !strstr($string, ' IN ')
            && !strstr($string, ' NOT IN ')
            && !strstr($string, '!=')
        ) {
            $string = '`'.$string.'`';
        }

        return $string;
    }

    /**
     * @param \stdClass|mixed $value
     */
    private function hydrateClass(\ReflectionClass $reflectionClass, mixed $value): mixed
    {
        $objectInstance = $reflectionClass->newInstance();

        foreach ($reflectionClass->getProperties() as $property) {
            if (!property_exists($value, $property->getName())) {
                continue;
            }

            $type = '';
            $docComment = $property->getDocComment();

            if (!empty($property->getType())) {
                $type = trim(strtolower($property->getType()->getName()));
                if ($property->getType()->allowsNull()) {
                    $type = $type.'|null';
                }
            } elseif (!empty($docComment)) {
                if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
                    $type = trim(strtolower($matches[1]));
                }
            }

            $property->setAccessible(true);

            /*
             * check for nullable
             */
            if (in_array('null', explode('|', $type)) && is_null($value->{$property->getName()})) {
                $property->setValue($objectInstance, $value->{$property->getName()});
            } else {
                $types = explode('|', $type);

                switch (reset($types)) {
                    case 'int':
                    case 'integer':
                        $property->setValue($objectInstance, (int) $value->{$property->getName()});
                        break;
                    case 'float':
                    case 'double':
                        $property->setValue($objectInstance, (float) $value->{$property->getName()});
                        break;
                    case 'string':
                        $property->setValue($objectInstance, (string) $value->{$property->getName()});
                        break;
                    case 'bool':
                    case 'boolean':
                        $property->setValue($objectInstance, boolval($value->{$property->getName()}));
                        break;
                    case 'datetime':
                    case "\datetime":
                        $property->setValue($objectInstance, new \DateTime($value->{$property->getName()}));
                        break;
                    default:
                        $property->setValue($objectInstance, $value->{$property->getName()});
                        break;
                }
            }
        }

        return $objectInstance;
    }
}
