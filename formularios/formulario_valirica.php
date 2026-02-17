<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Formulario Valírica</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Glegoo:wght@400;700&amp;display=swap"
      rel="stylesheet"
    />
    <style>
      @import url("https://use.typekit.net/qrv8fyz.css");
      body {
        background-color: #FFF4EE;
        font-family: "Gelica", sans-serif, Arial, Helvetica;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
      }
      .valirica-formulario {
        width: 90%;
        max-width: 900px;
        background-color: white;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
      }
      header h1 {
        color: #333;
        font-size: 50px;
        margin-bottom: 10px;
        text-align: center;
        font-family: "Gelica"
      }
      header p {
        text-align: center;
        font-size: 25px;
        margin-bottom: 30px;
        font-family: "Gelica"
      }
      h2 {
        color: #FF7800;
        font-size: 30px;
        margin-top: 20px;
        font-family: "Gelica"
      }
      label {
        font-size: 40px;
        display: block;
        margin-bottom: 35px;
        font-family: "Gelica"
      }
      select {
        font-size: 25px;
        width: 100%;
        padding: 10px;
        margin-bottom: 30px;
        font-family: "Gelica"
      }
      .navegacion {
        text-align: center;
        margin-top: 20px;
      }
      .navegacion button {
        font-size: 20px;
        margin: 5px;
        padding: 10px 20px;
        background-color: #FF7800;
        border: none;
        color: white;
        border-radius: 6px;
        cursor: pointer;
      }
      .navegacion button:hover {
        background-color: #e16e00;
      }
      #progress-bar-container {
        background-color: #ddd;
        height: 20px;
        width: 100%;
        margin: 30px 0;
        border-radius: 10px;
        overflow: hidden;
      }
      #progress-bar {
        height: 100%;
        width: 0%;
        background-color: #FF7800;
        text-align: center;
        color: white;
        line-height: 20px;
        transition: width 0.3s ease;
      }
      .feedback-zona {
        margin-top: 30px;
        font-size: 20px;
        color: #004758;
        text-align: center;
      }
      .navegacion button,
      select,
      option {
        font-family: "Gelica", sans-serif, Arial, Helvetica;
        font-size: 25px;
      }
      h1 {
        font-size: 50px;
        color: #FF6600;
        margin-bottom: 20px;
      }
      p {
        font-size: 25px;
      }
      label,
      select {
        font-size: 30px;
      }
      .pregunta {
        margin-bottom: 40px;
      }
      .modulo {
        display: none;
      }
      .modulo.activo {
        display: block;
      }
      .barra-progreso {
        width: 100%;
        background: #eee;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 30px;
        height: 12px;
      }
      .progreso {
        height: 100%;
        background: #00c6a7;
        width: 0%;
        transition: width 0.3s ease-in-out;
      }
      button {
        font-size: 25px;
        background-color: #FF6600;
        color: white;
        border: none;
        padding: 10px 20px;
        margin: 10px 5px 0 0;
        border-radius: 5px;
        cursor: pointer;
      }
      button#enviar {
        background-color: #00c6a7;
      }
      button:hover {
        opacity: 0.9;
      }
      .modulo {
        display: none;
      }
      .modulo.activo {
        display: block;
      }
      .barra-progreso {
        width: 100%;
        background: #eee;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 20px;
      }
      .progreso {
        height: 10px;
        background: #00c6a7;
        width: 0%;
        transition: width 0.3s ease-in-out;
      }
      button {
        margin-top: 15px;
      }
      
      
      /* === UX/UI Valírica — Overrides visuales para unificar con el Dashboard === */
@import url("https://use.typekit.net/qrv8fyz.css");

:root{
  /* Design tokens del dashboard */
  --c-primary:#012133;         /* azul noche */
  --c-secondary:#184656;       /* verde petróleo */
  --c-accent:#EF7F1B;          /* naranja Valírica */
  --c-soft:#FFF5F0;            /* fondo suave */
  --c-body:#474644;            /* texto */
  --c-bg:#FFFFFF;              /* fondo base */
  --radius:20px;               /* radios consistentes */
  --shadow:0 6px 20px rgba(0,0,0,0.06);
  --shadow-hover:0 8px 20px rgba(0,0,0,0.08);
}

/* Tipografía y base */
html, body{
  background:var(--c-bg) !important;
  color:var(--c-body) !important;
  font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif !important;
}

/* ===== Header (ya existente en el form) con look del dashboard ===== */
header{
  background:var(--c-primary) !important;
  color:var(--c-soft) !important;
  padding:18px 24px !important;
  box-shadow:0 3px 12px rgba(0,0,0,0.08);
  border-radius:0 0 16px 16px;
}
header h1{
  color:var(--c-soft) !important;
  font-size:clamp(22px,3vw,32px) !important;
  margin:0 0 6px 0 !important;
  letter-spacing:-0.3px;
}
header p{
  color:rgba(255,255,255,.85) !important;
  font-size:clamp(14px,1.8vw,18px) !important;
  margin:0 !important;
}

/* ===== Contenedor principal del formulario ===== */
main, .container, .form-wrapper{
  max-width:1100px;
  margin:24px auto;
  padding:0 16px;
}

