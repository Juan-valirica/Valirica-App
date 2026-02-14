<?php
session_start();
require 'config.php';

// Verificar si el usuario es administrador
$admin_emails = ["juan@valirica.com", "calderon10b@gmail.com"];
if (!isset($_SESSION['user_email']) || !in_array($_SESSION['user_email'], $admin_emails)) {
    die("❌ Acceso restringido.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdf_valores"])) {
    $user_id = intval($_POST["user_id"]);
    $pdf_dir = "uploads/analisis_valores/";

    if (!is_dir($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }

    $pdf_name = time() . "_" . basename($_FILES["pdf_valores"]["name"]);
    $pdf_path = $pdf_dir . $pdf_name;

    if (move_uploaded_file($_FILES["pdf_valores"]["tmp_name"], $pdf_path)) {
        $stmt = $conn->prepare("UPDATE usuarios SET pdf_valores = ? WHERE id = ?");
        $stmt->bind_param("si", $pdf_path, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: analisis_proposito.php");
        exit;
    } else {
        die("❌ Error al subir el archivo.");
    }
}
?>
