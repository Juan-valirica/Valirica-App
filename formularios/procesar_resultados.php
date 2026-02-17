<?php
include('../config.php');


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
ob_start(); // evita "headers already sent"


$equipo_id = isset($_GET['equipo_id']) ? intval($_GET['equipo_id']) : 0;
if ($equipo_id <= 0) die("⛔ ID de equipo no válido.");

$respuestas = [];
$sql = "SELECT pregunta_id, respuesta FROM detalle_formulario WHERE equipo_id = $equipo_id";
$res = mysqli_query($conn, $sql);
while ($fila = mysqli_fetch_assoc($res)) {
    $pid = intval($fila['pregunta_id']);
    $val = floatval($fila['respuesta']);
    $respuestas[$pid] = $val;
}


// RECOGE TODAS LAS RESPUESTAS DEL FORMULARIO
for ($i = 1; $i <= 210; $i++) {
    $key = "respuesta_$i";
    if (isset($_POST[$key])) {
        $respuestas[$i] = floatval($_POST[$key]);
    }
}

// INVERTIR ESCALA MASLOW: 1↔5, 2↔4, 3→3
for ($i = 191; $i <= 200; $i++) {
    if (isset($respuestas[$i])) {
        switch ($respuestas[$i]) {
            case 1: $respuestas[$i] = 5; break;
            case 2: $respuestas[$i] = 4; break;
            case 3: $respuestas[$i] = 3; break;
            case 4: $respuestas[$i] = 2; break;
            case 5: $respuestas[$i] = 1; break;
        }
    }
}


// Guarda las respuestas en detalle_formulario
mysqli_query($conn, "DELETE FROM detalle_formulario WHERE equipo_id = $equipo_id");

$fecha_actual = date('Y-m-d H:i:s');

foreach ($respuestas as $pid => $valor) {
    $pid = intval($pid);
    $valor = floatval($valor);

    // Escalar valores solo para preguntas DISC (IDs del 1 al 32)
    if ($pid >= 1 && $pid <= 32) {
        if ($valor == 1) $valor = 1.0;
        elseif ($valor == 2) $valor = 0.8;
        elseif ($valor == 3) $valor = 0.5;
        elseif ($valor == 4) $valor = 0.2;
    }

    $sql_insert = "INSERT INTO detalle_formulario (equipo_id, pregunta_id, respuesta, fecha_respuesta) 
                   VALUES ($equipo_id, $pid, $valor, '$fecha_actual')";
    mysqli_query($conn, $sql_insert);
}

// PROCESAMIENTO DISC
$valores['disc_d'] = 0;
if (isset($respuestas[1])) $valores['disc_d'] += $respuestas[1];
if (isset($respuestas[2])) $valores['disc_d'] += $respuestas[2];
if (isset($respuestas[3])) $valores['disc_d'] += $respuestas[3];
if (isset($respuestas[4])) $valores['disc_d'] += $respuestas[4];
if (isset($respuestas[5])) $valores['disc_d'] += $respuestas[5];
if (isset($respuestas[6])) $valores['disc_d'] += $respuestas[6];
if (isset($respuestas[7])) $valores['disc_d'] += $respuestas[7];
if (isset($respuestas[8])) $valores['disc_d'] += $respuestas[8];
$valores['disc_d'] = round($valores['disc_d'] / 8, 2);

$valores['disc_i'] = 0;
if (isset($respuestas[9])) $valores['disc_i'] += $respuestas[9];
if (isset($respuestas[10])) $valores['disc_i'] += $respuestas[10];
if (isset($respuestas[11])) $valores['disc_i'] += $respuestas[11];
if (isset($respuestas[12])) $valores['disc_i'] += $respuestas[12];
if (isset($respuestas[13])) $valores['disc_i'] += $respuestas[13];
if (isset($respuestas[14])) $valores['disc_i'] += $respuestas[14];
if (isset($respuestas[15])) $valores['disc_i'] += $respuestas[15];
if (isset($respuestas[16])) $valores['disc_i'] += $respuestas[16];
$valores['disc_i'] = round($valores['disc_i'] / 8, 2);

$valores['disc_s'] = 0;
if (isset($respuestas[17])) $valores['disc_s'] += $respuestas[17];
if (isset($respuestas[18])) $valores['disc_s'] += $respuestas[18];
if (isset($respuestas[19])) $valores['disc_s'] += $respuestas[19];
if (isset($respuestas[20])) $valores['disc_s'] += $respuestas[20];
if (isset($respuestas[21])) $valores['disc_s'] += $respuestas[21];
if (isset($respuestas[22])) $valores['disc_s'] += $respuestas[22];
if (isset($respuestas[23])) $valores['disc_s'] += $respuestas[23];
if (isset($respuestas[24])) $valores['disc_s'] += $respuestas[24];
$valores['disc_s'] = round($valores['disc_s'] / 8, 2);

