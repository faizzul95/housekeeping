<?php

namespace OnlyPHP\Housekeeping\Connections;

use PDO;
use mysqli;
use Exception;
use RuntimeException;

class DatabaseAdapter
{
    private $connection;
    private $connectionType;
    private $lastStatement;

    /**
     * @param mixed $connection mysqli|PDO|CI_DB_mysqli_driver
     * @throws RuntimeException
     */
    public function __construct($connection)
    {
        $this->connection = $connection;

        if ($connection instanceof PDO) {
            $this->connectionType = 'pdo';
        } elseif ($connection instanceof mysqli) {
            $this->connectionType = 'mysqli';
        } elseif (is_object($connection) && property_exists($connection, 'dbdriver') && $connection->dbdriver === 'mysqli') {
            $this->connectionType = 'codeigniter3';
        } else {
            throw new RuntimeException('Unsupported database connection type');
        }
    }

    /**
     * Begin a database transaction
     * @throws Exception
     */
    public function beginTransaction()
    {
        try {
            switch ($this->connectionType) {
                case 'pdo':
                    $this->connection->beginTransaction();
                    break;
                case 'mysqli':
                    $this->connection->begin_transaction();
                    break;
                case 'codeigniter3':
                    $this->connection->trans_start();
                    break;
                default:
                    throw new RuntimeException("Transaction start not supported for this connection type.");
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to start transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Commit a database transaction
     * @throws Exception
     */
    public function commit()
    {
        try {
            switch ($this->connectionType) {
                case 'pdo':
                    $this->connection->commit();
                    break;
                case 'mysqli':
                    $this->connection->commit();
                    break;
                case 'codeigniter3':
                    $this->connection->trans_complete();
                    break;
                default:
                    throw new RuntimeException("Commit not supported for this connection type.");
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to commit transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Roll back a database transaction
     * @throws Exception
     */
    public function rollback()
    {
        try {
            switch ($this->connectionType) {
                case 'pdo':
                    $this->connection->rollBack();
                    break;
                case 'mysqli':
                    $this->connection->rollback();
                    break;
                case 'codeigniter3':
                    $this->connection->trans_rollback();
                    break;
                default:
                    throw new RuntimeException("Rollback not supported for this connection type.");
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to rollback transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute a SQL query
     * @param string $sql
     * @param array $params
     * @return DatabaseStatement|null
     * @throws Exception
     */
    public function execute($sql, $params = [])
    {
        try {
            switch ($this->connectionType) {
                case 'pdo':
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);
                    $this->lastStatement = new DatabaseStatement($stmt, 'pdo');
                    break;

                case 'mysqli':
                    if (!empty($params)) {
                        $stmt = $this->connection->prepare($sql);
                        if ($stmt === false) {
                            throw new RuntimeException("Failed to prepare statement: " . $this->connection->error);
                        }

                        $types = '';
                        foreach ($params as $param) {
                            if (is_int($param)) $types .= 'i';
                            elseif (is_float($param)) $types .= 'd';
                            elseif (is_string($param)) $types .= 's';
                            else $types .= 'b';
                        }

                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $result = $stmt->get_result();
                    } else {
                        $result = $this->connection->query($sql);
                    }

                    if ($result === false) {
                        throw new RuntimeException("Query execution failed: " . $this->connection->error);
                    }

                    $this->lastStatement = new DatabaseStatement($result, 'mysqli');
                    break;

                case 'codeigniter3':
                    $query = $this->connection->query($sql, $params);
                    if ($query === false) {
                        throw new RuntimeException("Query execution failed: " . $this->connection->error());
                    }
                    $this->lastStatement = new DatabaseStatement($query, 'codeigniter3');
                    break;
            }

            return $this->lastStatement;
        } catch (Exception $e) {
            throw new RuntimeException("Query execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the native database connection
     * @return mixed
     */
    public function getNativeConnection()
    {
        return $this->connection;
    }

    /**
     * Get the type of database connection
     * @return string
     */
    public function getConnectionType()
    {
        return $this->connectionType;
    }
}