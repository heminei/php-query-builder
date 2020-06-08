<?php

namespace HemiFrame\Lib\SQLBuilder;

/**
 * @author heminei <heminei@heminei.com>
 */
class Query
{

    const DEFAULT_VALUE = "f056c6dc7b3fc49ea4655853bea9b1d4e637a52d3d4a582985175f513fb9589241e21657f686e633c36760416031546cfe283d9961b9ac19aa2c45175dbbc9dd";

    /**
     * @var array
     */
    public static $global = [
        "pdo" => null,
        "logs" => [
            "errors" => null,
        ],
        "executedQueries" => [],
        "resultCache" => [
            "implementation" => null,
        ],
    ];

    /**
     *
     * @var array
     */
    private $config = [];

    /**
     *
     * @var \PDO|null
     */
    private $pdo = null;

    /**
     *
     * @var array
     */
    private $query = array(
        "type" => null,
    );

    /**
     *
     * @var array
     */
    private $subQueries = [];

    /**
     *
     * @var float
     */
    private $executionTime = 0;

    /**
     *
     * @var array
     */
    private $vars = [];

    /**
     *
     * @var mixed
     */
    private $tables = null;

    /**
     *
     * @var array
     */
    private $columns = [];

    /**
     *
     * @var array
     */
    private $joinTables = [];

    /**
     *
     * @var array
     */
    private $whereConditions = [];

    /**
     *
     * @var array
     */
    private $havingConditions = [];

    /**
     *
     * @var array
     */
    private $groupByColumns = [];

    /**
     *
     * @var array
     */
    private $orderByColumns = [];

    /**
     *
     * @var array
     */
    private $setColumns = [];

    /**
     *
     * @var array
     */
    private $values = [];

    /**
     *
     * @var string|int
     */
    private $limit = null;

    /**
     *
     * @var string
     */
    private $onDuplicateKeyUpdate = null;

    /**
     *
     * @var array
     */
    private $unions = [];

    /**
     *
     * @var string|null
     */
    private $plainQueryString = null;

    /**
     * @var bool
     */
    private $useResultCache = false;

    /**
     * @var \Psr\SimpleCache\CacheInterface|null
     */
    private $resultCacheImplementation = null;

    /**
     * @var int
     */
    private $resultCacheLifeTime = 0;

    /**
     * @var string|null
     */
    private $resultCacheKey = null;

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

