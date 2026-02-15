<?php

// Helper de escape por si alguna vista no lo trae todavía

if (!function_exists('h')) {
  function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
  }
}


// === Datos del usuario loggeado (viewer) ===
// Estas variables deben venir definidas DESDE CADA VISTA:
// $viewer_id, $viewer_rol, $viewer_empresa, $viewer_logo, $viewer_cultura_tipo

$viewer_rol          = isset($viewer_rol) ? strtolower((string)$viewer_rol) : '';
$viewer_empresa      = $viewer_empresa      ?? '';
$viewer_logo         = $viewer_logo         ?? '';
$viewer_cultura_tipo = $viewer_cultura_tipo ?? '';



// Si la vista no pasó el rol del viewer, lo obtenemos desde la sesión
if ($viewer_rol === '' && isset($_SESSION['user_id'], $conn)) {
  $viewer_id_fallback = (int)$_SESSION['user_id'];

  if ($viewer_id_fallback > 0) {
    if ($stmtViewerHd = $conn->prepare("SELECT empresa, logo, rol, cultura_empresa_tipo FROM usuarios WHERE id = ? LIMIT 1")) {
      $stmtViewerHd->bind_param("i", $viewer_id_fallback);
      $stmtViewerHd->execute();
      $resViewerHd = stmt_get_result($stmtViewerHd);
      if ($rowViewerHd = $resViewerHd->fetch_assoc()) {
        // Rol real del usuario loggeado
        $viewer_rol = strtolower((string)($rowViewerHd['rol'] ?? $viewer_rol));

        // Solo rellenamos si no vinieron desde la vista
        if ($viewer_empresa === '') {
          $viewer_empresa = (string)($rowViewerHd['empresa'] ?? '');
        }
        if ($viewer_logo === '') {
          $viewer_logo = (string)($rowViewerHd['logo'] ?? '');
        }
        if ($viewer_cultura_tipo === '') {
          $viewer_cultura_tipo = (string)($rowViewerHd['cultura_empresa_tipo'] ?? '');
        }
      }
      $stmtViewerHd->close();
    }
  }
}


// ============================
// Blindaje global de variables
// ============================

$viewer_id        = isset($viewer_id) ? (int)$viewer_id : 0;
$viewer_rol       = isset($viewer_rol) ? strtolower((string)$viewer_rol) : '';
$viewer_empresa   = isset($viewer_empresa) ? (string)$viewer_empresa : '';
$viewer_logo      = isset($viewer_logo) ? (string)$viewer_logo : '';
$empresa          = isset($empresa) ? (string)$empresa : '';
$usuario_id       = isset($usuario_id) ? (int)$usuario_id : 0;

$docs_unread_count = isset($docs_unread_count) ? (int)$docs_unread_count : 0;
$has_new_docs      = isset($has_new_docs) ? (bool)$has_new_docs : false;



// === Datos de la empresa “objetivo” (si la vista los tiene) ===
// En brand y propósito/valores normalmente ya tienes:
// $empresa, $logo, $cultura_empresa_tipo, $usuario_id
$empresa              = $empresa              ?? '';
$logo                 = $logo                 ?? '';
$cultura_empresa_tipo = $cultura_empresa_tipo ?? '';

// Si la vista no tiene empresa objetivo, usamos la del viewer
$header_empresa = $empresa !== '' ? $empresa : ($viewer_empresa !== '' ? $viewer_empresa : 'Tu empresa');
$header_logo    = $logo    !== '' ? $logo    : $viewer_logo;
$header_cultura = $cultura_empresa_tipo !== '' ? $cultura_empresa_tipo : ($viewer_cultura_tipo !== '' ? $viewer_cultura_tipo : 'No definida');

// Param base para mantener ?usuario_id cuando un provider mira una company
$base_params = '';
if (isset($usuario_id, $viewer_id) && $usuario_id > 0 && $usuario_id !== $viewer_id) {
  $base_params = '?usuario_id=' . (int)$usuario_id;
}

// Pestaña activa (se define en cada vista: 'cultura-alineacion', 'proposito-valores', 'companies', etc.)
$active_tab = $active_tab ?? '';






// ============================
// Defaults de KPIs para header
// ============================

// Alineación cultural
$promedio_general      = $promedio_general      ?? null;
$aline_label           = $aline_label           ?? '';
$aline_class           = $aline_class           ?? '';
$aline_icon            = $aline_icon            ?? null;

// Motivación colectiva / energía
$energia_equipo        = $energia_equipo        ?? null;
$energia_status        = $energia_status        ?? '';
$mot_label             = $mot_label             ?? '';
$mot_class             = $mot_class             ?? '';
$mot_icon              = $mot_icon              ?? null;



// Estilo de aprendizaje (equipo: viene desde la vista si existe)
$estilo_equipo_aprend  = $estilo_equipo_aprend  ?? '';

// Estilo de aprendizaje de la marca (cultura_ideal.estilo_comunicacion)
$estilo_marca_aprend   = $estilo_marca_aprend   ?? '';

// --- 1) Detectamos el usuario "objetivo" para pedir su cultura_ideal ---
$target_usuario_id = 0;
if (isset($usuario_id) && (int)$usuario_id > 0) {
  // Company objetivo (brand / cultura_ideal vinculada a este usuario)
  $target_usuario_id = (int)$usuario_id;
} elseif (isset($viewer_id) && (int)$viewer_id > 0) {
  // Fallback: usuario loggeado
  $target_usuario_id = (int)$viewer_id;
}

// --- 2) Leemos estilo_comunicacion desde cultura_ideal ---
if ($target_usuario_id > 0 && isset($conn) && $conn instanceof mysqli) {
  if ($stmtCul = $conn->prepare("SELECT estilo_comunicacion FROM cultura_ideal WHERE usuario_id = ? LIMIT 1")) {
    $stmtCul->bind_param("i", $target_usuario_id);
    $stmtCul->execute();
    $resCul = stmt_get_result($stmtCul);
    if ($rowCul = $resCul->fetch_assoc()) {
      $estilo_marca_aprend = (string)($rowCul['estilo_comunicacion'] ?? '');
    }
    $stmtCul->close();
  }
}

// --- 3) Mapeo de códigos a etiquetas bonitas ---
$learning_label_map = [
  'visual'       => 'Visual',
  'auditivo'     => 'Auditivo',
  'kinestesico'  => 'Kinestésico',
  'kinestésico'  => 'Kinestésico'
];

$estilo_marca_label  = $learning_label_map[strtolower($estilo_marca_aprend)]  ?? ($estilo_marca_aprend ?: 'Sin definir');
$estilo_equipo_label = $learning_label_map[strtolower($estilo_equipo_aprend)] ?? ($estilo_equipo_aprend ?: 'Sin datos');

// --- 4) ¿Está alineado? ---
$aprend_alineado = (
  $estilo_marca_aprend  !== '' &&
  $estilo_equipo_aprend !== '' &&
  strtolower($estilo_marca_aprend) === strtolower($estilo_equipo_aprend)
);


