<?php

class Database
{
    private $host = "localhost";
    private $schema = "arc_test_db";
    private $username = "root";
    private $password = "";
    private $conn;

    public function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->schema};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Enables exceptions for errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch results as an associative array
                PDO::ATTR_EMULATE_PREPARES => false // Use real prepared statements
            ]);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }

    public function execute($sql, $params = [])
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die("Query failed: " . $e->getMessage());
        }
    }
}
