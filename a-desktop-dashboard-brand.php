<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';
require_once 'auth_scope.php';





// === Helpers de salida seguros y etiquetas de cultura ===
if (!function_exists('h')) {
    function h($v) {
        // htmlspecialchars que acepta null sin warnings
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}




function resolve_logo_url(?string $path): string {
    $valiricaDefault = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';
    $p = trim((string)$path);

    if ($p === '') return $valiricaDefault;

    // Ya es URL absoluta
    if (preg_match('~^https?://~i', $p)) return $p;

    // Doble slash (protocolo relativo) → fuerza https
    if (strpos($p, '//') === 0) return 'https:' . $p;

    // Empieza con slash → host + path
    if ($p[0] === '/') return 'https://app.valirica.com' . $p;

    // Path relativo en BD → asume que cuelga de /uploads/
    // Si tu BD guarda 'uploads/...', también queda bien.
    if (stripos($p, 'uploads/') === 0) return 'https://app.valirica.com/' . $p;

    // Cualquier otro caso → lo colgamos de /uploads/
    return 'https://app.valirica.com/uploads/' . $p;
}





function cultura_label($tipo) {
  // normaliza y mapea a tus etiquetas visibles
  $s = trim((string)$tipo);
  $n = mb_strtolower($s, 'UTF-8');
  // quitar acentos comunes
  $n = strtr($n, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
  switch ($n) {
    case 'clan':
    case 'cultura clan':
    case 'colaborativa':
    case 'cultura colaborativa':
      return 'Colaborativa';
    case 'adhocracia':
    case 'adhocratica':
    case 'cultura adhocratica':
    case 'innovadora':
    case 'cultura innovadora':
    case 'innovacion':
      return 'Ágil';
    case 'mercado':
    case 'cultura mercado':
    case 'orientada a resultados':
    case 'resultados':
    case 'enfoque a resultados':
      return 'Orientada a Resultados';
    case 'jerarquica':
    case 'jerárquica':
    case 'cultura jerarquica':
    case 'estructurada':
    case 'estructura':
      return 'Estructurada';
    default:
      return ucfirst($s);
  }
}




if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}






// Helper para redondeo seguro (evita round(null))
function safe_round($val, $precision = 0, $fallback = 0.0) {
    if ($val === null) return $fallback;
    return round((float)$val, $precision);
}

// === Hofstede → Ejes X/Y y etiqueta de cuadrante ===
function calcular_ejes(array $v): array {
  // Espera claves: distancia_poder, individualismo, masculinidad, incertidumbre, largo_plazo, indulgencia
  $indiv  = (float)($v['individualismo']  ?? 0);  // -1..+1
  $masc   = (float)($v['masculinidad']    ?? 0);
  $unc    = (float)($v['incertidumbre']   ?? 0);
  $power  = (float)($v['distancia_poder'] ?? 0);
  $lto    = (float)($v['largo_plazo']     ?? 0);
  $indulg = (float)($v['indulgencia']     ?? 0);

  $short_term = -$lto;    // corto plazo empuja a Externo (X+)
  $restraint  = -$indulg; // restricción empuja a Controlado

  // X: Interno(-) ↔ Externo(+)
  $X = 0.45*$indiv + 0.35*$masc + 0.20*$short_term;

  // Y_base controlado(+). Luego invertimos para que Y(+) = Flexible
  $Y_base = 0.35*$power + 0.35*$unc + 0.20*$restraint + 0.10*$lto;
  $Y_base += 0.25*$masc;   // +control
  $Y_base -= 0.25*$indiv;  // +flex
  $Y = -$Y_base;

  // Clip a [-1..1] y escala a [-5..5] (si necesitas plot)
  $X = max(-1, min(1, $X)) * 5.0;
  $Y = max(-1, min(1, $Y)) * 5.0;
  return [$X, $Y];
}
function cuadrante_label($x,$y){
  if ($x < 0 && $y > 0) return 'Colaborativa';
  if ($x >= 0 && $y > 0) return 'Ágil';
  if ($x < 0 && $y <= 0) return 'Estructurada';
  return 'Orientada a Resultados';
}




$viewer_id  = (int)($_SESSION['user_id'] ?? 0);     // quién está logueado (provider o company)
$usuario_id = isset($_GET['usuario_id'])
  ? (int)$_GET['usuario_id']                        // empresa objetivo (el cliente)
  : $viewer_id;                                     // fallback: su propia empresa


if ($usuario_id <= 0) {
    echo "<p style='color: red;'>⛔ Error: ID de usuario inválido.</p>";
    return;
}










// === Progreso de Metas: Año vs Mes (corte fin de mes) ===
date_default_timezone_set('Europe/Madrid');

// Aseguramos un ID de empresa consistente en todo el archivo
$empresa_id = (int)($usuario_id ?: ($_SESSION['user_id'] ?? 0));

$hoy        = new DateTime('now');
$year       = (int)$hoy->format('Y');
$monthStart = new DateTime("first day of this month 00:00:00");
$monthEnd   = new DateTime("last day of this month 23:59:59");
$yearStart  = new DateTime("$year-01-01 00:00:00");
$yearEnd    = new DateTime("$year-12-31 23:59:59");

// Helper: devuelve porcentaje redondeado (0..100) desde metas_personales
function avg_progress_between(mysqli $conn, int $empresa_id, string $from, string $to): int {
    $sql = "SELECT AVG(progress_pct) AS pct
            FROM metas_personales
            WHERE user_id = ? AND due_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $empresa_id, $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: ['pct' => 0];
    $stmt->close();
    return (int)round((float)$row['pct'] ?: 0);
}

// % AÑO (todas las metas personales del año calendario)
$pct_ano = avg_progress_between(
    $conn,
    $empresa_id,
    $yearStart->format('Y-m-d'),
    $yearEnd->format('Y-m-d')
);

// % MES (corte a fin de mes): incluye metas de meses anteriores del mismo año (YTD hasta fin de mes)
$pct_mes = avg_progress_between(
    $conn,
    $empresa_id,
    $yearStart->format('Y-m-d'),
    $monthEnd->format('Y-m-d')
);




$user_id = $_SESSION['user_id']; // si lo usas en otros lados, mantenlo
$stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo, rol, email, provider_id 
                        FROM usuarios 
                        WHERE id = ?");

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($usuario = $result->fetch_assoc()) {
        $empresa = (string)($usuario['empresa'] ?? '');
    $logo = (string)($usuario['logo'] ?? '');
    $cultura_empresa_tipo = (string)($usuario['cultura_empresa_tipo'] ?? '');
    $rol_usuario = (string)($usuario['rol'] ?? '');

} 

else {
    echo "No se encontró información del usuario.";
    exit;
}







// === Rol del usuario logueado (viewer) ===
$viewer_rol = null;

if (!empty($user_id)) {

    // Si el que está viendo ES la misma empresa, reutilizamos el rol sin otra consulta
    if ($user_id == $usuario_id) {
        $viewer_rol = $rol_usuario;
    } else {
        // Si es un provider mirando a una company (u otro caso), consultamos su rol
        if ($stmtViewer = $conn->prepare("SELECT rol FROM usuarios WHERE id = ? LIMIT 1")) {
            $stmtViewer->bind_param("i", $user_id);
            $stmtViewer->execute();
            $resViewer = $stmtViewer->get_result();
            if ($rowViewer = $resViewer->fetch_assoc()) {
                $viewer_rol = (string)($rowViewer['rol'] ?? null);
            }
            $stmtViewer->close();
        }
    }
}





// ==== Resolución de contacto y logo para la tarjeta "Intervención recomendada" ====
$VALIRICA_EMAIL = 'juan@valirica.com';
$VALIRICA_LOGO  = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';

$ctaEmail = $VALIRICA_EMAIL;   // fallback
$ctaLogo  = $VALIRICA_LOGO;    // fallback

if (strcasecmp((string)$rol_usuario, 'provider') === 0) {
    // Si el usuario logueado es provider → siempre contactan a Valírica y se muestra el logo de Valírica
    $ctaEmail = $VALIRICA_EMAIL;
    $ctaLogo  = $VALIRICA_LOGO;
} else {
    // Es company → buscamos su provider por provider_id
    $provider_id = (int)($usuario['provider_id'] ?? 0);
    if ($provider_id > 0) {
        if ($stp = $conn->prepare("SELECT email, logo FROM usuarios WHERE id = ? AND rol = 'provider' LIMIT 1")) {
            $stp->bind_param("i", $provider_id);
            $stp->execute();
            $rsp = $stp->get_result();
            if ($rowp = $rsp->fetch_assoc()) {
                $emailBD = trim((string)($rowp['email'] ?? ''));
                $logoBD  = resolve_logo_url($rowp['logo'] ?? '');

                // Asigna email si es válido; de lo contrario, fallback Valírica
                $ctaEmail = filter_var($emailBD, FILTER_VALIDATE_EMAIL) ? $emailBD : $VALIRICA_EMAIL;
                // Asigna logo; si queda vacío, fallback a Valírica
                $ctaLogo  = $logoBD ?: $VALIRICA_LOGO;
            }
            $stp->close();
        }
    } else {
        // Sin provider_id → intenta 1er provider del sistema (ajusta criterio si manejas multi-tenant)
        if ($stp = $conn->prepare("SELECT email, logo FROM usuarios WHERE rol = 'provider' ORDER BY id ASC LIMIT 1")) {
            $stp->execute();
            $rsp = $stp->get_result();
            if ($rowp = $rsp->fetch_assoc()) {
                $emailBD = trim((string)($rowp['email'] ?? ''));
                $logoBD  = resolve_logo_url($rowp['logo'] ?? '');

                $ctaEmail = filter_var($emailBD, FILTER_VALIDATE_EMAIL) ? $emailBD : $VALIRICA_EMAIL;
                $ctaLogo  = $logoBD ?: $VALIRICA_LOGO;
            }
            $stp->close();
        }
    }
}








// Traducción moderna de tipos de cultura con normalización robusta
function normalize_key($s){
    $s = trim(mb_strtolower((string)$s, 'UTF-8'));
    // quitar acentos
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'
    ]);
    // colapsar espacios
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}




// Traducción moderna de tipos de cultura
$cultura_labels = [
    'clan' => 'Colaborativa',
    'adhocratica' => 'Innovadora',
    'mercado' => 'Orientada a Resultados',
    'jerarquica' => 'Estructurada'
];




// ¿Cuántos miembros tiene el equipo?
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM equipo WHERE usuario_id = ?");
$stmt_count->bind_param("i", $usuario_id);
$stmt_count->execute();
$res_count = $stmt_count->get_result();
$row_count = $res_count->fetch_assoc();
$equipo_count = (int)($row_count['total'] ?? 0);
$stmt_count->close();
$hay_equipo = $equipo_count > 0;