// ============================
// Notificaciones de Documentos
// ============================

$docs_unread_count = 0;
$empresa_id_header = isset($usuario_id) && (int)$usuario_id > 0 
  ? (int)$usuario_id 
  : (isset($viewer_id) ? (int)$viewer_id : 0);

if ($empresa_id_header > 0 && isset($conn) && $conn instanceof mysqli) {
  if ($stmtDocs = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM documentos 
    WHERE empresa_id = ? 
      AND estado = 'nuevo'
  ")) {
    $stmtDocs->bind_param("i", $empresa_id_header);
    $stmtDocs->execute();
    $resDocs = stmt_get_result($stmtDocs);
    if ($rowDocs = $resDocs->fetch_assoc()) {
      $docs_unread_count = (int)$rowDocs['total'];
    }
    $stmtDocs->close();
  }
}

$has_new_docs = $docs_unread_count > 0;






?>





<style>
    /* Header Component Styles */
    /* Todas las variables CSS y estilos base están en valirica-design-system.css */

    /* Barra superior (sticky + glassmorphism) */
    header {
      position:sticky;
      top:0;
      width:100%;
      background:var(--c-primary);
      color:var(--c-soft);
      padding:16px clamp(20px,4vw,40px);
      display:flex;
      align-items:center;
      justify-content:space-between;
      box-shadow:var(--shadow-md);
      z-index:1000;
      transition:all var(--transition);
      border-bottom:1px solid rgba(255,255,255,0.05);
      backdrop-filter:blur(10px);
      -webkit-backdrop-filter:blur(10px);
    }

    /* Header scroll state (añade blur y shadow) */
    header.scrolled {
      background:rgba(1,33,51,0.95);
      box-shadow:var(--shadow-lg);
      backdrop-filter:blur(20px);
      -webkit-backdrop-filter:blur(20px);
    }
    .nav-left {
      display:flex;
      align-items:center;
      gap:var(--space-4);
      flex-shrink:0;
    }

    .brand-logo {
      width:48px;
      height:48px;
      border-radius:var(--radius);
      object-fit:cover;
      background:var(--gray-100);
      box-shadow:var(--shadow);
      transition:transform var(--transition-fast);
      border:2px solid rgba(255,255,255,0.1);
    }
    .brand-logo:hover {
      transform:scale(1.05);
    }

    .title {
      display:flex;
      flex-direction:column;
      gap:2px;
      min-width:0; /* permite text-overflow */
    }

    .title h1 {
      margin:0;
      font-size:clamp(18px,2.2vw,22px);
      font-weight:700;
      color:var(--c-soft);
      letter-spacing:-0.4px;
      line-height:1.2;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .title span {
      font-size:12px;
      font-weight:500;
      color:var(--c-soft);
      opacity:0.75;
      letter-spacing:0.3px;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    /* Nota: .wrap, .grid y .card ya están definidos en valirica-design-system.css */


    /* === Tarjeta: Alineación Cultural (círculo concéntrico) === */
.alignment-wrap {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;          /* cuadrado responsivo */
  min-height: 320px;
}
.alignment-canvas {
  width: 100%;
  height: 100%;
  display: block;
  border-radius: 12px;
}

/* Tooltip */
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
.vl-legend {
  margin-top: 12px;
  font-size: 12px;
  color: var(--c-body);
}
.vl-legend small {
  color: #6c6c6c;
}



/* === Tooltip de ayuda (?) en los KPIs === */
.kpi-help {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  margin-left: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  color: var(--c-soft);
  font-size: 11px;
  font-weight: 700;
  cursor: help;
  line-height: 1;
  user-select: none;
  transition:all var(--transition-fast);
  border:1px solid rgba(255,255,255,0.2);
}

.kpi-help:hover {
  background: rgba(255,255,255,0.25);
  transform:scale(1.1);
}

.kpi-help:hover::after,
.kpi-help:focus::after {
  content: attr(data-tooltip);
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  transform: translateX(0);
  background: rgba(1,33,51,0.98);
  color: #fff;
  padding: 10px 14px;
  font-size: 12px;
  font-weight:500;
  border-radius: var(--radius);
  box-shadow: var(--shadow-xl);
  white-space: normal;
  max-width:280px;
  opacity: 1;
  z-index: 10000;
  line-height:1.5;
  border:1px solid rgba(255,255,255,0.1);
  backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
}

.kpi-help::before {
  content:"";
  position:absolute;
  top:100%;
  right:6px;
  width:0;
  height:0;
  border-left:6px solid transparent;
  border-right:6px solid transparent;
  border-bottom:6px solid rgba(1,33,51,0.98);
  opacity:0;
  transition:opacity var(--transition-fast);
  z-index:10001;
}

.kpi-help:hover::before,
.kpi-help:focus::before {
  opacity:1;
}

.kpi-help::after {
  opacity: 0;
  transition: opacity var(--transition);
}





/* === Tags suaves Valírica === */
.vl-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}
.vl-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 9999px;
  background: var(--c-soft);
  color: var(--c-secondary);
  font-size: 12px;
  line-height: 1;
  border: 1px solid rgba(1,33,51,0.06);
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  user-select: none;
}
.vl-tag::before {
  content: "•";
  font-weight: 700;
  opacity: 0.6;
}




/* === Header KPIs (Alineación + Motivación) === */
.header-kpis {
  display: flex;
  align-items: center;
  gap:clamp(16px,2.5vw,24px);
  flex-wrap:wrap;
}

.kpi {
  display: grid;
  grid-template-columns: auto;
  align-items: center;
  gap: 4px;
  text-align: right;
  color: var(--c-soft);
  padding:var(--space-2) var(--space-3);
  border-radius:var(--radius);
  background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.08);
  transition:all var(--transition-fast);
  min-width:140px;
}

.kpi:hover {
  background:rgba(255,255,255,0.1);
  transform:translateY(-1px);
  box-shadow:var(--shadow-sm);
}

.kpi .kpi-label {
  font-size: 11px;
  line-height: 1.3;
  opacity: 0.8;
  letter-spacing: 0.5px;
  text-transform:uppercase;
  font-weight:600;
}

.kpi .kpi-value {
  font-size: clamp(20px,3vw,24px);
  line-height: 1;
  font-weight: 800;
  color: var(--c-accent);
  letter-spacing:-0.5px;
  transition:color var(--transition-fast);
}

/* Semantic colors para valores según % */
.kpi .kpi-value.kpi-critical {
  color: var(--c-danger); /* < 30% = Rojo */
}

.kpi .kpi-value.kpi-warning {
  color: var(--c-warning); /* 30-60% = Amarillo */
}

.kpi .kpi-value.kpi-good {
  color: var(--c-accent); /* 60-80% = Naranja (default) */
}

.kpi .kpi-value.kpi-excellent {
  color: var(--c-success); /* > 80% = Verde */
}

/* Icono antes del valor KPI (inline) */
.kpi .kpi-icon {
  width: 18px;
  height: 18px;
  opacity: 0.7;
  margin-right: 6px;
  flex-shrink: 0;
}

