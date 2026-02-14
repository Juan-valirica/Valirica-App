<?php
$host = 'smtp-relay.gmail.com';
$port = 587;
$timeout = 10;
$fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
if ($fp) {
    echo "OK - Conexión establecida a $host:$port\n";
    fclose($fp);
} else {
    echo "ERROR - No se pudo conectar a $host:$port -> $errno : $errstr\n";
}
