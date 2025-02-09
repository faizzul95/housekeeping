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
                return $this->statement instanceof \PDOStatement ? $this->statement->fetch(\PDO::FETCH_ASSOC) : null;

            case 'mysqli':
                return ($this->statement instanceof \mysqli_result) ? $this->statement->fetch_assoc() : null;

            case 'codeigniter3':
                return method_exists($this->statement, 'unbuffered_row') ? $this->statement->unbuffered_row('array') : null;

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
                return ($this->statement instanceof \PDOStatement) ? $this->statement->rowCount() : 0;

            case 'mysqli':
                if ($this->statement instanceof \mysqli_result) {
                    return $this->statement->num_rows; // For SELECT queries
                } elseif ($this->statement instanceof \mysqli_stmt) {
                    return $this->statement->affected_rows; // For INSERT/UPDATE/DELETE queries
                }
                return 0;

            case 'codeigniter3':
                return method_exists($this->statement, 'num_rows') ? $this->statement->num_rows() : 0;

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
                if ($this->statement instanceof \PDOStatement) {
                    $this->statement->closeCursor();
                }
                break;

            case 'mysqli':
                if ($this->statement instanceof \mysqli_result) {
                    $this->statement->free();
                }
                break;

            case 'codeigniter3':
                if (method_exists($this->statement, 'free_result')) {
                    $this->statement->free_result();
                }
                break;
        }

        $this->statement = null; // Explicitly free the reference
    }
}
