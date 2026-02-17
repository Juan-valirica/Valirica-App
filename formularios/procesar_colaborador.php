<?php
// formularios/procesar_colaborador.php
session_start();
require_once __DIR__ . '/../config.php';

// Para ver errores SQL reales
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
    // Entradas
    $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
    $nombre     = trim($_POST['nombre_persona']     ?? '');
    $apellido   = trim($_POST['apellido']           ?? '');
    $correo     = trim($_POST['correo']             ?? '');
    $cargo      = trim($_POST['cargo']              ?? '');
    $area       = trim($_POST['area_trabajo']       ?? '');
    $relacion   = trim($_POST['relacion_autoridad'] ?? 'pendiente');
    $sexo       = $_POST['sexo'] ?? '';$sexo = trim((string)$sexo);
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $fecha_nacimiento = $fecha_nacimiento !== '' ? $fecha_nacimiento : null;


// Whitelist (asegura valores válidos)
$sexo_allowed = ['Mujer','Hombre','No binario','Prefiero no decir','Otro','']; // '' = no responde
if (!in_array($sexo, $sexo_allowed, true)) {
  $sexo = ''; // fuerza a vacío para que NULLIF funcione
}


    if ($usuario_id <= 0)                           throw new RuntimeException('usuario_id inválido.');
    if ($nombre==='' || $apellido==='' || $correo==='' || $cargo==='' || $area==='') {
        throw new RuntimeException('Todos los campos son obligatorios.');
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo inválido.');

    // INSERT
   $stmt = $conn->prepare("
INSERT INTO equipo (
  nombre_persona,
  apellido,
  correo,
  cargo,
  area_trabajo,
  sexo,
  fecha_nacimiento,
  relacion_autoridad,
  usuario_id
) VALUES (
  ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?
)


");
$stmt->bind_param(
  "ssssssssi",
  $nombre,
  $apellido,
  $correo,
  $cargo,
  $area,
  $sexo,
  $fecha_nacimiento, // DATE → string YYYY-MM-DD
  $relacion,
  $usuario_id
);


    $stmt->execute();

    $equipo_id = (int)$conn->insert_id;     // ✅ en mysqli el ID está en la conexión
    $stmt->close();
    $conn->close();

    // Redirección (ajusta la ruta si tu formulario está en otra carpeta)
    header("Location: ../formularios/formulario_valirica.php?equipo_id={$equipo_id}");
    exit;

} catch (Throwable $e) {
    // Si ves "Unknown column 'ci.tipo'..." aquí, es un TRIGGER/vista/SP de la BD (no este archivo)
    http_response_code(400);
    echo "<!doctype html><html lang='es'><meta charset='utf-8'><body style='font-family:Arial;padding:2rem;background:#fff4ee'>";
    echo "<h2 style='color:#004758'>No pudimos registrar al colaborador</h2>";
    echo "<p style='color:#c00;max-width:700px'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:#333'>Si el mensaje dice <code>ci.tipo</code>, corrige el TRIGGER/VISTA/PROCEDIMIENTO que lo usa sobre la tabla <code>equipo</code>.</p>";
    echo "<p><a href='javascript:history.back()' style='color:#FF7800'>⟵ Volver</a></p>";
    echo "</body></html>";
}