$valores['disc_c'] = 0;
if (isset($respuestas[25])) $valores['disc_c'] += $respuestas[25];
if (isset($respuestas[26])) $valores['disc_c'] += $respuestas[26];
if (isset($respuestas[27])) $valores['disc_c'] += $respuestas[27];
if (isset($respuestas[28])) $valores['disc_c'] += $respuestas[28];
if (isset($respuestas[29])) $valores['disc_c'] += $respuestas[29];
if (isset($respuestas[30])) $valores['disc_c'] += $respuestas[30];
if (isset($respuestas[31])) $valores['disc_c'] += $respuestas[31];
if (isset($respuestas[32])) $valores['disc_c'] += $respuestas[32];
$valores['disc_c'] = round($valores['disc_c'] / 8, 2);

// PROCESAMIENTO CONFLICTO
$valores['conflicto_competitivo'] = 0;
if (isset($respuestas[161])) $valores['conflicto_competitivo'] += $respuestas[161];
if (isset($respuestas[162])) $valores['conflicto_competitivo'] += $respuestas[162];
if (isset($respuestas[163])) $valores['conflicto_competitivo'] += $respuestas[163];
$valores['conflicto_competitivo'] = round($valores['conflicto_competitivo'] / 3, 2);

$valores['conflicto_colaborativo'] = 0;
if (isset($respuestas[164])) $valores['conflicto_colaborativo'] += $respuestas[164];
if (isset($respuestas[165])) $valores['conflicto_colaborativo'] += $respuestas[165];
if (isset($respuestas[166])) $valores['conflicto_colaborativo'] += $respuestas[166];
$valores['conflicto_colaborativo'] = round($valores['conflicto_colaborativo'] / 3, 2);

$valores['conflicto_evitativo'] = 0;
if (isset($respuestas[167])) $valores['conflicto_evitativo'] += $respuestas[167];
if (isset($respuestas[168])) $valores['conflicto_evitativo'] += $respuestas[168];
if (isset($respuestas[169])) $valores['conflicto_evitativo'] += $respuestas[169];
$valores['conflicto_evitativo'] = round($valores['conflicto_evitativo'] / 3, 2);

$valores['conflicto_complaciente'] = 0;
if (isset($respuestas[170])) $valores['conflicto_complaciente'] += $respuestas[170];
if (isset($respuestas[171])) $valores['conflicto_complaciente'] += $respuestas[171];
if (isset($respuestas[172])) $valores['conflicto_complaciente'] += $respuestas[172];
$valores['conflicto_complaciente'] = round($valores['conflicto_complaciente'] / 3, 2);

$valores['conflicto_negociador'] = 0;
if (isset($respuestas[173])) $valores['conflicto_negociador'] += $respuestas[173];
if (isset($respuestas[174])) $valores['conflicto_negociador'] += $respuestas[174];
if (isset($respuestas[175])) $valores['conflicto_negociador'] += $respuestas[175];
$valores['conflicto_negociador'] = round($valores['conflicto_negociador'] / 3, 2);

// PROCESAMIENTO MASLOW
$valores['maslow_fis'] = 0;
if (isset($respuestas[191])) $valores['maslow_fis'] += $respuestas[191];
if (isset($respuestas[192])) $valores['maslow_fis'] += $respuestas[192];
$valores['maslow_fis'] = round($valores['maslow_fis'] / 2, 2);

$valores['maslow_seg'] = 0;
if (isset($respuestas[193])) $valores['maslow_seg'] += $respuestas[193];
if (isset($respuestas[194])) $valores['maslow_seg'] += $respuestas[194];
$valores['maslow_seg'] = round($valores['maslow_seg'] / 2, 2);

$valores['maslow_afi'] = 0;
if (isset($respuestas[195])) $valores['maslow_afi'] += $respuestas[195];
if (isset($respuestas[196])) $valores['maslow_afi'] += $respuestas[196];
$valores['maslow_afi'] = round($valores['maslow_afi'] / 2, 2);

$valores['maslow_rec'] = 0;
if (isset($respuestas[197])) $valores['maslow_rec'] += $respuestas[197];
if (isset($respuestas[198])) $valores['maslow_rec'] += $respuestas[198];
$valores['maslow_rec'] = round($valores['maslow_rec'] / 2, 2);

