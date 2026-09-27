<?php
require_once "../includes/admin_auth.php";
admin_end_session();

header("Location: ../pages/owner.php");
exit;
?>
