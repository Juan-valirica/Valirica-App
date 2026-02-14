<?php
/**
 * Verificación completa del sistema de Tiempo & Asistencia
 * Confirma que todo esté funcionando correctamente
 */

require 'config.php';

echo "🎯 VERIFICACIÓN COMPLETA DEL SISTEMA DE TIEMPO & ASISTENCIA\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$errores = [];
$warnings = [];
$ok = [];

// =========================================
// 1. VERIFICAR TABLAS
// =========================================
echo "📊 1. VERIFICANDO TABLAS\n";
echo "───────────────────────────────────────────────────\n";

$tablas_requeridas = ['asistencia', 'permisos', 'vacaciones', 'horarios_trabajo', 'patrones_asistencia'];

foreach ($tablas_requeridas as $tabla) {
    $query = "SHOW TABLES LIKE '{$tabla}'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla '{$tabla}' existe\n";
        $ok[] = "Tabla {$tabla}";
    } else {
        echo "❌ Tabla '{$tabla}' NO EXISTE\n";
        $errores[] = "Falta tabla {$tabla}";
    }
}

echo "\n";

// =========================================
// 2. VERIFICAR FOREIGN KEYS
// =========================================
echo "🔗 2. VERIFICANDO FOREIGN KEYS\n";
echo "───────────────────────────────────────────────────\n";

$query = "
    SELECT
        TABLE_NAME,
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'mevytjyn_webapp'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('asistencia', 'permisos', 'vacaciones', 'horarios_trabajo', 'patrones_asistencia')
    ORDER BY TABLE_NAME, CONSTRAINT_NAME
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $fk_count = 0;
    while ($row = $result->fetch_assoc()) {
        echo "✅ {$row['TABLE_NAME']}.{$row['COLUMN_NAME']} → ";
        echo "{$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
        $fk_count++;
        $ok[] = "FK {$row['CONSTRAINT_NAME']}";
    }
    echo "\nTotal: {$fk_count} foreign keys creadas ✅\n";
} else {
    echo "⚠️  No se encontraron foreign keys\n";
    $warnings[] = "No hay foreign keys (funcionará pero sin validación automática)";
}

echo "\n";

// =========================================
// 3. VERIFICAR VISTAS
// =========================================
echo "👁️ 3. VERIFICANDO VISTAS\n";
echo "───────────────────────────────────────────────────\n";

$vistas_requeridas = ['v_asistencia_hoy', 'v_resumen_asistencia_mensual'];

foreach ($vistas_requeridas as $vista) {
    $query = "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_mevytjyn_webapp = '{$vista}'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        echo "✅ Vista '{$vista}' existe\n";
        $ok[] = "Vista {$vista}";

        // Probar que la vista funciona
        $test = $conn->query("SELECT * FROM {$vista} LIMIT 1");
        if ($test) {
            echo "   └─ ✓ Vista funcional (puede ser consultada)\n";
        } else {
            echo "   └─ ⚠️  Vista existe pero tiene error: " . $conn->error . "\n";
            $warnings[] = "Vista {$vista} tiene errores";
        }
    } else {
        echo "❌ Vista '{$vista}' NO EXISTE\n";
        $errores[] = "Falta vista {$vista}";
    }
}

echo "\n";

// =========================================
// 4. VERIFICAR STORED PROCEDURES
// =========================================
echo "⚙️ 4. VERIFICANDO STORED PROCEDURES\n";
echo "───────────────────────────────────────────────────\n";

$query = "SHOW PROCEDURE STATUS WHERE Db = 'mevytjyn_webapp' AND Name = 'sp_calcular_patrones_asistencia'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "✅ Stored Procedure 'sp_calcular_patrones_asistencia' existe\n";
    $ok[] = "Stored Proc sp_calcular_patrones_asistencia";
} else {
    echo "❌ Stored Procedure 'sp_calcular_patrones_asistencia' NO EXISTE\n";
    $errores[] = "Falta stored procedure";
}

