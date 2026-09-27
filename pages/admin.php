<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
header('Location: admin-panel.php');
exit;
?>



