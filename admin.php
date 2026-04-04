<?php
session_start();
if (!empty($_SESSION['admin_user'])) {
    header('Location: admin_orders.php');
    exit;
}
header('Location: login.php');
exit;
