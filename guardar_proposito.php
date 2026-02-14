<?php
session_start();
require 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Procesar la petición POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $proposito = str_replace(["\r\n", "\r"], "\n", trim($_POST["proposito"]));


    // Validar que el campo de propósito no esté vacío
    if (empty($proposito)) {
        die("❌ Error: Debes ingresar un propósito.");
    }

    // Preparar y ejecutar la actualización del propósito en la base de datos
    $stmt = $conn->prepare("UPDATE usuarios SET proposito = ? WHERE id = ?");
    $stmt->bind_param("si", $proposito, $user_id);
    if ($stmt->execute()) {
        // Enviar notificación al administrador
        $to = "calderon10b@gmail.com";
        $subject = "Nuevo Propósito Agregado";
        $message = "El usuario {$_SESSION['user_name']} {$_SESSION['user_apellido']} ha ingresado su propósito: \n\n{$proposito}";
        $headers = "From: no-reply@app.valirica.com";

        mail($to, $subject, $message, $headers);

        // Redirigir al dashboard después de guardar
        header("Location: dashboard.php?status=proposito_guardado");
        exit;
    } else {
        echo "❌ Error al guardar el propósito: " . $stmt->error;
    }

    $stmt->close();
}
?>