.kpi-battery {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: flex-end;
}

.kpi-battery img {
  width: 28px;
  height: 14px;
  image-rendering: -webkit-optimize-contrast;
  filter: drop-shadow(0 1px 2px rgba(0,0,0,0.25));
}

.kpi-battery .kpi-badge {
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 9999px;
  background: rgba(255,255,255,0.12);
  color: var(--c-soft);
  border: 1px solid rgba(255,255,255,0.18);
  line-height: 1;
}

/* ===== Responsive Mobile-First ===== */

/* Tablet: 640px - 1023px */
@media (max-width: 1024px){
  header {
    padding: 14px var(--space-6);
  }

  .header-kpis {
    gap: var(--space-3);
  }

  .kpi {
    min-width: 120px;
    padding: var(--space-2);
  }
}

/* Mobile: < 768px */
@media (max-width: 768px){
  header {
    flex-wrap: wrap;
    padding: 12px var(--space-4);
    row-gap: var(--space-3);
  }

  .nav-left {
    gap: var(--space-3);
  }

  .brand-logo {
    width: 40px;
    height: 40px;
  }

  .title h1 {
    font-size: 16px;
  }

  .title span {
    font-size: 11px;
  }

  .header-kpis {
    width: 100%;
    justify-content: flex-start;
    gap: var(--space-2);
    order: 3;
  }

  .kpi {
    text-align: left;
    min-width: 100px;
    padding: var(--space-2) var(--space-3);
  }

  .kpi .kpi-value {
    font-size: 18px;
  }

  .btn-cta-primary.btn-sm {
    padding: 8px 12px;
    font-size: 11px;
  }
}

/* Extra small: < 375px */
@media (max-width: 374px){
  header {
    padding: 10px var(--space-3);
  }

  .brand-logo {
    width: 36px;
    height: 36px;
  }

  .title h1 {
    font-size: 14px;
  }

  .kpi {
    min-width: 90px;
    padding: 6px 10px;
  }

  .kpi .kpi-label {
    font-size: 9px;
  }

  .kpi .kpi-value {
    font-size: 16px;
  }
}



/* Inline row para KPI: valor/batería + chip en una sola línea */
.kpi-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: flex-end;
  flex-wrap: nowrap;
}

/* Permite que el chip se vea bien pegado al valor */
.kpi-inline .kpi-chip {
  margin-left: 6px;
}

/* En móviles, que pueda saltar de línea si no cabe */
@media (max-width: 768px){
  .kpi-inline { flex-wrap: wrap; justify-content: flex-start; }
}



/* === KPI: Estilo de Aprendizaje (reader) === */
.kpi-row {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: flex-end;
}




/* Chip de alineación / estilo (semantic colors) */
.kpi-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: var(--radius-full);
  line-height: 1;
  font-size: 11px;
  font-weight:700;
  letter-spacing:0.3px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.12);
  color: var(--c-soft);
  transition:all var(--transition-fast);
  text-transform:uppercase;
}

.kpi-chip:hover {
  transform:scale(1.02);
}

.kpi-chip.ok {
  border-color: rgba(0,217,143,0.4);
  background: rgba(0,217,143,0.18);
  color:#fff;
  box-shadow:0 0 12px rgba(0,217,143,0.2);
}

.kpi-chip.warn {
  border-color: rgba(255,176,32,0.5);
  background: rgba(255,176,32,0.2);
  color:#fff;
  box-shadow:0 0 12px rgba(255,176,32,0.15);
}

.kpi-chip.danger {
  border-color: rgba(255,59,109,0.5);
  background: rgba(255,59,109,0.2);
  color:#fff;
  box-shadow:0 0 12px rgba(255,59,109,0.2);
}

.kpi-chip svg {
  width: 14px;
  height: 14px;
  display: inline-block;
  flex-shrink:0;
}







/* === Riesgos de fuga === */
.rf-list { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }




/* La tarjeta ya ocupa la columna; garantizamos que la lista use el ancho completo */
#card-riesgos-fuga { width: 100%; }
#card-riesgos-fuga .rf-list { width: 100%; }




/* Ítem ahora con 3 columnas: left (info), center (chip), right (acciones) */
.rf-item {
  display: grid;
  grid-template-columns: 1fr auto auto; /* izquierda ocupa todo, luego chip, luego acciones */
  align-items: center;
  gap: 14px;
  width: 100%;
}

