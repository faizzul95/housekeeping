<?php

namespace OnlyPHP\Housekeeping\Connections;

class DatabaseStatement
{
    private $statement;
    private $type;
    private $affectedRows;

    public function __construct($statement, $type)
    {
        $this->statement = $statement;
        $this->type = $type;
        $this->affectedRows = $this->calculateAffectedRows();
    }

    /**
     * Fetch the next row from the result set
     * @return array|null
     */
    public function fetch()
    {
        if (!$this->statement) {
            return null;
        }

        switch ($this->type) {
            case 'pdo':
            case 'pdo_oci':
                return $this->statement instanceof \PDOStatement ? $this->statement->fetch(\PDO::FETCH_ASSOC) : null;

            case 'mysqli':
            case 'mariadb':
                return ($this->statement instanceof \mysqli_result) ? $this->statement->fetch_assoc() : null;

            case 'codeigniter3':
            case 'codeigniter3_oci':
                return method_exists($this->statement, 'unbuffered_row') ? $this->statement->unbuffered_row('array') : null;

            case 'oci':
            case 'oracle':
            case 'oci8':
                // OCIStatement fetch_assoc equivalent
                if (function_exists('oci_fetch_assoc') && is_resource($this->statement)) {
                    return oci_fetch_assoc($this->statement);
                }
                return null;

            case 'mariadb':
                // MariaDB uses the same interface as mysqli
                return ($this->statement instanceof \mysqli_result) ? $this->statement->fetch_assoc() : null;

            default:
                return null;
        }
    }

    /**
     * Fetch all remaining rows as an array of associative arrays
     * @return array
     */
    public function fetch_all()
    {
        $rows = [];
        while ($row = $this->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch a single row as an associative array
     * @return array|null
     */
    public function row_array()
    {
        if (!$this->statement) {
            return null;
        }

        switch ($this->type) {
            case 'pdo':
            case 'pdo_oci':
                return $this->statement instanceof \PDOStatement ?
                    $this->statement->fetch(\PDO::FETCH_ASSOC) : null;

            case 'mysqli':
            case 'mariadb':
                if ($this->statement instanceof \mysqli_result) {
                    $row = $this->statement->fetch_assoc();
                    // Reset the pointer for potential future fetches
                    if ($row && $this->statement->num_rows > 1) {
                        $this->statement->data_seek(0);
                    }
                    return $row;
                }
                return null;

            case 'codeigniter3':
            case 'codeigniter3_oci':
                return method_exists($this->statement, 'row_array') ?
                    $this->statement->row_array() : null;

            case 'oci':
            case 'oracle':
            case 'oci8':
                if (function_exists('oci_fetch_assoc') && is_resource($this->statement)) {
                    // Get the current position
                    $currentRow = oci_fetch_row($this->statement);
                    if (!$currentRow) {
                        return null;
                    }

                    // Reset to first row
                    oci_execute($this->statement, OCI_DEFAULT);

                    // Fetch as associative array
                    $row = oci_fetch_assoc($this->statement);

                    return $row;
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Get the number of affected rows
     * @return int
     */
    public function rowCount()
    {
        return $this->affectedRows;
    }

    /**
     * Calculate the number of affected rows based on connection type
     * @return int
     */
    private function calculateAffectedRows()
    {
        if (!$this->statement) {
            return 0;
        }

        switch ($this->type) {
            case 'pdo':
            case 'pdo_oci':
                return ($this->statement instanceof \PDOStatement) ? $this->statement->rowCount() : 0;

            case 'mysqli':
            case 'mariadb':
                if ($this->statement instanceof \mysqli_result) {
                    return $this->statement->num_rows; // For SELECT queries
                } elseif ($this->statement instanceof \mysqli_stmt) {
                    return $this->statement->affected_rows; // For INSERT/UPDATE/DELETE queries
                }
                return 0;

            case 'codeigniter3':
            case 'codeigniter3_oci':
                return method_exists($this->statement, 'num_rows') ? $this->statement->num_rows() : 0;

            case 'oci':
            case 'oracle':
            case 'oci8':
                if (is_resource($this->statement) && function_exists('oci_num_rows')) {
                    return oci_num_rows($this->statement);
                }
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Free the result set
     */
    public function free()
    {
        if (!$this->statement) {
            return;
        }

        switch ($this->type) {
            case 'pdo':
            case 'pdo_oci':
                if ($this->statement instanceof \PDOStatement) {
                    $this->statement->closeCursor();
                }
                break;

            case 'mysqli':
            case 'mariadb':
                if ($this->statement instanceof \mysqli_result) {
                    $this->statement->free();
                }
                break;

            case 'codeigniter3':
            case 'codeigniter3_oci':
                if (method_exists($this->statement, 'free_result')) {
                    $this->statement->free_result();
                }
                break;

            case 'oci':
            case 'oracle':
            case 'oci8':
                if (is_resource($this->statement) && function_exists('oci_free_statement')) {
                    oci_free_statement($this->statement);
                }
                break;
        }

        $this->statement = null; // Explicitly free the reference
    }
}
