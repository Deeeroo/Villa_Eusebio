<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: owner.php');
    exit;
}

header('Location: admin-panel.php');
exit;
?>
