<?php
$it = new RecursiveDirectoryIterator('/var/www/html');
foreach(new RecursiveIteratorIterator($it) as $file) {
    if (basename($file) == 'whatsapp_chat.php') {
        echo $file . "\n";
    }
}
