<?php
session_start();
require 'config.php';

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_GET['user_id'] ?? 0; // Obtener el ID del usuario desde la URL

if (!$user_id) {
    header("Location: ingresar_analisis_proposito.php");
    exit;
}

// Verifica que el usuario existe
$stmt = $conn->prepare("SELECT empresa FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($empresa = $result->fetch_assoc()) {
    $empresa_nombre = $empresa['empresa'];
} else {
    die("Empresa no encontrada.");
}
$stmt->close();

// Obtener los títulos de los valores de marca para el usuario
$stmt = $conn->prepare("SELECT titulo FROM valores_marca WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$valores_result = $stmt->get_result();
$valores = [];
while ($row = $valores_result->fetch_assoc()) {
    $valores[] = $row['titulo'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingresar Análisis de Propósito</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="dashboard-container">
    <h1>Ingresar Análisis para <?= htmlspecialchars($empresa_nombre) ?></h1>
    <form action="guardar_analisis.php" method="post">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

        <!-- Dimensiones del propósito con explicaciones -->
        <div class="form-group">
            <label for="interno_externo">Interno (1) VS Externo (10):</label>
            <input type="number" id="interno_externo" name="interno_externo" min="1" max="10" required>
            <textarea id="exp_interno_externo" name="exp_interno_externo" placeholder="Explicación para Interno VS Externo" required></textarea></br></br>
            
                        <label for="social_economico">Social (1) VS Económico (10):</label>
            <input type="number" id="social_economico" name="social_economico" min="1" max="10" required>
            <textarea id="exp_social_economico" name="exp_social_economico" placeholder="Explicación para Social VS Económico" required></textarea></br></br>
            
                        <label for="corto_largo">Corto Plazo (1) VS Largo Plazo (10):</label>
            <input type="number" id="corto_largo" name="corto_largo" min="1" max="10" required>
            <textarea id="exp_corto_largo" name="exp_corto_largo" placeholder="Explicación para Cortp Plazo VS Largo Plazo" required></textarea></br></br>
            
                        <label for="tangible_intangible">Tangible (1) VS Intangible (10):</label>
            <input type="number" id="tangible_intangible" name="tangible_intangible" min="1" max="10" required>
            <textarea id="exp_tangible_intangible" name="exp_tangible_intangible" placeholder="Explicación para Tangible VS Intangible" required></textarea></br></br>
            
                        <label for="estabilidad_innovacion">Estabilidad (1) VS Innovación (10):</label>
            <input type="number" id="estabilidad_innovacion" name="estabilidad_innovacion" min="1" max="10" required>
            <textarea id="exp_estabilidad_innovacion" name="exp_estabilidad_innovacion" placeholder="Explicación para Estabilidad VS Innovación" required></textarea></br></br>
        </div>

        <!-- Similar structure for other dimensions -->
        <!-- Fields for social_economico, corto_largo, tangible_intangible, estabilidad_innovacion -->

     

        <div class="form-group">
            <label for="incoherencias">Incoherencias:</label>
            <textarea id="incoherencias" name="incoherencias" placeholder="Describa las incoherencias encontradas" required></textarea></br></br>
        </div>

        <div class="form-group">
            <label for="sugerencias">Sugerencias:</label>
            <textarea id="sugerencias" name="sugerencias" placeholder="Proporcione sugerencias de mejora" required></textarea>
        </div>

        <button type="submit">Guardar Análisis</button></br></br>
    </form>
    <a href="dashboard.php">Volver al Dashboard</a>
</div>
</body>
</html>