/* IZQUIERDA */
.rf-left { 
  display: grid; 
  grid-template-columns: 40px auto;  /* avatar un poco más grande */
  gap: 12px; 
  min-width: 0; 
}
.rf-avatar {
  width: 40px; height: 40px; border-radius: 9999px;
  background: #EF7F1B; color: #fff; font-weight: 700; font-size: 15px;
  display: grid; place-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.rf-id { display: grid; grid-template-rows: auto auto; gap: 2px; min-width: 0; }
.rf-name { font-weight: 700; color: var(--c-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rf-role { font-weight: 400; font-size:12px; color: #6a6a6a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* CENTRO: chip separado del bloque de nombre/cargo */
.rf-center { 
  display: inline-flex; 
  align-items: center; 
  gap: 10px; 
  justify-self: center;
}

/* DERECHA: pegada al extremo derecho */
.rf-right { 
  display: inline-flex; 
  align-items: center; 
  gap: 12px; 
  justify-self: end; 
}

/* Batería más grande, sin distorsión (proporción intacta) */
.rf-battery { height: 20px; width: auto; display: block; image-rendering: -webkit-optimize-contrast; }
.rf-battery-lg { height: 20px; }     /* ⬅️ tamaño grande */
@media (max-width: 768px){
  .rf-battery-lg { height: 20px; }   /* un toque más pequeño en móvil */
}

/* Chip de riesgo y alerta (igual que antes) */
.rf-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 9999px; line-height: 1; font-size: 12px;
  border: 1px solid rgba(0,0,0,0.06);
  background: color-mix(in srgb, var(--rf-color) 18%, #fff);
  color: #012133;
}
.rf-alert { font-size: 16px; line-height: 1; }

/* Botón */
.rf-btn {
  padding: 8px 12px; border-radius: 10px; background: var(--c-soft); color: var(--c-secondary);
  border: 1px solid rgba(1,33,51,0.08); text-decoration: none; font-size: 12px; font-weight: 600;
}
.rf-btn:hover { background: #fff; }





/* Separador sutil entre miembros */
.rf-list { gap: 16px; } /* puedes ajustar el espacio */
.rf-item { position: relative; }

/* Dibuja una línea muy suave entre items (no en el último) */
.rf-item + .rf-item::before {
  content: "";
  position: absolute;
  top: -8px;              /* aparece justo en el espacio entre items */
  left: 12px;
  right: 12px;
  height: 1px;
  background: linear-gradient(
    90deg,
    rgba(1,33,51,0.04) 0%,
    rgba(1,33,51,0.10) 12%,
    rgba(1,33,51,0.04) 100%
  );
  pointer-events: none;
}



/* === Lista de Riesgos de Equipo (Tarjeta derecha 2) === */
.gr-list { list-style:none; margin:8px 0 0; padding:0; display:flex; flex-direction:column; gap:16px; }
.gr-item { display:grid; grid-template-columns: 1fr auto auto; gap:14px; align-items:center; position:relative; }
.gr-left { display:grid; grid-template-rows:auto auto; gap:4px; min-width:0; }
.gr-title { font-weight:700; color:var(--c-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.gr-desc  { font-size:12px; color:#6a6a6a; line-height:1.5; }
.gr-center { justify-self:center; }
.gr-right { display:inline-flex; gap:12px; justify-self:end; align-items:center; }

.gr-chip {
  display:inline-flex; align-items:center; gap:6px;
  padding:4px 10px; border-radius:9999px; line-height:1; font-size:12px;
  border:1px solid rgba(0,0,0,0.06);
  background: color-mix(in srgb, var(--gr-color) 18%, #fff);
  color:#012133;
}
.gr-score { font-size:12px; opacity:.7; }

.gr-btn {
  padding:8px 12px; border-radius:10px; background:var(--c-soft); color:var(--c-secondary);
  border:1px solid rgba(1,33,51,0.08); text-decoration:none; font-size:12px; font-weight:600;
}
.gr-btn:hover { background:#fff; }

/* Separador sutil entre items (igual patrón que rf) */
.gr-item + .gr-item::before {
  content:""; position:absolute; top:-8px; left:12px; right:12px; height:1px;
  background:linear-gradient(90deg, rgba(1,33,51,0.04) 0%, rgba(1,33,51,0.10) 12%, rgba(1,33,51,0.04) 100%);
}




/* === Scroll interno solo para las listas de las tarjetas derechas === */
/* Riesgos de fuga (ul.rf-list) y Áreas de oportunidad (ul.gr-list) */
#card-riesgos-fuga .rf-list,
#card-riesgos-equipo .gr-list {
  max-height: min(42vh, 520px); /* altura responsiva sin romper layout */
  overflow: auto;               /* habilita el scroll interno */
  padding-right: 6px;           /* evita que el scroll tape contenido */
  scrollbar-gutter: stable;     /* mantiene el ancho al aparecer la barra */
}

/* Estética opcional del scrollbar (respetando tu UI) */
#card-riesgos-fuga .rf-list::-webkit-scrollbar,
#card-riesgos-equipo .gr-list::-webkit-scrollbar {
  width: 8px;
}
#card-riesgos-fuga .rf-list::-webkit-scrollbar-thumb,
#card-riesgos-equipo .gr-list::-webkit-scrollbar-thumb {
  background: rgba(1,33,51,0.18);
  border-radius: 6px;
}
#card-riesgos-fuga .rf-list::-webkit-scrollbar-track,
#card-riesgos-equipo .gr-list::-webkit-scrollbar-track {
  background: transparent;
}

/* En móvil, que no limite la altura ni obligue scroll si no hace falta */
@media (max-width: 1024px) {
  #card-riesgos-fuga .rf-list,
  #card-riesgos-equipo .gr-list {
    max-height: none;
    overflow: visible;
    padding-right: 0;
  }
}







/* === Card Emergencia / CTA Consultoría === */
.card-cta {
  position: relative;
  border: 1px solid rgba(1,33,51,0.08);
  background:
    linear-gradient(#fff,#fff) padding-box,
    linear-gradient(135deg, rgba(239,127,27,0.25), rgba(1,33,51,0.20)) border-box;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}

.card-cta .cta-head {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 10px;
  color: var(--c-secondary);
}
.card-cta .cta-icon {
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--c-soft);
  display: grid; place-items: center; font-weight: 800; color: var(--c-accent);
}
.card-cta .cta-copy { color: var(--c-body); font-size: 14px; line-height: 1.55; }

.cta-actions {
  display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px;
}
.btn-cta-primary, .btn-cta-ghost {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none;
}
.btn-cta-primary {
  background: var(--c-accent); color: #fff; border: 1px solid rgba(0,0,0,0.06);
}
.btn-cta-primary:hover { filter: brightness(0.98); }
.btn-cta-ghost {
  background: var(--c-soft); color: var(--c-secondary); border: 1px solid rgba(1,33,51,0.12);
}
.btn-cta-ghost:hover { background: #fff; }

.cta-bullets {
  margin-top: 10px; color: #5f5f5f; font-size: 13px;
  display: grid; gap: 6px;
}
.cta-bullets span::before { content: "• "; color: var(--c-secondary); font-weight: 700; }



/* === Botón CTA principal (mejor diseño) === */
.btn-cta-primary {
  display: inline-flex;
  align-items: center;
  justify-content:center;
  gap: 8px;
  padding: 11px 18px;
  border-radius: var(--radius);
  font-weight: 700;
  font-size: 13px;
  letter-spacing:0.3px;
  text-decoration: none;
  background: linear-gradient(135deg, var(--c-accent) 0%, #FF8C3A 100%);
  color: #fff;
  border: none;
  box-shadow: var(--shadow), 0 0 20px rgba(239,127,27,0.2);
  cursor: pointer;
  transition:all var(--transition);
  white-space:nowrap;
  position:relative;
  overflow:hidden;
}

.btn-cta-primary::before {
  content:"";
  position:absolute;
  top:0;
  left:-100%;
  width:100%;
  height:100%;
  background:linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition:left var(--transition-slow);
}

.btn-cta-primary:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg), 0 0 30px rgba(239,127,27,0.35);
}

.btn-cta-primary:hover::before {
  left:100%;
}

.btn-cta-primary:active {
  transform: translateY(0);
}

/* Tamaño compacto para header */
.btn-cta-primary.btn-sm {
  padding: 9px 16px;
  font-size: 12px;
  border-radius: var(--radius-sm);
}

/* Botón Secondary (Ghost style - menor jerarquía) */
.btn-cta-secondary {
  display: inline-flex;
  align-items: center;
  justify-content:center;
  gap: 8px;
  padding: 11px 18px;
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 13px;
  letter-spacing:0.3px;
  text-decoration: none;
  background: transparent;
  color: var(--c-soft);
  border: 1.5px solid rgba(255,255,255,0.3);
  cursor: pointer;
  transition:all var(--transition);
  white-space:nowrap;
}

.btn-cta-secondary:hover {
  background: rgba(255,255,255,0.1);
  border-color: rgba(255,255,255,0.5);
  transform: translateY(-1px);
}

.btn-cta-secondary:active {
  transform: translateY(0);
}

.btn-cta-secondary.btn-sm {
  padding: 9px 16px;
  font-size: 12px;
  border-radius: var(--radius-sm);
}

/* Avatar + User Menu Dropdown */
.user-menu {
  position: relative;
  display: inline-block;
}



.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--c-accent) 0%, #FF8C3A 100%);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  border: 2px solid rgba(255,255,255,0.2);
  box-shadow: var(--shadow);
  text-transform: uppercase;
  letter-spacing: -0.5px;
}

.user-avatar:hover {
  transform: scale(1.05);
  box-shadow: var(--shadow-md), 0 0 20px rgba(239,127,27,0.3);
  border-color: rgba(255,255,255,0.4);
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  min-width: 220px;
  background: rgba(1,33,51,0.98);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xl);
  border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: all var(--transition);
  z-index: 10000;
  overflow: hidden;
}

.user-menu.is-open .user-dropdown {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

.user-dropdown-header {
  padding: 16px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.user-dropdown-name {
  font-weight: 700;
  font-size: 14px;
  color: var(--c-soft);
  margin-bottom: 4px;
}

.user-dropdown-email {
  font-size: 12px;
  color: var(--c-soft);
  opacity: 0.7;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-dropdown-menu {
  list-style: none;
  padding: 8px;
  margin: 0;
}

.user-dropdown-item {
  margin: 0;
}

.user-dropdown-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  color: var(--c-soft);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
}

.user-dropdown-link:hover {
  background: rgba(255,255,255,0.1);
  transform: translateX(2px);
}

.user-dropdown-link svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  opacity: 0.8;
}

.user-dropdown-divider {
  height: 1px;
  background: rgba(255,255,255,0.1);
  margin: 8px 0;
}

@media (max-width: 768px) {
  .user-avatar {
    width: 36px;
    height: 36px;
    font-size: 12px;
  }

  .user-dropdown {
    min-width: 200px;
  }
}


/* === Notificaciones Documentos === */

.user-avatar-wrapper {
  position: relative;
  display: inline-block;
}

.avatar-notification-dot {
  position: absolute;
  top: -2px;
  right: -2px;
  width: 10px;
  height: 10px;
  background: var(--c-accent);
  border-radius: 50%;
  border: 2px solid var(--c-primary);
  box-shadow: 0 0 0 2px rgba(239,127,27,0.2);
}

.notification-badge {
  margin-left: auto;
  background: var(--c-accent);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 7px;
  border-radius: 9999px;
  min-width: 18px;
  text-align: center;
  box-shadow: 0 0 8px rgba(239,127,27,0.3);
}

.documentos-link {
  position: relative;
  display: flex;
  align-items: center;
}

.documentos-link.has-notification {
  background: rgba(239,127,27,0.08);
}









/* ===== Submenú secundario (pills style) ===== */
.subnav {
  width: 100%;
  background: var(--gray-50);
  border: 0;
  border-bottom: 1px solid var(--gray-200);
  box-shadow: var(--shadow-sm);
  position:sticky;
  top:72px; /* debajo del header */
  z-index:999;
  transition:all var(--transition);
}

.subnav-inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 10px clamp(16px,3vw,40px);
  overflow-x:auto;
  scrollbar-width:thin;
  -webkit-overflow-scrolling:touch;
}

.subnav-inner::-webkit-scrollbar {
  height:4px;
}

.subnav-inner::-webkit-scrollbar-thumb {
  background:var(--gray-200);
  border-radius:2px;
}

.subnav-list {
  display: flex;
  align-items: center;
  justify-content:flex-start;
  list-style: none;
  gap: var(--space-2);
  padding: 0;
  min-width:max-content;
}

/* Pills style */
.subnav-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 38px;
  padding: 0 18px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-600);
  text-decoration: none;
  letter-spacing: 0.2px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: var(--radius);
  transition: all var(--transition-fast);
  white-space:nowrap;
}

