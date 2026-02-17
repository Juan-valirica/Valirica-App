<?php
session_start();
require 'config.php';

$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
if ($usuario_id <= 0) {
    die("⛔ Error: usuario_id inválido.");
}

// MODO EDICIÓN: Cargar datos existentes si existen
$datos_existentes = null;
$stmt = $conn->prepare("SELECT * FROM cultura_ideal WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = stmt_get_result($stmt);
if ($result->num_rows > 0) {
    $datos_existentes = $result->fetch_assoc();
}
$stmt->close();



// =================== INICIO: handler de Áreas (paso 1) ===================
// =================== INICIO: handler de Áreas (paso 1) ===================
// =================== INICIO: handler de Áreas (paso 1) ===================
// =================== INICIO: handler de Áreas (paso 1) ===================


$areasGuardadasOk = false;
$areasErrores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'guardar_areas')) {
    try {
        // 1) Normaliza/valida entradas
        $areas = $_POST['areas'] ?? [];
        if (!is_array($areas)) { $areas = []; }

        // Limpieza básica
        $areas = array_map(function($s){
            $s = trim((string)$s);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        }, $areas);

        // Quita vacíos
        $areas = array_values(array_filter($areas, fn($s) => $s !== ''));

        // Límite y mínimo
        if (count($areas) < 1) {
            $areasErrores[] = 'Debes ingresar al menos un área.';
        }
        if (count($areas) > 200) {
            $areasErrores[] = 'Has ingresado demasiadas áreas. Reduce la lista.';
        }

        if ($areasErrores) {
            throw new RuntimeException(implode(' ', $areasErrores));
        }

        // 2) Trae nombre de empresa de tabla usuarios (columna empresa)
        $stmtEmp = $conn->prepare("SELECT empresa FROM usuarios WHERE id = ?");
        if (!$stmtEmp) { throw new RuntimeException('Error preparando consulta de empresa: '.$conn->error); }
        $stmtEmp->bind_param("i", $usuario_id);
        if (!$stmtEmp->execute()) { throw new RuntimeException('Error ejecutando consulta de empresa: '.$stmtEmp->error); }
        $resEmp = stmt_get_result($stmtEmp);
        $rowEmp = $resEmp ? $resEmp->fetch_assoc() : null;
        $stmtEmp->close();

        $empresa = $rowEmp['empresa'] ?? null;
        if (!$empresa) {
            throw new RuntimeException('No se encontró la empresa asociada a este usuario.');
        }

        // 3) Inserta/actualiza (idempotente por UNIQUE (usuario_id, nombre_area))
        $sqlIns = "INSERT INTO areas_trabajo (usuario_id, empresa, nombre_area, created_at)
                   VALUES (?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE nombre_area = VALUES(nombre_area)";
        $stmtIns = $conn->prepare($sqlIns);
        if (!$stmtIns) { throw new RuntimeException('Error preparando inserción de áreas: '.$conn->error); }

        // Tipos: i=usuario_id, s=empresa, s=nombre_area
        $nombreFmt = ''; // se asigna en el loop
        $stmtIns->bind_param("iss", $usuario_id, $empresa, $nombreFmt);

        // Transacción
        $conn->begin_transaction();

        foreach ($areas as $nombre) {
            if (mb_strlen($nombre) > 150) {
                throw new RuntimeException('Un nombre de área supera los 150 caracteres.');
            }
            // Capitaliza opcionalmente
            $nombreFmt = mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");

            if (!$stmtIns->execute()) {
                throw new RuntimeException('Error insertando área "'.$nombreFmt.'": '.$stmtIns->error);
            }
        }

        $conn->commit();
        $stmtIns->close();

        $areasGuardadasOk = true;

    } catch (Throwable $e) {
        // Si hay transacción abierta, revierte
        if ($conn && $conn->errno === 0) {
            // No hay forma directa de saber si hay tx abierta; intentamos rollback por seguridad
            @$conn->rollback();
        } else {
            @$conn->rollback();
        }
        $areasErrores[] = $e->getMessage();
    }
}

// Para precargar áreas ya guardadas (útil si recargas la página)
$areasExistentes = [];
try {
    $stmtList = $conn->prepare("SELECT nombre_area FROM areas_trabajo WHERE usuario_id = ? ORDER BY nombre_area ASC");
    if ($stmtList) {
        $stmtList->bind_param("i", $usuario_id);
        if ($stmtList->execute()) {
            $resList = stmt_get_result($stmtList);
            if ($resList) {
                while ($r = $resList->fetch_assoc()) {
                    $areasExistentes[] = $r['nombre_area'];
                }
            }
        }
        $stmtList->close();
    }
} catch (Throwable $e) {
    // Silencioso: no bloquea el resto del formulario
}

// =================== FIN: handler de Áreas (paso 1) ===================
// =================== FIN: handler de Áreas (paso 1) ===================
// =================== FIN: handler de Áreas (paso 1) ===================
// =================== FIN: handler de Áreas (paso 1) ===================





if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && (!isset($_POST['action']) || $_POST['action'] !== 'guardar_areas')
) {

    // 1. Dimensiones Hofstede
    $distancia_poder = intval($_POST["distancia_poder"]);
    $individualismo = intval($_POST["individualismo"]);
    $masculinidad = intval($_POST["masculinidad"]);
    $incertidumbre = intval($_POST["incertidumbre"]);
    $largo_plazo = intval($_POST["largo_plazo"]);
    $indulgencia = intval($_POST["indulgencia"]);

    // 2. Propósito (solo texto)
    $proposito = trim($_POST["proposito"]);

    // 3. Valores (solo nombre y descripción)
    $valores = [];
    if (isset($_POST['valor_nombre'])) {
        for ($i = 0; $i < count($_POST['valor_nombre']); $i++) {
            $valores[] = [
                "nombre" => $_POST['valor_nombre'][$i],
                "descripcion" => $_POST['valor_descripcion'][$i]
            ];
        }
    }

    $valores_json = json_encode($valores, JSON_UNESCAPED_UNICODE);

    // 4. Resto de campos
    
$comunicacion = $_POST["estilo_comunicacion"];
$ubicacion = trim($_POST["ubicacion"]);
$uso_app = isset($_POST["uso_app"]) ? implode(", ", $_POST["uso_app"]) : '';
$canal_origen = $_POST["canal_origen"] ?? '';
$canal_otro = $_POST["canal_otro"] ?? '';
$preguntas_json = json_encode($_POST, JSON_UNESCAPED_UNICODE);

if ($_POST["ubicacion"] === "Otro" && !empty($_POST["otro_pais"])) {
    $_POST["ubicacion"] = trim($_POST["otro_pais"]);
}
$ubicacion = trim($_POST["ubicacion"]); // <- **añade esta línea**





    // 5. Insertar o actualizar cultura_ideal
    $stmt = $conn->prepare("REPLACE INTO cultura_ideal (
        usuario_id, distancia_poder, individualismo, masculinidad, incertidumbre, largo_plazo, indulgencia,
        proposito, valores_json, estilo_comunicacion, ubicacion, uso_app, respuestas_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iiiiiiissssss",
        $usuario_id, $distancia_poder, $individualismo, $masculinidad, $incertidumbre, $largo_plazo, $indulgencia,
        $proposito, $valores_json, $comunicacion, $ubicacion, $uso_app, $preguntas_json
    );

    if ($stmt->execute()) {
        // 6. Guardar los valores en la tabla `valores_marca`
        $stmt->close();
        // Limpiar valores anteriores si es necesario
        $conn->query("DELETE FROM valores_marca WHERE usuario_id = $usuario_id");

        foreach ($valores as $valor) {
            $titulo = $valor['nombre'];
            $descripcion = $valor['descripcion'];

            $stmt_valor = $conn->prepare("INSERT INTO valores_marca (
                usuario_id, titulo, descripcion
            ) VALUES (?, ?, ?)");

            $stmt_valor->bind_param("iss", $usuario_id, $titulo, $descripcion);
            $stmt_valor->execute();
            $stmt_valor->close();
        }

        // 7. Redirigir
        header("Location: a-desktop-dashboard-brand.php");
        exit;
    } else {
        echo "❌ Error al guardar: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>







<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cultura ideal — Valírica</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    @import url("https://use.typekit.net/qrv8fyz.css");

    :root{
      --c-primary:#012133; --c-secondary:#184656; --c-accent:#EF7F1B;
      --c-soft:#FFF5F0; --c-body:#474644; --c-bg:#ffffff;
      --radius:20px; --shadow:0 6px 20px rgba(0,0,0,0.06);
      --ring: 0 0 0 4px color-mix(in srgb, var(--c-accent) 18%, transparent);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;font-family:"gelica",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;background:#fff;color:var(--c-body);min-height:100svh;overflow:auto;scrollbar-gutter:stable both-edges}

    header.app{width:100%;background:var(--c-primary);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:14px 24px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
    .app-brand{display:flex;align-items:center;gap:12px}
    .app-brand img{width:36px;height:36px;border-radius:10px;object-fit:cover;background:#fff}
    .app-brand h1{font-size:18px;margin:0;letter-spacing:.2px}
    .app-sub{font-size:12px;opacity:.85}
    .tags{display:flex;flex-wrap:wrap;gap:8px}
    .vl-tag{display:inline-flex;gap:6px;align-items:center;padding:6px 10px;font-size:12px;line-height:1;border-radius:9999px;background:var(--c-soft);color:var(--c-secondary);border:1px solid rgba(1,33,51,.08);user-select:none}

    .wrap{width:min(1100px,100%);margin:clamp(16px,4vh,56px) auto;padding:0 clamp(16px,3vw,24px);display:grid;gap:24px}
    .card{background:#fff;border:1px solid #f1f1f1;border-radius:var(--radius);box-shadow:var(--shadow);padding:clamp(16px,3vh,28px)}
    .card h2{margin:0 0 10px;color:var(--c-secondary);font-size:clamp(18px,2.4vw,22px)}
    .card p.lead{margin:0 0 14px;color:#6b6b6b}
    .section{display:grid;gap:16px}
    .divider{height:1px;background:linear-gradient(90deg, rgba(1,33,51,.04) 0%, rgba(1,33,51,.10) 12%, rgba(1,33,51,.04) 100%);margin:14px 0}

    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .grid-auto{display:grid;gap:16px}
    @media (max-width:880px){.grid-2,.grid-3{grid-template-columns:1fr}}

    label{display:block;font-weight:700;color:var(--c-secondary);font-size:14px;margin-bottom:8px}
    .input,.textarea,.select{width:100%;padding:11px 12px;font-size:16px;border:1px solid #e6e6e6;border-radius:12px;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
    .input:hover,.textarea:hover,.select:hover{border-color:#dcdcdc}
    .input:focus,.textarea:focus,.select:focus{border-color:var(--c-accent);box-shadow:var(--ring)}
    .textarea{min-height:100px;resize:vertical}
    .small{font-size:12px;color:#6b6b6b}

    /* ===== RANGES: Título arriba + extremos claros + full width ===== */
    .range-block{display:grid;gap:8px}
    .range-title{font-weight:800;color:var(--c-secondary);font-size:14px}
    .range-rail{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center}
    .range-ext{font-size:12px;color:#6b6b6b}
    .range{-webkit-appearance:none;appearance:none;height:6px;background:#eee;border-radius:9999px;outline:none;width:100%}
    .range::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:18px;height:18px;background:var(--c-accent);border-radius:50%;cursor:pointer;box-shadow:0 0 0 4px rgba(239,127,27,.15)}
    .range-value{justify-self:end;font-weight:800;color:var(--c-secondary);font-size:13px}

    /* Chips / áreas y canales */
    .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
    .chip{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #ececec;border-radius:9999px;background:#fff;font-size:13px}
    .chip button{border:0;background:#FFF5F0;color:var(--c-secondary);padding:2px 6px;border-radius:8px;cursor:pointer;font-weight:700}
    .pill-input{display:flex;gap:10px}
    .pill-input .input{flex:1}

    /* Valores */
    .value-card{border:1px solid #f1f1f1;border-radius:16px;padding:14px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.04);display:grid;gap:12px}
    /* Cada dimensión de valores ocupa el ancho completo */
.value-mini-grid{
  display:grid;
  grid-template-columns:1fr;
  gap:24px;
}



    
    
    
    
    .value-mini .range-subtitle{font-weight:700;color:var(--c-secondary);font-size:13px;margin-bottom:6px}

    /* Radio cards — Estilo aprendizaje */
    .radio-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    @media (max-width:880px){.radio-cards{grid-template-columns:1fr}}
    .rcard{border:1px solid #ececec;border-radius:14px;padding:12px;background:#fff;display:grid;gap:6px;cursor:pointer}
    .rcard input{appearance:none;width:0;height:0;position:absolute;opacity:0}
    .rcard strong{color:var(--c-secondary)}
    .rcard .rc-desc{font-size:12px;color:#6b6b6b;line-height:1.5}
    .rcard:has(input:checked){border-color:var(--c-accent);box-shadow:var(--ring)}

    /* Botones */
    .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px;border-radius:14px;font-weight:800;font-size:16px;border:1px solid rgba(0,0,0,.06);cursor:pointer;text-decoration:none;transition:transform .06s ease,filter .12s ease;box-shadow:var(--shadow)}
    .btn-primary{background:var(--c-accent);color:#fff}
    .btn-ghost{background:var(--c-soft);color:var(--c-secondary)}
    .btn:hover{filter:brightness(.98)} .btn:active{transform:translateY(1px)}

    .alert{border-radius:12px;padding:10px 12px;font-size:14px;line-height:1.5;border:1px solid #eee;background:#fff}
    .alert.ok{border-color:rgba(0,128,0,.15);background:#f3fff3}
    .alert.error{border-color:rgba(239,127,27,.25);background:#fff6ef}
    
    
    
    .value-mini .range{width:100%;}



/* === Ajuste global de sliders Valírica === */
/* Unifica el inicio, centro y final de todos los sliders
   (Hofstede, Propósito y Valores) y agrega una guía central sutil */

:root{
  --rail-side: 260px;   /* ancho fijo de los textos laterales */
  --rail-gap: 16px;     /* separación entre texto y slider */
  --rail-height: 6px;   /* grosor de la línea del slider */
}

/* Aplica a todos los bloques de sliders (usa .range-rail en tu HTML) */
.range-rail{
  display: grid;
  grid-template-columns: var(--rail-side) 1fr var(--rail-side);
  gap: var(--rail-gap);
  align-items: center;
  position: relative;        /* para posicionar la guía central */
}

/* Slider ocupa todo el ancho del espacio central */
.range{
  width: 100%;
  height: var(--rail-height);
  background: #eee;
  border-radius: 9999px;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
  position: relative;
}

/* Centro visual — línea vertical sutil */
.range-rail::before{
  content: "";
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 1px;
  height: calc(var(--rail-height) * 3);
  background: rgba(1,33,51,0.15);
  pointer-events: none;
}

/* Pistas y extremos */
.range::-webkit-slider-thumb{
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  background: var(--c-accent);
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 0 0 4px rgba(239,127,27,0.15);
}
.range::-moz-range-thumb{
  width: 18px;
  height: 18px;
  background: var(--c-accent);
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 0 0 4px rgba(239,127,27,0.15);
}

/* Textos laterales (extremos) */
.range-ext{
  display: grid;
  align-content: center;
  font-size: 12px;
  color: #6b6b6b;
}
.range-ext .small{ margin-top: 2px; line-height: 1.3; }

/* Responsivo — reduce ancho lateral en pantallas más pequeñas */
@media (max-width: 980px){
  :root{ --rail-side: 200px; }
}
@media (max-width: 720px){
  :root{ --rail-side: 150px; }
}
@media (max-width: 560px){
  .range-rail{
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .range-rail::before{ display: none; } /* ocultamos la guía en pantallas pequeñas */
  .range-ext{ text-align: left; }
}


    
    
  </style>
</head>
<body>
  <header class="app" role="banner">
    <div class="app-brand">
      <img src="/uploads/logo-192.png" alt="Valírica">
      <div>
        <h1>Cultura ideal</h1>
        <div class="app-sub">Define tu norte cultural con datos</div>
      </div>
    </div>
    <div class="tags">
      <span class="vl-tag">Formulario</span>
      <span class="vl-tag">UX Valírica</span>
    </div>
  </header>

  <div class="wrap">

    <!-- PASO 1: Áreas (sin botón — se guardan antes del submit final) -->
    <section class="card" aria-labelledby="areas-title">
      <h2 id="areas-title">Áreas de trabajo</h2>
      <p class="lead">Agrega las áreas/equipos que harán parte del análisis (puedes añadir varias).</p>

      <?php if (!empty($areasGuardadasOk)): ?>
        <div class="alert ok">✅ Áreas guardadas correctamente.</div>
      <?php endif; ?>
      <?php if (!empty($areasErrores)): ?>
        <div class="alert error">
          <strong>Atención:</strong>
          <?php echo htmlspecialchars(implode(' ', $areasErrores), ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <div id="chipsContainer" class="chips" aria-live="polite">
        <?php foreach (($areasExistentes ?? []) as $area): ?>
          <span class="chip"><?php echo htmlspecialchars($area, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>

      <div class="section">
        <div class="pill-input">
          <input id="areaInput" class="input" type="text" placeholder="Ej.: Ventas, Producto, Talento" aria-label="Nombre del área">
          <button type="button" class="btn btn-ghost" onclick="addArea()">Añadir</button>
        </div>
        <div id="areasHidden"></div>
        <p class="small">Consejo: separa múltiples áreas con coma para agregarlas más rápido.</p>
      </div>
    </section>

    <!-- FORM PRINCIPAL -->
    <form id="formPrincipal" class="card" action="?usuario_id=<?php echo (int)$usuario_id; ?>" method="POST" novalidate>
      <h2>Definición de cultura ideal</h2>
      <p class="lead">Ajusta los deslizadores y completa los campos. Guardaremos todo en un único paso.</p>

      <!-- Hofstede: títulos arriba + extremos claros -->
      <div class="section" role="group" aria-label="Dimensiones Hofstede">
        <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">Dimensiones culturales (Hofstede)</h3>
        <?php
          function slider_block($id,$title,$name,$left,$right,$value){
            $v = is_null($value)?0:intval($value);
            echo '<div class="range-block">
                    <div class="range-title">'.$title.'</div>
                    <div class="range-rail">
                      <span class="range-ext">'.$left.'</span>
                      <input class="range" id="'.$id.'" name="'.$name.'" type="range" min="-5" max="5" step="1" value="'.$v.'" oninput="this.parentElement.nextElementSibling.textContent=this.value">
                      <span class="range-ext" style="text-align:right">'.$right.'</span>
                    </div>
                    <div class="range-value">'.$v.'</div>
                  </div>';
          }
          slider_block('d_poder','Distancia de poder','distancia_poder',
                       'Relación cercana, feedback fluye',
                       'Estructura marcada, decisión por niveles',
                       $datos_existentes['distancia_poder'] ?? 0);

          slider_block('individualismo','Individualismo',
                       'individualismo',
                       'Trabajo interdependiente y co-creación',
                       'Autonomía y ownership individual',
                       $datos_existentes['individualismo'] ?? 0);

          slider_block('masculinidad','Orientación a resultados (Masculinidad)',
                       'masculinidad',
                       'Cuidado, calidad y aprendizaje',
                       'Énfasis en metas y desempeño',
                       $datos_existentes['masculinidad'] ?? 0);

          slider_block('incertidumbre','Evasión de incertidumbre',
                       'incertidumbre',
                       'Flexibilidad, iteración y pilotos',
                       'Procedimientos y control estables',
                       $datos_existentes['incertidumbre'] ?? 0);

          slider_block('largo_plazo','Orientación temporal',
                       'largo_plazo',
                       'Impacto inmediato y entregas rápidas',
                       'Compromisos y visión a futuro',
                       $datos_existentes['largo_plazo'] ?? 0);

          slider_block('indulgencia','Normas vs. flexibilidad (Indulgencia)',
                       'indulgencia',
                       'Reglas claras y consistentes',
                       'Adaptación a contextos y personas',
                       $datos_existentes['indulgencia'] ?? 0);
        ?>
      </div>

      <div class="divider"></div>

      <!-- Propósito: títulos arriba + extremos claros -->
      <div class="section" role="group" aria-label="Propósito">
        <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">Propósito</h3>

        <label for="proposito">Describe el propósito de tu marca</label>
        <textarea id="proposito" name="proposito" class="textarea" placeholder="¿Para qué existe tu organización? ¿Qué impacto busca generar en el mundo?"><?php
          echo htmlspecialchars($datos_existentes['proposito'] ?? '', ENT_QUOTES, 'UTF-8');
        ?></textarea>
        <p class="small">El propósito es la razón de ser de tu organización más allá de generar utilidades. Define el impacto que quieres tener.</p>
      </div>

      <div class="divider"></div>

      <!-- Valores: solo nombre y descripción -->
<div class="section" id="valoresSection" aria-label="Valores de la marca">
  <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">Valores de la marca</h3>
  <p class="small">Define los valores fundamentales que guían las decisiones y comportamientos en tu organización.</p>

  <div id="valoresContainer" class="grid-auto"></div>

  <div class="actions">
    <button type="button" class="btn btn-ghost" onclick="addValor()">Añadir valor</button>
  </div>
</div>


      <div class="divider"></div>

      <!-- Estilo de aprendizaje (radio cards con explicación) -->
      <div class="section">
        <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">Estilo de aprendizaje de la marca</h3>
        <p class="small">Elige cómo tu empresa **transmite y asimila** información clave de manera natural.</p>
        <?php $estilo = $datos_existentes['estilo_comunicacion'] ?? ''; ?>
        <div class="radio-cards" role="radiogroup" aria-label="Estilo de aprendizaje">
          <label class="rcard">
            <input type="radio" name="estilo_comunicacion" value="Visual" <?php echo (strcasecmp($estilo,'Visual')===0)?'checked':''; ?>>
            <strong>Visual</strong>
            <div class="rc-desc">Diagramas, dashboards, mockups, videos cortos. Lo importante se **entiende viéndolo**.</div>
          </label>
          <label class="rcard">
            <input type="radio" name="estilo_comunicacion" value="Auditivo" <?php echo (strcasecmp($estilo,'Auditivo')===0)?'checked':''; ?>>
            <strong>Auditivo</strong>
            <div class="rc-desc">Reuniones, explicaciones habladas, notas de voz y podcasts. Lo clave se **capta escuchando**.</div>
          </label>
          <label class="rcard">
            <input type="radio" name="estilo_comunicacion" value="Kinestésico" <?php echo (strcasecmp($estilo,'Kinestésico')===0)?'checked':''; ?>>
            <strong>Kinestésico</strong>
            <div class="rc-desc">Pilotos, talleres y práctica guiada. Se aprende **haciendo y probando**.</div>
          </label>
        </div>
      </div>

      <div class="divider"></div>

      <!-- ¿Cómo conociste el software? (chips) -->
      <div class="section">
        <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">¿Cómo conociste este software?</h3>
        <?php
          $co = $_POST['canal_origen'] ?? ($datos_existentes['canal_origen'] ?? '');
          $canales = ['Provider','Valírica','Recomendación','Redes','Evento','Otro'];
        ?>
        <div class="chips" role="radiogroup" aria-label="Canal de origen">
          <?php foreach($canales as $c):
            $id = 'canal_'.preg_replace('/\W+/','', strtolower($c));
            $checked = (strcasecmp($co,$c)===0)?'checked':'';
          ?>
            <label for="<?php echo $id; ?>" class="vl-tag" style="cursor:pointer;">
              <input id="<?php echo $id; ?>" type="radio" name="canal_origen" value="<?php echo $c; ?>" <?php echo $checked; ?>>
              <?php echo $c; ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="grid-2">
          <div>
            <label for="canal_otro">Si elegiste “Otro”, ¿cuál?</label>
            <input id="canal_otro" name="canal_otro" class="input" type="text" value="<?php echo htmlspecialchars($_POST['canal_otro'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div></div>
        </div>
      </div>
      
      <div class="divider"></div>

<div class="divider"></div>

<!-- País / Ubicación -->
<div class="section" aria-label="Ubicación de la empresa">
  <h3 style="margin:0;color:var(--c-secondary);font-size:18px;">Ubicación de la empresa</h3>
  <p class="small">Indica el país principal donde opera tu empresa. Lo usaremos para contextualizar tu cultura ideal.</p>

  <?php 
    $ubic = $datos_existentes['ubicacion'] ?? ''; 
    $otro_pais = '';
    if ($ubic && !in_array($ubic, ['España','Colombia','México','Chile','Argentina','Estados Unidos'])) {
        $otro_pais = $ubic;
        $ubic = 'Otro';
    }
  ?>

  <div class="grid-2">
    <div>
      <label for="ubicacion">País</label>
      <select id="ubicacion" name="ubicacion" class="select" required onchange="toggleOtroPais(this.value)">
        <option value="" <?php echo $ubic===''?'selected':''; ?>>Selecciona un país</option>
        <option value="España" <?php echo strcasecmp($ubic,'España')===0?'selected':''; ?>>🇪🇸 España</option>
        <option value="Colombia" <?php echo strcasecmp($ubic,'Colombia')===0?'selected':''; ?>>🇨🇴 Colombia</option>
        <option value="México" <?php echo strcasecmp($ubic,'México')===0?'selected':''; ?>>🇲🇽 México</option>
        <option value="Chile" <?php echo strcasecmp($ubic,'Chile')===0?'selected':''; ?>>🇨🇱 Chile</option>
        <option value="Argentina" <?php echo strcasecmp($ubic,'Argentina')===0?'selected':''; ?>>🇦🇷 Argentina</option>
        <option value="Estados Unidos" <?php echo strcasecmp($ubic,'Estados Unidos')===0?'selected':''; ?>>🇺🇸 Estados Unidos</option>
        <option value="Otro" <?php echo strcasecmp($ubic,'Otro')===0?'selected':''; ?>>🌍 Otro</option>
      </select>
    </div>

    <div id="otroPaisWrap" style="display: <?php echo ($ubic==='Otro'?'block':'none'); ?>;">
      <label for="otro_pais">Especifica el país</label>
      <input type="text" id="otro_pais" name="otro_pais" class="input" placeholder="Ej.: Perú, Costa Rica..." 
             value="<?php echo htmlspecialchars($otro_pais, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
  </div>
</div>

<script>
  function toggleOtroPais(val){
    const wrap = document.getElementById('otroPaisWrap');
    const input = document.getElementById('otro_pais');
    if(val === 'Otro'){
      wrap.style.display = 'block';
      input.focus();
    } else {
      wrap.style.display = 'none';
      input.value = '';
    }
  }
</script>


      
      
      

      <!-- Acciones -->
      <div class="actions">
        <button id="btnGuardar" class="btn btn-primary" type="submit">Guardar y continuar</button>
        <a class="btn btn-ghost" href="a-desktop-dashboard-brand.php">Cancelar</a>
      </div>
    </form>
  </div>

  <script>
    /* ====== ÁREAS: chips + hidden; pre-POST antes del submit final ====== */
    const chipsContainer = document.getElementById('chipsContainer');
    const hiddenWrap = document.getElementById('areasHidden');
    const areaInput = document.getElementById('areaInput');
    const formPrincipal = document.getElementById('formPrincipal');
    const btnGuardar = document.getElementById('btnGuardar');

    function cssId(t){ return t.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,''); }
    function escapeHtml(s){ return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    function addArea(){
      const raw = (areaInput.value || '').trim();
      if (!raw) return;
      const parts = raw.split(',').map(s => s.trim()).filter(Boolean);
      parts.forEach(addChip);
      areaInput.value = '';
    }
    function addChip(text){
      const exists = Array.from(chipsContainer.querySelectorAll('.chip')).some(c => c.textContent.replace('×','').trim().toLowerCase() === text.toLowerCase());
      if (exists) return;
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.innerHTML = `${escapeHtml(text)} <button type="button" aria-label="Eliminar" onclick="removeChip(this, '${cssId(text)}')">×</button>`;
      chipsContainer.appendChild(chip);
      const h = document.createElement('input');
      h.type='hidden'; h.name='areas[]'; h.value=text; h.id='h_'+cssId(text);
      hiddenWrap.appendChild(h);
    }
    function removeChip(btn, key){
      btn.closest('.chip').remove();
      const el = document.getElementById('h_'+key);
      if (el) el.remove();
    }
    (function initPreloadAreas(){
      Array.from(chipsContainer.querySelectorAll('.chip')).forEach(ch=>{
        const label = ch.textContent.trim().replace(/×$/,'').trim();
        const h = document.createElement('input');
        h.type='hidden'; h.name='areas[]'; h.value=label; h.id='h_'+cssId(label);
        hiddenWrap.appendChild(h);
      });
    })();

    formPrincipal.addEventListener('submit', async (ev)=>{
      const areas = Array.from(hiddenWrap.querySelectorAll('input[name="areas[]"]')).map(i=>i.value).filter(Boolean);
      if (areas.length) {
        ev.preventDefault();
        btnGuardar.disabled = true; btnGuardar.textContent = 'Guardando…';
        try {
          const fd = new FormData();
          fd.append('action','guardar_areas');
          areas.forEach(a => fd.append('areas[]', a));
          await fetch(window.location.href, { method:'POST', body: fd, credentials:'same-origin' });
        } catch(e){ /* noop */ }
        finally { formPrincipal.submit(); }
      }
    });

    /* ====== VALORES dinámicos: solo nombre y descripción ====== */
    const valoresContainer = document.getElementById('valoresContainer');

    function valueCardTemplate(idx, data={}){
      const v = Object.assign({nombre:'', descripcion:''}, data);
      return `
      <div class="value-card" data-idx="${idx}">
        <div class="grid-2">
          <div>
            <label>Nombre del valor</label>
            <input class="input" name="valor_nombre[]" type="text" value="${html(v.nombre)}" placeholder="Ej.: Integridad, Innovación, Excelencia" required>
          </div>
          <div>
            <label>Descripción</label>
            <textarea class="textarea" name="valor_descripcion[]" placeholder="¿Qué significa este valor para tu organización? ¿Cómo se manifiesta?" required style="min-height:60px;">${html(v.descripcion)}</textarea>
          </div>
        </div>
        <div class="actions">
          <button type="button" class="btn btn-ghost" onclick="removeValor(this)">Eliminar este valor</button>
        </div>
      </div>`;
    }

    
    
    

    function html(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
    function addValor(prefill){ const idx = valoresContainer.children.length; const wrap = document.createElement('div'); wrap.innerHTML = valueCardTemplate(idx, prefill||{}); valoresContainer.appendChild(wrap.firstElementChild); }
    function removeValor(btn){ const card = btn.closest('.value-card'); if (card) card.remove(); }

    (function preloadValores(){
      let seed = [];
      try {
        seed = JSON.parse(<?php
          $json = $datos_existentes['valores_json'] ?? '[]';
          echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        ?>) || [];
        if (typeof seed === 'string') seed = JSON.parse(seed);
      } catch(e){ seed = []; }
      if (!Array.isArray(seed)) seed = [];
      if (seed.length === 0){ addValor(); }
      else { seed.forEach(v => addValor({
        nombre:v.nombre,
        descripcion:v.descripcion
      })); }
    })();
  </script>
</body>
</html>