/* ===== Tarjetas (aplicadas a cada .modulo del form) ===== */
.modulo{
  background:#fff !important;
  border-radius:var(--radius) !important;
  box-shadow:var(--shadow) !important;
  padding:22px !important;
  margin:0 0 24px 0 !important;
  transition:transform .2s ease, box-shadow .2s ease;
}
.modulo.activo{ display:block !important; }
.modulo:hover{ transform:translateY(-2px); box-shadow:var(--shadow-hover); }
/* Títulos de módulo con acento Valírica */
.modulo h2{
  font-size:clamp(18px,2.6vw,22px);
  border-left:4px solid var(--c-accent);
  padding-left:10px;
}
.modulo h3{
  font-size:clamp(16px,2.2vw,18px);
  color:#184656;
}
}

/* Sub-secciones dentro de cada módulo (si usas fieldset/legend) */
fieldset{
  border:1px solid #eee; border-radius:14px; padding:16px 14px; margin:14px 0;
  background:#fff;
}
legend{
  padding:0 8px; font-weight:600; color:#012133;
}

/* ===== Inputs & Selects con estilos del dashboard ===== */
input[type="text"], input[type="email"], input[type="number"],
input[type="date"], textarea, select{
  width:100%;
  background:#fff;
  border:1px solid #E7E7E7;
  border-radius:14px;
  padding:12px 14px;
  font-size:16px;
  outline:none;
  box-shadow:0 0 0 0 rgba(0,0,0,0);
  transition:border-color .15s ease, box-shadow .15s ease;
  appearance:none;

}

/* Los selects de ranking deben mantener render nativo */
.conflicto-selector,
.maslow-selector{
  appearance:auto !important;
}


/* Estándar Valírica para rankings numéricos */
.conflicto-selector,
.maslow-selector{
  min-height:44px;
  line-height:44px;
  text-align:center;
  font-weight:600;
  box-sizing:border-box;
}


input:focus, textarea:focus, select:focus{
  border-color:var(--c-secondary);
  box-shadow:0 0 0 3px rgba(24,70,86,.12);
}

/* Radios/checkbox alineados con la estética */
label{
  display:block; margin:8px 0; line-height:1.35;
}
input[type="radio"], input[type="checkbox"]{
  accent-color:var(--c-accent);
}

/* Ayudas/feedback */
.help, .hint, small{
  color:#6B6B6B; font-size:13px;
}

/* ===== Botones de navegación (ya existentes: .navegacion button) ===== */
.navegacion{
  display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin:16px 0 8px 0;
}
.navegacion button{
  appearance:none; border:none; cursor:pointer;
  border-radius:14px !important;
  padding:12px 18px !important;
  font-weight:600;
  letter-spacing:.2px;
  background:var(--c-accent) !important;
  color:#fff !important;
  box-shadow:var(--shadow);
  transition:transform .15s ease, box-shadow .15s ease, background .15s ease, opacity .15s ease;
}
.navegacion button:hover{ transform:translateY(-1px); box-shadow:var(--shadow-hover); }
.navegacion button:disabled{ opacity:.5; cursor:not-allowed; transform:none; }

/* Botón alterno (si lo usas con clase .btn-secondary en el HTML) */
.btn-secondary{
  background:var(--c-secondary) !important;
}

/* ===== Barra de progreso (IDs ya existentes en el form) ===== */
#progress-bar-container{
  background:#F2F4F7 !important;
  height:14px !important;
  width:100%;
  margin:24px 0 !important;
  border-radius:999px !important;
  overflow:hidden !important;
  box-shadow:inset 0 1px 2px rgba(0,0,0,0.04);
}
#progress-bar{
  height:100% !important;
  background:linear-gradient(90deg, var(--c-accent), #ffa45b) !important;
  color:#fff !important; 
  text-align:right !important;
  padding-right:10px;
  font-size:12px; line-height:14px;
  border-radius:999px !important;
  transition:width .3s ease !important;
}

/* ===== Chips (por si el form muestra etiquetas/resúmenes) ===== */
.chip{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 10px; border-radius:999px;
  background:var(--c-soft); color:#7A7A7A; font-size:13px; font-weight:600;
  border:1px solid #FFE2D1;
}