.subnav-link:hover {
  background: rgba(239,127,27,0.08);
  color: var(--c-accent);
  border-color:rgba(239,127,27,0.15);
}

/* Estado activo: pill filled */
.subnav-link.is-active {
  background: var(--c-accent);
  color: #fff;
  border-color: var(--c-accent);
  box-shadow: var(--shadow-sm), 0 0 12px rgba(239,127,27,0.25);
  font-weight:700;
}

.subnav-link.is-active:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow), 0 0 16px rgba(239,127,27,0.35);
}

.subnav-link:focus-visible {
  outline: 2px solid var(--c-accent);
  outline-offset: 2px;
}

@media (max-width: 768px){
  .subnav {
    top:68px;
  }
  .subnav-inner {
    padding: 8px var(--space-4);
  }
  .subnav-link {
    font-size: 12px;
    height: 36px;
    padding: 0 14px;
  }
}












/* Botón CTA con logo del proveedor */
.btn-cta-with-logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 14px;
  font-weight: 800;
  letter-spacing: .2px;
  box-shadow: var(--shadow);
  transition: transform .08s ease, box-shadow .12s ease, filter .12s ease;
  will-change: transform;
}
.btn-cta-with-logo:hover {
  transform: translateY(-1px);
  filter: brightness(0.98);
  box-shadow: 0 10px 24px rgba(0,0,0,0.10);
}
.btn-cta-with-logo .btn-logo {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: #fff;      /* marco neutro para PNGs con transparencia */
  object-fit: contain;
  box-shadow: 0 1px 2px rgba(0,0,0,0.10);
}











/* === CTA con logo fuera del botón === */
.cta-with-logo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap; /* móvil: botón arriba, logo abajo */
  gap: 16px;
  margin-top: 18px;
}

/* Botón principal */
.cta-with-logo .btn-cta-primary {
  flex-shrink: 0;
  font-size: 15px;
  font-weight: 700;
  padding: 12px 20px;
  border-radius: 14px;
  background: var(--c-accent);
  color: #fff;
  border: none;
  box-shadow: var(--shadow);
  transition: transform .12s ease, box-shadow .15s ease;
}
.cta-with-logo .btn-cta-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.12);
  filter: brightness(0.98);
}

/* Logo del provider fuera del botón */
.cta-provider-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border: 1px solid rgba(1,33,51,0.08);
  border-radius: 12px;
  padding: 10px 14px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  transition: transform .12s ease;
}
.cta-provider-logo:hover {
  transform: scale(1.02);
}

.cta-provider-logo img {
  max-height: 40px;
  width: auto;
  display: block;
  object-fit: contain;
}

