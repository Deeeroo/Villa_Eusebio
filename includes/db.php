<?php

$conn = mysqli_connect("localhost", "root", "", "villa_eusebio_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>