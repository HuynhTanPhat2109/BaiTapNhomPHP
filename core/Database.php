<?php

class Database
{
    private PDO $connection;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";

        $this->connection = new PDO(
            $dsn,
            $config['username'],
            $config['password']
        );

        $this->connection->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
?>