/* Responsive: botón centrado + logo debajo */
@media (max-width: 768px) {
  .cta-with-logo {
    flex-direction: column;
    align-items: flex-start;
  }
  .cta-provider-logo {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 10px;
  }
  .cta-provider-logo img {
    max-height: 36px;
  }
}









  </style>


    <!-- Barra superior -->
 <header>
  <div class="nav-left">
    <!-- Logo dinámico -->
    <img class="brand-logo"
     src="<?php echo h(!empty($logo) ? resolve_logo_url($logo) : 'https://app.valirica.com/uploads/logo-192.png'); ?>"

     alt="Logo de <?php echo h($empresa ?? 'Empresa'); ?>">

    <!-- Datos de identidad -->
    <div class="title">
      <h1><?php echo h($empresa ?? 'Nombre de la empresa'); ?></h1>
      <span>
        Cultura: <?php echo !empty($cultura_empresa_tipo) ? h($cultura_empresa_tipo) : 'No definida'; ?>
      </span>
    </div>
  </div>

  <!-- KPIs de estado en header -->
  
    <!-- KPIs de estado en header -->
    
    
  <div class="header-kpis">
      
      
    <!-- Alineación Cultural -->
    
    
    <div class="kpi">
  <div class="kpi-label">
    Alineación Cultural
    <span class="kpi-help" tabindex="0"
      data-tooltip="Tu cultura ideal: <?php echo h($cultura_empresa_tipo ?: 'No definida'); ?> | Tu equipo promedia: <?php echo (float)$promedio_general; ?>%&#10;&#10;Esto significa que <?php echo $promedio_general >= 70 ? 'la mayoría de' : ($promedio_general >= 40 ? 'una parte de' : 'pocos'); ?> tu equipo piensan y actúan según los valores culturales que definiste.<?php if($promedio_general < 60): ?>&#10;&#10;⚠️ Atención: Bajo 60% indica riesgo de desconexión cultural y posible fuga de talento.<?php endif; ?>">?</span>
  </div>

  <!-- MISMA LÍNEA: icono + % + CHIP -->
  <div class="kpi-inline">
    <?php
    // Semantic color según %
    $valor_class = '';
    if ($promedio_general < 30) { $valor_class = 'kpi-critical'; }
    elseif ($promedio_general < 60) { $valor_class = 'kpi-warning'; }
    elseif ($promedio_general < 80) { $valor_class = 'kpi-good'; }
    else { $valor_class = 'kpi-excellent'; }
    ?>

    <!-- Icono Chart inline -->
    <svg class="kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="20" x2="12" y2="10"></line>
      <line x1="18" y1="20" x2="18" y2="4"></line>
      <line x1="6" y1="20" x2="6" y2="16"></line>
    </svg>

    <div class="kpi-value <?php echo $valor_class; ?>"><?php echo (float)$promedio_general; ?>%</div>

    <span class="kpi-chip <?php echo $aline_class; ?>">
      <span><?php echo h($aline_label); ?></span>

      <?php if ($aline_icon === 'check'): ?>
        <!-- Icono Check -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
      <?php elseif ($aline_icon === 'x'): ?>
        <!-- Icono X -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      <?php endif; ?>
    </span>
  </div>
</div>



    <!-- Motivación Colectiva -->
    
    
    <div class="kpi">
  <div class="kpi-label">
    Motivación Colectiva
    <span class="kpi-help" tabindex="0"
      data-tooltip="Tu equipo: <?php echo $energia_equipo; ?>% de energía colectiva | Estado: <?php echo h($energia_status); ?>&#10;&#10;Combina motivación intrínseca (propósito, autonomía, maestría) con necesidades básicas cubiertas.&#10;&#10;<?php if($energia_equipo < 50): ?>⚠️ Energía baja indica riesgo de burnout o fuga.<?php elseif($energia_equipo >= 75): ?>✓ Equipo motivado y con necesidades cubiertas.<?php else: ?>Energía media: revisa carga laboral y reconocimiento.<?php endif; ?>">?</span>
  </div>

  <!-- MISMA LÍNEA: icono batería + % + CHIP -->
  <div class="kpi-inline">
    <?php
    // Semantic color según %
    $energia_class = '';
    if ($energia_equipo < 30) { $energia_class = 'kpi-critical'; }
    elseif ($energia_equipo < 60) { $energia_class = 'kpi-warning'; }
    elseif ($energia_equipo < 80) { $energia_class = 'kpi-good'; }
    else { $energia_class = 'kpi-excellent'; }
    ?>

    <!-- Icono Battery inline -->
    <svg class="kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="6" width="18" height="12" rx="2" ry="2"></rect>
      <line x1="23" y1="13" x2="23" y2="11"></line>
    </svg>

    <div class="kpi-value <?php echo $energia_class; ?>" title="<?php echo $energia_equipo; ?>%"><?php echo $energia_equipo; ?>%</div>

    <span class="kpi-chip <?php echo $mot_class; ?>">
      <span><?php echo h($mot_label); ?></span>

      <?php if ($mot_icon === 'check'): ?>
        <!-- Icono Check -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
      <?php elseif ($mot_icon === 'x'): ?>
        <!-- Icono X -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      <?php endif; ?>
    </span>
  </div>
</div>



    <!-- Estilo de Aprendizaje (Equipo vs Cultura) -->
    
    
    <!-- Estilo de Aprendizaje (Equipo vs Cultura) -->
<div class="kpi">
  <div class="kpi-label">
    Estilo de Aprendizaje
    <span class="kpi-help" tabindex="0"
      data-tooltip="Tu marca definió: <?php echo h($estilo_marca_label); ?> | Tu equipo promedia: <?php echo h($estilo_equipo_label); ?>&#10;&#10;<?php if($aprend_alineado): ?>✓ Alineados: Tu equipo aprende de la forma en que tu marca comunica.<?php else: ?>⚠️ Desalineación: Tu marca comunica de forma <?php echo h($estilo_marca_label); ?> pero tu equipo aprende mejor con métodos <?php echo h($estilo_equipo_label); ?>.&#10;&#10;Acción: Adapta tus recursos de capacitación<?php if($estilo_equipo_label == 'Kinestésico'): ?> a métodos hands-on, simulaciones y práctica<?php elseif($estilo_equipo_label == 'Auditivo'): ?> a podcasts, conversaciones y debates<?php else: ?> a infografías, videos y presentaciones visuales<?php endif; ?>.<?php endif; ?>">?</span>
  </div>

    <div class="kpi-row">
    <!-- Icono Book inline -->
    <svg class="kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
    </svg>

    <span class="kpi-chip <?php echo $aprend_alineado ? 'ok' : 'warn'; ?>">
      <?php if ($aprend_alineado): ?>
        <!-- Caso ALINEADO: solo la marca + check -->
        <span><?php echo h($estilo_marca_label); ?></span>
        <!-- Icono Check -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
      <?php else: ?>
        <!-- Caso NO alineado: Marca Vs Equipo -->
        <span>
          <?php echo h($estilo_marca_label); ?> Vs <?php echo h($estilo_equipo_label); ?>
        </span>
        <!-- Icono X -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      <?php endif; ?>
    </span>
  </div>

</div>



 <button
    type="button"
    class="btn-cta-primary btn-sm"
    id="btnInvite"
    onclick="copyInviteLink()"
    title="Copiar enlace de invitación">
    Invitar al equipo
  </button>

<?php if ($viewer_rol === 'provider'): ?>
  <button
    type="button"
    class="btn-cta-secondary btn-sm"
    id="btnInviteCompany"
    onclick="createAndCopyCompanyInvite()"
    title="Crear y copiar enlace de registro para una empresa">
    Invitar empresa
  </button>
<?php endif; ?>

<!-- Avatar + User Menu -->

<?php
// ============================
// Generar iniciales empresa
// ============================

