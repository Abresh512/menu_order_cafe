<?php
// Simple test to check PHP functionality
session_start();

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadJson($path) {
    $content = @file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function normalizePhone($phone) {
    return preg_replace('/[^0-9]/', '', (string)$phone);
}

// Test basic functionality
echo "PHP is working!";

// Test file loading
$orders = loadJson('orders.json');
echo "<br>Orders loaded: " . count($orders) . " items";

// Test menu loading
$menu = loadJson('menu.json');
echo "<br>Menu loaded: " . count($menu) . " items";
?>
