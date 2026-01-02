<?php
echo "Current Dir: " . getcwd() . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "Check: " . __DIR__ . "/../../../main.inc.php\n";
if (file_exists(__DIR__ . "/../../../main.inc.php")) {
    echo "EXISTS 3 up\n";
}
if (file_exists(__DIR__ . "/../../../../main.inc.php")) {
    echo "EXISTS 4 up\n";
}