$empresa_display = isset($empresa) && trim($empresa) !== '' 
  ? $empresa 
  : (isset($viewer_empresa) ? $viewer_empresa : 'Usuario');

$empresa_display = trim((string)$empresa_display);

$partes = explode(' ', $empresa_display);
$iniciales = '';

if (count($partes) >= 2) {
  $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
  $iniciales = strtoupper(substr($empresa_display, 0, 2));
}

// Seguridad extra
if ($iniciales === '') {
  $iniciales = 'U';
}
?>



<div class="user-menu" id="userMenu">
    <div class="user-avatar-wrapper">
      <div class="user-avatar <?php echo $has_new_docs ? 'has-notification' : ''; ?>"
           id="userAvatar"
           role="button"
           aria-haspopup="true"
           aria-expanded="false">
        <?php echo htmlspecialchars((string)$iniciales, ENT_QUOTES, 'UTF-8'); ?>

      </div>
    
      <?php if ($has_new_docs): ?>
        <span class="avatar-notification-dot"></span>
      <?php endif; ?>
    </div>


  <div class="user-dropdown" id="userDropdown">
    <div class="user-dropdown-header">
      <div class="user-dropdown-name"><?php echo h($empresa ?? 'Usuario'); ?></div>
      <div class="user-dropdown-email"><?php echo h($usuario['email'] ?? ''); ?></div>
    </div>

    <ul class="user-dropdown-menu">
      <li class="user-dropdown-item">
        <a href="perfil.php" class="user-dropdown-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <span>Mi Perfil</span>
        </a>
      </li>

      <li class="user-dropdown-item">
        <a href="documentos.php" class="user-dropdown-link documentos-link <?php echo $has_new_docs ? 'has-notification' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
        
          <span>Documentos</span>
        
          <?php if ($has_new_docs): ?>
            <span class="notification-badge">
              <?php echo $docs_unread_count; ?>
            </span>
          <?php endif; ?>
        </a>

      </li>

      <div class="user-dropdown-divider"></div>

      <li class="user-dropdown-item">
        <a href="logout.php" class="user-dropdown-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span>Cerrar Sesión</span>
        </a>
      </li>
    </ul>
  </div>
</div>


  </div>

</header>





<?php
  // Script actual para poder marcar la pestaña activa
  $current_script = basename($_SERVER['PHP_SELF'] ?? '');

  $is_tab_cultura   = ($current_script === 'a-desktop-dashboard-brand.php');
  $is_tab_proposito = ($current_script === 'a-cultura-proposito-valores.php');
  $is_tab_companies = ($current_script === 'a-provider_companies.php');

  // Para mantener el contexto de empresa cuando hay ?usuario_id=
  $usuario_id_param = '';
  if (isset($usuario_id) && (int)$usuario_id > 0) {
    $usuario_id_param = '?usuario_id=' . (int)$usuario_id;
  }
?>



<?php
  // === ID del usuario logueado para menú especial de INNERMETRIX ===
  // === ID del usuario logueado para menú especial de INNERMETRIX ===
  // === ID del usuario logueado para menú especial de INNERMETRIX ===
  // === ID del usuario logueado para menú especial de INNERMETRIX ===
  $viewer_id_current = 0;

  // 1) Si la vista ya pasó $viewer_id, lo usamos
  if (isset($viewer_id) && (int)$viewer_id > 0) {
    $viewer_id_current = (int)$viewer_id;

  // 2) Si no, usamos el id de sesión (fallback habitual en tu app)
  } elseif (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $viewer_id_current = (int)$_SESSION['user_id'];
  }

  // Lista de IDs que verán el menú especial (puedes añadir más)
  $menu_ids_especiales = [51];
?>






<!-- Submenú secundario (sutil con línea separadora) -->
<nav class="subnav" aria-label="Navegación secundaria">
  <div class="subnav-inner">
    <ul class="subnav-list" role="tablist">

      <!-- 🔹 Ítems comunes para cualquier rol -->
<li role="presentation">
  <a class="subnav-link<?= $is_tab_cultura ? ' is-active' : '' ?>"
     href="a-desktop-dashboard-brand.php<?= $usuario_id_param ?>"
     role="tab"
     aria-selected="<?= $is_tab_cultura ? 'true' : 'false' ?>"
     data-key="cultura-alineacion">
    Riesgos y oportunidades
  </a>
</li>

      <li role="presentation">
        <a class="subnav-link"
           href="a-analisis-equipos.php<?= $usuario_id_param ?>"
           role="tab"
           aria-selected="false"
           data-key="tu-equipo">
          Mapa cultural
        </a>
      </li>

      <li role="presentation">
        <a class="subnav-link"
           href="a-desempeno-dashboard.php<?= $usuario_id_param ?>"
           role="tab"
           aria-selected="false"
           data-key="aprender-actuar">
          Desempeño y Asistencia
        </a>
      </li>



<!--    TAB PARA BENEFICIOS  TAB PARA BENEFICIOS  TAB PARA BENEFICIOS  TAB PARA BENEFICIOS



      <li role="presentation">
        <a class="subnav-link"
           href="a-desempeno-dashboard.php<?= $usuario_id_param ?>"
           role="tab"
           aria-selected="false"
           data-key="aprender-actuar">
          Beneficios
        </a>
      </li>
-->
      <!-- 🔸 Ítems extra SOLO para PROVIDER -->
<?php if ($viewer_rol === 'provider'): ?>
  
  <li role="presentation">
    <a class="subnav-link<?= $is_tab_companies ? ' is-active' : '' ?>"
       href="a-provider_companies.php"
       role="tab"
       aria-selected="<?= $is_tab_companies ? 'true' : 'false' ?>"
       data-key="vista-provider">
      Formación
    </a>
  </li>
  
  <li role="presentation">
    <a class="subnav-link<?= $is_tab_companies ? ' is-active' : '' ?>"
       href="a-provider_companies.php"
       role="tab"
       aria-selected="<?= $is_tab_companies ? 'true' : 'false' ?>"
       data-key="vista-provider">
      Clientes
    </a>
  </li>
  
<?php endif; ?>

      <!-- 🔹 Ítems extra SOLO para ciertos IDs de usuario -->
<?php if (in_array($viewer_id_current, $menu_ids_especiales, true)): ?>
  <li role="presentation">
    <a class="subnav-link"
       href="a-innermetrix-import.php"
       role="tab"
       aria-selected="false"
       data-key="vista-especial">
      Innermetrix
    </a>
  </li>
<?php endif; ?>






        <!-- Aquí luego añadimos las nuevas secciones que quieras para provider -->
        <!--
        <li role="presentation">
          <a class="subnav-link"
             href="a-provider_company_riesgos.php?company_id=<?= (int)$usuario_id ?>"
             role="tab"
             aria-selected="false"
             data-key="riesgos-fuga">
            Riesgos de fuga
          </a>
        </li>
        -->

    </ul>
  </div>
</nav>



