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
    $titulos = $_POST["titulo"];
    $descripciones = $_POST["descripcion"];

    if (empty($titulos) || empty($descripciones)) {
        die("❌ Error: Todos los campos deben ser llenados.");
    }

    foreach ($titulos as $index => $titulo) {
        $descripcion = str_replace(["\r\n", "\r"], "\n", trim($descripciones[$index]));


        // Verificar si el valor ya existe para este usuario
        $stmt = $conn->prepare("SELECT id FROM valores_marca WHERE usuario_id = ? AND titulo = ?");
        $stmt->bind_param("is", $user_id, $titulo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE valores_marca SET descripcion = ? WHERE usuario_id = ? AND titulo = ?");
            $stmt->bind_param("sis", $descripcion, $user_id, $titulo);
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO valores_marca (usuario_id, titulo, descripcion) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $titulo, $descripcion);
        }

        $stmt->execute();
        $stmt->close();
    }

    // Redirigir al dashboard después de guardar
    header("Location: dashboard.php?status=valores_guardados");
    exit;
}
?>
