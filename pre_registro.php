<?php
session_start();
require 'config.php';  // Asegúrate de tener este archivo para conectar con la DB

// Verifica si el formulario ha sido enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';

    // Preparar y ejecutar la consulta para insertar los datos
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Guardar nombre en la sesión para usarlo en la página de agradecimiento
        $_SESSION['username'] = $username;
        // Redireccionar a la página de agradecimiento
        header("Location: gracias.php");
        exit;
    } else {
        echo "<p>Error al registrar.</p>";
    }
    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="left-section">
            <h1>Las empresas con baja <a style="font-color:#FF7800;">alineación cultural</a> pierden hasta un 30% de productividad...</h1>
            <p>Vamos a descubrir el <b>estado de alineación de tu marca</b> </p>
        </div>
        <div class="right-section">
            <div class="form-container">
                <form action="registro.php" method="POST">
                    <label for="username">Nombre (mantengamoslo informal):</label>
                    <input class="input-field" type="text" id="username" name="username" required>
                    </br>
                    <label for="email">Correo:</label>
                    <input class="input-field" type="email" id="email" name="email" required>
                    </br></br>
                    <button class="btn" type="submit">Registrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
