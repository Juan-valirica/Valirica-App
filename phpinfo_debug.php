<?php
// Archivo temporal de diagnóstico - BORRAR después de usar
echo "<h2>PHP Version: " . phpversion() . "</h2>";

echo "<h3>mbstring</h3>";
if (extension_loaded('mbstring')) {
    echo "<p style='color:green;font-weight:bold;'>mbstring CARGADA</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>mbstring NO CARGADA</p>";
}

echo "<h3>mysqli</h3>";
if (extension_loaded('mysqli')) {
    echo "<p style='color:green;font-weight:bold;'>mysqli CARGADA</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>mysqli NO CARGADA</p>";
}

echo "<h3>Todas las extensiones cargadas:</h3>";
$exts = get_loaded_extensions();
sort($exts);
echo "<pre>" . implode("\n", $exts) . "</pre>";

echo "<h3>php.ini en uso:</h3>";
echo "<pre>" . php_ini_loaded_file() . "</pre>";

echo "<h3>Archivos .ini adicionales:</h3>";
echo "<pre>" . php_ini_scanned_dir() . "</pre>";

// phpinfo completo por si necesitamos más detalle
echo "<hr><h2>phpinfo() completo</h2>";
phpinfo();
