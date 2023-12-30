<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

include 'ORM.php';
ORM::connect($servername, $username, $password, $dbname);

$user = new ORM('user2');
$user->mk_column('code', 'VARCHAR(100)');
$user->mk_column('name', 'VARCHAR(200)');
$user->mk_column('password', 'VARCHAR(200)');
// $user->mk_column('password2', 'VARCHAR(100)');
$user->publish();
// $user->check_table('User');

ORM::close_connection();
?>