/* ===== Mensajes de validación/estado ===== */
.alert, .error, .success{
  border-radius:14px; padding:12px 14px; margin:12px 0; font-weight:600;
}
.alert{ background:#FFF9E6; border:1px solid #FFE7A8; color:#6E5900; }
.error{ background:#FFF1F1; border:1px solid #FFC9C9; color:#7A1E1E; }
.success{ background:#EEFFF4; border:1px solid #BFECD2; color:#1F6C46; }

/* ===== Tablas (si aparecen) ===== */
table{
  width:100%; border-collapse:separate; border-spacing:0; background:#fff;
  border:1px solid #eee; border-radius:14px; overflow:hidden; box-shadow:var(--shadow);
}
th, td{ text-align:left; padding:12px 14px; }
thead th{
  background:#F7FAFC; color:#253238; font-weight:700; border-bottom:1px solid #ECECEC;
}
tbody tr + tr td{ border-top:1px solid #F1F1F1; }

/* ===== Footer de firma ===== */
.vlr-footer{
  display:flex; justify-content:center; align-items:center; gap:10px;
  color:#8A8A8A; font-size:13px; margin:28px 0 40px 0;
}
.vlr-dot{ width:6px; height:6px; border-radius:999px; background:#D9D9D9; display:inline-block; }
.vlr-footer a{ color:#8A8A8A; text-decoration:none; }
.vlr-footer a:hover{ color:#555; text-decoration:underline; }

      
      
      
/* === Booster de especificidad para garantizar el look del dashboard === */
/* Aplica SOLO dentro del body.theme-valirica para no afectar otras pantallas */

.theme-valirica{
  /* tipografía y color base */
  color: var(--c-body) !important;
  font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif !important;
}

.theme-valirica :is(h1,h2,h3,h4,h5,h6, p, label, small){
  font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif !important;
}

/* Contenedor y ritmo vertical */
.theme-valirica main,
.theme-valirica .container,
.theme-valirica .form-wrapper{
  max-width:1100px !important;
  margin:32px auto !important;
  padding:0 16px !important;
}

/* Cards / módulos */
.theme-valirica .modulo{
  background:#fff !important;
  border-radius:20px !important;
  box-shadow:0 6px 20px rgba(0,0,0,.06) !important;
  padding:24px !important;
  margin:0 0 24px 0 !important;
  transition:transform .2s ease, box-shadow .2s ease !important;
}
.theme-valirica .modulo:hover{ transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.08) !important; }

/* Títulos dentro de módulos */
.theme-valirica .modulo h2{
  font-size:clamp(18px,2.6vw,22px) !important;
  line-height:1.2 !important;
  color:#012133 !important;
  border-left:4px solid var(--c-accent);
  padding-left:10px;
  margin:0 0 12px 0 !important;
}
.theme-valirica .modulo h3{
  font-size:clamp(16px,2.2vw,18px) !important;
  color:#184656 !important;
}

/* Inputs y selects (44px) */
.theme-valirica input[type="text"],
.theme-valirica input[type="email"],
.theme-valirica input[type="number"],
.theme-valirica input[type="date"],
.theme-valirica textarea,
.theme-valirica select{
  width:100% !important;
  min-height:44px !important;
  background:#fff !important;
  border:1px solid #E7E7E7 !important;
  border-radius:14px !important;
  padding:12px 14px !important;
  font-size:16px !important;
  outline:none !important;
  box-shadow:none !important;
  transition:border-color .15s ease, box-shadow .15s ease !important;
  appearance:none !important;
}
.theme-valirica :is(input, textarea, select):focus{
  border-color:var(--c-secondary) !important;
  box-shadow:0 0 0 3px rgba(24,70,86,.12) !important;
}

/* Radios/checkbox coherentes */
.theme-valirica input[type="radio"],
.theme-valirica input[type="checkbox"]{
  accent-color:var(--c-accent) !important;
}

/* Botonera de navegación */
.theme-valirica .navegacion{
  display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin:16px 0 8px 0 !important;
}
.theme-valirica .navegacion button{
  appearance:none !important; border:none !important; cursor:pointer !important;
  border-radius:14px !important;
  padding:12px 18px !important;
  font-weight:600 !important;
  font-size:16px !important;
  letter-spacing:.2px !important;
  background:var(--c-accent) !important;
  color:#fff !important;
  box-shadow:0 6px 20px rgba(0,0,0,.06) !important;
  transition:transform .15s ease, box-shadow .15s ease, background .15s ease, opacity .15s ease !important;
}
.theme-valirica .navegacion button:hover{ transform:translateY(-1px) !important; box-shadow:0 8px 20px rgba(0,0,0,.08) !important; }
.theme-valirica .navegacion button:disabled{ opacity:.5 !important; cursor:not-allowed !important; transform:none !important; }
.theme-valirica .navegacion .btn-secondary{ background:var(--c-secondary) !important; }

/* Barra de progreso */
.theme-valirica #progress-bar-container{
  background:#F2F4F7 !important; height:14px !important; width:100% !important;
  margin:24px 0 !important; border-radius:999px !important; overflow:hidden !important;
  box-shadow:inset 0 1px 2px rgba(0,0,0,.04) !important;
}
.theme-valirica #progress-bar{
  height:100% !important;
  background:linear-gradient(90deg, var(--c-accent), #ffa45b) !important;
  color:#fff !important; text-align:right !important; padding-right:10px !important;
  font-size:12px !important; font-weight:700 !important; letter-spacing:.2px !important; line-height:14px !important;
  border-radius:999px !important; transition:width .3s ease !important;
}

/* Chips */
.theme-valirica .chip{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 10px; border-radius:999px;
  background:var(--c-soft); color:#7A7A7A; font-size:13px; font-weight:600;
  border:1px solid #FFE2D1;
}

/* Ayudas y errores */
.theme-valirica .help, .theme-valirica .hint, .theme-valirica small{ color:#6B6B6B !important; font-size:13px !important; }
.theme-valirica .error{ background:#FFF1F1 !important; border:1px solid #FFC9C9 !important; color:#7A1E1E !important; border-radius:14px !important; padding:12px 14px !important; }
.theme-valirica .success{ background:#EEFFF4 !important; border:1px solid #BFECD2 !important; color:#1F6C46 !important; border-radius:14px !important; padding:12px 14px !important; }
      
      
.conflicto-bloque{
  display:flex;
  flex-direction:column;
  gap:14px;
}

.conflicto-row{
  display:grid;
  grid-template-columns:80px 1fr;
  align-items:center;
  gap:20px;
}

.conflicto-selector{
  width:72px;
  min-height:44px;
  line-height:44px;
  text-align:center;
  font-size:18px;
  font-weight:600;
  border-radius:6px;
  border:2px solid #888;
  appearance:auto !important;
}

.conflicto-texto{
  color:#004758;
  font-size:25px;
  line-height:1.35;
}

      
    </style>
  </head>
  <body class="theme-valirica">
  <div class="valirica-formulario">
      <header>
        <h1>Formulario de Evaluación</h1>
        <p>Responde cada afirmación según tu experiencia.</p>
      </header>

      <?php $equipo_id = $_GET['equipo_id'] ?? null; ?>

<div class="modulo activo" id="bienvenida-valirica" data-skip-validation="true">


  <h2 style="margin-bottom:12px;">
    Aloja! 🤙
  </h2>

  <p style="font-size:20px; line-height:1.45; margin-bottom:14px;">
    Este no es un test ni una evaluación.
    Es un espacio breve para entender cómo vives tu trabajo hoy.
  </p>

  <p style="font-size:20px; line-height:1.45; margin-bottom:14px;">
    No hay respuestas buenas o malas.
    Lo único que realmente importa es que respondas con honestidad,
    desde tu experiencia real.
  </p>

  <p style="font-size:20px; line-height:1.45;">
    Cuanto más sincera sea tu respuesta,
    más fácil será construir conversaciones claras
    y diseñar una cultura de equipo que sí funcione en la práctica.
  </p>

  <div class="chip" style="margin-top:18px;">
    ✨ Empieza con calma. Esto es para construir, no para juzgar.
  </div>

</div>


      <div id="progress-bar-container">
        <div id="progress-bar"></div>
      </div>

      <form
        action="procesar_resultados.php?equipo_id=<?= $equipo_id ?>"
        id="formularioValirica"
        method="POST"
      >
        <input
          name="equipo_id"
          type="hidden"
          value="<?= htmlspecialchars($equipo_id) ?>"
        />

        <!-- Cargar SortableJS desde CDN (solo una vez necesario en todo el formulario) -->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

        <!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->
        <!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->
        <!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->

  

        <!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI -->
        <!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI -->

   

        <!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE -->
        <!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE --><!-- PREGUNTAS HOFSTEDE -->

        <div class="modulo" id="modulo_113">
          <label>"Prefiero que las decisiones las tomen los líderes sin consultar al equipo."</label>
          <select name="respuesta_113"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_114">
          <label>"Me siento cómodo siguiendo órdenes sin cuestionarlas."</label>
          <select name="respuesta_114"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_115">
          <label>"Es importante para mí conocer mi lugar dentro de la jerarquía."</label>
          <select name="respuesta_115"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_116">
          <label>"Los equipos funcionan mejor cuando hay un líder claro y fuerte."</label>
          <select name="respuesta_116"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_117">
          <label>"Me incomoda cuando los roles no están bien definidos."</label>
          <select name="respuesta_117"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_118">
          <label>"Creo que las decisiones deben venir de los niveles más altos."</label>
          <select name="respuesta_118"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_119">
          <label>"Me cuesta expresarme libremente si hay personas de mayor rango presentes."</label>
          <select name="respuesta_119"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_120">
          <label>"Las empresas necesitan estructuras jerárquicas para funcionar bien."</label>
          <select name="respuesta_120"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_121">
          <label>"Me motiva más alcanzar metas personales que grupales."</label>
          <select name="respuesta_121"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_122">
          <label>"Prefiero asumir responsabilidades de manera individual."</label>
          <select name="respuesta_122"="">
            <option disabled="" selected="" value="">Selecciona una opción</option>
            <option value="">ELIGE UNA OPCIÓN</option>
            <option value="1">Nada de acuerdo</option>
            <option value="2">En desacuerdo</option>
            <option value="3">Neutral</option>
            <option value="4">De acuerdo</option>
            <option value="5">Totalmente de acuerdo</option>
          </select>
        </div>

        <div class="modulo" id="modulo_123">
  <label>"Me motiva saber que mi trabajo tiene un impacto en el equipo."</label>
  <select name="respuesta_123"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_124">
  <label>"Prefiero colaborar en grupo antes que trabajar solo."</label>
  <select name="respuesta_124"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_125">
  <label>"Me importa sentirme parte de una comunidad laboral."</label>
  <select name="respuesta_125"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_126">
  <label>"Creo que las decisiones deben tomarse pensando en el bienestar del equipo."</label>
  <select name="respuesta_126"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_127">
  <label>"Me cuesta tomar decisiones que beneficien solo a unos pocos."</label>
  <select name="respuesta_127"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_128">
  <label>"Me siento incómodo cuando se celebran más los logros individuales que los colectivos."</label>
  <select name="respuesta_128"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_129">
  <label>"Valoro más el rendimiento que el bienestar emocional."</label>
  <select name="respuesta_129"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_130">
  <label>"Prefiero enfocarme en metas ambiciosas, aunque generen estrés."</label>
  <select name="respuesta_130"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_131">
  <label>"Creo que el éxito laboral se mide por los resultados, no por las relaciones."</label>
  <select name="respuesta_131"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_132">
  <label>"La competencia interna puede ser positiva para el crecimiento."</label>
  <select name="respuesta_132"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_133">
  <label>"Es más importante lograr metas que evitar conflictos."</label>
  <select name="respuesta_133"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_134">
  <label>"Me enfoco más en ganar que en disfrutar el proceso."</label>
  <select name="respuesta_134"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_135">
  <label>"El bienestar emocional del equipo debe estar por encima del logro de objetivos."</label>
  <select name="respuesta_135"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_136">
  <label>"Una buena cultura laboral se basa más en cuidar a las personas que en exigir resultados."</label>
  <select name="respuesta_136"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_137">
  <label>"Me incomoda cuando no hay un plan claro de trabajo."</label>
  <select name="respuesta_137"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_138">
  <label>"Me gusta anticiparme a posibles riesgos."</label>
  <select name="respuesta_138"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_139">
  <label>"Prefiero tener reglas claras, aunque limiten la creatividad."</label>
  <select name="respuesta_139"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_140">
  <label>"Me cuesta adaptarme cuando las cosas cambian constantemente."</label>
  <select name="respuesta_140"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_141">
  <label>"Tengo la capacidad de avanzar incluso si hay muchas cosas inciertas."</label>
  <select name="respuesta_141"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_142">
  <label>"Me motivan los desafíos que implican improvisar."</label>
  <select name="respuesta_142"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_143">
  <label>"Me siento cómodo trabajando sin una estructura definida."</label>
  <select name="respuesta_143"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_144">
  <label>"Prefiero que el entorno laboral sea flexible, aunque implique ambigüedad."</label>
  <select name="respuesta_144"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_145">
  <label>"Prefiero proyectos con beneficios inmediatos."</label>
  <select name="respuesta_145"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_146">
  <label>"Me cuesta visualizar metas a largo plazo."</label>
  <select name="respuesta_146"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_147">
  <label>"Tiendo a actuar con una visión estratégica del futuro."</label>
  <select name="respuesta_147"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_148">
  <label>"Valoro más la planificación a largo plazo que los resultados inmediatos."</label>
  <select name="respuesta_148"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_149">
  <label>"Pienso más en el impacto futuro que en el reconocimiento actual."</label>
  <select name="respuesta_149"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_150">
  <label>"Me frustra cuando un proyecto no tiene resultados visibles pronto."</label>
  <select name="respuesta_150"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_151">
  <label>"Me esfuerzo por construir logros sostenibles, aunque tarden más."</label>
  <select name="respuesta_151"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_152">
  <label>"Me motiva ver avances rápidos, aunque sean pequeños."</label>
  <select name="respuesta_152"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_153">
  <label>"Me gusta tener libertad para hacer las cosas a mi manera."</label>
  <select name="respuesta_153"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_154">
  <label>"Prefiero que las normas se adapten a cada caso particular."</label>
  <select name="respuesta_154"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_155">
  <label>"Me siento incómodo si hay demasiadas reglas."</label>
  <select name="respuesta_155"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_156">
  <label>"Necesito estructura y límites claros para dar lo mejor de mí."</label>
  <select name="respuesta_156"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_157">
  <label>"Me frustra cuando hay poca claridad en las normas."</label>
  <select name="respuesta_157"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_158">
  <label>"Me motiva poder tomar decisiones sin seguir un protocolo fijo."</label>
  <select name="respuesta_158"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_159">
  <label>"Soy más creativo cuando puedo romper reglas establecidas."</label>
  <select name="respuesta_159"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_160">
  <label>"Rindo mejor cuando tengo autonomía para decidir cómo actuar."</label>
  <select name="respuesta_160"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO -->
<!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO --><!-- PREGUNTAS CONFLICTO -->

<div class="modulo" id="conflicto_bloque_1">
  <label style="font-size: 30px; display:block; margin-bottom:20px;">
    Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.
  </label>
  <fieldset class="conflicto-bloque" data-bloque="1">
    <legend style="display:none;">Responde las 5 afirmaciones:</legend>
    <div class="conflicto-bloque" data-bloque="1">

  <div class="conflicto-row">
    <select name="respuesta_161" required class="conflicto-selector" data-bloque="1">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Cuando hay un conflicto, intento que se haga lo que considero correcto."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_164" required class="conflicto-selector" data-bloque="1">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Busco soluciones donde todos ganen cuando hay un desacuerdo."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_167" required class="conflicto-selector" data-bloque="1">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "A veces prefiero evitar conflictos para no generar tensión."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_170" required class="conflicto-selector" data-bloque="1">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Prefiero ceder en una discusión si eso mantiene la armonía."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_173" required class="conflicto-selector" data-bloque="1">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "En los conflictos, trato de negociar un punto medio."
    </span>
  </div>

</div>

  </fieldset>
</div>

<div class="modulo" id="conflicto_bloque_2">
  <label style="font-size: 30px; display:block; margin-bottom:20px;">
    Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.
  </label>
  <fieldset class="conflicto-bloque" data-bloque="2">
    <legend style="display:none;">Responde las 5 afirmaciones:</legend>
    <div class="conflicto-bloque" data-bloque="2">

  <div class="conflicto-row">
    <select name="respuesta_162" required class="conflicto-selector" data-bloque="2">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Me esfuerzo por defender mi posición, aunque otros no estén de acuerdo."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_165" required class="conflicto-selector" data-bloque="2">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Me gusta escuchar con atención antes de proponer una solución."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_168" required class="conflicto-selector" data-bloque="2">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Si el problema no es urgente, suelo dejarlo pasar."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_171" required class="conflicto-selector" data-bloque="2">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Si alguien está molesto, hago lo posible por calmarlo, aunque no sea mi culpa."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_174" required class="conflicto-selector" data-bloque="2">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Estoy dispuesto a ceder parcialmente para llegar a un acuerdo."
    </span>
  </div>

</div>

  </fieldset>
</div>

<div class="modulo" id="conflicto_bloque_3">
  <label style="font-size: 30px; display:block; margin-bottom:20px;">
    Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.
  </label>
  <fieldset class="conflicto-bloque" data-bloque="3">
    <legend style="display:none;">Responde las 5 afirmaciones:</legend>
    <div class="conflicto-bloque" data-bloque="3">

  <div class="conflicto-row">
    <select name="respuesta_163" required class="conflicto-selector" data-bloque="3">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Suelo actuar con firmeza para proteger mis ideas o intereses."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_166" required class="conflicto-selector" data-bloque="3">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Me esfuerzo por entender el punto de vista del otro, incluso si no estoy de acuerdo."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_169" required class="conflicto-selector" data-bloque="3">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Evito discutir aunque sepa que tengo razón."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_172" required class="conflicto-selector" data-bloque="3">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      "Pongo las necesidades de otros por encima de las mías para evitar confrontaciones."
    </span>
  </div>

  <div class="conflicto-row">
    <select name="respuesta_175" required class="conflicto-selector" data-bloque="3">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span class="conflicto-texto">
      Prefiero resolver los desacuerdos buscando una solución equilibrada, aunque implique ajustar mis expectativas.
    </span>
  </div>

</div>

  </fieldset>
</div>

<script>
  document.querySelectorAll('.conflicto-bloque').forEach(bloque => {
    const selects = bloque.querySelectorAll('select');
    selects.forEach(sel => {
      sel.addEventListener('change', () => {
        const valores = Array.from(selects).map(s => s.value).filter(v => v !== "");
        const duplicados = valores.filter((v, i, a) => a.indexOf(v) !== i);
        if (duplicados.length > 0) {
          alert("No puedes repetir el mismo valor en este bloque. Cada afirmación debe tener un número único entre 1 y 5.");
          sel.value = "";
        }
      });
    });
  });
</script>

<!-- PREGUNTAS ORIENTACION SENSORIAL --><!-- PREGUNTAS ORIENTACION SENSORIAL --><!-- PREGUNTAS ORIENTACION SENSORIAL -->
<!-- PREGUNTAS ORIENTACION SENSORIAL --><!-- PREGUNTAS ORIENTACION SENSORIAL --><!-- PREGUNTAS ORIENTACION SENSORIAL -->

<div class="modulo" id="modulo_176">
  <label>"Me ayuda mucho ver esquemas, gráficos o imágenes para entender un tema."</label>
  <label><input name="respuesta_176"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_176" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_177">
  <label>"Prefiero leer instrucciones antes de hacer algo nuevo."</label>
  <label><input name="respuesta_177"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_177" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_178">
  <label>"Tomo apuntes detallados cuando asisto a una capacitación."</label>
  <label><input name="respuesta_178"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_178" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_179">
  <label>"Memorizo mejor cuando visualizo mentalmente la información."</label>
  <label><input name="respuesta_179"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_179" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_180">
  <label>"Cuando me explican algo, necesito ver cómo se escribe o se representa gráficamente."</label>
  <label><input name="respuesta_180"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_180" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_181">
  <label>"Recuerdo con facilidad lo que escucho, incluso si no lo anoto."</label>
  <label><input name="respuesta_181"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_181" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_182">
  <label>"Aprendo mejor cuando escucho a alguien explicar un tema."</label>
  <label><input name="respuesta_182"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_182" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_183">
  <label>"Prefiero participar en discusiones y sesiones habladas para entender bien."</label>
  <label><input name="respuesta_183"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_183" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_184">
  <label>"Me ayuda repetir en voz alta lo que quiero memorizar."</label>
  <label><input name="respuesta_184"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_184" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_185">
  <label>"Prefiero que me expliquen oralmente los pasos antes de leerlos."</label>
  <label><input name="respuesta_185"="" type="radio" value="Sí"> Correcto</input></label><br>
  <label><input name="respuesta_185" type="radio" value="No"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_186">
  <label>"Necesito hacer las cosas por mí mismo para entenderlas bien."</label>
  <label><input name="respuesta_186"="" type="radio" value="1"> Correcto</input></label><br>
  <label><input name="respuesta_186" type="radio" value="0"> Incorrecto</input></label>
  </br>
</div>

<div class="modulo" id="modulo_187">
  <label>"Me resulta difícil aprender si no puedo aplicar lo que estoy viendo."</label>
  <label><input name="respuesta_187"="" type="radio" value="1"> Correcto</input></label><br/>
  <label><input name="respuesta_187" type="radio" value="0"> Incorrecto</input></label>
</div>

<div class="modulo" id="modulo_188">
  <label>"Aprendo mejor cuando practico con ejemplos reales o simulaciones."</label>
  <label><input name="respuesta_188"="" type="radio" value="1"> Correcto</input></label><br/>
  <label><input name="respuesta_188" type="radio" value="1"> Incorrecto</input></label>
</div>

<div class="modulo" id="modulo_189">
  <label>"Me impaciento si hay demasiada teoría y poca acción."</label>
  <label><input name="respuesta_189"="" type="radio" value="1"> Correcto</input></label><br/>
  <label><input name="respuesta_189" type="radio" value="0"> Incorrecto</input></label>
</div>

<div class="modulo" id="modulo_190">
  <label>"Prefiero descubrir el funcionamiento de algo probándolo por mi cuenta."</label>
  <label><input name="respuesta_190"="" type="radio" value="1"> Correcto</input></label><br/>
  <label><input name="respuesta_190" type="radio" value="0"> Incorrecto</input></label>
</div>

<!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW -->
<!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW --><!-- PREGUNTAS MASLOW -->

<div class="modulo" id="maslow_bloque_1">
  <label style="font-size: 30px; display:block; margin-bottom:20px;">
    Asigna un valor del 1 al 5 según el nivel de necesidad que hoy tiene más urgencia en tu vida laboral y personal. <strong>1</strong> representa la necesidad más urgente para ti en este momento, y <strong>5</strong> la menos prioritaria.
  </label>
  <fieldset class="maslow-bloque" data-bloque="1">
    <legend style="display:none;">Responde las 5 afirmaciones:</legend>
    <div class="maslow-list">

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_191" required class="maslow-selector" data-bloque="1"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Dormir y alimentarme de forma que me sienta equilibrado.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_193" required class="maslow-selector" data-bloque="1"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Sentirme emocional y económicamente estable para planear con tranquilidad.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_195" required class="maslow-selector" data-bloque="1"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Sentirme parte de mi equipo, incluso más allá de lo laboral.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_197" required class="maslow-selector" data-bloque="1"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Recibir reconocimiento por lo que hago.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_199" required class="maslow-selector" data-bloque="1"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Aprender, crecer y retarme para desarrollar mi máximo potencial.
    </span>
  </div>

</div>

  </fieldset>
</div>

<div class="modulo" id="maslow_bloque_2">
  <label style="font-size: 30px; display:block; margin-bottom:20px;">
    Asigna un valor del 1 al 5 según el nivel de necesidad que hoy tiene más urgencia en tu vida laboral y personal. <strong>1</strong> representa la necesidad más urgente para ti en este momento, y <strong>5</strong> la menos prioritaria.
  </label>
  <fieldset class="maslow-bloque" data-bloque="2">
    <legend style="display:none;">Responde las 5 afirmaciones:</legend>
    <div class="maslow-list">

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_192" required class="maslow-selector" data-bloque="2"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Tener energía física suficiente para sobrellevar mi rutina laboral.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_194" required class="maslow-selector" data-bloque="2"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Sentirme protegido frente a cambios imprevistos en mi entorno laboral.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_196" required class="maslow-selector" data-bloque="2"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Tener interacciones sociales que me hagan sentir conectado.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_198" required class="maslow-selector" data-bloque="2"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Sentir que otros valoran mi trabajo.
    </span>
  </div>

  <div class="maslow-item" style="display:flex; align-items:flex-start; gap:20px; padding:25px 0;">
    <select name="respuesta_200" required class="maslow-selector" data-bloque="2"
      style="padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;">
      <option value="">--</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
      <option value="5">5</option>
    </select>
    <span style="color:#004758; font-size:25px;">
      Desarrollar mi máximo potencial personal y profesional.
    </span>
  </div>

</div>

  </fieldset>
</div>

<script>
  document.querySelectorAll('.maslow-bloque').forEach(bloque => {
    const selects = bloque.querySelectorAll('select');
    selects.forEach(sel => {
      sel.addEventListener('change', () => {
        const valores = Array.from(selects).map(s => s.value).filter(v => v !== "");
        const duplicados = valores.filter((v, i, a) => a.indexOf(v) !== i);
        if (duplicados.length > 0) {
          alert("No puedes repetir el mismo valor en este bloque. Cada afirmación debe tener un número único entre 1 y 5.");
          sel.value = "";
        }
      });
    });
  });
</script>

<!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK -->
<!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK --><!-- PREGUNTAS PINK -->

<div class="modulo" id="modulo_201">
  <label>Hoy siento que lo que hago en mi trabajo tiene un sentido personal, incluso cuando no es fácil.</label>
  <select name="respuesta_201"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_202">
  <label>Hoy me resulta fácil ver cómo mi trabajo impacta más allá de mi tarea puntual. </label>
  <select name="respuesta_202"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_203">
  <label>Actualmente tengo libertad para decidir cómo hago mi trabajo y eso me motiva.</label>
  <select name="respuesta_203"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_204">
  <label>Hoy me siento con autonomía para tomar decisiones sobre la forma en que realizo mi trabajo.</label>
  <select name="respuesta_204"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_205">
  <label>Siento que estoy desarrollando mis habilidades y creciendo profesionalmente.</label>
  <select name="respuesta_205"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_206">
  <label>Últimamente, las tareas que realizo me ayudan a sentirme más capaz.</label>
  <select name="respuesta_206"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_207">
  <label>Hoy en día estoy cuidando activamente mi salud física y mental.</label>
  <select name="respuesta_207"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_208">
  <label>En este momento, estoy priorizando mi bienestar físico y emocional dentro de mi rutina.</label>
  <select name="respuesta_208"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_209">
  <label>Hoy en día, mis relaciones laborales me energizan y me hacen sentir conectado.</label>
  <select name="respuesta_209"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="modulo" id="modulo_210">
  <label>Actualmente me vinculo emocionalmente de forma saludable con las personas con las que trabajo.</label>
  <select name="respuesta_210"="">
    <option disabled="" selected="" value="">Selecciona una opción</option>
    <option value="">ELIGE UNA OPCIÓN</option>
    <option value="1">Nada de acuerdo</option>
    <option value="2">En desacuerdo</option>
    <option value="3">Neutral</option>
    <option value="4">De acuerdo</option>
    <option value="5">Totalmente de acuerdo</option>
  </select>
</div>

<div class="navegacion">
  <button id="anterior" type="button">Anterior</button>
  <button id="siguiente" type="button">Siguiente</button>
  <button id="enviar" type="submit" style="display:none;">Enviar</button>
</div>
</form>
</div>

<script>
  const modulos = document.querySelectorAll('.modulo');
  const progreso = document.getElementById('progress-bar');
  const btnSiguiente = document.getElementById('siguiente');
  const btnAnterior = document.getElementById('anterior');
  const btnEnviar = document.getElementById('enviar');
  let actual = 0;

  function mostrarModulo(i) {
    modulos.forEach((m, idx) => {
      m.classList.toggle('activo', idx === i);
    });
    progreso.style.width = ((i + 1) / modulos.length * 100) + '%';
    btnAnterior.style.display = i === 0 ? 'none' : 'inline-block';
    btnSiguiente.style.display = i === modulos.length - 1 ? 'none' : 'inline-block';
    btnEnviar.style.display = i === modulos.length - 1 ? 'inline-block' : 'none';
  }

  btnSiguiente.addEventListener('click', () => {
    const actualModulo = modulos[actual];
    
    // Si el módulo es informativo (bienvenida), avanzar sin validar
if (actualModulo.dataset.skipValidation === "true") {
  actual++;
  mostrarModulo(actual);
  return;
}

    
    const inputs = actualModulo.querySelectorAll('input, select');
    let valid = false;

    inputs.forEach(input => {
      if ((input.type === 'radio' && input.checked) || (input.tagName === 'SELECT' && input.value !== '')) {
        valid = true;
      }
    });

    if (!valid) {
      alert("Por favor responde esta pregunta antes de continuar.");
      return;
    }

    if (actual < modulos.length - 1) {
      actual++;
      mostrarModulo(actual);
    }
  });

  btnAnterior.addEventListener('click', () => {
    if (actual > 0) {
      actual--;
      mostrarModulo(actual);
    }
  });

  mostrarModulo(actual);
</script>

<script>
  document.querySelectorAll('.modulo').forEach(modulo => {
    const inputs = modulo.querySelectorAll('input[type="number"]');
    inputs.forEach(input => {
      input.addEventListener('change', () => {
        const valores = Array.from(inputs).map(i => parseInt(i.value));
        const repetidos = valores.filter((v, i, a) => v && a.indexOf(v) !== i);
        if (repetidos.length > 0) {
          alert("Los valores del 1 al 4 deben ser únicos en este grupo.");
          input.value = "";
        }
      });
    });
  });
</script>

<script>
 

  Sortable.create(document.getElementById('sortable-conflicto-1'), {
    animation: 150,
    onEnd: () => {
      const valores = [5.0, 4.0, 3.0, 2.0, 1.0];
      document.querySelectorAll('#sortable-conflicto-1 li').forEach((el, index) => {
        const id = el.getAttribute('data-id');
        document.getElementById('input_' + id).value = valores[index];
      });
    }
  });

  Sortable.create(document.getElementById('sortable-conflicto-2'), {
    animation: 150,
    onEnd: () => {
      const valores = [5.0, 4.0, 3.0, 2.0, 1.0];
      document.querySelectorAll('#sortable-conflicto-2 li').forEach((el, index) => {
        const id = el.getAttribute('data-id');
        document.getElementById('input_' + id).value = valores[index];
      });
    }
  });

  Sortable.create(document.getElementById('sortable-conflicto-3'), {
    animation: 150,
    onEnd: () => {
      const valores = [5.0, 4.0, 3.0, 2.0, 1.0];
      document.querySelectorAll('#sortable-conflicto-3 li').forEach((el, index) => {
        const id = el.getAttribute('data-id');
        document.getElementById('input_' + id).value = valores[index];
      });
    }
  });

  Sortable.create(document.getElementById('sortable-maslow-1'), {
    animation: 150,
    onEnd: () => {
      const valores = [5.0, 4.0, 3.0, 2.0, 1.0];
      document.querySelectorAll('#sortable-maslow-1 li').forEach((el, index) => {
        const id = el.getAttribute('data-id');
        document.getElementById('input_' + id).value = valores[index];
      });
    }
  });

  Sortable.create(document.getElementById('sortable-maslow-2'), {
    animation: 150,
    onEnd: () => {
      const valores = [5.0, 4.0, 3.0, 2.0, 1.0];
      document.querySelectorAll('#sortable-maslow-2 li').forEach((el, index) => {
        const id = el.getAttribute('data-id');
        document.getElementById('input_' + id).value = valores[index];
      });
    }
  });
</script>
</body>
</html>
