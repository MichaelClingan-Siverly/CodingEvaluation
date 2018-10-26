<?php
//Put this in its own file so any other user won't have to dig around much to change this
$host = "localhost";
$user = "root";
$pass = "4AdClear";
$schema = "eval_schema";
$port = 3306;
$link = mysqli_connect($host, $user, $pass, $schema, $port) or die("Could not connect to database");
?>