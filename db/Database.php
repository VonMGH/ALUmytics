<?php
class Database {
    private static $instance;
    private $conn;

    private function __construct() {
        // Database connection details
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'alumytics';

        $this->conn = new mysqli($host, $username, $password, $database);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        $this->conn->query("SET time_zone = '+08:00'");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?> 