$valores['maslow_aut'] = 0;
if (isset($respuestas[199])) $valores['maslow_aut'] += $respuestas[199];
if (isset($respuestas[200])) $valores['maslow_aut'] += $respuestas[200];
$valores['maslow_aut'] = round($valores['maslow_aut'] / 2, 2);




$valores = [
    'disc_d' => 0, 'disc_i' => 0, 'disc_s' => 0, 'disc_c' => 0,
    'mbti_e' => 0, 'mbti_i' => 0, 'mbti_s' => 0, 'mbti_n' => 0,
    'mbti_t' => 0, 'mbti_f' => 0, 'mbti_j' => 0, 'mbti_p' => 0,
    'mbti_a' => 0, 'mbti_c' => 0,
    'hofstede_poder' => 0, 'hofstede_individualismo' => 0, 'hofstede_resultados' => 0,
    'hofstede_incertidumbre' => 0, 'hofstede_largo_plazo' => 0, 'hofstede_espontaneidad' => 0,
    'conflicto_colaborativo' => 0, 'conflicto_competitivo' => 0, 'conflicto_complaciente' => 0,
    'conflicto_evitativo' => 0, 'conflicto_negociador' => 0,
    'visual' => 0, 'auditivo' => 0, 'kinestesico' => 0 ,
    
    'maslow_fis' => 0, 'maslow_seg' => 0, 'maslow_afi' => 0, 'maslow_rec' => 0, 'maslow_aut' => 0,
'pink_purp' => 0, 'pink_auto' => 0, 'pink_maes' => 0, 'pink_fis' => 0, 'pink_rel' => 0

    
];



// DISC
$dimensiones_disc = [
    'disc_d' => [1,2,3,4,5,6,7,8],
    'disc_i' => [9,10,11,12,13,14,15,16],
    'disc_s' => [17,18,19,20,21,22,23,24],
    'disc_c' => [25,26,27,28,29,30,31,32],
];
foreach ($dimensiones_disc as $dim => $preguntas) {
    $suma = 0;
    $conteo = 0;
    foreach ($preguntas as $pid) {
        if (isset($respuestas[$pid])) {
            $suma += $respuestas[$pid];
            $conteo++;
        }
    }
    $valores[$dim] = $conteo > 0 ? round($suma / $conteo, 2) : 0;
}

// MBTI
$dimensiones_mbti = [
    'mbti_e' => [33,34,35,36,37,38,39,40],
    'mbti_i' => [41,42,43,44,45,46,47,48],
    'mbti_s' => [49,50,51,52,53,54,55,56],
    'mbti_n' => [57,58,59,60,61,62,63,64],
    'mbti_t' => [65,66,67,68,69,70,71,72],
    'mbti_f' => [73,74,75,76,77,78,79,80],
    'mbti_j' => [81,82,83,84,85,86,87,88],
    'mbti_p' => [89,90,91,92,93,94,95,96],
    'mbti_a' => [97,98,99,100,101,102,103,104],
    'mbti_c' => [105,106,107,108,109,110,111,112],
];
calcular_mbti_global_intensidad($respuestas, $valores);

// Hofstede
$valores['hofstede_poder'] = calcular_hofstede_intensidad([[113, 1], [119, 1], [125, -1], [131, 1], [137, -1], [143, -1], [149, 1], [155, -1]], $respuestas);
$valores['hofstede_individualismo'] = calcular_hofstede_intensidad([[114, 1], [120, 1], [126, -1], [132, 1], [138, -1], [144, -1], [150, 1], [156, -1]], $respuestas);
$valores['hofstede_resultados'] = calcular_hofstede_intensidad([[115, 1], [121, 1], [127, -1], [133, 1], [139, -1], [145, -1], [151, 1], [157, -1]], $respuestas);
$valores['hofstede_incertidumbre'] = calcular_hofstede_intensidad([[116, 1], [122, 1], [128, -1], [134, 1], [140, -1], [146, -1], [152, 1], [158, -1]], $respuestas);
$valores['hofstede_largo_plazo'] = calcular_hofstede_intensidad([[117, 1], [123, 1], [129, -1], [135, 1], [141, -1], [147, -1], [153, 1], [159, -1]], $respuestas);
$valores['hofstede_espontaneidad'] = calcular_hofstede_intensidad([[118, 1], [124, 1], [130, -1], [136, 1], [142, -1], [148, -1], [154, 1], [160, -1]], $respuestas);


