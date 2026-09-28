<?php
require_once "../includes/admin_auth.php";
admin_require_post_csrf();
admin_end_session();

header("Location: ../pages/owner.php");
exit;
?>
