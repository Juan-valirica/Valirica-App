<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cultura'])) {
    $user_id = $_SESSION['user_id'];
    $cultura = $conn->real_escape_string($_POST['cultura']);

    $stmt = $conn->prepare("UPDATE usuarios SET cultura_empresa_tipo = ? WHERE id = ?");
    $stmt->bind_param("si", $cultura, $user_id);
    if ($stmt->execute()) {
        $_SESSION['cultura_empresa_tipo'] = $cultura; // Guardar la selección de cultura en la sesión
        header("Location: dashboard.php?cultura_guardada=true"); // Redirigir con indicador de cultura guardada
        exit;
    } else {
        die("Error al guardar la cultura de la empresa: " . $stmt->error);
    }
    $stmt->close();
} else {
    header("Location: dashboard.php");
    exit;
}
?>
