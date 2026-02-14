<?php
/**
 * SCRIPT DE DIAGNÓSTICO - Estructura de metas_personales
 * Este script verifica la estructura real de la tabla y ayuda a diagnosticar errores
 */

session_start();
require 'config.php';

header('Content-Type: application/json');

try {
    // 1. Verificar estructura de metas_personales
    $result = $conn->query("SHOW COLUMNS FROM metas_personales");

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = [
            'Field' => $row['Field'],
            'Type' => $row['Type'],
            'Null' => $row['Null'],
            'Key' => $row['Key'],
            'Default' => $row['Default']
        ];
    }

    // 2. Verificar estructura de metas (para comparar)
    $result2 = $conn->query("SHOW COLUMNS FROM metas");

    $metasColumns = [];
    while ($row = $result2->fetch_assoc()) {
        $metasColumns[] = [
            'Field' => $row['Field'],
            'Type' => $row['Type']
        ];
    }

    // 3. Obtener un registro de ejemplo de metas con tipo='area'
    $sample = $conn->query("
        SELECT id, user_id, tipo, area_id, descripcion
        FROM metas
        WHERE tipo = 'area'
        LIMIT 1
    ");
    $sampleMeta = $sample->fetch_assoc();

    // 4. Verificar si existe area_id en equipo
    $equipoCheck = $conn->query("SHOW COLUMNS FROM equipo LIKE 'area_id'");
    $hasAreaIdInEquipo = $equipoCheck->num_rows > 0;

    echo json_encode([
        'ok' => true,
        'metas_personales_columns' => $columns,
        'metas_columns' => $metasColumns,
        'sample_area_meta' => $sampleMeta,
        'equipo_has_area_id' => $hasAreaIdInEquipo,
        'diagnostic' => [
            'has_area_id_in_metas_personales' => in_array('area_id', array_column($columns, 'Field')),
            'has_meta_empresa_id' => in_array('meta_empresa_id', array_column($columns, 'Field')),
            'has_meta_area_id' => in_array('meta_area_id', array_column($columns, 'Field')),
            'has_persona_id' => in_array('persona_id', array_column($columns, 'Field'))
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
