<?php
// test-session.php
session_start();
header('Content-Type: text/plain');

echo "=== TEST SESSION ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Cookie PHPSESSID: " . ($_COOKIE['PHPSESSID'] ?? 'NON DÉFINI') . "\n";
echo "Session panier: " . print_r($_SESSION['panier'] ?? [], true) . "\n";

// Ajouter un test
if (!isset($_SESSION['test_time'])) {
    $_SESSION['test_time'] = date('H:i:s');
}
echo "Test time: " . $_SESSION['test_time'] . "\n";
?>