// Obtener los promedios de las dimensiones de alineación
$promedios = [
    'promedio_hofstede' => 0,
    'promedio_maslow' => 0,
    'promedio_bienestar' => 0,
    'promedio_proposito' => 0,
    'promedio_valores' => 0,
    'promedio_rol' => 0
];
$stmt_prom = $conn->prepare("
    SELECT 
        AVG(hofstede_porcentaje) AS promedio_hofstede,
        AVG(maslow_porcentaje) AS promedio_maslow,
        AVG(bienestar_personal_porcentaje) AS promedio_bienestar,
        AVG(alineacion_proposito_porcentaje) AS promedio_proposito,
        AVG(alineacion_valores_porcentaje) AS promedio_valores,
        AVG(actividades_laborales_porcentaje) AS promedio_rol
    FROM equipo
    WHERE usuario_id = ?
");

if ($stmt_prom) {
    $stmt_prom->bind_param("i", $usuario_id);
    $stmt_prom->execute();
    $result = $stmt_prom->get_result();
    if ($fila = $result->fetch_assoc()) {
        $promedios = array_map(function ($valor) {
            return is_null($valor) ? 0 : round(floatval($valor));
        }, $fila);
    }
    $stmt_prom->close();

}












// Obtener y mostrar perfiles de los empleados
$stmt = $conn->prepare("SELECT id, nombre_persona, 
    hofstede_poder, hofstede_individualismo, hofstede_resultados,
    hofstede_incertidumbre, hofstede_largo_plazo, hofstede_espontaneidad
    FROM equipo 
    WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$perfiles = [];

$stmt_ideal = $conn->prepare("
    SELECT 
        distancia_poder, individualismo, masculinidad, incertidumbre, largo_plazo, indulgencia 
    FROM cultura_ideal 
    WHERE usuario_id = ?
");
$stmt_ideal->bind_param("i", $usuario_id);
$stmt_ideal->execute();
$result_ideal = $stmt_ideal->get_result();
$cultura_ideal = $result_ideal->fetch_assoc() ?: [];
$stmt_ideal->close();

// Normalizamos cultura ideal
$valores_ideales = [];
foreach ($cultura_ideal as $clave => $valor) {
    $valores_ideales[$clave] = round($valor / 5, 3);  // de -5 a 5 → -1 a 1
}


// === Tipo de cultura de la MARCA desde Hofstede ideal ===
list($ejeX_ideal, $ejeY_ideal) = calcular_ejes($valores_ideales);
$cuad_empresa = cuadrante_label($ejeX_ideal, $ejeY_ideal);

// Fuerza sincronía en header
$cultura_empresa_tipo = $cuad_empresa;

// (Opcional) Persistir para otras pantallas
if (!empty($cuad_empresa)) {
  $stmt = $conn->prepare("UPDATE usuarios SET cultura_empresa_tipo = ? WHERE id = ?");
  $stmt->bind_param("si", $cuad_empresa, $usuario_id);
  $stmt->execute();
  $stmt->close();
}




// Procesar cada perfil
while ($perfil = $result->fetch_assoc()) {
    $suma = 0;
    $dimensiones = 0;

    // Asegúrate de que estos valores estén en escala [-1..+1]
    $mapeo = [
        'distancia_poder' => (float)$perfil['hofstede_poder'],
        'individualismo'  => (float)$perfil['hofstede_individualismo'],
        'masculinidad'    => (float)$perfil['hofstede_resultados'],
        'incertidumbre'   => (float)$perfil['hofstede_incertidumbre'],
        'largo_plazo'     => (float)$perfil['hofstede_largo_plazo'],
        'indulgencia'     => (float)$perfil['hofstede_espontaneidad'],
    ];

    foreach ($valores_ideales as $clave => $ideal) {
        if (!array_key_exists($clave, $mapeo)) continue;

        $real = (float)$mapeo[$clave];      // esperado en [-1..+1]
        $ideal_norm = (float)$ideal;        // ya normalizado a [-1..+1]

        // ✅ Normaliza por la distancia máxima (2) y clamp a [0..1]
        $alineacion = 1 - (abs($real - $ideal_norm) / 2);
        $alineacion = max(0.0, min(1.0, $alineacion));

        $suma += $alineacion;
        $dimensiones++;
    }

    // % individual en 0..100 con 1 decimal
    $porcentaje = ($dimensiones > 0) ? round(($suma / $dimensiones) * 100, 1) : 0.0;

    $perfiles[] = [
        'nombre'     => $perfil['nombre_persona'],
        'id'         => $perfil['id'],
        'alineacion' => $porcentaje
    ];
}


// $stmt->close(); // ⚠️ ya se cerró antes, evita error





// Ordenar de mayor a menor
usort($perfiles, function($a, $b) {
    return $b['alineacion'] <=> $a['alineacion'];
});






$stmt_ideal = $conn->prepare("
    SELECT 
        distancia_poder, individualismo, masculinidad, incertidumbre, largo_plazo, indulgencia 
    FROM cultura_ideal 
    WHERE usuario_id = ?
");
$stmt_ideal->bind_param("i", $usuario_id);
$stmt_ideal->execute();
$result_ideal = $stmt_ideal->get_result();
$cultura_ideal = $result_ideal->fetch_assoc() ?: [];
$stmt_ideal->close();

// Normalizar valores ideales de -5 a 5 → a -1 a 1
$valores_ideales = [];
foreach ($cultura_ideal as $clave => $valor) {
    $valores_ideales[$clave] = round($valor / 5, 3);  // escala -1 a 1
}


// Obtener perfiles del equipo con mapeo correcto
$stmt_equipo = $conn->prepare("
    SELECT 
        hofstede_poder as distancia_poder,
        hofstede_individualismo as individualismo,
        hofstede_resultados as masculinidad,
        hofstede_incertidumbre as incertidumbre,
        hofstede_largo_plazo as largo_plazo,
        hofstede_espontaneidad as indulgencia
    FROM equipo 
    WHERE usuario_id = ?
");
$stmt_equipo->bind_param("i", $usuario_id);
$stmt_equipo->execute();
$result_equipo = $stmt_equipo->get_result();

$total_alineacion = 0;
$cantidad_miembros = 0;

while ($row = $result_equipo->fetch_assoc()) {
    $suma_alineacion_individual = 0;
    $dimensiones = 0;

    foreach ($valores_ideales as $clave => $ideal_normalizado) {
        if (isset($row[$clave])) {
            $real = floatval($row[$clave]); // ya en escala -1 a 1
            // Corrección: normalización para evitar negativos
$alineacion = 1 - (abs($real - $ideal_normalizado) / 2);
$alineacion = max(0, min(1, $alineacion)); // clamp: asegura que quede entre 0 y 1

            $suma_alineacion_individual += $alineacion;
            $dimensiones++;
        }
    }

    if ($dimensiones > 0) {
        $alineacion_individual = $suma_alineacion_individual / $dimensiones;
        $total_alineacion += $alineacion_individual;
        $cantidad_miembros++;
    }
}

$promedio_general = ($cantidad_miembros > 0)
    ? round(($total_alineacion / $cantidad_miembros) * 100, 1)  // % final
    : 0;








// === PINK (motivación) para Energía del Equipo ===
$res_pink = mysqli_query($conn, "SELECT 
  SUM(pink_purp) AS proposito, 
  SUM(pink_auto) AS autonomia, 
  SUM(pink_maes) AS maestria, 
  SUM(pink_fis)  AS salud, 
  SUM(pink_rel)  AS relaciones
  FROM equipo WHERE usuario_id = $usuario_id");
$pink = mysqli_fetch_assoc($res_pink) ?: ['proposito'=>0,'autonomia'=>0,'maestria'=>0,'salud'=>0,'relaciones'=>0];

$res_count_pink = mysqli_query($conn, "SELECT COUNT(*) AS total FROM equipo WHERE usuario_id = $usuario_id");
$equipo_count_pink = intval(mysqli_fetch_assoc($res_count_pink)['total'] ?? 0);

// Total sobre 5 dimensiones (máximo por persona = 25)
$total_pink   = array_sum($pink);
$maximo_pink  = $equipo_count_pink * 25;
$porcentaje_pink = ($maximo_pink > 0) ? min(100, round(($total_pink / $maximo_pink) * 100)) : 0;

// === MASLOW (necesidad latente) para Energía del Equipo ===
$res_maslow = mysqli_query($conn, "SELECT 
  AVG(maslow_fis) AS fisiologica, 
  AVG(maslow_seg) AS seguridad, 
  AVG(maslow_afi) AS afiliacion, 
  AVG(maslow_rec) AS reconocimiento, 
  AVG(maslow_aut) AS autorrealizacion
  FROM equipo WHERE usuario_id = $usuario_id");
$maslow = mysqli_fetch_assoc($res_maslow) ?: ['fisiologica'=>0,'seguridad'=>0,'afiliacion'=>0,'reconocimiento'=>0,'autorrealizacion'=>0];
$dominante_maslow = array_keys($maslow, max($maslow))[0] ?? 'fisiologica';

// === Energía del Equipo (0.4 Alineación + 0.4 Motivación + 0.2 Maslow) ===
$__maslow_map = [
  'fisiologica'      => 0,
  'seguridad'        => 25,
  'afiliacion'       => 50,
  'reconocimiento'   => 75,
  'autorrealizacion' => 100
];

$alineacion_pct = max(0, min(100, (float)$promedio_general));
$motivacion_pct = max(0, min(100, (float)$porcentaje_pink));
$maslow_pct     = max(0, min(100, (float)($__maslow_map[$dominante_maslow] ?? 0)));


// === Energía del Equipo (Motivación Colectiva) ===
// Se calcula como 60% Pink + 40% Maslow
$energia_equipo = (int) round(0.6 * $porcentaje_pink + 0.4 * $maslow_pct);


// === Icono + etiqueta para Motivación Colectiva (batería) ===
if ($energia_equipo <= 25) {
    $energia_icon = '/uploads/Battery-low.png';
    $energia_status = 'Baja';
} elseif ($energia_equipo <= 50) {
    $energia_icon = '/uploads/Battery-mid.png';
    $energia_status = 'Media';
} elseif ($energia_equipo <= 75) {
    $energia_icon = '/uploads/Battery-high.png';
    $energia_status = 'Alta';
} else {
    $energia_icon = '/uploads/Battery-full.png';
    $energia_status = 'Óptima';
}







// === Estilo de Aprendizaje — Equipo vs Cultura (reader) ===
// Calculado igual que en el otro dashboard: AVG por canal y dominante por máximo.

function norm_style($s){
  $s = trim(mb_strtolower((string)$s, 'UTF-8'));
  // quitar acentos
  $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
  // colapsar espacios
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

// 1) Promedios del equipo (misma lógica del primer archivo)
$sen_visual = $sen_auditivo = $sen_kinestesico = 0.0;

if ($stmt_sen = $conn->prepare("
    SELECT 
      AVG(visual)      AS visual, 
      AVG(auditivo)    AS auditivo, 
      AVG(kinestesico) AS kinestesico
    FROM equipo
    WHERE usuario_id = ?
")) {
    $stmt_sen->bind_param("i", $usuario_id);
    $stmt_sen->execute();
    $res_sen = $stmt_sen->get_result();
    if ($row = $res_sen->fetch_assoc()) {
        $sen_visual      = (float)($row['visual'] ?? 0);
        $sen_auditivo    = (float)($row['auditivo'] ?? 0);
        $sen_kinestesico = (float)($row['kinestesico'] ?? 0);
    }
    $stmt_sen->close();
}

// 2) Determinar dominante del equipo por máximo
$promedios_sensoriales = [
  'visual'      => $sen_visual,
  'auditivo'    => $sen_auditivo,
  'kinestesico' => $sen_kinestesico
];

// Si todo es 0 (sin datos), marcamos null para no “inventar” el estilo
$hay_datos_sensoriales = array_sum($promedios_sensoriales) > 0;

if ($hay_datos_sensoriales) {
    $dominante_sensorial_clave = array_keys($promedios_sensoriales, max($promedios_sensoriales))[0];
} else {
    $dominante_sensorial_clave = null; // sin datos aún
}

// 3) Etiqueta “bonita” para mostrar
$LABELS_SENSORIALES = [
  'visual'      => 'Visual',
  'auditivo'    => 'Auditivo',
  'kinestesico' => 'Kinestésico'
];

$estilo_equipo_aprend = $dominante_sensorial_clave ? $LABELS_SENSORIALES[$dominante_sensorial_clave] : 'Sin datos';



// 5) Normalización y comparación para “Alineado / Desalineado”
$STYLE_POS = [
  'kinestesico' => 0,
  'auditivo'    => 1,
  'visual'      => 2,
];

$_eq = norm_style($estilo_equipo_aprend);

if (!isset($estilo_cultura_aprend) || $estilo_cultura_aprend === null || $estilo_cultura_aprend === '') {
    $estilo_cultura_aprend = 'Visual';
}



$_cu = norm_style($estilo_cultura_aprend);

$aprend_alineado = (isset($STYLE_POS[$_eq], $STYLE_POS[$_cu]) && $STYLE_POS[$_eq] === $STYLE_POS[$_cu]);

// Si no hay datos del equipo, no marcamos alineado (mostrará el chip “warn” pero con la etiqueta “Sin datos”)
if (!$hay_datos_sensoriales) {
    $aprend_alineado = false;
}









// Determinar URL de redirección según el promedio
if ($promedio_general < 30) {
    $url_analisis = "resultados-alineación-cultural/resultado_bajo.php";
} elseif ($promedio_general <= 55) {
    $url_analisis = "resultados-alineación-cultural/resultado_medio_bajo.php";
} elseif ($promedio_general <= 75) {
    $url_analisis = "resultados-alineación-cultural/resultado_medio_alto.php";
} elseif ($promedio_general <= 90) {
    $url_analisis = "resultados-alineación-cultural/resultado_alto.php";
} else {
    $url_analisis = "resultados-alineación-cultural/resultado_excepcional.php";
}





// === Chips para KPIs (texto + clase + icono) ===
// Alineación cultural: 5 stops en quintiles
function chip_for_alineacion($pct){
  $pct = max(0, min(100, (float)$pct));

  if ($pct < 20)  return ['Baja',      'warn', 'x'];
  if ($pct < 40)  return ['Media - Baja',        'warn', 'x'];
  if ($pct < 60)  return ['Media','',     null];
  if ($pct < 80)  return ['Media - Alta',     '',     null];
  /* >= 80 */      return ['Alta',        'ok',   'check'];
}

// Motivación: mapea tu energia_status → chip
function chip_for_motivacion($status){
  $s = mb_strtolower((string)$status, 'UTF-8');
  if ($s === 'baja')   return ['Baja',   'warn', 'x'];
  if ($s === 'media')  return ['Media',  '',     null];
  // 'Alta' o 'Óptima' → ok
  return [ucfirst($status), 'ok', 'check'];
}

// Construir chips
list($aline_label, $aline_class, $aline_icon) = chip_for_alineacion($promedio_general);
list($mot_label,   $mot_class,   $mot_icon)   = chip_for_motivacion($energia_status);





// === Nivel + icono + texto descriptivo por % de alineación ===
$nivel_titulo = '';
$nivel_icon = '';
$nivel_texto = '';

if ($promedio_general < 30) {
    // NIVEL 1
    $nivel_titulo = 'Nivel Warrior';
    $nivel_icon   = '/uploads/Niveles-1.png';
    $nivel_texto  = 'Estás en plena batalla. Tu cultura está desordenada, las señales que das al mundo aún no son claras y eso pone en riesgo tu reputación. Aquí comienza el viaje: aún con caos, pero ya estás luchando por algo más grande.';
} elseif ($promedio_general <= 55) {
    // NIVEL 2
    $nivel_titulo = 'Nivel Héroe';
    $nivel_icon   = '/uploads/Niveles-2.png';
    $nivel_texto  = 'Empiezas a destacarte. Hay esfuerzos de alineación cultural, pero aún hay mucho por trabajar. Algunas personas ya conectan con tu marca; a otras aún les falta. Tu historia recién empieza.';
} elseif ($promedio_general <= 75) {
    // NIVEL 3
    $nivel_titulo = 'Nivel Leyenda';
    $nivel_icon   = '/uploads/Niveles-3.png';
    $nivel_texto  = 'Tu cultura ya tiene peso. Se siente, se vive y empieza a generar reputación. Tus stakeholders perciben coherencia. Las leyendas nacen de la coherencia interna.';
} elseif ($promedio_general <= 90) {
    // NIVEL 4
    $nivel_titulo = 'Nivel Worthy';
    $nivel_icon   = '/uploads/Niveles-4.png';
    $nivel_texto  = 'Has trascendido. Tu cultura inspira. Hay coherencia, propósito y compromiso desde dentro hacia afuera. Eres uno de los dignos: lo que proyectas empieza a transformar.';
} else {
    // NIVEL 5
    $nivel_titulo = 'Nivel VALHALLA — Destiny';
    $nivel_icon   = '/uploads/Niveles-5.png';
    $nivel_texto  = '¡WOW!, tu cultura es un caso de estudio. Has alcanzado el máximo nivel: una cultura que es símbolo, ejemplo y legado. Ya no construyes reputación: la encarnas.';
}








// Identificar la dimensión con menor puntaje
$dimensiones_texto = [
    'Cultural' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita reforzar su conexión cultural</strong></br></br>Cuando las reglas del juego no están claras, todo se complica. Refuerza la forma en que se entienden, comunican y conviven los valores y decisiones culturales. Es el momento perfecto para alinear lo invisible que sostiene tu cultura.</br></br></br>',
    'Motivación' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita reconectar con sus motivaciones personales</strong></br></br>No todo es sueldo y beneficios. Lo que realmente impulsa a las personas es sentirse parte de algo con sentido. Este es un buen punto de partida para hablar de lo que les inspira y cómo eso conecta con el trabajo que hacen.</br></br></br>',
    'Bienestar' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita sentirse bien para dar lo mejor</strong></br></br>El bienestar físico y emocional no es un extra, es una base. Si esta dimensión está baja, es momento de crear espacios reales de cuidado, descanso y contención. Lo agradecerán en energía, foco y compromiso.</br></br></br>',
    'Propósito' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita entender el "para qué"</strong></br></br>Cuando el propósito es difuso, se pierde motivación. Haz visible el impacto que tienen en la misión de la empresa y cómo cada acción conecta con una visión más grande. Eso transforma la forma en que trabajan.</br></br></br>',
    'Valores' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita vivir los valores, no solo recordarlos</strong></br></br>Si los valores no se sienten en el día a día, pierden fuerza. Es hora de traducirlos en rituales, decisiones y reconocimientos. Eso hace que se conviertan en cultura, no solo en palabras bonitas.</br></br></br>',
    'Rol' => '<strong style="color:#004758; font-size:40px;">Tu equipo necesita tener claro su impacto individual</strong></br></br>Cuando no se sabe para qué sirve lo que uno hace, el compromiso se diluye. Revisa si cada persona tiene claro su rol, su valor dentro del equipo y cómo su trabajo suma a algo más grande.</br></br></br>'];

// Detectar la clave con menor puntaje (devuelve la clave asociativa correcta)
$dimension_menor_key = array_keys($promedios, min($promedios), true)[0] ?? 'promedio_hofstede';

// Traducir nombre técnico a etiqueta amigable
$dimension_claves = [
    'promedio_hofstede'  => 'Cultural',
    'promedio_maslow'    => 'Motivación',
    'promedio_bienestar' => 'Bienestar',
    'promedio_proposito' => 'Propósito',
    'promedio_valores'   => 'Valores',
    'promedio_rol'       => 'Rol'
];

$nombre_dimension = $dimension_claves[$dimension_menor_key] ?? 'Cultural';


// Texto recomendado
$texto_dimension_menor = $dimensiones_texto[$nombre_dimension];





// === Preparación de íconos batería (reutiliza los tuyos) ===
function battery_icon_for_pct($pct){
    if ($pct <= 25)  return '/uploads/Battery-low.png';
    if ($pct <= 50)  return '/uploads/Battery-mid.png';
    if ($pct <= 75)  return '/uploads/Battery-high.png';
    return '/uploads/Battery-full.png';
}

// === Estilo cultural ideal para aprendizaje ===
$estilo_cultura_aprend = 'Visual';
if (!empty($cultura_ideal['estilo_aprendizaje'])) {
    $estilo_cultura_aprend = $cultura_ideal['estilo_aprendizaje'];
}
$LABELS_SENSORIALES = ['visual'=>'Visual','auditivo'=>'Auditivo','kinestesico'=>'Kinestésico'];
$NORM = fn($s)=>preg_replace('/\s+/', ' ', strtr(mb_strtolower(trim((string)$s),'UTF-8'),
        ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']));
$_estilo_cultura_norm = $NORM($estilo_cultura_aprend);

// === Traer datos de equipo necesarios en una sola consulta ===
$stmt_rf = $conn->prepare("
    SELECT 
      id, nombre_persona, COALESCE(cargo,'') AS cargo,
      -- Hofstede (ya en -1..1 según tu BD)
      hofstede_poder             AS distancia_poder,
      hofstede_individualismo    AS individualismo,
      hofstede_resultados        AS masculinidad,
      hofstede_incertidumbre     AS incertidumbre,
      hofstede_largo_plazo       AS largo_plazo,
      hofstede_espontaneidad     AS indulgencia,
      -- PINK (0..5)
      COALESCE(pink_purp,0) AS pink_purp, COALESCE(pink_auto,0) AS pink_auto,
      COALESCE(pink_maes,0) AS pink_maes, COALESCE(pink_fis,0)  AS pink_fis,
      COALESCE(pink_rel,0)  AS pink_rel,
      -- MASLOW (0..5)
      COALESCE(maslow_fis,0) AS maslow_fis, COALESCE(maslow_seg,0) AS maslow_seg,
      COALESCE(maslow_afi,0) AS maslow_afi, COALESCE(maslow_rec,0) AS maslow_rec,
      COALESCE(maslow_aut,0) AS maslow_aut,
      -- APRENDIZAJE (0..1)
      COALESCE(visual,0) AS visual, COALESCE(auditivo,0) AS auditivo, COALESCE(kinestesico,0) AS kinestesico,
      -- CONFLICTO (0..5)
      COALESCE(conflicto_evitativo,0)     AS c_evitativo,
      COALESCE(conflicto_colaborativo,0)  AS c_colaborativo,
      COALESCE(conflicto_competitivo,0)   AS c_competitivo,
      COALESCE(conflicto_complaciente,0)  AS c_complaciente,
      COALESCE(conflicto_negociador,0)    AS c_negociador
    FROM equipo
    WHERE usuario_id = ?
");
$stmt_rf->bind_param("i", $usuario_id);
$stmt_rf->execute();
$res_rf = $stmt_rf->get_result();

$riesgos = [];

while ($row = $res_rf->fetch_assoc()) {

    // === 1) Alineación cultural individual (0..100)
    $suma = 0; $dim = 0;
    foreach ($valores_ideales as $k => $ideal) {
        if (!isset($row[$k])) continue;
        $real = (float)$row[$k];           // -1..1
        $alineacion = 1 - (abs($real - (float)$ideal) / 2);
        $alineacion = max(0, min(1, $alineacion));
        $suma += $alineacion; $dim++;
    }
    $alineacion_pct = $dim ? round(($suma / $dim)*100, 1) : 0.0;
    $riesgo_cultural = 100 - $alineacion_pct;

    // === 2) Pink (0..5 → 0..100) y batería individual
    $pink_avg = ($row['pink_purp'] + $row['pink_auto'] + $row['pink_maes'] + $row['pink_fis'] + $row['pink_rel']) / 5.0;
    $pink_pct = max(0, min(100, round(($pink_avg / 5.0) * 100)));
    $riesgo_pink = 100 - $pink_pct;

    // === 3) Maslow: estadio dominante → riesgo mapeado
    $mas = [
        'fisiologica'      => (float)$row['maslow_fis'],
        'seguridad'        => (float)$row['maslow_seg'],
        'afiliacion'       => (float)$row['maslow_afi'],
        'reconocimiento'   => (float)$row['maslow_rec'],
        'autorrealizacion' => (float)$row['maslow_aut']
    ];
    $maslow_dom = array_keys($mas, max($mas))[0] ?? 'fisiologica';
    $maslow_risk_map = ['fisiologica'=>100,'seguridad'=>75,'afiliacion'=>50,'reconocimiento'=>25,'autorrealizacion'=>0];
    $riesgo_maslow = $maslow_risk_map[$maslow_dom] ?? 100;

    // === 4) Estilo de aprendizaje: binario ligero
    $apr = ['visual'=>(float)$row['visual'], 'auditivo'=>(float)$row['auditivo'], 'kinestesico'=>(float)$row['kinestesico']];
    $apr_dom = array_keys($apr, max($apr))[0] ?? null;
    $_apr_norm = $apr_dom ? $NORM($LABELS_SENSORIALES[$apr_dom]) : null;
    $alineado_aprend = ($_apr_norm && $_apr_norm === $_estilo_cultura_norm);
    $riesgo_aprend = $alineado_aprend ? 0 : 100;

    // === 5) Conflicto: si evitativo o complaciente → riesgo 100, else 0
    $conf = [
        'evitativo'     => (float)$row['c_evitativo'],
        'colaborativo'  => (float)$row['c_colaborativo'],
        'competitivo'   => (float)$row['c_competitivo'],
        'complaciente'  => (float)$row['c_complaciente'],
        'negociador'    => (float)$row['c_negociador']
    ];
    $conf_dom = array_keys($conf, max($conf))[0] ?? 'evitativo';
    $riesgo_conflicto = (in_array($conf_dom, ['evitativo','complaciente'], true)) ? 100 : 0;

    // === Energía/batería individual: 60% Pink + 40% Maslow (invertido)
    // Mapeo Maslow a “energía” ya lo tenías para equipo; lo replicamos:
    $maslow_energy_map = ['fisiologica'=>0,'seguridad'=>25,'afiliacion'=>50,'reconocimiento'=>75,'autorrealizacion'=>100];
    $maslow_pct = $maslow_energy_map[$maslow_dom] ?? 0;
    $energia_pct = (int) round(0.6 * $pink_pct + 0.4 * $maslow_pct);
    $battery_icon = battery_icon_for_pct($energia_pct);


    // === Combinación ponderada (0..100) con pesos igualados en los 3 pilares
$riesgo_total = 
    0.25 * $riesgo_cultural +   // Hofstede vs Cultura Ideal (PO-fit)
    0.25 * $riesgo_pink +       // Motivación intrínseca (SDT/Pink)
    0.25 * $riesgo_maslow +     // Nivel de necesidades (dominante)
    0.15 * $riesgo_conflicto +  // Estilo de conflicto (evitativo/complaciente penaliza)
    0.10 * $riesgo_aprend;      // Fit del estilo de aprendizaje (binario ligero)

$riesgo_total = round($riesgo_total, 1);


    // Nivel y alerta
    if ($riesgo_total >= 60) { $nivel = 'Riesgo Alto'; $nivel_color = '#ff009e'; $alerta = true; }
    elseif ($riesgo_total >= 33) { $nivel = 'Riesgo Moderado'; $nivel_color = '#ffe600'; $alerta = false; }
    else { $nivel = 'Riesgo Bajo'; $nivel_color = '#00ff6d'; $alerta = false; }

    // Avatar iniciales
    $iniciales = function($name){
        $parts = preg_split('/\s+/u', trim((string)$name));
        $a = isset($parts[0][0]) ? mb_strtoupper(mb_substr($parts[0],0,1)) : '';
        $b = isset($parts[1][0]) ? mb_strtoupper(mb_substr($parts[1],0,1)) : '';
        return $a . $b;
    };

    $riesgos[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre_persona'] ?: '—',
        'cargo' => $row['cargo'] ?: '—',
        'iniciales' => $iniciales($row['nombre_persona'] ?: ''),
        'riesgo_total' => $riesgo_total,
        'nivel' => $nivel,
        'nivel_color' => $nivel_color,
        'alerta' => $alerta,
        'battery_icon' => $battery_icon
    ];
}
$stmt_rf->close();

// Ordenar desc por riesgo total
usort($riesgos, fn($a,$b) => $b['riesgo_total'] <=> $a['riesgo_total']);


// Ya están ordenados desc por riesgo_total
$riesgos_sorted = $riesgos; // todos los miembros




// Si no hay equipo, no mostramos lista
if (!$hay_equipo) {
    $riesgos_sorted = [];
}










/* ============================
   RIESGOS DE EQUIPO (Tarjeta 2)
   ============================ */

// === Helpers de UI (mismos niveles/colores que tu tarjeta de fuga) ===
function risk_level_from_score(float $score): array {
    if ($score >= 60) return ['Riesgo Alto', '#ff009e'];     // fucsia
    if ($score >= 33) return ['Riesgo Moderado', '#ffe600']; // amarillo
    return ['Riesgo Bajo', '#00ff6d'];                       // verde
}
function push_risk(array &$arr, string $titulo, string $desc, float $score, ?string $ctaHref = null) {
    list($nivel,$color) = risk_level_from_score($score);
    $arr[] = [
        'titulo' => $titulo,
        'descripcion' => $desc,
        'score' => round($score,1),
        'nivel' => $nivel,
        'color' => $color,
        'cta' => $ctaHref
    ];
}

$riesgos_equipo = [];

/* ---------------------------------------------------
   1) GAP CULTURAL (Hofstede) — con DIRECCIÓN del gap
   ---------------------------------------------------
   Escala: valores_ideales [-1..1], promedios equipo [-1..1]
   risk% = |team - ideal| / 2 * 100
   Microcopy distinto si team > ideal (exceso) o team < ideal (déficit).
*/
$team_avg = [
    'distancia_poder' => 0, 'individualismo' => 0, 'masculinidad' => 0,
    'incertidumbre' => 0, 'largo_plazo' => 0, 'indulgencia' => 0
];

$stmt_avg = $conn->prepare("
    SELECT 
        AVG(hofstede_poder)          AS distancia_poder,
        AVG(hofstede_individualismo) AS individualismo,
        AVG(hofstede_resultados)     AS masculinidad,
        AVG(hofstede_incertidumbre)  AS incertidumbre,
        AVG(hofstede_largo_plazo)    AS largo_plazo,
        AVG(hofstede_espontaneidad)  AS indulgencia
    FROM equipo WHERE usuario_id = ?
");
$stmt_avg->bind_param("i", $usuario_id);
$stmt_avg->execute();
$res_avg = $stmt_avg->get_result();
if ($row = $res_avg->fetch_assoc()) {
    foreach ($team_avg as $k => $v) $team_avg[$k] = (float)($row[$k] ?? 0);
}
$stmt_avg->close();





// Textos por dimensión y dirección (UP: team > ideal | DOWN: team < ideal)
$hofstede_text = [

  /* =========================================================
   * 1. JERARQUÍA (Distancia al poder)
   * ========================================================= */
  'distancia_poder' => [

    'up' => [
      'Exceso de jerarquía',
      'La empresa quiere una cultura más horizontal, pero el equipo espera instrucciones claras y validación constante. Esto frena la iniciativa porque las personas no se sienten autorizadas a decidir por sí mismas.',
      'Define zonas explícitas de autonomía. Aclara qué decisiones puede tomar el equipo sin pedir permiso y cuáles requieren validación. La evidencia muestra que la autonomía funciona cuando tiene límites claros.'
    ],

    'down' => [
      'Autoridad difusa',
      'El equipo opera de forma muy horizontal, pero la empresa espera orden y decisiones claras. Esto genera confusión y ralentiza la ejecución.',
      'Aclara roles de decisión, no cargos. Define quién decide qué por tema o proyecto. La claridad en la toma de decisiones reduce fricción y acelera resultados.'
    ],

  ],

  /* =========================================================
   * 2. TRABAJO EN EQUIPO VS. AUTONOMÍA
   * ========================================================= */
  'individualismo' => [

    'up' => [
      'Islas de talento',
      'Hay personas muy capaces, pero cada una trabaja de forma aislada. La empresa busca colaboración, pero el sistema premia resultados individuales.',
      'Introduce objetivos compartidos reales. Cuando el éxito depende del resultado colectivo, la colaboración aparece de forma natural.'
    ],

    'down' => [
      'Exceso de consenso',
      'El equipo prioriza tanto el acuerdo que las decisiones se vuelven lentas. Nadie quiere incomodar, aunque eso frene el avance.',
      'Normaliza el desacuerdo estructurado. Define espacios donde cuestionar decisiones sea obligatorio para mejorar la calidad de las decisiones.'
    ],

  ],

  /* =========================================================
   * 3. RESULTADOS VS. BIENESTAR
   * ========================================================= */
  'masculinidad' => [

    'up' => [
      'Rendimiento extremo',
      'El equipo empuja fuerte por resultados, pero empieza a mostrar señales de desgaste. El bienestar queda en segundo plano.',
      'Incluye métricas de sostenibilidad del rendimiento, no solo resultados. Equipos que miden energía rinden mejor a largo plazo.'
    ],

    'down' => [
      'Confort sin tracción',
      'El clima es bueno, pero los resultados no avanzan al ritmo esperado. Se evita la incomodidad necesaria para crecer.',
      'Define retos claros y medibles sin romper el clima. La exigencia con sentido impulsa el rendimiento.'
    ],

  ],

  /* =========================================================
   * 4. MANEJO DE LA INCERTIDUMBRE
   * ========================================================= */
  'incertidumbre' => [

    'up' => [
      'Miedo al error',
      'La empresa pide innovación, pero el equipo necesita certezas. Cada error se vive como una amenaza, lo que bloquea la iniciativa.',
      'Diseña experimentos pequeños y seguros. Limita el riesgo y aclara consecuencias. La seguridad psicológica aumenta cuando el error es controlado.'
    ],

    'down' => [
      'Caos creativo',
      'El equipo experimenta constantemente, pero la empresa necesita estabilidad. Se lanzan ideas sin estructura clara.',
      'Cierra cada experimento con aprendizajes documentados. La innovación sostenible necesita ciclos claros de aprendizaje.'
    ],

  ],

  /* =========================================================
   * 5. MIRADA DE LARGO PLAZO
   * ========================================================= */
  'largo_plazo' => [

    'up' => [
      'Visión sin cierre',
      'El equipo piensa estratégicamente, pero le cuesta cerrar ciclos. Las ideas se quedan en planes.',
      'Divide la visión en hitos cortos y visibles. Los logros intermedios mantienen foco y ejecución.'
    ],

    'down' => [
      'Urgencia constante',
      'El equipo vive apagando fuegos y no logra conectar su trabajo con una visión de futuro.',
      'Conecta cada proyecto con impacto futuro visible. Entender el para qué aumenta el compromiso.'
    ],

  ],

  /* =========================================================
   * 6. FLEXIBILIDAD VS. ESTRUCTURA
   * ========================================================= */
  'indulgencia' => [

    'up' => [
      'Rigidez excesiva',
      'La empresa quiere una cultura más flexible, pero el equipo necesita reglas claras y procesos definidos para sentirse seguro. Cuando todo queda abierto a interpretación, aparece ansiedad y bloqueo.',
      'Define reglas mínimas no negociables y deja libertad dentro de esos márgenes. La flexibilidad funciona cuando el equipo sabe exactamente dónde están los límites.'
    ],

    'down' => [
      'Falta de estructura',
      'El equipo se mueve con mucha libertad e improvisación, pero la empresa necesita mayor consistencia. Sin acuerdos claros, cada persona trabaja a su manera.',
      'Introduce estructuras ligeras: checklists simples, acuerdos básicos y formas estándar de trabajar. Pequeñas reglas bien definidas aumentan la calidad sin frenar la agilidad.'
    ],

  ],

];


// ================================
// Textos — Estilos de conflicto
// ================================
$conflicto_text = [

  'evitativo' => [
    'titulo' => 'Conflicto aplazado',
    'que_pasa' => 'El equipo evita conversaciones incómodas para mantener la armonía. Los problemas no se hablan, se acumulan y luego estallan en forma de fricción, errores repetidos o desgaste silencioso.',
    'quick_win' => 'Define una regla clara: todo desacuerdo relevante se conversa en máximo 72 horas. Usa una guía simple: hechos → impacto → acuerdo concreto con responsable.'
  ],

  'complaciente' => [
    'titulo' => 'Sobrecarga silenciosa',
    'que_pasa' => 'El equipo acepta tareas o decisiones aunque no esté de acuerdo o no se sienta preparado. Se dice “sí” para evitar tensión, pero luego aparecen frustración, estrés y bajo compromiso.',
    'quick_win' => 'Antes de aceptar una tarea, pide que la persona indique su nivel real de dominio y capacidad. Normalizar el “no informado” previene burnout y errores.'
  ],

  'competitivo' => [
    'titulo' => 'Tensión competitiva',
    'que_pasa' => 'Las personas defienden sus ideas con fuerza y buscan ganar la discusión. Esto puede acelerar decisiones, pero si no se gestiona bien genera roces y desgaste.',
    'quick_win' => 'Define reglas de debate claras: se confrontan ideas, no personas. Cierra siempre con una decisión explícita y un responsable.'
  ],

  'colaborativo' => [
    'titulo' => 'Colaboración madura',
    'que_pasa' => 'El equipo busca soluciones donde todas las partes ganen. Hay escucha y construcción conjunta. Bien canalizado, este estilo eleva la calidad de las decisiones.',
    'quick_win' => 'Potencia este estilo usando retrospectivas breves después de decisiones clave para consolidar aprendizaje.'
  ],

  'negociador' => [
    'titulo' => 'Equilibrio estratégico',
    'que_pasa' => 'El equipo sabe ceder y priorizar para avanzar. Facilita acuerdos sostenidos, aunque puede diluir decisiones si no se concreta.',
    'quick_win' => 'Asegura que cada acuerdo cierre con tres puntos claros: qué se decidió, quién es responsable y cuándo se revisa.'
  ],

];



// Calcular riesgos por dimensión y empujar los que superen umbral
$UMBRAL_HOFSTEDE = 20; // % de riesgo mínimo para mostrar
foreach ($team_avg as $k => $team_val) {
    $ideal = (float)($valores_ideales[$k] ?? 0);          // [-1..1]
    $diff  = abs($team_val - $ideal);                     // 0..2
    $risk  = min(100, ($diff / 2) * 100);
    if ($risk < $UMBRAL_HOFSTEDE) continue;

    $dir = ($team_val > $ideal) ? 'up' : 'down';
    $conf = $hofstede_text[$k][$dir];
    $titulo = "Desajuste cultural — ".$conf[0];
    $desc   = "Qué pasa: ".$conf[1]."  |  Haz esto: ".$conf[2];

    push_risk($riesgos_equipo, $titulo, $desc, $risk, $url_analisis ?? "#");
}

/* -----------------------------------------------
   2) PINK (Motivación intrínseca) — por dimensión
   -----------------------------------------------
   Cada dimensión de Pink es un riesgo independiente.
   % = promedio(dim)/5 * 100   → riesgo = 100 - %
*/
$res_pink_dim = mysqli_query($conn, "
  SELECT 
    AVG(pink_purp) AS proposito, 
    AVG(pink_auto) AS autonomia, 
    AVG(pink_maes) AS maestria, 
    AVG(pink_fis)  AS salud, 
    AVG(pink_rel)  AS relaciones
  FROM equipo WHERE usuario_id = $usuario_id
");
$pink_dim = mysqli_fetch_assoc($res_pink_dim) ?: ['proposito'=>0,'autonomia'=>0,'maestria'=>0,'salud'=>0,'relaciones'=>0];

$pink_labels = [
  'proposito'  => ['Propósito','Falta conexión con el “para qué” del trabajo.','Cuenta el impacto real del cliente y cómo cada rol suma.'],
  'autonomia'  => ['Autonomía','Poca libertad para decidir “cómo” se hace.','Delega el “cómo” con límites claros y evita microgestión.'],
  'maestria'   => ['Maestría','Poca sensación de progreso y aprendizaje.','Define mini-metas de mejora y celebra avances semanales.'],
  'salud'      => ['Energía y salud','Se nota cansancio/estrés sostenido.','Ajusta cargas, protege horarios y bloquea recuperación real.'],
  'relaciones' => ['Relaciones','Vínculos débiles en el equipo.','Activa rituales simples: dailies cortos, demos y retro abierta.'],
];

$UMBRAL_PINK = 60; // si % < 60 → riesgo relevante
foreach ($pink_dim as $k => $avg5) {
    $pct = max(0, min(100, round(($avg5 / 5) * 100)));
    $risk = 100 - $pct;
    if ($pct >= $UMBRAL_PINK) continue;
    $cfg = $pink_labels[$k];
    $titulo = "Motivación — ".$cfg[0];
    $desc   = "Qué pasa: ".$cfg[1]."  |  Haz esto: ".$cfg[2];
    push_risk($riesgos_equipo, $titulo, $desc, $risk, "recursos/motivacion.php");
}

/* -----------------------------------------
   3) MASLOW (necesidad dominante del equipo)
   -----------------------------------------
   Riesgo potencial de fuga si la necesidad dominante está en niveles bajos.
*/
$res_maslow = mysqli_query($conn, "
  SELECT 
    AVG(maslow_fis) AS fisiologica, 
    AVG(maslow_seg) AS seguridad, 
    AVG(maslow_afi) AS afiliacion, 
    AVG(maslow_rec) AS reconocimiento, 
    AVG(maslow_aut) AS autorrealizacion
  FROM equipo WHERE usuario_id = $usuario_id
");
$maslow = mysqli_fetch_assoc($res_maslow) ?: ['fisiologica'=>0,'seguridad'=>0,'afiliacion'=>0,'reconocimiento'=>0,'autorrealizacion'=>0];
$dom = array_keys($maslow, max($maslow))[0] ?? 'fisiologica';

// Riesgo por nivel (más bajo = mayor riesgo de fuga por salario/estabilidad)
$maslow_risk_map = [
  'fisiologica'      => [95, 'Lo que más preocupa hoy es cubrir lo básico (tiempo, energía, salario).', 'Cuida horarios, cargas y revisa compensación mínima.'],
  'seguridad'        => [80, 'La prioridad es la estabilidad (seguridad laboral y claridad).', 'Aporta claridad de contrato/beneficios y reduce incertidumbre.'],
  'afiliacion'       => [55, 'Se busca pertenencia y equipo.', 'Refuerza rituales de confianza y vínculos entre áreas.'],
  'reconocimiento'   => [35, 'Se busca visibilidad y valoración.', 'Implementa feedback frecuente y reconocimientos concretos.'],
  'autorrealizacion' => [15, 'Se busca reto y crecimiento.', 'Diseña retos claros y proyectos de alto impacto.']
];
list($maslow_risk, $maslow_desc, $maslow_act) = $maslow_risk_map[$dom];
push_risk(
  $riesgos_equipo,
  "Necesidad actual — ".ucfirst($dom),
  "Qué pasa: ".$maslow_desc."  |  Haz esto: ".$maslow_act,
  $maslow_risk,
  "recursos/maslow.php"
);

/* -------------------------
   4) Estilo de conflicto
   ------------------------- */
$stmt_conf = $conn->prepare("
    SELECT 
      AVG(conflicto_evitativo)    AS evitativo,
      AVG(conflicto_complaciente) AS complaciente,
      AVG(conflicto_competitivo)  AS competitivo,
      AVG(conflicto_colaborativo) AS colaborativo,
      AVG(conflicto_negociador)   AS negociador
    FROM equipo WHERE usuario_id = ?
");
$stmt_conf->bind_param("i", $usuario_id);
$stmt_conf->execute();
$res_conf = $stmt_conf->get_result();

if ($c = $res_conf->fetch_assoc()) {

    $domc = array_keys($c, max($c))[0] ?? 'evitativo';

    if (isset($conflicto_text[$domc])) {

        $txt = $conflicto_text[$domc];

        // Riesgo alto solo para estos estilos
        $es_riesgo = in_array($domc, ['evitativo','complaciente','competitivo'], true);
        $score = $es_riesgo ? 75 : 40;

        $titulo = "Gestión del conflicto — " . $txt['titulo'];
        $desc   = "Qué pasa: " . $txt['que_pasa'] . "  |  Haz esto: " . $txt['quick_win'];

        push_risk(
            $riesgos_equipo,
            $titulo,
            $desc,
            $score,
            "recursos/conflicto.php"
        );
    }
}
$stmt_conf->close();


/* -------------------------------------------
   5) Desincronías internas (variabilidad alta)
   ------------------------------------------- */
$alineaciones = array_map(fn($p)=>(float)($p['alineacion'] ?? 0), $perfiles);
if (count($alineaciones) >= 3) {
    $m = array_sum($alineaciones)/count($alineaciones);
    $var = 0.0;
    foreach ($alineaciones as $x) { $var += pow($x - $m, 2); }
    $std = sqrt($var / (count($alineaciones)-1)); // muestral
   if ($std >= 18) {
    push_risk(
        $riesgos_equipo,
        "Desincronías internas — Equipo partido",
        "Qué pasa: dentro del equipo hay personas muy alineadas con la cultura y otras claramente desconectadas. Esto crea subculturas internas, fricción y desgaste silencioso.  |  Haz esto: segmenta el equipo por área o rol y actúa primero donde la desconexión es mayor. Las intervenciones focalizadas generan mejoras más rápidas y sostenibles que los mensajes generales.",
        70,
        "analitica/segmentos.php"
    );
} elseif ($std >= 12) {
    push_risk(
        $riesgos_equipo,
        "Desincronías internas — Alineación desigual",
        "Qué pasa: el equipo comparte una cultura común, pero no todos la viven de la misma forma. Hay diferencias visibles en decisiones, comunicación y ejecución.  |  Haz esto: identifica los grupos con menor alineación y trabaja con ellos primero antes de que la brecha crezca.",
        45,
        "analitica/segmentos.php"
    );
}

}

/* Orden final por severidad */
usort($riesgos_equipo, fn($a,$b)=> $b['score'] <=> $a['score']);



// Sin equipo → no mostramos áreas de oportunidad
if (!$hay_equipo) {
    $riesgos_equipo = [];
}





// Señales de riesgo para mostrar CTA de intervención
$riesgos_altos_fuga = array_filter($riesgos_sorted ?? [], fn($r) => ($r['riesgo_total'] ?? 0) >= 60);
$conteo_riesgos_altos_fuga = count($riesgos_altos_fuga);

// Si ya calculaste $riesgos_equipo, usa los altos; si no, definimos vacío
$riesgos_altos_equipo = array_filter($riesgos_equipo ?? [], fn($g) => ($g['score'] ?? 0) >= 60);
$conteo_riesgos_altos_equipo = count($riesgos_altos_equipo);

// Umbrales
$umbral_alineacion_baja = 55;   // % 
$umbral_energia_baja    = 50;   // % batería

$mostrar_cta_emergencia = $hay_equipo && (
    ($promedio_general ?? 0) <= $umbral_alineacion_baja ||
    ($energia_equipo   ?? 0) <= $umbral_energia_baja   ||
    $conteo_riesgos_altos_fuga > 0                     ||
    $conteo_riesgos_altos_equipo > 0
);








/* ============================
   INNERMETRIX — DATA LAYER
   ============================ */

// Promedios DISC (natural/auth vs adaptado/mod) para la marca
$disc_avg = [
  'da'=>0,'ia'=>0,'sa'=>0,'ca'=>0,
  'dm'=>0,'im'=>0,'sm'=>0,'cm'=>0
];
if ($q = $conn->prepare("
  SELECT AVG(d_auth) da, AVG(i_auth) ia, AVG(s_auth) sa, AVG(c_auth) ca,
         AVG(d_mod)  dm, AVG(i_mod)  im, AVG(s_mod)  sm, AVG(c_mod)  cm
  FROM imx_disc d
  JOIN equipo e ON e.id = d.equipo_id
  WHERE e.usuario_id = ?
")) {
  $q->bind_param("i", $usuario_id);
  $q->execute();
  $disc_avg = $q->get_result()->fetch_assoc() ?: $disc_avg;
  $q->close();
}

// Promedios Motivadores (0..100)
$values_avg = ['aes'=>0,'eco'=>0,'ind'=>0,'pol'=>0,'alt'=>0,'reg'=>0,'the'=>0];
if ($q = $conn->prepare("
  SELECT AVG(aes) aes, AVG(eco) eco, AVG(ind) ind, AVG(pol) pol,
         AVG(alt) alt, AVG(reg) reg, AVG(the) the
  FROM imx_values v
  JOIN equipo e ON e.id = v.equipo_id
  WHERE e.usuario_id = ?
")) {
  $q->bind_param("i", $usuario_id);
  $q->execute();
  $values_avg = $q->get_result()->fetch_assoc() ?: $values_avg;
  $q->close();
}

// Dominantes (para chips)
$disc_dom_auth = '—'; $disc_dom_mod = '—'; $disc_gap_max = 0;
if ($disc_avg) {
  $auth = ['D'=>$disc_avg['da']*1, 'I'=>$disc_avg['ia']*1, 'S'=>$disc_avg['sa']*1, 'C'=>$disc_avg['ca']*1];
  $mod  = ['D'=>$disc_avg['dm']*1, 'I'=>$disc_avg['im']*1, 'S'=>$disc_avg['sm']*1, 'C'=>$disc_avg['cm']*1];
  if (array_sum($auth) > 0) $disc_dom_auth = array_keys($auth, max($auth))[0];
  if (array_sum($mod)  > 0) $disc_dom_mod  = array_keys($mod, max($mod))[0];
  $disc_gap_max = max(
    abs(($disc_avg['dm']??0)-($disc_avg['da']??0)),
    abs(($disc_avg['im']??0)-($disc_avg['ia']??0)),
    abs(($disc_avg['sm']??0)-($disc_avg['sa']??0)),
    abs(($disc_avg['cm']??0)-($disc_avg['ca']??0))
  );
}


// Flags para mostrar/ocultar tarjetas según haya datos reales
$hay_disc_equipo = false;
if (!empty($disc_avg)) {
    $sumaDisc = 0;
    foreach ($disc_avg as $v) {
        $sumaDisc += (float)$v;
    }
    // Si todos son 0 → no hay data útil
    $hay_disc_equipo = $sumaDisc > 0;
}

$hay_motivadores_equipo = false;
if (!empty($values_avg)) {
    $sumaVal = 0;
    foreach ($values_avg as $v) {
        $sumaVal += (float)$v;
    }
    // Si todos son 0 → no hay data útil
    $hay_motivadores_equipo = $sumaVal > 0;
}



// Top & bottom motivadores (chips)
$values_labels = ['aes'=>'Estético','eco'=>'Económico','ind'=>'Individualista','pol'=>'Político','alt'=>'Altruista','reg'=>'Normativo','the'=>'Teórico'];
$values_sorted = $values_avg; arsort($values_sorted);
$motiv_top_key = array_key_first($values_sorted);
$motiv_top_label = $values_labels[$motiv_top_key] ?? '—';
$motiv_top_val = round($values_sorted[$motiv_top_key] ?? 0);
$motiv_bottom_key = array_key_last($values_sorted);
$motiv_bottom_label = $values_labels[$motiv_bottom_key] ?? '—';
$motiv_bottom_val = round($values_sorted[$motiv_bottom_key] ?? 0);







?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard principal — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- PWA Meta -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Valírica">
  <link rel="apple-touch-icon" href="/uploads/logo-192.png">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#000000">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
/* === Dashboard Page Specific Styles === */
/* Nota: .wrap, .grid-sidebar y .card base ya están en valirica-design-system.css */

/* Card text alignment overrides para dashboard */
.card {
  text-align: justify;
}
.card h3 {
  text-align: left;
}
.card p {
  text-align: justify;
}

/* Alignment circle (canvas) */
.alignment-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; min-height: 320px; }
.alignment-canvas { width: 100%; height: 100%; display: block; border-radius: 12px; }

/* Tooltip for canvas */
.vl-tooltip {
  position: absolute;
  pointer-events: none;
  background: #012133;
  color: #fff;
  font-family: "gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 13px;
  line-height: 1.35;
  padding: 8px 10px;
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transform: translate(-50%, -120%);
  white-space: nowrap;
  opacity: 0;
  transition: opacity .12s ease;
  z-index: 2;
  border: 1px solid rgba(255,255,255,0.08);
}
.vl-legend { margin-top: 12px; font-size: 12px; color: var(--c-body); }
.vl-legend small { color: #6c6c6c; }

/* KPI help tooltip */
.kpi-help {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px; height: 16px;
  margin-left: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,0.18);
  color: var(--c-soft);
  font-size: 11px; font-weight: 600; cursor: pointer; line-height: 1; user-select: none;
}
.kpi-help:hover::after,
.kpi-help:focus::after {
  content: attr(data-tooltip);
  position: absolute;
  top: 130%;
  right: 0;
  transform: translateX(0);
  background: #012133; color: #fff; padding: 8px 10px; font-size: 12px; border-radius: 8px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.25); white-space: nowrap; opacity: 1; z-index: 10;
}
.kpi-help::after { opacity: 0; transition: opacity 0.15s ease; }

/* Tags */
.vl-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.vl-tag {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 9999px;
  background: var(--c-soft); color: var(--c-secondary); font-size: 12px; line-height: 1;
  border: 1px solid rgba(1,33,51,0.06); box-shadow: 0 1px 2px rgba(0,0,0,0.04); user-select: none;
}
.vl-tag::before { content: "•"; font-weight: 700; opacity: 0.6; }

/* Header KPIs */
.header-kpis { display: flex; align-items: center; gap: 22px; }
.kpi { display: grid; grid-template-columns: auto; align-items: center; gap: 2px; text-align: right; color: var(--c-soft); }
.kpi .kpi-label { font-size: 12px; line-height: 1.1; opacity: 0.85; letter-spacing: 0.2px; }
.kpi .kpi-value { font-size: 18px; line-height: 1.1; font-weight: 700; color: var(--c-accent); }
.kpi-battery { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }
.kpi-battery img { width: 28px; height: 14px; image-rendering: -webkit-optimize-contrast; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.25)); }
.kpi-battery .kpi-badge { font-size: 12px; padding: 4px 8px; border-radius: 9999px; background: rgba(255,255,255,0.12); color: var(--c-soft); border: 1px solid rgba(255,255,255,0.18); line-height: 1; }

@media (max-width: 768px){
  header { flex-wrap: wrap; row-gap: 8px; }
  .header-kpis { width: 100%; justify-content: flex-start; padding-top: 6px; }
  .kpi { text-align: left; }
}

/* KPI inline */
.kpi-inline { display: flex; align-items: center; gap: 8px; justify-content: flex-end; flex-wrap: nowrap; }
.kpi-inline .kpi-chip { margin-left: 6px; }
@media (max-width: 768px){ .kpi-inline { flex-wrap: wrap; justify-content: flex-start; } }

.kpi-row { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }

.kpi-chip {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px;
  line-height: 1; font-size: 12px; border: 1px solid rgba(255,255,255,0.22); background: rgba(255,255,255,0.12); color: var(--c-soft);
}
.kpi-chip.ok { border-color: rgba(24,70,86,0.5); background: rgba(24,70,86,0.25); }
.kpi-chip.warn { border-color: rgba(239,127,27,0.55); background: rgba(239,127,27,0.2); }
.kpi-chip svg { width: 14px; height: 14px; display: inline-block; }

/* Risks list (rf-list) */
.rf-list { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
#card-riesgos-fuga { width: 100%; }
#card-riesgos-fuga .rf-list { width: 100%; }

.rf-avatar { width: 40px; height: 40px; border-radius: 9999px; background: var(--c-accent); color: #fff; font-weight: 700; font-size: 15px; display: grid; place-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
.rf-id { display: grid; grid-template-rows: auto auto; gap: 2px; min-width: 0; }
.rf-name { font-weight: 700; color: var(--c-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rf-role { font-weight: 400; font-size:12px; color: #6a6a6a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.rf-battery { height: 20px; width: auto; display: block; image-rendering: -webkit-optimize-contrast; }
.rf-battery-lg { height: 20px; }
@media (max-width: 768px){ .rf-battery-lg { height: 20px; } }

.rf-chip {
  display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; line-height: 1; font-size: 12px;
  border: 1px solid rgba(0,0,0,0.06); background: color-mix(in srgb, var(--rf-color) 18%, #fff); color: #012133;
}
.rf-alert { font-size: 16px; line-height: 1; }

.rf-btn {
  padding: 8px 12px; border-radius: 10px; background: var(--c-soft); color: var(--c-secondary);
  border: 1px solid rgba(1,33,51,0.08); text-decoration: none; font-size: 12px; font-weight: 600;
}
.rf-btn:hover { background: #fff; }

/* Separator line between rf items */
.rf-item { position: relative; }
.rf-item + .rf-item::before {
  content: "";
  position: absolute;
  top: -8px;
  left: 12px;
  right: 12px;
  height: 1px;
  background: linear-gradient(90deg, rgba(1,33,51,0.04) 0%, rgba(1,33,51,0.10) 12%, rgba(1,33,51,0.04) 100%);
  pointer-events: none;
}

/* Consolidated, non-conflicting styles for gr-list (collapsible items) */
/* Use CSS Grid to keep right column fixed and avoid reflow when expanding */
.gr-list { list-style:none; margin:8px 0 0; padding:0; display:flex; flex-direction:column; gap:16px; }


.gr-item {
  display: grid;
  grid-template-columns: auto 1fr auto; /* avatar | content | actions */
  grid-template-rows: auto 0px;
  gap: 8px 12px;
  align-items: center;
  /* reducimos padding lateral para acercar contenido a los bordes */
  padding: 10px 8px;
  width: 100%;
  box-sizing: border-box;
  position: relative;
  transition: background .18s ease;
  border-radius: 10px;
}


.gr-item:hover { background: rgba(1,33,51,0.02); }

.gr-left {
  grid-column: 1 / 3;
  grid-row: 1 / 2;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0; /* crucial para que 1fr pueda reducirse y no generar overflow */
  cursor: pointer;
  padding-right: 8px; /* separación interna sin crear gap con la derecha */
}

.gr-left .gr-title {
  font-weight:700;
  font-size:14px;
  color:#163444;
  white-space:normal;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* collapsible description: occupies second row full width when expanded */
.gr-desc {
  grid-column: 1 / -1;
  grid-row: 2 / 3;
  max-height: 0;
  overflow: hidden;
  transition: max-height .32s ease, opacity .22s ease;
  opacity: 0;
  padding-top: 6px;
  font-size:12px;
  color:#6a6a6a;
  line-height:1.5;
}

/* Right column: ya no empuja el contenido con margin-left:auto */
.gr-right {
  grid-column: 3 / 4;
  grid-row: 1 / 2;
  /* quitamos margin-left:auto para que la columna no cree espacio lateral */
  margin-left: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
  justify-content: flex-end;
  min-width: 120px; /* fuerza un ancho razonable para chips/botón y evita colapso */
}


/* arrow rotation helper */
.risk-arrow { transition: transform .28s ease; }
.risk-item.is-collapsed .risk-arrow { transform: rotate(0deg); }
.risk-item:not(.is-collapsed) .risk-arrow { transform: rotate(180deg); }

/* expanded state: open second row without lateral reflow */
.risk-item.expanded { grid-template-rows: auto 1fr; }
.risk-item.expanded .gr-desc { max-height: 480px; opacity: 1; }

/* actions / bullets */
.gr-actions { margin:6px 0 0 18px; padding:0; }
.gr-actions li { margin-bottom:6px; font-size:13px; color:#5f666b; }

/* buttons */
.gr-btn { padding:6px 10px; border-radius:8px; font-weight:700; text-decoration:none; }

/* accessible toggle button */
.risk-toggle-btn { padding:6px; border-radius:8px; background:transparent; border:none; cursor:pointer; }
.risk-toggle-btn:focus { outline:2px solid rgba(239,127,27,0.18); outline-offset:2px; }

/* Responsive: adapt grid to narrow screens */
@media (max-width:768px) {
  .gr-item { grid-template-columns: 1fr auto; grid-template-rows: auto 0px; }
  .gr-left { grid-column: 1 / 2; }
  .gr-right { grid-column: 2 / 3; grid-row: 1 / 2; }
  .gr-desc { grid-column: 1 / -1; }
  .risk-item.expanded { grid-template-rows: auto 1fr; }
  .gr-list .gr-item.risk-item { flex-direction:row; gap:10px; }
  .gr-right { gap:6px; }
}

/* Contenedores de lista: quitar padding extra y usar gutter estable sin crear gap visible */
#card-riesgos-fuga .rf-list,
#card-riesgos-equipo .gr-list {
  max-height: min(42vh, 520px);
  overflow: auto;
  /* eliminamos padding-right que crea espacio visual antes del scrollbar */
  padding-right: 0;
  /* mantiene estabilidad del layout cuando aparece/desaparece el scrollbar,
     pero evita reservar un espacio extra visible al final */
  scrollbar-gutter: stable both-edges;
  -webkit-overflow-scrolling: touch;
}

#card-riesgos-fuga .rf-list::-webkit-scrollbar,
#card-riesgos-equipo .gr-list::-webkit-scrollbar { width: 8px; }
#card-riesgos-fuga .rf-list::-webkit-scrollbar-thumb,
#card-riesgos-equipo .gr-list::-webkit-scrollbar-thumb { background: rgba(1,33,51,0.18); border-radius: 6px; }
#card-riesgos-fuga .rf-list::-webkit-scrollbar-track,
#card-riesgos-equipo .gr-list::-webkit-scrollbar-track { background: transparent; }
@media (max-width: 1024px) {
  #card-riesgos-fuga .rf-list,
  #card-riesgos-equipo .gr-list { max-height: none; overflow: visible; padding-right: 0; }
}

/* CTA card */
.card-cta {
  position: relative;
  border: 1px solid rgba(1,33,51,0.08);
  background:
    linear-gradient(#fff,#fff) padding-box,
    linear-gradient(135deg, rgba(239,127,27,0.25), rgba(1,33,51,0.20)) border-box;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}
.card-cta .cta-head { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; color: var(--c-secondary); }
.card-cta .cta-icon { width: 28px; height: 28px; border-radius: 8px; background: var(--c-soft); display: grid; place-items: center; font-weight: 800; color: var(--c-accent); }
.card-cta .cta-copy { color: var(--c-body); font-size: 14px; line-height: 1.55; }

.cta-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.btn-cta-primary, .btn-cta-ghost {
  display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none;
}
.btn-cta-primary { background: var(--c-accent); color: #fff; border: 1px solid rgba(0,0,0,0.06); box-shadow: var(--shadow); }
.btn-cta-primary:hover { filter: brightness(0.98); }
.btn-cta-ghost { background: var(--c-soft); color: var(--c-secondary); border: 1px solid rgba(1,33,51,0.12); }
.btn-cta-ghost:hover { background: #fff; }

.cta-bullets { margin-top: 10px; color: #5f5f5f; font-size: 13px; display: grid; gap: 6px; }
.cta-bullets span::before { content: "• "; color: var(--c-secondary); font-weight: 700; }

/* CTA w/ provider logo */
.btn-cta-with-logo { display: inline-flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 14px; font-weight: 800; letter-spacing: .2px; box-shadow: var(--shadow); transition: transform .08s ease, box-shadow .12s ease, filter .12s ease; will-change: transform; }
.btn-cta-with-logo:hover { transform: translateY(-1px); filter: brightness(0.98); box-shadow: 0 10px 24px rgba(0,0,0,0.10); }
.btn-cta-with-logo .btn-logo { width: 20px; height: 20px; border-radius: 6px; background: #fff; object-fit: contain; box-shadow: 0 1px 2px rgba(0,0,0,0.10); }

.cta-with-logo { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-top: 18px; }
.cta-with-logo .btn-cta-primary { flex-shrink: 0; font-size: 15px; font-weight: 700; padding: 12px 20px; border-radius: 14px; background: var(--c-accent); color: #fff; border: none; box-shadow: var(--shadow); transition: transform .12s ease, box-shadow .15s ease; }
.cta-with-logo .btn-cta-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); filter: brightness(0.98); }
.cta-provider-logo { display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid rgba(1,33,51,0.08); border-radius: 12px; padding: 10px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform .12s ease; }
.cta-provider-logo:hover { transform: scale(1.02); }
.cta-provider-logo img { max-height: 40px; width: auto; display: block; object-fit: contain; }
@media (max-width: 768px) {
  .cta-with-logo { flex-direction: column; align-items: flex-start; }
  .cta-provider-logo { margin-top: 8px; padding: 8px 12px; border-radius: 10px; }
  .cta-provider-logo img { max-height: 36px; }
}

/* Team count badge */
.team-count-badge {
  display:inline-flex; align-items:center; gap:8px; background:linear-gradient(90deg,var(--c-accent),#ffb37a); color:#fff; font-weight:700; padding:6px 10px; border-radius:999px; font-size:13px; box-shadow:0 6px 18px rgba(2,34,51,0.12); margin-left:0px; vertical-align:middle;
}
.team-count-badge .count-num { background:rgba(255,255,255,0.12); padding:4px 8px; border-radius:12px; min-width:36px; text-align:center; font-feature-settings:"tnum" 1; }
@media (max-width:680px){ .team-count-badge { font-size:12px; padding:5px 8px; margin-left:8px; } .team-count-badge .count-num { min-width:30px; padding:3px 6px; } }

/* Subnav */
.subnav { width: 100%; background: transparent; border: 0; border-bottom: 1px solid rgba(1,33,51,0.08); box-shadow: 0 1px 0 rgba(1,33,51,0.05); }
.subnav-inner { max-width: 1400px; margin: 0 auto; padding: 6px clamp(16px,3vw,40px); }
.subnav-list { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; align-items: center; justify-items: center; list-style: none; gap: 0; padding: 6px 0; }
.subnav-link { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 8px; font-size: 14px; font-weight: 700; color: var(--c-accent); text-decoration: none; letter-spacing: .2px; background: transparent; border: 0; transition: opacity .15s ease, transform .12s ease; opacity: .9; }
.subnav-link:hover { opacity: 1; transform: translateY(-1px); }
.subnav-link.is-active { position: relative; opacity: 1; }
.subnav-link.is-active::after { content: ""; position: absolute; left: 25%; right: 25%; bottom: -6px; height: 2px; background: var(--c-accent); border-radius: 2px; opacity: .95; }
.subnav-link:focus-visible { outline: 2px solid color-mix(in srgb, var(--c-accent) 30%, transparent); outline-offset: 3px; }
@media (max-width: 768px){ .subnav-link { font-size: 13px; height: 34px; } .subnav-link.is-active::after { left: 20%; right: 20%; bottom: -4px; } }

/* Utility */
.risk-arrow { transition: transform .28s ease; }
.gr-title { line-height:1.1; }

/* End of stylesheet */
</style>

  
</head>
<body>

  <!-- Barra superior -->
<?php require __DIR__ . '/a-header-desktop-brand.php'; ?>






















  <!-- Contenido principal -->
  <div class="wrap">

<?php if (!empty($mostrar_cta_emergencia)): ?>
    <div class="card card-cta">
      <div class="cta-head">
        <div class="cta-icon">!</div>
        <h3 style="margin:0;">Intervención recomendada</h3>
      </div>
      <p class="cta-copy">
        Te ayudamos a convertir los datos del dashboard en un <strong>plan claro</strong> para
        <strong>reducir riesgos de fuga</strong> y <strong>elevar la conexión</strong> entre el equipo.
      </p>
      <div class="cta-actions cta-with-logo">
        <a class="btn-cta-primary"
           href="mailto:<?php echo h($ctaEmail); ?>?subject=Diagn%C3%B3stico%20Val%C3%ADrica&body=Hola%2C%20quiero%20agendar%20un%20diagn%C3%B3stico%20guiado.%20Mi%20empresa%20es%3A%20<?php echo rawurlencode((string)$empresa); ?>"
           rel="noopener"
           title="Escribir a nuestro equipo">
          Habla con nuestro equipo
        </a>
        <div class="cta-provider-logo">
          <img src="<?php echo h($ctaLogo); ?>"
               alt="Logo del proveedor"
               onerror="this.onerror=null;this.src='https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';">
        </div>
      </div>
    </div>
<?php endif; ?>

    <div class="grid-sidebar">

<!-- Columna izquierda: Riesgos de fuga -->
<section>
  <div class="card" id="card-riesgos-fuga">
<h3>Riesgos de fuga</h3>



  
  
  
  <!-- Tags suaves debajo del texto -->
  <div class="vl-tags" style="margin-bottom:16px;">
    <span class="vl-tag">Posible Fuga de Talentos</span>
  </div>
  
  <p style="margin-bottom:16px;">Miembros del equipo con <strong>alto riesgo</strong> de fuga.</p>

  
  <?php if (empty($riesgos_sorted)): ?>
    <p style="opacity:.75;">No hay datos del equipo para calcular riesgo de fuga.</p>
  <?php else: ?>
    <ul class="rf-list">
      <?php foreach ($riesgos_sorted as $r): ?>
        
        
        <li class="rf-item">
  <!-- Columna IZQUIERDA: avatar + nombre + cargo -->
  <div class="rf-left">
    <div class="rf-avatar" aria-hidden="true"><?php echo htmlspecialchars($r['iniciales']); ?></div>

    <div class="rf-id">
      <div class="rf-name"><?php echo htmlspecialchars($r['nombre']); ?></div>
      <div class="rf-role"><?php echo htmlspecialchars($r['cargo']); ?></div>
    </div>
  </div>

  <!-- Columna CENTRO: label de riesgo (chip) + alerta si aplica -->
  <div class="rf-center">
    <?php if ($r['riesgo_total'] >= 60): ?>
      <span class="rf-alert" title="Riesgo alto" aria-label="Riesgo alto" role="img">⚠️</span>
    <?php endif; ?>
    <span class="rf-chip" style="--rf-color: <?php echo $r['nivel_color']; ?>;">
      <?php echo htmlspecialchars($r['nivel']); ?> — <?php echo (float)$r['riesgo_total']; ?>%
    </span>
  </div>

  <!-- Columna DERECHA: batería (más grande) + botón -->
  <div class="rf-right">
    <img src="<?php echo htmlspecialchars($r['battery_icon']); ?>" alt="Energía" class="rf-battery rf-battery-lg" />
    <a class="rf-btn" href="dashboard_empleado.php?id=<?php echo (int)$r['id']; ?>">Ver perfil</a>
  </div>
</li>


      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

</section>

<!-- Columna derecha: Áreas de oportunidad -->
<section>
  <div class="card" id="card-riesgos-equipo">
  <h3>Áreas de oportunidad</h3>

  <!-- Tags suaves -->
  <div class="vl-tags" style="margin-bottom:16px;">
    <span class="vl-tag">Desincronías clave</span>
    <span class="vl-tag">Prioriza acciones</span>
  </div>

  <p style="margin-bottom:16px;">
    Lista priorizada de <strong>riesgos colectivos</strong> detectados (equipo ↔ cultura/empresa).
    Úsala para definir <em>quick wins</em> y planes específicos.
  </p>

  <?php if (empty($riesgos_equipo)): ?>
    <p style="opacity:.75;">No hay riesgos colectivos detectables con los datos actuales.</p>
  <?php else: ?>
    <ul class="gr-list">
      <?php foreach ($riesgos_equipo as $g): ?>
        
        <li class="gr-item risk-item is-collapsed" aria-expanded="false" data-score="<?php echo (float)$g['score']; ?>">
  <!-- IZQUIERDA: título visible -->
  <div class="gr-left" style="cursor:pointer" data-toggle="toggle-desc-<?php echo md5($g['titulo'].$g['score']); ?>">
    <div class="gr-title"><?php echo htmlspecialchars($g['titulo']); ?></div>
    <!-- descripción oculta por defecto: la dejamos en el DOM para lectura por JS -->
    <div id="toggle-desc-<?php echo md5($g['titulo'].$g['score']); ?>" class="gr-desc" hidden>
      <?php
        // limpiamos HTML y separamos "Qué pasa" y "Haz esto" si vienen juntos con '|'
        $raw = (string)($g['descripcion'] ?? '');
        $clean = trim(strip_tags($raw));
        $parts = preg_split('/\s*\|\s*/u', $clean, 2);
        if (count($parts) === 2) {
          $q = trim(preg_replace('/^Qué\s*pasa\s*:\s*/iu','',$parts[0]));
          $h = trim(preg_replace('/^Haz\s*esto\s*:\s*/iu','',$parts[1]));
        } else {
          $q = $clean;
          $h = '';
        }
      ?>
      <div class="gr-what"><strong>Qué pasa</strong><div class="gr-what-text"><?php echo nl2br(htmlspecialchars($q)); ?></div></div>
      <?php if ($h !== ''): ?>
        <div class="gr-do" style="margin-top:8px;">
          <strong>Haz esto</strong>
          <?php
            $acts = preg_split('/\s*\.\s*/u', $h);
            $acts = array_filter(array_map('trim',$acts));
            if (count($acts) > 1) {
              echo '<ul class="gr-actions">';
              foreach ($acts as $a) if ($a !== '') echo '<li>'.htmlspecialchars($a).'.</li>';
              echo '</ul>';
            } else {
              echo '<div class="gr-do-text">'.nl2br(htmlspecialchars($h)).'</div>';
            }
          ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: grupo compacto alineado a la derecha: chip + flecha -->
  <div class="gr-right" role="group" aria-label="Nivel y control">
    <span class="gr-chip" style="--gr-color: <?php echo $g['color']; ?>;">
      <?php echo htmlspecialchars($g['nivel']); ?> — <?php echo (float)$g['score']; ?>%
    </span>

    <button class="risk-toggle-btn" type="button" aria-controls="toggle-desc-<?php echo md5($g['titulo'].$g['score']); ?>" aria-expanded="false" title="Expandir detalle">
      <svg class="risk-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" role="img">
        <path d="M6 9l6 6 6-6" stroke="#EF7F1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>

</li>

        
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>


</section>

    </div><!-- /grid-sidebar -->

    <!-- Fila inferior: Motivadores + DISC (opcionales, debajo del grid) -->
    <div class="grid-sidebar">

      <section>
<?php if ($hay_motivadores_equipo): ?>
  <div class="card" id="card-motivadores-equipo">
    <h3>Motivadores del equipo</h3>
    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Valores</span>
      <span class="vl-tag">Qué impulsa decisiones</span>
    </div>
    <p style="margin-bottom:16px;">
      Distribución promedio de motivadores (0–100). Úsalos para alinear roles, reconocimientos y rituales.
    </p>
    <canvas id="chartValuesEquipo" style="max-height:340px;"></canvas>
  </div>
<?php endif; ?>
      </section>

      <section>
<?php if ($hay_disc_equipo): ?>
  <div class="card" id="card-disc-equipo">
    <h3>Mapa DISC del equipo</h3>
    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Natural vs Adaptado</span>
      <span class="vl-tag">Comportamiento Observable</span>
    </div>
    <p style="margin-bottom:16px;">
      Promedios por factor. Compara el estilo <strong>natural (auth)</strong> y el <strong>adaptado (mod)</strong> del equipo.
    </p>
    <canvas id="chartDiscEquipo" style="max-height:320px;"></canvas>
  </div>
<?php endif; ?>
      </section>

    </div><!-- /grid-sidebar charts -->

  </div><!-- /wrap -->
  
  
  
  
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  <!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS --><!-- SCRIPTS -->
  
  
  
  
  
<!-- Script del círculo de alineación movido a a-analisis-equipos.php -->


  













<script>
(function(){
  // === Paleta coherente con tu círculo ===
  const css        = getComputedStyle(document.documentElement);
  const TEXT_COLOR = (css.getPropertyValue("--c-body") || "#474644").trim();
  const ACCENT     = "#EF7F1B";              // Naranja Valírica
  const SECONDARY  = "#184656";              // Azul secundario (dataset 2)
  const GREEN      = "#184656";              // Verde de niveles "ok" ya usado en UI
  const GRAY_SOFT  = "rgba(0,0,0,0.06)";     // Gris tenue (ejes del círculo)

  // Defaults de Chart.js para tu look & feel
  if (window.Chart) {
    Chart.defaults.font.family = 'gelica, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif';
    Chart.defaults.color = TEXT_COLOR;
    Chart.defaults.elements.line.borderWidth = 2;
    Chart.defaults.elements.point.radius = 2;
    Chart.defaults.elements.point.hoverRadius = 3;
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.tooltip.backgroundColor = "#012133";
    Chart.defaults.plugins.tooltip.titleColor = "#FFFFFF";
    Chart.defaults.plugins.tooltip.bodyColor  = "#FFFFFF";
    Chart.defaults.plugins.tooltip.borderColor = "rgba(255,255,255,0.08)";
    Chart.defaults.plugins.tooltip.borderWidth = 1;
  }

  // ===============================
  // DISC — Barras (ejes verdes, grid gris)
  // ===============================
  (function(){
    const ctx = document.getElementById('chartDiscEquipo');
    if (!ctx || !window.Chart) return;

    const DISC = <?php echo json_encode($disc_avg, JSON_NUMERIC_CHECK); ?>;
    const labels = ['D','I','S','C'];
    const auth = [DISC.da||0, DISC.ia||0, DISC.sa||0, DISC.ca||0];
    const mod  = [DISC.dm||0, DISC.im||0, DISC.sm||0, DISC.cm||0];

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label:'Natural (auth)',
            data: auth,
            borderColor: ACCENT,
            backgroundColor: 'rgba(239,127,27,0.12)',
            borderWidth: 2
          },
          {
            label:'Adaptado (mod)',
            data: mod,
            borderColor: SECONDARY,
            backgroundColor: 'rgba(24,70,86,0.10)',
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive:true,
        maintainAspectRatio:false,
        scales: {
          x: {
            // Bordes del eje en verde
            border: { color: GREEN, width: 2 },
            grid: {
              // Líneas de fondo en gris tenue y delgadas
              color: GRAY_SOFT,
              lineWidth: 1,
              drawOnChartArea: true,
              drawTicks: false
            },
            ticks:{ color: TEXT_COLOR }
          },
          y: {
            beginAtZero:true, max:100,
            // Bordes del eje en verde
            border: { color: GREEN, width: 2 },
            grid: {
              color: GRAY_SOFT,
              lineWidth: 1,
              drawTicks: false
            },
            ticks:{ stepSize:10, color: TEXT_COLOR }
          }
        },
        plugins: {
          legend: { position:'top' },
          tooltip: {
            callbacks: {
              label:(ctx)=> `${ctx.dataset.label}: ${Math.round(ctx.parsed.y)}`
            }
          }
        },
        datasets: {
          bar: { borderRadius: 8 } // esquinas suaves coherentes con tus cards
        }
      }
    });
  })();

  // ===============================
  // Motivadores — Radar
  // - Solo el anillo exterior en verde
  // - Resto de anillos y rayos en gris tenue
  // ===============================
  (function(){
    const ctx = document.getElementById('chartValuesEquipo');
    if (!ctx || !window.Chart) return;

    const VALUES = <?php echo json_encode($values_avg, JSON_NUMERIC_CHECK); ?>;
    const labels = ['Estético','Económico','Individualista','Político','Altruista','Normativo','Teórico'];
    const data = [
      VALUES.aes||0, VALUES.eco||0, VALUES.ind||0,
      VALUES.pol||0, VALUES.alt||0, VALUES.reg||0, VALUES.the||0
    ];

    // Usamos opciones "scriptables" para colorear solo el anillo exterior en verde
    new Chart(ctx, {
      type: 'radar',
      data: {
        labels,
        datasets: [
          {
            label: 'Promedio equipo',
            data,
            borderColor: ACCENT,                      // contorno naranja
            backgroundColor: 'rgba(239,127,27,0.12)', // fondo naranja tenue
            pointBackgroundColor: ACCENT,
            pointBorderColor: '#fff',
            fill: true     
          }
        ]
      },
      options: {
        responsive:true,
        maintainAspectRatio:false,
        scales: {
          r: {
            beginAtZero: true,
            suggestedMax: 100,
            ticks: {
              display: false
            },
            // Coloreamos grid y grosor por anillo:
            grid: {
              color: (ctx) => {
                // último índice = anillo exterior
                const scale = ctx.chart.scales.r;
                const last  = scale.ticks.length - 1;
                return (ctx.index === last) ? GREEN : GRAY_SOFT;
              },
              lineWidth: (ctx) => {
                const scale = ctx.chart.scales.r;
                const last  = scale.ticks.length - 1;
                return (ctx.index === last) ? 2 : 1;
              }
            },
            // Rayos (líneas angulares) en gris tenue
            angleLines: {
              color: GRAY_SOFT,
              lineWidth: 1
            },
            // Etiquetas de eje
            pointLabels: {
              color: TEXT_COLOR,
              font: { weight: 700 }
            }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx)=> `${ctx.label}: ${Math.round(ctx.parsed.r)}`
            }
          }
        },
        elements: { line: { tension: 0 } }
      }
    });
  })();

})();
</script>





<script>
document.addEventListener('click', function(e){
  // Detectamos clicks sobre el área izquierda (gr-left) o el botón de flecha
  const left = e.target.closest('.gr-left');
  const arrowBtn = e.target.closest('.risk-toggle-btn');
  if (!left && !arrowBtn) return;

  const li = left ? left.closest('.gr-item') : arrowBtn.closest('.gr-item');
  if (!li) return;

  const panel = li.querySelector('.gr-desc');
  if (!panel) return;

  const btn = li.querySelector('.risk-toggle-btn');

  const isExpanded = li.classList.contains('expanded');

  if (!isExpanded) {
    // Abrir
    li.classList.remove('is-collapsed');
    li.classList.add('expanded');
    li.setAttribute('aria-expanded', 'true');

    // Mostrar panel y animar altura
    panel.hidden = false;
    // fuerza recalculo de altura para la transición
    panel.style.maxHeight = panel.scrollHeight + 'px';
    panel.style.opacity = 1;

    if (btn) btn.setAttribute('aria-expanded', 'true');

    if (window.innerWidth < 900) {
      // en móviles centramos el scroll
      panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  } else {
    // Cerrar
    li.classList.add('is-collapsed');
    li.classList.remove('expanded');
    li.setAttribute('aria-expanded', 'false');

    // Animar cierre y luego esconder del DOM (para accesibilidad)
    panel.style.maxHeight = panel.scrollHeight + 'px'; // aseguro valor inicial para la transición
    // forzamos reflow para que la transición coja el valor anterior
    // eslint-disable-next-line no-unused-expressions
    panel.offsetHeight;
    panel.style.maxHeight = '0px';
    panel.style.opacity = 0;

    if (btn) btn.setAttribute('aria-expanded', 'false');

    // tras la transición, aplicamos hidden para que lectura de screen-readers no lea contenido oculto
    setTimeout(() => {
      if (!li.classList.contains('expanded')) {
        panel.hidden = true;
        panel.style.maxHeight = null;
      }
    }, 300); // coincide con tus transiciones CSS (.32s / .22s)
  }
});
</script>








</body>
</html>