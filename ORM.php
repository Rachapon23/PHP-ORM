<?php
class ORM
{
    public static $conn;
    public static $dbname;
    public static $tbname;
    private static $tb_exist = null;
    private static $table = null;
    public static $structure = [];
    public static $structure_tracker = [];
    private static $tracker_state = [
        1 => 1, // cloumn add,
        2 => 2, // cloumn change,
        3 => 3, // cloumn remove,
    ];
    private static $non_active_cloumn = [];

    function __construct($tbname)
    {
        self::$tbname = $tbname;
        self::mk_table($tbname);
    }

    public static function connect($host, $user, $pass, $db, $db_type = 'my_sql',)
    {
        if ($db_type === 'my_sql') {
            self::$conn = new mysqli($host, $user, $pass, $db);
            if (self::$conn->connect_error) {
                die("Connection failed: " . self::$conn->connect_error);
            }
            self::$dbname = $db;
            echo "ORM: Connected successfully<br/>";
        }
    }

    function mk_table($name)
    {
        self::$tb_exist = self::check_table(self::$dbname, $name);
        if (!self::$tb_exist) {
            self::$table = "CREATE TABLE IF NOT EXISTS `$name`(";
        } else {
            $_tbname = self::$tbname;
            $json = file_get_contents("./model/$_tbname.json");
            self::$structure = json_decode($json, true);
            self::$non_active_cloumn = self::$structure;
        }
    }

    function mk_column(string $name, string $data_type): void
    {
        if (!self::$table && !self::$tb_exist) die("mk_table must call first");

        if (!self::$tb_exist) {
            self::$table .= "`$name` $data_type,";
        } else {

            // cloumn is not exist add new one
            echo json_encode(!self::$structure[$name]);
            if (!self::$structure[$name]) {
                self::$structure[$name] = $data_type;
                self::$structure_tracker[$name] = self::$tracker_state[1];
            }

            // cloumn is already exist and data type is changes, change data type to new one
            if (self::$structure[$name] && self::$structure[$name] !== $data_type) {
                self::$structure[$name] = $data_type;
                self::$structure_tracker[$name] = self::$tracker_state[2];
            }

            // column exist remove from non-active-cloumn
            if (self::$structure[$name]) {
                unset(self::$non_active_cloumn[$name]);
            }
        }
    }
    function publish()
    {

        if (!self::$tb_exist) {
            $str_length = strlen(self::$table);
            self::$table[$str_length - 1] = ")";
            self::query(self::$table);
        } else {


            // drop column
            foreach (self::$non_active_cloumn as $col_name => $_) {
                self::$structure_tracker[$col_name] = self::$tracker_state[3];
            }
            echo json_encode(self::$structure_tracker);

            foreach (self::$structure_tracker as $col_name => $state) {

                // current state is not 1 and previous state and current are equal that mean -> this column is no longer use

                switch ($state) {
                    case 1: {
                            $_tablename = self::$tbname;
                            $_datatype = self::$structure[$col_name];
                            self::$table = "ALTER TABLE `$_tablename`
                            ADD `$col_name` $_datatype";
                        }
                        break;
                    case 2: {
                            $_tablename = self::$tbname;
                            $_datatype = self::$structure[$col_name];
                            self::$table = "ALTER TABLE `$_tablename`
                            MODIFY COLUMN `$col_name` $_datatype";
                        }
                        break;
                    case 3: {
                            unset(self::$structure[$col_name]);
                            $_tablename = self::$tbname;
                            $_datatype = self::$structure[$col_name];
                            self::$table = "ALTER TABLE `$_tablename`
                            DROP COLUMN `$col_name`";
                        }
                        break;
                }

                echo json_encode(self::$table);
                self::query(self::$table);
            }
        }



        // clear state
        self::$tb_exist = null;
        // echo "ORM: Create table success";
        echo '<br/>';
        // echo json_encode(self::$structure);


        $_tbname = self::$tbname;
        file_put_contents("./model/$_tbname.json", json_encode(self::$structure));
    }

    function check_table(string $db_name, string $tb_name)
    {
        $sql = "SELECT * 
        FROM information_schema.tables
        WHERE table_schema = ?
        AND table_name = ?
        LIMIT 1";
        $params = [$db_name, $tb_name];
        $rs = self::query($sql, 'ss', $params);
        $row = mysqli_num_rows($rs);
        if ($row > 0) {
            // echo 'FOUND';
            return true;
        }
        // echo 'NOT FOUND';
        return false;
    }

    public static function query($sql, $params_type = "", $params = [])
    {
        $rs = NULL;
        if ($stmt = self::$conn->prepare($sql)) {
            if (strlen($params_type) > 0) {
                $stmt->bind_param($params_type, ...$params);
            }
            $stmt->execute();
            $rs = $stmt->get_result();
            $stmt->close();
        }
        return $rs;
    }

    public static function close_connection()
    {
        self::$conn->close();
    }
}
