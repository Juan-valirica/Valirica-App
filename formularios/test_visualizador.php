
<?php
include('../config.php');

$equipo_id = isset($_GET['equipo_id']) ? intval($_GET['equipo_id']) : 0;
if ($equipo_id <= 0) die("⛔ ID de equipo no válido.");

$res = mysqli_query($conn, "SELECT * FROM detalle_formulario WHERE equipo_id = $equipo_id");
$respuestas = [];
while ($row = mysqli_fetch_assoc($res)) {
    $respuestas[$row['pregunta_id']] = $row['respuesta'];
}

$res2 = mysqli_query($conn, "SELECT * FROM equipo WHERE id = $equipo_id");
$valores = mysqli_fetch_assoc($res2);
unset($valores['id'], $valores['nombre_persona'], $valores['apellido'], $valores['correo'], $valores['cargo'], $valores['area_trabajo'], $valores['relacion_autoridad'], $valores['usuario_id']);

echo "<h1>📊 Diagnóstico para equipo ID $equipo_id</h1>";
echo "<h2>Respuestas (detalle_formulario)</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Pregunta ID</th><th>Respuesta</th></tr>";
foreach ($respuestas as $pid => $val) {
    echo "<tr><td>$pid</td><td>$val</td></tr>";
}
echo "</table><br>";

echo "<h2>Resultados calculados (tabla equipo)</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Dimensión</th><th>Valor</th></tr>";
foreach ($valores as $key => $val) {
    echo "<tr><td>$key</td><td>$val</td></tr>";
}
echo "</table>";
?>
