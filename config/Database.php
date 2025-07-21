<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? 'messaging_system';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            try {
                // For XAMPP on macOS, use the socket path
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4;unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock";
                $this->pdo = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 10,
                ]);
            } catch (PDOException $e) {
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }
        
        return $this->pdo;
    }
}