        // if (!$this->pdo instanceof \PDO) {
        //     throw new QueryException("Set PDO object");
        // }
    }

    /**
     * Set PDO object
     * @param \PDO $pdo
     * @throws QueryException
     * @return self
     */
    public function setPdo(\PDO $pdo): self
    {
        if (!$pdo instanceof \PDO) {
            throw new QueryException("Set PDO object");
        }
        $this->pdo = $pdo;

        return $this;
    }

    /**
     *
     * @return \PDO|null
     */
    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    /**
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setVar(string $name, $value): self
    {
        if (empty($name)) {
            throw new QueryException("Enter var name");
        }
        if (!is_string($name)) {
            throw new QueryException("Var name must be string");
        }
        if ($value instanceof \DateTime) {
            $value = $value->format("Y-m-d H:i:s");
        }

        $this->vars[$name] = $value;

        return $this;
    }

    /**
     *
     * @param array $array
     * @return self
     */
    public function setVars(array $array): self
    {
        if (!is_array($array)) {
            throw new QueryException("Invalid array");
        }
        foreach ($array as $key => $value) {
            $this->setVar($key, $value);
        }
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     *
     * @return array
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
     *
     * @return array
     */
    public function getSubQueries(): array
    {
        return $this->subQueries;
    }

    /**
     *
     * @param string $name
     * @return mixed
     */
    public function getVar(string $name)
    {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }
        return null;
    }

    /**
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     *
     * @return string
     */
    public function getLastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Initiates a transaction
     * Returns TRUE on success or FALSE on failure.
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     * Returns TRUE on success or FALSE on failure.
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rolls back a transaction
     * Returns TRUE on success or FALSE on failure.
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Checks if inside a transaction
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Prepares a statement for execution and returns a statement object
     * @param string $query
     * @return \PDOStatement
     */
    public function prepare(string $query): \PDOStatement
    {
        return $this->pdo->prepare($query);
    }

    /**
     *
     * @return \PDOStatement
     * @throws QueryException
     */
    public function execute(): \PDOStatement
    {
        $timeStart = microtime(true);

        $PDOStatement = $this->prepare($this->getQueryString());

        /**
         * bind vars
         */
        foreach ($this->getVars() as $key => $value) {
            if (is_int($value)) {
                $PDOStatement->bindValue(":" . $key, $value, \PDO::PARAM_INT);
            } elseif (is_null($value)) {
                $PDOStatement->bindValue(":" . $key, $value, \PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $PDOStatement->bindValue(":" . $key, $value, \PDO::PARAM_BOOL);
            } else {
                $PDOStatement->bindValue(":" . $key, $value, \PDO::PARAM_STR);
            }
        }

        $PDOStatement->execute();
        $this->executionTime = microtime(true) - $timeStart;

        $error = $PDOStatement->errorInfo();
        if (!empty($error[2])) {
            if (!empty($this->config['logs']["errors"])) {
                $fileErrors = fopen($this->config['logs']["errors"], "a+");
                $text = $error[2] . " - " . $this->getQueryString() . "\n\n";
                fwrite($fileErrors, $text);
                fclose($fileErrors);
            }
            throw new QueryException($error[2] . " ==> " . $this->getQueryString());
        }

        self::$global['executedQueries'][] = [
            "query" => $this->getQueryString(),
            "time" => $this->getExecutionTime(),
        ];

        return $PDOStatement;
    }

    /**
     *
     * @return array
     */
    public function fetchObjects(): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = __METHOD__ . "-" . $this->getQueryString(true);
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                $data = $this->resultCacheImplementation->get($cacheKey);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_OBJ);
        if (!is_array($data)) {
            $data = [];
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            $this->resultCacheImplementation->set($cacheKey, $data, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     *
     * @return mixed
     */
    public function fetchFirstObject()
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = __METHOD__ . "-" . $this->getQueryString(true);
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                $data = $this->resultCacheImplementation->get($cacheKey);
                if (is_object($data) || $data == null) {
                    return $data;
                }
            }
        }

        $data = $this->execute()->fetchObject();
        if (!is_object($data) && $data !== null) {
            $data = null;
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            $this->resultCacheImplementation->set($cacheKey, $data, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     *
     * @return array
     */
    public function fetchArrays(): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = __METHOD__ . "-" . $this->getQueryString(true);
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                $data = $this->resultCacheImplementation->get($cacheKey);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($data)) {
            $data = [];
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            $this->resultCacheImplementation->set($cacheKey, $data, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     *
     * @return array
     * @throws QueryException
     */
    public function fetchColumn(): array
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = __METHOD__ . "-" . $this->getQueryString(true);
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                $data = $this->resultCacheImplementation->get($cacheKey);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        $data = $this->execute()->fetchAll(\PDO::FETCH_COLUMN);
        if (!is_array($data)) {
            $data = [];
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            $this->resultCacheImplementation->set($cacheKey, $data, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     *
     * @return mixed
     */
    public function fetchFirstArray()
    {
        $cacheKey = $this->resultCacheKey;
        if (empty($cacheKey)) {
            $cacheKey = __METHOD__ . "-" . $this->getQueryString(true);
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            if ($this->resultCacheImplementation->has($cacheKey)) {
                $data = $this->resultCacheImplementation->get($cacheKey);
                if (is_array($data) || $data == null) {
                    return $data;
                }
            }
        }

        $data = $this->execute()->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($data) && $data !== null) {
            $data = null;
        }

        if ($this->useResultCache == true && $this->resultCacheLifeTime > 0) {
            $this->resultCacheImplementation->set($cacheKey, $data, $this->resultCacheLifeTime);
        }

        return $data;
    }

    /**
     *
     * @return int
     */
    public function rowCount(): int
    {
        return $this->execute()->rowCount();
    }

    /**
     *
     * @param mixed $tables
     * @return self
     */
    public function insertInto($tables): self
    {
        $this->setQueryType("insertInto");
        $this->setTables($tables);
        return $this;
    }

    /**
     *
     * @param mixed $tables
     * @return self
     */
    public function insertIgnore($tables): self
    {
        $this->setQueryType("insertIgnore");
        $this->setTables($tables);
        return $this;
    }

    /**
     *
     * @param mixed $tables
     * @return self
     */
    public function insertDelayed($tables): self
    {
        $this->setQueryType("insertDelayed");
        $this->setTables($tables);
        return $this;
    }

    /**
     *
     * @param mixed $columns
     * @return self
     */
    public function select($columns = "*"): self
    {
        $this->setQueryType("select");
        if (is_string($columns)) {
            $arrayColumns = explode(",", $columns);
            $this->columns = array_merge($this->columns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->columns = array_merge($this->columns, $columns);
        }
        return $this;
    }

    /**
     * @param string|array $column
     * @param mixed $value
     * @return self
     */
    public function set($column, $value = self::DEFAULT_VALUE): self
    {
        if (is_string($column)) {
            $column = trim($column);
            if ($value === self::DEFAULT_VALUE) {
                $this->setColumns[] = array("column" => $column, "parameter" => false);
            } else {
                $parameterName = $this->generateParameterName($value);
                $this->setColumns[] = array("column" => $column, "parameter" => ":" . $parameterName);
            }
        } elseif (is_array($column)) {
            foreach ($column as $k => $v) {
                $this->set($k, $v);
            }
        }

        return $this;
    }

    /**
     *
     * @param mixed $columns
     * @param array $values
     * @return self
     */
    public function values($columns, $values): self
    {
        if (is_string($columns)) {
            $this->values["columns"] = explode(",", $columns);
        } elseif (is_array($columns)) {
            $this->values["columns"] = $columns;
        }

        if (empty($this->values["columns"])) {
            throw new QueryException("Columns are empty");
        }
        if (empty($values)) {
            throw new QueryException("Values are empty");
        }
        if (!is_array($values)) {
            throw new QueryException("Values is not array");
        }

        $this->values["columns"] = array_map(function ($item) {
            if (!strstr($item, "`") && !strstr($item, ".")) {
                $item = "`$item`";
            }
            return $item;
        }, $this->values["columns"]);

        $this->values["values"] = array_map(function ($row) {
            foreach ($row as $keyValue => $value) {
                $parameterName = $this->generateParameterName($value);
                $row[$keyValue] = ":" . $parameterName;
            }
            return $row;
        }, $values);

        return $this;
    }

    /**
     *
     * @param mixed $table
     * @return $this
     */
    public function update($table): self
    {
        if (empty($table)) {
            throw new QueryException("Enter table name");
        }
        $this->setTables($table);
        $this->setQueryType("update");
        return $this;
    }

    /**
     * @param string $string
     * @return self
     */
    public function onDuplicateKeyUpdate(string $string): self
    {
        $this->onDuplicateKeyUpdate = $string;

        return $this;
    }

    /**
     *
     * @param mixed $table
     * @return $this
     */
    public function delete($table = null): self
    {
        if (!empty($table)) {
            $this->setTables($table);
        }
        $this->setQueryType("delete");
        return $this;
    }

    /**
     *
     * @param mixed $table
     * @param string $alias
     * @return $this
     */
    public function from($table, $alias = ""): self
    {
        if (empty($table)) {
            throw new QueryException("Enter table name");
        }
        $this->setTables($table, $alias);
        return $this;
    }

    /**
     *
     * @param mixed $table
     * @param string $alias
     * @param string $relation
     * @return $this
     */
    public function leftJoin($table, $alias, $relation): self
    {
        $this->setJoinTable("LEFT JOIN", $table, $alias, $relation);
        return $this;
    }

    /**
     *
     * @param mixed $table
     * @param string $alias
     * @param string $relation
     * @return $this
     */
    public function rightJoin($table, $alias, $relation): self
    {
        $this->setJoinTable("RIGHT JOIN", $table, $alias, $relation);
        return $this;
    }

    /**
     *
     * @param mixed $table
     * @param string $alias
     * @param string $relation
     * @return $this
     */
    public function innerJoin($table, $alias, $relation): self
    {
        $this->setJoinTable("INNER JOIN", $table, $alias, $relation);
        return $this;
    }

    /**
     *
     * @param mixed $table
     * @param string $alias
     * @param string $relation
     * @return $this
     */
    public function straightJoin($table, $alias, $relation): self
    {
        $this->setJoinTable("STRAIGHT_JOIN", $table, $alias, $relation);
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return self
     */
    public function where(string $column, $value = self::DEFAULT_VALUE, string $operator = "="): self
    {
        return $this->setWhereCondition($column, $value, $operator);
    }

    /**
     * @param string $string
     * @return self
     */
    public function having(string $string): self
    {
        $this->havingConditions = [];
        $this->havingConditions[] = array("operator" => "", "condition" => $string);
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return self
     */
    public function andWhere(string $column, $value = self::DEFAULT_VALUE, string $operator = "="): self
    {
        return $this->setWhereCondition($column, $value, $operator, "AND");
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return self
     */
    public function orWhere(string $column, $value = self::DEFAULT_VALUE, string $operator = "="): self
    {
        return $this->setWhereCondition($column, $value, $operator, "OR");
    }

    /**
     * @param mixed $columns
     * @return $this
     */
    public function groupBy($columns): self
    {
        if (is_string($columns)) {
            $arrayColumns = explode(",", $columns);
            $this->groupByColumns = array_merge($this->groupByColumns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->groupByColumns = array_merge($this->groupByColumns, $columns);
        }
        return $this;
    }

    /**
     *
     * @param mixed $columns
     * @return $this
     */
    public function orderBy($columns): self
    {
        if (is_string($columns)) {
            $arrayColumns = explode(",", $columns);
            $this->orderByColumns = array_merge($this->orderByColumns, $arrayColumns);
        } elseif (is_array($columns)) {
            $this->orderByColumns = array_merge($this->orderByColumns, $columns);
        }
        return $this;
    }

    /**
     *
     * @param string|self $query
     * @return $this
     */
    public function unionAll($query)
    {
        if (is_string($query)) {
            $this->unions[] = [
                "type" => "ALL",
                "query" => $query,
            ];
        } elseif ($query instanceof self) {
            $this->subQueries[] = $query;
            $this->unions[] = [
                "type" => "ALL",
                "query" => $query,
            ];
        }
        return $this;
    }

    /**
     * @param string|int $limit
     * @return $this
     */
    public function limit($limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     *
     * @param int $page
     * @param int $itemsPerPage
     * @return $this
     */
    public function paginationLimit(int $page = 1, int $itemsPerPage = 10): self
    {
        $offset = ($page - 1) * $itemsPerPage;
        $this->limit("$offset, $itemsPerPage");
        return $this;
    }

    /**
     *
     * @param string $query
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
        $tab = "  ";
        if ($this->plainQueryString !== null) {
            $queryString = $this->plainQueryString;
        } else {
            if (empty($this->tables)) {
                throw new QueryException("Enter table");
            }
            if ($this->getQueryType() === null) {
                throw new QueryException("Set query type (insertInto, insertIgnore, insertDelayed, select, update, delete)");
            }

            if ($this->tables instanceof self) {
                $this->tables = ["(" . $this->tables->getQueryString() . ")"];
            }

            $queryString = "";

            switch ($this->getQueryType()) {
                case "insertInto":
                case "insertIgnore":
                case "insertDelayed":
                    if ($this->getQueryType() == "insertInto") {
                        $queryString .= "INSERT INTO" . $newLine;
                    } elseif ($this->getQueryType() == "insertIgnore") {
                        $queryString .= "INSERT IGNORE" . $newLine;
                    } elseif ($this->getQueryType() == "insertDelayed") {
                        $queryString .= "INSERT DELAYED" . $newLine;
                    }

                    /**
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    if (!empty($this->setColumns) && !empty($this->values)) {
                        throw new QueryException("Use only SET or VALUES");
                    }

                    if (!empty($this->setColumns)) {
                        /**
                         * ADD SET COLUMNS
                         */
                        $setColumns = array_map(function ($array) use ($newLine) {
                            $column = $array["column"];
                            $parameter = $array["parameter"];
                            if ($parameter === false) {
                                return $column . $newLine;
                            } else {
                                return "`" . $column . "`=$parameter" . $newLine;
                            }
                        }, $this->setColumns);
                        $queryString .= "SET" . $newLine . $tab . implode("$tab,", $setColumns);
                    }
                    if (!empty($this->values)) {
                        /**
                         * ADD VALUES COLUMNS
                         */
                        $queryString .= $tab . "(" . implode(",", $this->values['columns']) . ")" . $newLine;

                        $values = array_map(function ($array) use ($newLine) {
                            return "(" . implode(",", $array) . ")" . $newLine;
                        }, $this->values['values']);
                        $queryString .= "VALUES" . $newLine . $tab . implode("$tab,", $values);
                    }

                    if ($this->onDuplicateKeyUpdate !== null) {
                        $queryString .= "ON DUPLICATE KEY UPDATE " . $newLine . $tab . $this->onDuplicateKeyUpdate . $newLine;
                    }

                    break;
                case "select":
                    $queryString .= "SELECT " . $newLine;

                    /**
                     * ADD SELECTED COLUMNS
                     */
                    $columns = array_map(function ($column) use ($newLine) {
                        return trim($column) . $newLine;
                    }, $this->columns);
                    $queryString .= $tab . implode("$tab,", $columns);

                    $queryString .= "FROM" . $newLine;

                    /**
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /**
                     * ADD JOIN TABLES
                     */
                    foreach ($this->joinTables as $joinTable) {
                        if ($joinTable['table'] instanceof self) {
                            $joinTable['table'] = "(" . $joinTable['table']->getQueryString() . ")";
                        }
                        if (!empty($joinTable['alias'])) {
                            $joinTable['alias'] = " AS " . $joinTable['alias'];
                        }
                        $queryString .= trim($joinTable['type'] . ' ' . $joinTable['table'] . $joinTable['alias'] . $newLine . $tab . "ON " . $joinTable['relation']) . " " . $newLine;
                    }

                    /**
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /**
                     * GROUP BY
                     */
                    if (count($this->groupByColumns) > 0) {
                        $queryString .= "GROUP BY " . implode(",", $this->groupByColumns) . $newLine;
                    }

                    /**
                     * HAVING CONDITIONS
                     */
                    if (count($this->havingConditions) > 0) {
                        $havingConditions = "HAVING" . $newLine;
                        $i = 1;
                        foreach ($this->havingConditions as $value) {
                            if ($i == 1) {
                                $havingConditions .= $tab . $value['condition'] . $newLine;
                            } else {
                                $havingConditions .= $tab . $value['operator'] . " " . $value['condition'] . $newLine;
                            }
                            $i++;
                        }
                        $queryString .= $havingConditions;
                    }

                    /**
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= "ORDER BY " . implode(",", $this->orderByColumns) . $newLine;
                    }

                    /**
                     * LIMIT
                     */
                    if ($this->limit !== null) {
                        $queryString .= "LIMIT " . $this->limit . $newLine;
                    }

                    /**
                     * UNION
                     */
                    foreach ($this->unions as $key => $value) {
                        if ($value['query'] instanceof self) {
                            $value['query'] = $value['query']->getQueryString();
                        }
                        $queryString .= "UNION" . $newLine . $value['type'] . " " . $value['query'] . " " . $newLine;
                    }

                    break;
                case "update":
                    $queryString .= "UPDATE" . $newLine;

                    /**
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /**
                     * ADD JOIN TABLES
                     */
                    foreach ($this->joinTables as $joinTable) {
                        if ($joinTable['table'] instanceof self) {
                            $joinTable['table'] = "(" . $joinTable['table']->getQueryString() . ")";
                        }
                        if (!empty($joinTable['alias'])) {
                            $joinTable['alias'] = " AS " . $joinTable['alias'];
                        }
                        $queryString .= trim($joinTable['type'] . ' ' . $joinTable['table'] . $joinTable['alias'] . $newLine . $tab . "ON " . $joinTable['relation']) . " " . $newLine;
                    }

                    /**
                     * ADD SET COLUMNS
                     */
                    $setColumns = array_map(function ($array) use ($newLine) {
                        $column = $array["column"];
                        $parameter = $array["parameter"];
                        if ($parameter === false) {
                            return $column . $newLine;
                        } else {
                            return "`" . $column . "`=$parameter" . $newLine;
                        }
                    }, $this->setColumns);
                    $queryString .= "SET" . $newLine . $tab . implode("$tab,", $setColumns);

                    /**
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /**
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= "ORDER BY " . implode(",", $this->orderByColumns) . $newLine;
                    }

                    /**
                     * LIMIT
                     */
                    if ($this->limit !== null) {
                        $queryString .= "LIMIT " . $this->limit . $newLine;
                    }

                    break;
                case "delete":
                    $queryString .= "DELETE FROM" . $newLine;

                    /**
                     * Parse tables
                     */
                    $queryString .= $this->parseTables($newLine, $tab);

                    /**
                     * PARSE WHERE CONDITIONS
                     */
                    $queryString .= $this->parseWhereConditions($newLine, $tab);

                    /**
                     * ORDER BY
                     */
                    if (count($this->orderByColumns) > 0) {
                        $queryString .= "ORDER BY " . implode(",", $this->orderByColumns) . $newLine;
                    }

                    /**
                     * LIMIT
                     */
                    if ($this->limit !== null) {
                        $queryString .= "LIMIT " . $this->limit . $newLine;
                    }

                    break;

                default:
                    throw new QueryException("Invalid query type");
            }
        }
        if ($replaceParameters === true) {
            foreach ($this->getVars() as $name => $value) {
                if (is_int($value)) {
                    $queryString = str_replace(":" . $name, (string) $value, $queryString);
                } else {
                    $queryString = str_replace(":" . $name, '"' . $value . '"', $queryString);
                }
            }
        }

        return trim($queryString);
    }

    /**
     * @return array
     */
    public function getDatabaseTables(): array
    {
        $query = new Query($this->config);
        $query->select([
            "table_name AS `name`",
            "table_schema AS `schema`",
            "engine AS `engine`",
            "table_rows AS `tableRows`",
            "data_length AS `dataLength`",
            "index_length AS `indexLength`",
            "create_time AS `createTime`",
            "update_time AS `updateTime`",
            "table_collation AS `tableCollation`",
        ])->from("information_schema.tables");
        $query->where("table_schema=DATABASE()");

        return $query->fetchObjects();
    }

    /**
     * @return array
     */
    public static function getExecutedQueries(): array
    {
        return self::$global['executedQueries'];
    }

    /**
     * Enable result query cache
     *
     * @param boolean $bool
     * @param integer $lifeTime
     * @param string|null $key
     * @return self
     */
    public function useResultCache(bool $bool = true, int $lifeTime = 300, ?string $key = null): self
    {
        $this->useResultCache = $bool;
        $this->resultCacheLifeTime = $lifeTime;
        $this->resultCacheKey = $key;

        return $this;
    }

    /**
     *
     * @param mixed $column
     * @param mixed $value
     * @param string $operator
     * @param string $whereOperator
     * @return self
     */
    private function setWhereCondition($column, $value, string $operator = "", $whereOperator = null): self
    {
        $condition = "";
        if ($value === self::DEFAULT_VALUE) {
            $condition = $column;
        } else {
            $column = $this->escapeString($column);
            if (is_null($value)) {
                if ($operator == "!=") {
                    $condition = $column . " IS NOT NULL";
                } else {
                    $condition = $column . " IS NULL";
                }
            } elseif (is_array($value)) {
                $parameters = [];
                foreach ($value as $v) {
                    $parameterName = $this->generateParameterName($v);
                    $parameters[] = ":" . $parameterName;
                }
                if ($operator == "!=") {
                    $operator = "NOT IN";
                } else {
                    $operator = "IN";
                }
                $condition = $column . " $operator (" . implode(",", $parameters) . ")";
            } elseif ($value instanceof self) {
                $this->subQueries[] = $value;
                if ($operator == "!=") {
                    $operator = "NOT IN";
                } else {
                    $operator = "IN";
                }
                $condition = $column . " $operator ";
            } else {
                $parameterName = $this->generateParameterName($value);
                $condition = $column . $operator . ":" . $parameterName;
            }
        }
        $this->whereConditions[] = [
            "condition" => $condition,
            "column" => $column,
            "value" => $value,
            "operator" => $operator,
            "whereOperator" => $whereOperator,
        ];

        return $this;
    }

    /**
     *
     * @param string $type
     * @return self
     */
    private function setQueryType(string $type): self
    {
        $this->query['type'] = $type;

        return $this;
    }

    /**
     * @return string|null
     */
    private function getQueryType(): ?string
    {
        return $this->query['type'];
    }

    /**
     * @param string|self $table
     * @param string $alias
     * @return self
     */
    private function setTables($table, string $alias = ""): self
    {
        if ($table instanceof self) {
            /** @var Query $table */
            $this->subQueries[] = $table;
        }
        $this->tables[] = [
            "table" => $table,
            "alias" => $alias,
        ];
        return $this;
    }

    /**
     * @param string $type
     * @param mixed $table
     * @param string|null $alias
     * @param string|null $relation
     * @return self
     */
    private function setJoinTable(string $type, $table, ?string $alias = null, ?string $relation = null): self
    {
        if ($table instanceof self) {
            $this->subQueries[] = $table;
        }
        $this->joinTables[] = [
            "type" => $type,
            "table" => $table,
            "alias" => $alias,
            "relation" => $relation,
        ];
        return $this;
    }

    /**
     * @param string $newLine
     * @param string $tab
     * @return string
     */
    private function parseTables(string $newLine = PHP_EOL, string $tab = '  '): string
    {
        $tables = [];
        foreach ($this->tables as $table) {
            if ($table['table'] instanceof self) {
                $table['table'] = "(" . $table['table']->getQueryString() . ")";
            }
            if (!empty($table['alias'])) {
                $table['alias'] = " AS " . $table['alias'];
            }
            $tables[] = $table['table'] . $table['alias'];
        }
        return $tab . implode("$tab,", $tables) . " " . $newLine;
    }

    /**
     * @param string $newLine
     * @param string $tab
     * @return string
     */
    private function parseWhereConditions(string $newLine = PHP_EOL, string $tab = '  '): string
    {
        $queryString = "";
        if (!empty($this->whereConditions)) {
            $queryString .= "WHERE " . $newLine;
        }
        foreach ($this->whereConditions as $key => $value) {
            if ($value['value'] instanceof self) {
                $value['condition'] .= "(" . $value['value']->getQueryString() . ")";
            }
            if ($key == 0) {
                $queryString .= $tab . $value['condition'] . " " . $newLine;
            } else {
                $queryString .= $tab . $value['whereOperator'] . " " . $value['condition'] . $newLine;
            }
        }

        return $queryString;
    }

    /**
     *
     * @param mixed $value
     * @param bool $bindToQuery
     * @return string
     */
    private function generateParameterName($value, bool $bindToQuery = true): string
    {
        $name = sha1(uniqid("param", true) . sha1($value));
        if ($bindToQuery) {
            $this->setVar($name, $value);
        }
        return $name;
    }

    /**
     *
     * @param string $string
     * @return string
     */
    private function escapeString(string $string): string
    {
        if (
            !strstr($string, ".") &&
            !strstr($string, "`") &&
            !strstr($string, "<") &&
            !strstr($string, ",") &&
            !strstr($string, ">") &&
            !strstr($string, "=") &&
            !strstr($string, "(") &&
            !strstr($string, ")") &&
            !strstr($string, " IN ") &&
            !strstr($string, " NOT IN ") &&
            !strstr($string, "!=")
        ) {
            $string = "`" . $string . "`";
        }
        return $string;
    }
}