<script>
function copyInviteLink() {
const empresaId = <?= json_encode($usuario_id) ?>;
  const url = `https://app.valirica.com/formularios/formulario_datos_colaborador.php?usuario_id=${empresaId}`;

  const btn = document.getElementById('btnInvite');
  const original = btn.textContent;

  navigator.clipboard.writeText(url)
    .then(() => {
      btn.textContent = '¡Enlace copiado! ✅';
      btn.disabled = true;
      setTimeout(() => {
        btn.textContent = original;
        btn.disabled = false;
      }, 2000);
    })
    .catch(() => {
      // Fallback: crea input temporal si el Clipboard API falla
      const el = document.createElement('input');
      el.value = url;
      document.body.appendChild(el);
      el.select();
      try { document.execCommand('copy'); } catch(e){}
      document.body.removeChild(el);

      btn.textContent = 'Copiado (fallback) ✅';
      setTimeout(() => { btn.textContent = original; }, 2000);
    });
}
</script>




<script>
/* ====== COPIA ROBUSTA AL PORTAPAPELES (HTTPS + fallback) ====== */
async function copyInviteRobust(text) {
  // 1) Intento moderno (HTTPS o localhost)
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (_) { /* sigue al fallback */ }
  }
  // 2) Fallback universal (sirve en HTTP y navegadores viejos)
  try {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    return true;
  } catch (_) {
    return false;
  }
}

/* ====== CREAR INVITACIÓN Y COPIAR ENLACE ====== */
async function createAndCopyCompanyInvite() {
  const btn = document.getElementById('btnInviteCompany');
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Creando enlace…';

  const endpoint = new URL('create_invite.php', window.location.href).toString();
  let inviteUrl = '';
  let serverPayload = null;

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });

    const raw = await res.text();
    try { serverPayload = JSON.parse(raw); } catch { serverPayload = { ok: false, raw }; }

    if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);
    if (!serverPayload || serverPayload.ok !== true || !serverPayload.url) {
      const errMsg = (serverPayload && (serverPayload.error || serverPayload.raw)) || 'Respuesta inválida';
      throw new Error(errMsg);
    }

    inviteUrl = serverPayload.url;

    // Copiar (con fallback) y feedback de UI
    const copied = await copyInviteRobust(inviteUrl);
    btn.textContent = copied ? '¡Enlace copiado! ✅' : 'Copia manual ⤵';
    showInviteBanner(inviteUrl, copied);

  } catch (err) {
    console.error('Invite error:', err, 'payload:', serverPayload);
    if (inviteUrl) {
      showInviteBanner(inviteUrl, false);
    } else {
      const details = (serverPayload && (serverPayload.error || serverPayload.raw)) ? `\n\nDetalle: ${serverPayload.error || serverPayload.raw}` : '';
      alert('No se pudo crear el enlace.\nRevisa la consola (F12 → Network → create_invite.php).' + details);
    }
  } finally {
    setTimeout(() => {
      btn.textContent = original;
      btn.disabled = false;
    }, 1600);
  }
}

/* ====== BANNER CON ENLACE + BOTÓN COPIAR ====== */
function showInviteBanner(url, copiedInitially) {
  let banner = document.getElementById('inviteBanner');
  if (!banner) {
    banner = document.createElement('div');
    banner.id = 'inviteBanner';
    banner.style.position = 'fixed';
    banner.style.bottom = '16px';
    banner.style.right = '16px';
    banner.style.zIndex = '9999';
    banner.style.maxWidth = '90vw';
    banner.style.background = '#fff';
    banner.style.border = '1px solid rgba(0,0,0,0.08)';
    banner.style.boxShadow = '0 6px 20px rgba(0,0,0,0.12)';
    banner.style.borderRadius = '12px';
    banner.style.padding = '12px';

    banner.innerHTML = `
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-family: gelica, system-ui;">
        <strong style="color:#184656;">Enlace de registro (empresa)</strong>
        <button id="inviteBannerCopy" type="button"
          style="margin-left:auto; padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.1); background:#FFF5F0; color:#184656; cursor:pointer; font-weight:700;">
          Copiar
        </button>
        <button id="inviteBannerClose" type="button"
          style="padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.1); background:#ffffff; color:#184656; cursor:pointer;">
          Cerrar
        </button>
      </div>
      <input id="inviteBannerInput" type="text" readonly
        style="width:100%; padding:8px; border:1px solid #eee; border-radius:8px; font-size:14px; color:#012133; font-family: gelica, system-ui;">
    `;
    document.body.appendChild(banner);

    // Botón Copiar del banner (usa el mismo fallback robusto)
    document.getElementById('inviteBannerCopy').addEventListener('click', async () => {
      const input = document.getElementById('inviteBannerInput');
      const ok = await copyInviteRobust(input.value);
      const btn = document.getElementById('inviteBannerCopy');
      const txt = btn.textContent;
      btn.textContent = ok ? '¡Copiado!' : 'No se pudo copiar';
      setTimeout(() => { btn.textContent = txt; }, 1200);
    });

    document.getElementById('inviteBannerClose').addEventListener('click', () => banner.remove());
  }
  document.getElementById('inviteBannerInput').value = url;

  // Si ya se copió automáticamente, deja marcado el botón un instante
  const copyBtn = document.getElementById('inviteBannerCopy');
  if (copiedInitially) {
    const txt = copyBtn.textContent;
    copyBtn.textContent = '¡Copiado!';
    setTimeout(() => { copyBtn.textContent = txt; }, 1200);
  }
}

/* ====== HEADER SCROLL EFFECT (Glassmorphism) ====== */
(function initHeaderScrollEffect() {
  const header = document.querySelector('header');
  if (!header) return;

  let ticking = false;
  let lastScrollY = window.scrollY;

  function updateHeader() {
    const scrollY = window.scrollY;

    if (scrollY > 20) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }

    lastScrollY = scrollY;
    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(updateHeader);
      ticking = true;
    }
  }

  // Initial check
  if (window.scrollY > 20) {
    header.classList.add('scrolled');
  }

  // Listen to scroll
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* ====== USER MENU TOGGLE ====== */
(function initUserMenu() {
  const userMenu = document.getElementById('userMenu');
  const userAvatar = document.getElementById('userAvatar');
  const userDropdown = document.getElementById('userDropdown');

  if (!userMenu || !userAvatar) return;

  // Toggle menu on avatar click
  userAvatar.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = userMenu.classList.contains('is-open');
    userMenu.classList.toggle('is-open');
    userAvatar.setAttribute('aria-expanded', !isOpen);
  });

  // Close menu when clicking outside
  document.addEventListener('click', function(e) {
    if (!userMenu.contains(e.target)) {
      userMenu.classList.remove('is-open');
      userAvatar.setAttribute('aria-expanded', 'false');
    }
  });

  // Close menu on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && userMenu.classList.contains('is-open')) {
      userMenu.classList.remove('is-open');
      userAvatar.setAttribute('aria-expanded', 'false');
      userAvatar.focus(); // Return focus to avatar for accessibility
    }
  });
})();
</script>