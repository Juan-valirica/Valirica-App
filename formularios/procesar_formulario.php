
<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
$equipo_id = $_POST['equipo_id'] ?? null;

    if (!$equipo_id) {
        die("Error: No hay sesión de empleado activa.");
    }

    $respuestas = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'respuesta_') === 0) {
            $pregunta_id = str_replace('respuesta_', '', $key);
            $respuestas[] = [
                'pregunta_id' => (int)$pregunta_id,
                'respuesta' => trim($value),
            ];
        }
    }

    if (empty($respuestas)) {
        die("No se recibieron respuestas.");
    }

    $stmt = $conn->prepare("INSERT INTO detalle_formulario (equipo_id, pregunta_id, respuesta) VALUES (?, ?, ?)");

    foreach ($respuestas as $r) {
        $stmt->bind_param("iis", $equipo_id, $r['pregunta_id'], $r['respuesta']);
        $stmt->execute();
    }

    header("Location: procesar_resultados.php?equipo_id=" . $equipo_id);
    exit;
} else {
    echo "Acceso no válido.";
}
?>
