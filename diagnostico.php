<?php
// ==================================
// DIAGNÓSTICO VALIRICA - BORRAR DESPUÉS DE USAR
// ==================================
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO VALIRICA ===\n\n";

echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n\n";

echo "=== EXTENSIONES CRÍTICAS ===\n";
$checks = [
    'mysqli'     => class_exists('mysqli'),
    'mysqlnd'    => function_exists('mysqli_fetch_all'),
    'json'       => function_exists('json_encode'),
    'session'    => function_exists('session_start'),
    'mbstring'   => function_exists('mb_strlen'),
];

foreach ($checks as $name => $ok) {
    echo "  $name: " . ($ok ? "OK" : "FALTA") . "\n";
}

echo "\n=== EXTENSIONES CARGADAS ===\n";
$exts = get_loaded_extensions();
sort($exts);
echo implode(', ', $exts) . "\n";

echo "\n=== TEST MYSQLI ===\n";
if (class_exists('mysqli')) {
    echo "  mysqli class: OK\n";
    echo "  mysqli_stmt class: " . (class_exists('mysqli_stmt') ? 'OK' : 'FALTA') . "\n";

    // Verificar si get_result existe
    try {
        $ref = new ReflectionClass('mysqli_stmt');
        $has_get_result = $ref->hasMethod('get_result');
        echo "  get_result() method: " . ($has_get_result ? 'DISPONIBLE (mysqlnd activo)' : 'NO DISPONIBLE (necesita polyfill)') . "\n";
    } catch (\Throwable $e) {
        echo "  Reflection error: " . $e->getMessage() . "\n";
    }

    // Test de conexión
    echo "\n=== TEST CONEXIÓN DB ===\n";
    try {
        $conn = new mysqli('localhost', 'mevytjyn_webapp_user', 'xydqe7-rycsux-jyBmoq', 'mevytjyn_webapp');
        if ($conn->connect_error) {
            echo "  Conexión: FALLO - " . $conn->connect_error . "\n";
        } else {
            echo "  Conexión: OK\n";
            echo "  Server: " . $conn->server_info . "\n";
            echo "  Charset: " . $conn->character_set_name() . "\n";
            $conn->close();
        }
    } catch (\Throwable $e) {
        echo "  Conexión: EXCEPCIÓN - " . $e->getMessage() . "\n";
    }
} else {
    echo "  mysqli NO DISPONIBLE - esta es la causa del 503\n";
}

echo "\n=== INI SETTINGS ===\n";
echo "  display_errors: " . ini_get('display_errors') . "\n";
echo "  error_reporting: " . ini_get('error_reporting') . "\n";
echo "  error_log: " . (ini_get('error_log') ?: '(default)') . "\n";

echo "\n=== FIN DIAGNÓSTICO ===\n";
echo "IMPORTANTE: Borra este archivo después de usarlo.\n";
