<?php
/**
 * Script de verificación de estructura de tablas existentes
 * Para diagnosticar problemas de compatibilidad
 */

require 'config.php';

echo "🔍 VERIFICANDO ESTRUCTURA DE TABLAS EXISTENTES\n";
echo "═══════════════════════════════════════════════════\n\n";

// Tablas a verificar
$tablas_verificar = ['usuarios', 'equipo'];

foreach ($tablas_verificar as $tabla) {
    echo "📊 Tabla: {$tabla}\n";
    echo "─────────────────────────────────────────────────\n";

    $query = "DESCRIBE {$tabla}";
    $result = $conn->query($query);

    if ($result) {
        echo sprintf("%-20s %-20s %-10s %-10s %-10s\n", "Campo", "Tipo", "Null", "Key", "Extra");
        echo str_repeat("─", 80) . "\n";

        while ($row = $result->fetch_assoc()) {
            echo sprintf(
                "%-20s %-20s %-10s %-10s %-10s\n",
                $row['Field'],
                $row['Type'],
                $row['Null'],
                $row['Key'],
                $row['Extra']
            );
        }
        echo "\n";
    } else {
        echo "❌ Error al obtener estructura: " . $conn->error . "\n\n";
    }
}

// Verificar foreign keys existentes
echo "🔗 FOREIGN KEYS EXISTENTES\n";
echo "─────────────────────────────────────────────────\n";

$query = "
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'mevytjyn_webapp'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('usuarios', 'equipo', 'areas_trabajo', 'metas', 'metas_personales', 'cultura_ideal')
    ORDER BY TABLE_NAME, CONSTRAINT_NAME
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "• {$row['TABLE_NAME']}.{$row['COLUMN_NAME']} → ";
        echo "{$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
        echo "  (FK: {$row['CONSTRAINT_NAME']})\n\n";
    }
} else {
    echo "No se encontraron foreign keys o hubo un error.\n\n";
}

// Verificar si ya existe la tabla asistencia
echo "📋 VERIFICANDO SI YA EXISTEN TABLAS DE ASISTENCIA\n";
echo "─────────────────────────────────────────────────\n";

$tablas_asistencia = ['asistencia', 'permisos', 'vacaciones', 'horarios_trabajo', 'patrones_asistencia'];

foreach ($tablas_asistencia as $tabla) {
    $query = "SHOW TABLES LIKE '{$tabla}'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        echo "⚠️  Tabla '{$tabla}' YA EXISTE\n";
    } else {
        echo "✅ Tabla '{$tabla}' no existe (lista para crear)\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════\n";
echo "✅ Verificación completa\n";

$conn->close();
?>
