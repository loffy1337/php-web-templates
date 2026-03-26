<?php
/*
 ___      _______  _______  _______  __   __        ____   _______  _______  _______ 
|   |    |       ||       ||       ||  | |  |      |    | |       ||       ||       |
|   |    |   _   ||    ___||    ___||  |_|  |       |   | |___    ||___    ||___    |
|   |    |  | |  ||   |___ |   |___ |       |       |   |  ___|   | ___|   |    |   |
|   |___ |  |_|  ||    ___||    ___||_     _| ___   |   | |___    ||___    |    |   |
|       ||       ||   |    |   |      |   |  |   |  |   |  ___|   | ___|   |    |   |
|_______||_______||___|    |___|      |___|  |___|  |___| |_______||_______|    |___|
*/

class MySQLDatabase {
    private $db, $err;
    private $database, $username, $password, $host, $port;

    // Функция для открытия соединения с базой данных
    function __construct($database, $username, $password, $host="localhost", $port=3306) {
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->db = @mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port);
        if ($this->db) {
            @mysqli_set_charset($this->db, "utf8mb4");
        }
    }

    // Функция для закрытия соединения с базой данных
    function __destruct() {
        if ($this->db instanceof mysqli) {
            mysqli_close($this->db);
        }
    }

    // Функция для получения последней ошибки базы данных
    public function get_error() {
        $err = $this->err;
        $this->err = null;
        return $err;
    }

    // Функция для выполнения произвольного SQL-запроса (значения параметров должны передаваться, только через params)
    public function query($sql, $params = []) {
        return $this->execute($sql, $params);
    }

    // Функция для получения данных из таблицы
    public function select($table, $columns = "*", $where = "", $params = []) {
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        if (is_array($columns)) {
            $columns = $this->secure_identifier_list($columns);
            if ($columns == false) {
                return false;
            }
        } elseif ($columns != "*") {
            return false;
        }
        $sql = "select " . $columns . " from " . $table;
        if (!empty($where)) {
            $sql .= " where " . $where;
        }
        return $this->execute($sql, $params);
    }

    // Функция для получения первой записи из таблицы
    public function get_row($table, $columns = "*", $where = "", $params = []) {
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        if (is_array($columns)) {
            $columns = $this->secure_identifier_list($columns);
            if ($columns == false) {
                return false;
            }
        } elseif ($columns != "*") {
            return false;
        }
        $sql = "select " . $columns . " from " . $table;
        if (!empty($where)) {
            $sql .= " where " . $where;
        }
        $sql .= " limit 1";
        $result = $this->execute($sql, $params);
        if (is_array($result) && count($result) > 0) {
            return $result[0];
        }
        return false;
    }

    // Функция для получения первого значения из таблицы
    public function get_value($table, $column, $where = "", $params = []) {
        $result = $this->get_row($table, $column, $where, $params);
        if (is_array($result) && count($result) > 0) {
            return array_values($result)[0];
        }
        return false;
    }

    // Функция для вставки данных в таблицу
    public function insert($table, $data) {
        if (!is_array($data) || empty($data)) {
            return false;
        }
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        $columns = $this->secure_identifier_list(array_keys($data));
        if ($columns == false) {
            return false;
        }
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "insert into " . $table . " (" . $columns . ") values (" . $placeholders . ")";
        return $this->execute($sql, array_values($data));
    }

    // Функция для обновления данных в таблице
    public function update($table, $data, $where = "", $params = []) {
        if (!is_array($data) || empty($data)) {
            return false;
        }
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        $set = [];
        foreach ($data as $column => $value) {
            $secure_column = $this->secure_identifier($column);
            if ($secure_column == false) {
                return false;
            }
            $set[] = $secure_column . " = ?";
        }
        $sql = "update " . $table . " set " . implode(", ", $set);
        if (!empty($where)) {
            $sql .= " where " . $where;
        }
        return $this->execute($sql, array_merge(array_values($data), $params));
    }

    // Функция для удаления данных из таблицы
    public function delete($table, $where, $params = []) {
        if ($where == "") {
            return false;
        }
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        $sql = "delete from " . $table . " where " . $where;
        return $this->execute($sql, $params);
    }

    // Функция для очистки таблицы
    public function truncate($table) {
        $table = $this->secure_identifier($table);
        if ($table == false) {
            return false;
        }
        $sql = "truncate table " . $table;
        return $this->execute($sql);
    }

    // Функция для проверки соединения с базой данных и его восстановления при необходимости
    private function check_connection() {
        if ($this->db && @mysqli_query($this->db, "SELECT 1") != false) {
            return true;
        }
        $this->db = @mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port);
        if ($this->db) {
            @mysqli_set_charset($this->db, "utf8mb4");
            return true;
        }
        return false;
    }

    // Функция для выполнения SQL-запроса
    private function execute($sql, $params = []) {
        $check = $this->check_connection();
        if ($check == false) {
            $this->err = "Unable to connect to the database.";
            return false;
        }
        $stmt = @mysqli_prepare($this->db, $sql);
        if ($stmt === false) {
            return false;
        }
        if (!empty($params)) {
            $types = $this->secure_types($params);
            $bind = array_merge([$stmt, $types], $this->ref_values($params));
            if (!@call_user_func_array("mysqli_stmt_bind_param", $bind)) {
                $this->err = mysqli_stmt_error($stmt);
                @mysqli_stmt_close($stmt);
                return false;
            }
        }
        if (!@mysqli_stmt_execute($stmt)) {
            $this->err = mysqli_stmt_error($stmt);
            @mysqli_stmt_close($stmt);
            return false;
        }
        $result = @mysqli_stmt_get_result($stmt);
        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = @mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
            @mysqli_stmt_close($stmt);
            return $rows;
        }
        $affected = @mysqli_stmt_affected_rows($stmt);
        $id = @mysqli_stmt_insert_id($stmt);
        @mysqli_stmt_close($stmt);
        return $id != 0 ? $id : $affected;
    }

    // Функция для проверки валидности имени таблицы и имени столбца
    private function is_valid_identifier($name) {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) == 1;
    }

    // Функция для безопасного экранирования имени таблицы и имени столбца
    private function secure_identifier($name) {
        if ($this->is_valid_identifier($name) == false) {
            return false;
        }
        return "`$name`";
    }

    // Функция для безопасного экранирования списка имен таблиц и имен столбцов
    private function secure_identifier_list($names) {
        $result = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                return false;
            }
            $secure = $this->secure_identifier($name);
            if ($secure == false) {
                return false;
            }
            $result[] = $secure;
        }
        return implode(", ", $result);
    }

    // Функция для определения типов параметров для bind_param и их безопасного преобразования
    private function secure_types(&$params) {
        $types = "";
        foreach ($params as $idx => $value) {
            if (is_bool($value)) {
                $types .= "i";
                $params[$idx] = $value ? 1 : 0;
            } elseif (is_int($value)) {
                $types .= "i";
            } elseif (is_float($value)) {
                $types .= "d";
            } else {
                $types .= "s";
            }
        }
        return $types;
    }

    // Функция для получения массива ссылок на значения параметров для bind_param
    private function ref_values(&$values) {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }
        return $refs;
    }
}