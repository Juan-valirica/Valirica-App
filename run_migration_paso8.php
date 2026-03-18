<?php
/**
 * run_migration_paso8.php — Añade canal_slug a complaint_channel_config
 * EJECUTAR UNA SOLA VEZ y luego ELIMINAR este archivo.
 */
require_once __DIR__ . '/config.php';

$migrations = [
    "paso8_canal_slug" => "
        ALTER TABLE complaint_channel_config
            ADD COLUMN canal_slug VARCHAR(60) NULL UNIQUE AFTER company_id,
            ADD INDEX idx_canal_slug (canal_slug)
    ",
];

echo '<pre style="font-family:monospace;font-size:14px;padding:24px;">';
echo "=== Migración Paso 8 — canal_slug ===\n\n";

foreach ($migrations as $name => $sql) {
    $sql = trim($sql);
    $result = $conn->query($sql);
    if ($result) {
        echo "✅ [{$name}] OK\n";
    } else {
        $errno = $conn->errno;
        if (in_array($errno, [1060, 1061, 1062, 1050, 1091], true)) {
            echo "⚠️  [{$name}] Ya existía (errno {$errno}) — saltando\n";
        } else {
            echo "❌ [{$name}] ERROR {$errno}: " . $conn->error . "\n";
        }
    }
}

echo "\n=== Listo. Elimina este archivo ahora. ===\n";
echo '</pre>';