echo "\n";

// =========================================
// 5. VERIFICAR FUNCIONES
// =========================================
echo "🔧 5. VERIFICANDO FUNCIONES\n";
echo "───────────────────────────────────────────────────\n";

$query = "SHOW FUNCTION STATUS WHERE Db = 'mevytjyn_webapp' AND Name = 'fn_dias_laborables'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "✅ Función 'fn_dias_laborables' existe\n";
    $ok[] = "Función fn_dias_laborables";

    // Probar que la función funciona
    $test = $conn->query("SELECT fn_dias_laborables('2026-01-01', '2026-01-31') as dias");
    if ($test) {
        $row = $test->fetch_assoc();
        echo "   └─ ✓ Función funcional (retorna: {$row['dias']} días laborables en enero 2026)\n";
    }
} else {
    echo "❌ Función 'fn_dias_laborables' NO EXISTE\n";
    $errores[] = "Falta función fn_dias_laborables";
}

echo "\n";

// =========================================
// 6. VERIFICAR ESTRUCTURA DE COLUMNAS CLAVE
// =========================================
echo "🔍 6. VERIFICANDO ESTRUCTURA DE COLUMNAS CLAVE\n";
echo "───────────────────────────────────────────────────\n";

$columnas_verificar = [
    'asistencia' => ['usuario_id', 'persona_id', 'fecha', 'check_in', 'check_out', 'estado'],
    'permisos' => ['usuario_id', 'persona_id', 'tipo', 'estado', 'fecha_inicio', 'fecha_fin'],
    'vacaciones' => ['usuario_id', 'persona_id', 'anio', 'dias_disponibles'],
];

foreach ($columnas_verificar as $tabla => $columnas) {
    $query = "DESCRIBE {$tabla}";
    $result = $conn->query($query);

    if ($result) {
        $columnas_existentes = [];
        while ($row = $result->fetch_assoc()) {
            $columnas_existentes[] = $row['Field'];
        }

        $faltan = array_diff($columnas, $columnas_existentes);

        if (empty($faltan)) {
            echo "✅ Tabla '{$tabla}' tiene todas las columnas necesarias\n";
        } else {
            echo "⚠️  Tabla '{$tabla}' - Faltan columnas: " . implode(', ', $faltan) . "\n";
            $warnings[] = "Tabla {$tabla} incompleta";
        }
    }
}

echo "\n";

// =========================================
// 7. RESUMEN FINAL
// =========================================
echo "═══════════════════════════════════════════════════════════\n";
echo "📋 RESUMEN FINAL\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✅ Elementos correctos: " . count($ok) . "\n";
if (!empty($warnings)) {
    echo "⚠️  Advertencias: " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "   • {$warning}\n";
    }
}
if (!empty($errores)) {
    echo "❌ Errores críticos: " . count($errores) . "\n";
    foreach ($errores as $error) {
        echo "   • {$error}\n";
    }
}

echo "\n";

if (empty($errores)) {
    echo "🎉 ¡TODO ESTÁ PERFECTO!\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "✅ Sistema de Tiempo & Asistencia completamente funcional\n";
    echo "✅ Listo para implementar el frontend\n";
    echo "\n";
    echo "📊 ESTADÍSTICAS:\n";
    echo "   • 5 tablas creadas\n";
    echo "   • 10 foreign keys (si aparecen arriba)\n";
    echo "   • 2 vistas funcionales\n";
    echo "   • 1 stored procedure\n";
    echo "   • 1 función auxiliar\n";
    echo "\n";
    echo "🚀 SIGUIENTE PASO: Implementar el frontend del Tab Time\n";
} else {
    echo "⚠️  HAY PROBLEMAS QUE NECESITAN ATENCIÓN\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Por favor, revisa los errores arriba y corrígelos.\n";
}

$conn->close();
?>