// CONFLICTO
$dimensiones_conflicto = [
    'conflicto_colaborativo' => [161,162,163],
    'conflicto_competitivo' => [164,165,166],
    'conflicto_complaciente' => [167,168,169],
    'conflicto_evitativo' => [170,171,172],
    'conflicto_negociador' => [173,174,175],
];
foreach ($dimensiones_conflicto as $dim => $preguntas) {
    $suma = 0;
    $conteo = 0;
    foreach ($preguntas as $pid) {
        if (isset($respuestas[$pid])) {
            $suma += $respuestas[$pid];
            $conteo++;
        }
    }
    $valores[$dim] = $conteo > 0 ? round($suma / $conteo, 2) : 0;
}

// SENSORIAL
$dimensiones_sensorial = [
    'visual' => [176,177,178,179,180],
    'auditivo' => [181,182,183,184,185],
    'kinestesico' => [186,187,188,189,190],
    
    
    
];
foreach ($dimensiones_sensorial as $dim => $preguntas) {
    $suma = 0;
    $conteo = 0;
    foreach ($preguntas as $pid) {
        if (isset($respuestas[$pid])) {
            $suma += $respuestas[$pid];
            $conteo++;
        }
    }
    $valores[$dim] = $conteo > 0 ? round($suma / $conteo, 2) : 0;
}


// Procesamiento de Maslow

$maslow_fis = (isset($respuestas[191]) && isset($respuestas[192])) ? ($respuestas[191] + $respuestas[192]) / 2 : 0;
$maslow_seg = (isset($respuestas[193]) && isset($respuestas[194])) ? ($respuestas[193] + $respuestas[194]) / 2 : 0;
$maslow_afi = (isset($respuestas[195]) && isset($respuestas[196])) ? ($respuestas[195] + $respuestas[196]) / 2 : 0;
$maslow_rec = (isset($respuestas[197]) && isset($respuestas[198])) ? ($respuestas[197] + $respuestas[198]) / 2 : 0;
$maslow_aut = (isset($respuestas[199]) && isset($respuestas[200])) ? ($respuestas[199] + $respuestas[200]) / 2 : 0;

// Procesamiento de Pink

$pink_purp = (isset($respuestas[201]) && isset($respuestas[202])) ? ($respuestas[201] + $respuestas[202]) / 2 : 0;
$pink_auto = (isset($respuestas[203]) && isset($respuestas[204])) ? ($respuestas[203] + $respuestas[204]) / 2 : 0;
$pink_maes = (isset($respuestas[205]) && isset($respuestas[206])) ? ($respuestas[205] + $respuestas[206]) / 2 : 0;
$pink_fis  = (isset($respuestas[207]) && isset($respuestas[208])) ? ($respuestas[207] + $respuestas[208]) / 2 : 0;
$pink_rel  = (isset($respuestas[209]) && isset($respuestas[210])) ? ($respuestas[209] + $respuestas[210]) / 2 : 0;


$valores['maslow_fis'] = $maslow_fis;
$valores['maslow_seg'] = $maslow_seg;
$valores['maslow_afi'] = $maslow_afi;
$valores['maslow_rec'] = $maslow_rec;
$valores['maslow_aut'] = $maslow_aut;
$valores['pink_purp']  = $pink_purp;
$valores['pink_auto']  = $pink_auto;
$valores['pink_maes']  = $pink_maes;
$valores['pink_fis']   = $pink_fis;
$valores['pink_rel']   = $pink_rel;



// Guardar
$campos = [];
foreach ($valores as $k => $v) { $campos[] = "$k = $v"; }
$sql_update = "UPDATE equipo SET " . implode(", ", $campos) . " WHERE id = $equipo_id";
mysqli_query($conn, $sql_update);


// FUNCIONES


