<?php

namespace OnlyPHP\Housekeeping\Connections;

use PDO;
use mysqli;
use Exception;
use Throwable;
use RuntimeException;

class DatabaseAdapter
{
    private $connection;
    private $connectionType;
    private $lastStatement;

    /**
     * @param mixed $connection mysqli|PDO|CI_DB_mysqli_driver|resource Oracle OCI connection
     * @throws RuntimeException
     */
    public function __construct($connection)
    {
        $this->connection = $connection;

        if ($connection instanceof PDO) {
            // Check if it's an OCI PDO connection
            $driverName = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
            if (in_array($driverName, ['oci', 'oracle'])) {
                $this->connectionType = 'pdo_oci';
            } else {
                $this->connectionType = 'pdo';
            }
        } elseif ($connection instanceof mysqli) {
            // Check if it's a MariaDB connection
            if (method_exists($connection, 'get_server_info') && stripos($connection->get_server_info(), 'mariadb') !== false) {
                $this->connectionType = 'mariadb';
            } else {
                $this->connectionType = 'mysqli';
            }
        } elseif (is_object($connection) && property_exists($connection, 'dbdriver')) {
            if ($connection->dbdriver === 'mysqli') {
                $this->connectionType = 'codeigniter3';
            } elseif (in_array($connection->dbdriver, ['oci8', 'oracle'])) {
                $this->connectionType = 'codeigniter3_oci';
            }
        } elseif (is_resource($connection) && get_resource_type($connection) === 'oci8 connection') {
            $this->connectionType = 'oci';
        } else {
            throw new RuntimeException('Unsupported database connection type');
        }
    }

    /**
     * Begin a database transaction
     * @throws RuntimeException
     */
    public function beginTransaction()
    {
        if (!$this->connection) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        try {
            switch ($this->connectionType) {
                case 'pdo':
                case 'pdo_oci':
                    if (!$this->connection->inTransaction()) { // Prevent nested transactions
                        $this->connection->beginTransaction();
                    }
                    break;
                case 'mysqli':
                case 'mariadb':
                    $this->connection->begin_transaction();
                    break;
                case 'codeigniter3':
                case 'codeigniter3_oci':
                    if (method_exists($this->connection, 'trans_start')) {
                        $this->connection->trans_start();
                    } else {
                        throw new RuntimeException("Transaction start not supported.");
                    }
                    break;
                case 'oci':
                case 'oracle':
                case 'oci8':
                    // OCI auto-commits unless explicitly told not to
                    // We don't need to do anything here as it begins implicitly
                    break;
                default:
                    throw new RuntimeException("Transaction start not supported for this connection type.");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to start transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Commit a database transaction
     * @throws RuntimeException
     */
    public function commit()
    {
        if (!$this->connection) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        try {
            switch ($this->connectionType) {
                case 'pdo':
                case 'pdo_oci':
                    if ($this->connection->inTransaction()) {
                        $this->connection->commit();
                    }
                    break;
                case 'mysqli':
                case 'mariadb':
                    $this->connection->commit();
                    break;
                case 'codeigniter3':
                case 'codeigniter3_oci':
                    if (method_exists($this->connection, 'trans_complete')) {
                        $this->connection->trans_complete();
                    } else {
                        throw new RuntimeException("Commit not supported.");
                    }
                    break;
                case 'oci':
                case 'oracle':
                case 'oci8':
                    if (function_exists('oci_commit')) {
                        oci_commit($this->connection);
                    }
                    break;
                default:
                    throw new RuntimeException("Commit not supported for this connection type.");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to commit transaction: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Roll back a database transaction
     * @throws RuntimeException
     */
    public function rollback()
    {
        if (!$this->connection) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        try {
            switch ($this->connectionType) {
                case 'pdo':
                case 'pdo_oci':
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                    break;
                case 'mysqli':
                case 'mariadb':
                    $this->connection->rollback();
                    break;
                case 'codeigniter3':
                case 'codeigniter3_oci':
                    if (method_exists($this->connection, 'trans_rollback')) {
                        $this->connection->trans_rollback();
                    } else {
                        throw new RuntimeException("Rollback not supported.");
                    }
                    break;
                case 'oci':
                case 'oracle':
                case 'oci8':
                    if (function_exists('oci_rollback')) {
                        oci_rollback($this->connection);
                    }
                    break;
                default:
                    throw new RuntimeException("Rollback not supported for this connection type.");
            }
        } catch (Throwable $e) {
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
        if (!$this->connection) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        try {
            switch ($this->connectionType) {
                case 'pdo':
                case 'pdo_oci':
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);
                    $this->lastStatement = new DatabaseStatement($stmt, $this->connectionType);
                    break;

                case 'mysqli':
                case 'mariadb':
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

                    $this->lastStatement = new DatabaseStatement($result, $this->connectionType);
                    break;

                case 'codeigniter3':
                case 'codeigniter3_oci':
                    $query = $this->connection->query($sql, $params);
                    if ($query === false) {
                        throw new RuntimeException("Query execution failed: " . $this->connection->error());
                    }
                    $this->lastStatement = new DatabaseStatement($query, $this->connectionType);
                    break;

                case 'oci':
                case 'oracle':
                case 'oci8':
                    $stmt = oci_parse($this->connection, $sql);
                    if ($stmt === false) {
                        throw new RuntimeException("Failed to parse OCI statement");
                    }

                    // Bind parameters if any
                    if (!empty($params)) {
                        foreach ($params as $key => $value) {
                            $paramName = is_numeric($key) ? ':p' . $key : ':' . $key;
                            oci_bind_by_name($stmt, $paramName, $params[$key]);
                        }
                    }

                    // Execute the statement
                    $result = oci_execute($stmt, OCI_DEFAULT); // OCI_DEFAULT to support transactions
                    if (!$result) {
                        $error = oci_error($stmt);
                        throw new RuntimeException("OCI query execution failed: " . ($error ? $error['message'] : 'Unknown error'));
                    }

                    $this->lastStatement = new DatabaseStatement($stmt, 'oci');
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
