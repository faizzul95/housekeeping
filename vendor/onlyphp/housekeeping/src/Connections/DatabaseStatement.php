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
        switch ($this->type) {
            case 'pdo':
                return $this->statement->fetch(\PDO::FETCH_ASSOC);

            case 'mysqli':
                if ($this->statement instanceof \mysqli_result) {
                    return $this->statement->fetch_assoc();
                }
                return null;

            case 'codeigniter3':
                return $this->statement->unbuffered_row('array');

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
        switch ($this->type) {
            case 'pdo':
                return $this->statement->rowCount();

            case 'mysqli':
                if ($this->statement instanceof \mysqli_result) {
                    return $this->statement->num_rows;
                }
                return $this->statement->affected_rows ?? 0;

            case 'codeigniter3':
                return $this->statement->num_rows();

            default:
                return 0;
        }
    }

    /**
     * Free the result set
     */
    public function free()
    {
        switch ($this->type) {
            case 'pdo':
                $this->statement->closeCursor();
                break;

            case 'mysqli':
                if ($this->statement instanceof \mysqli_result) {
                    $this->statement->free();
                }
                break;

            case 'codeigniter3':
                $this->statement->free_result();
                break;
        }
    }
}