function calcular_mbti_global_intensidad($respuestas, &$valores) {
    $dimensiones = [
        33 => 'mbti_e', 34 => 'mbti_e', 35 => 'mbti_e', 36 => 'mbti_e',
        37 => 'mbti_e', 38 => 'mbti_e', 39 => 'mbti_e', 40 => 'mbti_e',
        41 => 'mbti_i', 42 => 'mbti_i', 43 => 'mbti_i', 44 => 'mbti_i',
        45 => 'mbti_i', 46 => 'mbti_i', 47 => 'mbti_i', 48 => 'mbti_i',
        49 => 'mbti_s', 50 => 'mbti_s', 51 => 'mbti_s', 52 => 'mbti_s',
        53 => 'mbti_s', 54 => 'mbti_s', 55 => 'mbti_s', 56 => 'mbti_s',
        57 => 'mbti_n', 58 => 'mbti_n', 59 => 'mbti_n', 60 => 'mbti_n',
        61 => 'mbti_n', 62 => 'mbti_n', 63 => 'mbti_n', 64 => 'mbti_n',
        65 => 'mbti_t', 66 => 'mbti_t', 67 => 'mbti_t', 68 => 'mbti_t',
        69 => 'mbti_t', 70 => 'mbti_t', 71 => 'mbti_t', 72 => 'mbti_t',
        73 => 'mbti_f', 74 => 'mbti_f', 75 => 'mbti_f', 76 => 'mbti_f',
        77 => 'mbti_f', 78 => 'mbti_f', 79 => 'mbti_f', 80 => 'mbti_f',
        81 => 'mbti_j', 82 => 'mbti_j', 83 => 'mbti_j', 84 => 'mbti_j',
        85 => 'mbti_j', 86 => 'mbti_j', 87 => 'mbti_j', 88 => 'mbti_j',
        89 => 'mbti_p', 90 => 'mbti_p', 91 => 'mbti_p', 92 => 'mbti_p',
        93 => 'mbti_p', 94 => 'mbti_p', 95 => 'mbti_p', 96 => 'mbti_p',
        97 => 'mbti_a', 98 => 'mbti_a', 99 => 'mbti_a', 100 => 'mbti_a',
        101 => 'mbti_a', 102 => 'mbti_a', 103 => 'mbti_a', 104 => 'mbti_a',
        105 => 'mbti_c', 106 => 'mbti_c', 107 => 'mbti_c', 108 => 'mbti_c',
        109 => 'mbti_c', 110 => 'mbti_c', 111 => 'mbti_c', 112 => 'mbti_c',
    ];

    $contraparte = [
        'mbti_e' => 'mbti_i', 'mbti_i' => 'mbti_e',
        'mbti_s' => 'mbti_n', 'mbti_n' => 'mbti_s',
        'mbti_t' => 'mbti_f', 'mbti_f' => 'mbti_t',
        'mbti_j' => 'mbti_p', 'mbti_p' => 'mbti_j',
        'mbti_a' => 'mbti_c', 'mbti_c' => 'mbti_a'
    ];

    foreach ($respuestas as $pid => $valor) {
        if (isset($dimensiones[$pid])) {
            $dim = $dimensiones[$pid];
            $opuesta = $contraparte[$dim];
            switch ($valor) {
                case 1:
                    $valores[$opuesta] += 2;
                    break;
                case 2:
                    $valores[$opuesta] += 1;
                    break;
                case 3:
                    $valores[$dim] += 1;
                    $valores[$opuesta] += 1;
                    break;
                case 4:
                    $valores[$dim] += 1;
                    break;
                case 5:
                    $valores[$dim] += 2;
                    break;
            }
        }
    }
}




function calcular_hofstede_intensidad($preguntas, $respuestas) {
    $suma = 0;
    $conteo = 0;

    foreach ($preguntas as [$pid, $orientacion]) {
        if (isset($respuestas[$pid])) {
            $valor = intval($respuestas[$pid]);

            // Asignación de puntos base
            switch ($valor) {
                case 1: $puntos = -2; break;
                case 2: $puntos = -1; break;
                case 3: $puntos = 0;  break;
                case 4: $puntos = 1;  break;
                case 5: $puntos = 2;  break;
                default: $puntos = 0;
            }

            // Se ajusta según orientación
            $suma += $puntos * $orientacion;
            $conteo++;
        }
    }

    return $conteo > 0 ? round($suma / $conteo, 2) : 0;
}



// --- Redirección a la página de confirmación (Fase 2: Innermetrix) ---
session_write_close();

// Construye URL absoluta según el host actual
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'app.valirica.com';
$basePath = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'] ?? '/')), '/');

// ⚠️ Si confirmacion_fase2.php está en otra carpeta, pon ruta absoluta desde la raíz, ej.:
// $target = '/formularios/confirmacion_fase2.php?equipo_id=' . urlencode((string)$equipo_id);
$target   = $basePath . '/confirmacion_fase2.php?equipo_id=' . urlencode((string)$equipo_id);

$absoluteUrl = $scheme . '://' . $host . $target;


mysqli_close($conn);


// Envía redirección POST→GET con 303
header('Location: ' . $absoluteUrl, true, 303);
exit;

// Fallback (por si ya se enviaron cabeceras por accidente)
if (headers_sent($file, $line)) {
  echo "<!doctype html><meta charset='utf-8'>
        <title>Redirección</title>
        <p>Redireccionando… Si no avanza automáticamente, haz clic aquí:</p>
        <p><a href='".htmlspecialchars($absoluteUrl, ENT_QUOTES, 'UTF-8')."'>Continuar a Fase 2</a></p>
        <!-- headers already sent at $file:$line -->";
  exit;
}



?>
