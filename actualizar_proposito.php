<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $proposito = trim($_POST["proposito"]);

    $stmt = $conn->prepare("UPDATE usuarios SET proposito = ? WHERE id = ?");
    $stmt->bind_param("si", $proposito, $user_id);

    if ($stmt->execute()) {
        header("Location: dashboard.php"); // Redirige al dashboard después de guardar
        exit;
    } else {
        echo "Error al actualizar el propósito.";
    }

    $stmt->close();
    $conn->close();
}
?>
