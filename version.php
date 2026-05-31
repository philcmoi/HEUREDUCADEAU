<?php
echo "=== VERSION DES FICHIERS ===\n\n";
echo "index.php : " . date("Y-m-d H:i:s", filemtime("index.php")) . "\n";
echo "catalogue.php : " . date("Y-m-d H:i:s", filemtime("catalogue.php")) . "\n";
echo "session_verification.php : " . date("Y-m-d H:i:s", filemtime("session_verification.php")) . "\n";
echo "admin_protection.php : " . date("Y-m-d H:i:s", filemtime("admin_protection.php")) . "\n";
?>