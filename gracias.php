<?php
session_start();
require 'config.php';

if (isset($_POST['accept'])) {
    $username = $_SESSION['username'] ?? 'Invitado';
    $email = $_SESSION['email'] ?? '';

    // Insertar o actualizar la base de datos aquí
    $stmt = $conn->prepare("UPDATE usuarios SET acepto_informacion=1 WHERE nombre=? AND correo=?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->close();

    echo "<p>Gracias, $username, por aceptar recibir información.</p>";
} else {
    $username = $_SESSION['username'] ?? 'Invitado';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gracias por Registrarte</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="dashboard-container">
    <h1>¡Gracias por llegar hasta acá <?= $username ?>!</h1>
    <p>Estamos haciendo ajustes en la plataforma para garantizar la mejor calidad en el análisis de los resultados. 
    
    </br>Si quieres que te avisemos tan pronto actualicemos nuestra plataforma, haz click en aceptar.</p>
    <form action="gracias.php" method="POST">
        <button name="accept" type="submit">Aceptar</button>
    </form>
     </br>De lo contrario, igual gracias por llegar hasta este punto y esperamos convencerte en una próxima ocasión.</p>
</div>
</body>
</html>
<?php
}
?>
