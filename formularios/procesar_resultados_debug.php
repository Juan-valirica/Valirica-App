
<?php
include('../config.php');

$equipo_id = isset($_GET['equipo_id']) ? intval($_GET['equipo_id']) : 0;
if ($equipo_id <= 0) die("⛔ ID de equipo no válido.");

$res = mysqli_query($conn, "SELECT pregunta_id, respuesta FROM detalle_formulario WHERE equipo_id = $equipo_id");
$respuestas = [];
while ($row = mysqli_fetch_assoc($res)) {
    $pid = intval($row['pregunta_id']);
    $val = floatval($row['respuesta']);
    $respuestas[$pid] = $val;
}

// Calculamos solo disc_d para este debug
$preguntas_disc_d = [1, 2, 3, 4, 5, 6, 7, 8];
$suma = 0;
$conteo = 0;
foreach ($preguntas_disc_d as $pid) {
    if (isset($respuestas[$pid])) {
        $valor = $respuestas[$pid];
        $suma += $valor;
        $conteo++;
    }
}
$disc_d = $conteo > 0 ? round($suma / $conteo, 2) : 0;

// Intentamos actualizar en la tabla equipo
echo "<h1>UPDATE Debug</h1>";
echo "<p>Intentando guardar disc_d = $disc_d en equipo_id = $equipo_id</p>";

$sql_update = "UPDATE equipo SET disc_d = $disc_d WHERE id = $equipo_id";
echo "<pre>$sql_update</pre>";

$result = mysqli_query($conn, $sql_update);
if ($result) {
    echo "<p style='color: green;'>✅ UPDATE ejecutado correctamente.</p>";
} else {
    echo "<p style='color: red;'>❌ Error al ejecutar el UPDATE: " . mysqli_error($conn) . "</p>";
}
?>
