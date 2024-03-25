<?php
class DatabaseConnection {
    private function __clone() {}
    public function __wakeup() { throw new Exception("Unserialization of AuthClass instances is not allowed.");}
    private static $conn = null;
    private function __construct() {
        $conf = require '../../conf.php'; 
        self::$conn = new mysqli($conf['mysqlDB']['host'], $conf['mysqlDB']['username'], $conf['mysqlDB']['password'], $conf['mysqlDB']['databaseName']);
        if (self::$conn->connect_error) {
            die("Connection failed: " . self::$conn->connect_error);
        }
    }
    public static function getInstance() {
        if (self::$conn === null) {
            new DatabaseConnection();
        }
        return self::$conn;
    }
}