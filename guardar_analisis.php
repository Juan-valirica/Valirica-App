<?php
session_start();
require 'config.php';
error_log("Contenido de POST: " . print_r($_POST, true));

// Activar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Operación no permitida. Token CSRF inválido.");
    }

    $user_id = intval($_POST["user_id"]);
    $conn->begin_transaction();

    try {
        error_log("Iniciando actualización de valores de marca");

      

        // ✅ CREAR UNA NUEVA FILA EN analisis_proposito PARA CADA USUARIO
        error_log("Guardando nueva entrada en analisis_proposito...");

$interno_externo = intval($_POST["interno_externo"]);
$social_economico = intval($_POST["social_economico"]);
$corto_largo = intval($_POST["corto_largo"]);
$tangible_intangible = intval($_POST["tangible_intangible"]);
$estabilidad_innovacion = intval($_POST["estabilidad_innovacion"]);

$exp_interno_externo = $conn->real_escape_string($_POST["exp_interno_externo"]);

$exp_social_economico = $conn->real_escape_string($_POST["exp_social_economico"]);

$exp_corto_largo = $conn->real_escape_string($_POST["exp_corto_largo"]);

$exp_tangible_intangible = $conn->real_escape_string($_POST["exp_tangible_intangible"]);

$exp_estabilidad_innovacion = $conn->real_escape_string($_POST["exp_estabilidad_innovacion"]);

$incoherencias = str_replace(["\r\n", "\r"], "\n", trim($_POST["incoherencias"]));
$sugerencias = str_replace(["\r\n", "\r"], "\n", trim($_POST["sugerencias"]));


       // ✅ Insertar correctamente los valores en analisis_proposito
$stmt = $conn->prepare("INSERT INTO analisis_proposito 
    (usuario_id, interno_externo, social_economico, corto_largo, tangible_intangible, estabilidad_innovacion, 
    exp_interno_externo, exp_social_economico, exp_corto_largo, exp_tangible_intangible, exp_estabilidad_innovacion, 
    incoherencias, sugerencias) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("iiiiiiissssss", $user_id, $interno_externo, $social_economico, $corto_largo, 
    $tangible_intangible, $estabilidad_innovacion, $exp_interno_externo, 
    $exp_social_economico, $exp_corto_largo, $exp_tangible_intangible, $exp_estabilidad_innovacion, 
    $incoherencias, $sugerencias);

if (!$stmt->execute()) {
    error_log("⚠️ Error en INSERT de analisis_proposito: " . $stmt->error);
}
$stmt->close();

        $conn->commit();
        error_log("Transacción completada correctamente");

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction error: " . $e->getMessage());
        die('Transaction error: ' . $e->getMessage());
    }

    header("Location: ver_analisis.php?user_id=" . $user_id . "&status=success");
    exit;
}
?>

