<?php

namespace GraceChurch;

class DatabaseConnect {

    private $conn;

    function __construct() {
    }

    /**
     * Establishing database connection
     * @return database connection handler
     */
    function connect($db_name) {

        // Connecting to mysql database
        $this->conn = new \mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, $db_name);

        // Check for database connection error
        if (mysqli_connect_errno()) {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }

        // returing connection resource
        return $this->conn;
    }

}

?>
