<!DOCTYPE html>

<html lang="es">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Formulario Valírica</title>
<link href="https://fonts.googleapis.com/css2?family=Glegoo:wght@400;700&amp;display=swap" rel="stylesheet"/>
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
    label, select {
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
  
    .modulo { display: none; }
    .modulo.activo { display: block; }
    .barra-progreso { width: 100%; background: #eee; border-radius: 5px; overflow: hidden; margin-bottom: 20px; }
    .progreso { height: 10px; background: #00c6a7; width: 0%; transition: width 0.3s ease-in-out; }
    button { margin-top: 15px; }
  </style>
</head>
<body>
<div class="valirica-formulario">
<header>
<h1>Formulario de Evaluación</h1>
<p>Responde cada afirmación según tu experiencia.</p>
</header>
<?php
    $equipo_id = $_GET['equipo_id'] ?? null;
    ?>
<div id="progress-bar-container"><div id="progress-bar"></div></div>

<form action="procesar_resultados.php?equipo_id=<?= $equipo_id ?>" id="formularioValirica" method="POST">
<input name="equipo_id" type="hidden" value="<?= htmlspecialchars($equipo_id) ?>" />
<!-- Cargar SortableJS desde CDN (solo una vez necesario en todo el formulario) -->




<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>



<!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->
<!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->
<!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC --><!-- PREGUNTAS DISC -->




<div class="modulo" id="disc_bloque_1">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="1">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_1' required class='disc-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Tomo decisiones difíciles con rapidez cuando la situación lo requiere.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_9' required class='disc-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me gusta animar a otros cuando presento nuevas ideas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_17' required class='disc-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Mantengo la calma incluso bajo mucha presión.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta' required class='disc-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me apego a las reglas, incluso cuando nadie supervisa.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_2">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="2">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_2' required class='disc-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me siento capaz de liderar incluso en momentos de caos.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_10' required class='disc-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me esfuerzo por mantener un ambiente optimista en el equipo.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_18' required class='disc-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Prefiero ambientes laborales donde todo esté planificado.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_26' required class='disc-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Reviso mi trabajo cuidadosamente para asegurar su calidad.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_3">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="3">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_3' required class='disc-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me motiva enfrentar grandes retos y superarlos.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_11' required class='disc-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me gusta persuadir a otros para que se sumen a mis propuestas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_19' required class='disc-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Evito discusiones para mantener la armonía del grupo.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_27' required class='disc-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me siento incómodo cuando los procesos no están bien definidos.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_4">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="4">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_4' required class='disc-selector' data-bloque='4' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me gusta que las cosas avancen con rapidez y eficiencia.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_12' required class='disc-selector' data-bloque='4' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Suelo generar confianza rápidamente en quienes me rodean.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_20' required class='disc-selector' data-bloque='4' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me adapto fácilmente a los cambios cuando hay estabilidad.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_28' required class='disc-selector' data-bloque='4' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me gusta seguir instrucciones claras paso a paso.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_5">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="5">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_5' required class='disc-selector' data-bloque='5' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me siento cómodo asumiendo el control en situaciones críticas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_13' required class='disc-selector' data-bloque='5' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me llena de energía interactuar con otras personas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_21' required class='disc-selector' data-bloque='5' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me incomoda cambiar de planes constantemente.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_29' required class='disc-selector' data-bloque='5' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Prefiero tener claridad total antes de comenzar cualquier tarea.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_6">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="6">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_6' required class='disc-selector' data-bloque='6' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me gusta resolver conflictos con firmeza y enfoque.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_14' required class='disc-selector' data-bloque='6' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Disfruto conversar y compartir mis ideas abiertamente.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_22' required class='disc-selector' data-bloque='6' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Prefiero seguir rutinas y métodos ya establecidos.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_30' required class='disc-selector' data-bloque='6' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me esfuerzo por mantener altos estándares en todo lo que hago.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_7">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="7">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_7' required class='disc-selector' data-bloque='7' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Soy directo al comunicar mis ideas, con claridad y seguridad.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_15' required class='disc-selector' data-bloque='7' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Encuentro fácilmente puntos en común con personas nuevas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_23' required class='disc-selector' data-bloque='7' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me esfuerzo por mantener relaciones estables y duraderas.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_31' required class='disc-selector' data-bloque='7' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me resulta importante actuar con precisión y lógica.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="disc_bloque_8">
<label style="font-size: 30px; display:block; margin-bottom:20px;">
Organiza las siguientes afirmaciones según tu afinidad, donde "1" es la más afín y "4" la menos afín.
</label>
<fieldset class="disc-bloque" data-bloque="8">
<legend style="display:none;">Responde las 4 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_8' required class='disc-selector' data-bloque='8' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Disfruto tener la responsabilidad de liderar decisiones importantes.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_16' required class='disc-selector' data-bloque='8' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me entusiasma influir positivamente en el estado de ánimo del equipo.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_24' required class='disc-selector' data-bloque='8' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me siento cómodo en entornos tranquilos y predecibles.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_32' required class='disc-selector' data-bloque='8' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Me enfoco en hacer las cosas bien, más allá de la velocidad.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<script>
document.querySelectorAll('.disc-bloque').forEach(bloque => {
  const selects = bloque.querySelectorAll('select');
  selects.forEach(sel => {
    sel.addEventListener('change', () => {
      const valores = Array.from(selects).map(s => s.value).filter(v => v !== "");
      const duplicados = valores.filter((v, i, a) => a.indexOf(v) !== i);
      if (duplicados.length > 0) {
        alert("No puedes repetir el mismo valor en este bloque. Cada afirmación debe tener un número único entre 1 y 4.");
        sel.value = "";
      }
    });
  });
});
</script>



<!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI -->
<!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI --><!-- PREGUNTAS MBTI -->






<div class="modulo" id="modulo_33">
<label>"Me siento lleno de energía después de pasar tiempo con otras personas."</label>
<select name="respuesta_33"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_34">
<label>"Disfruto ser el centro de atención en reuniones o eventos."</label>
<select name="respuesta_34"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_35">
<label>"Me es fácil iniciar conversaciones con personas que no conozco."</label>
<select name="respuesta_35"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_36">
<label>"Me gusta compartir mis ideas en voz alta mientras las desarrollo."</label>
<select name="respuesta_36"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_37">
<label>"Prefiero espacios laborales donde haya mucho movimiento y gente."</label>
<select name="respuesta_37"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_38">
<label>"Me siento cómodo hablando en público sin mucha preparación."</label>
<select name="respuesta_38"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_39">
<label>"Disfruto planear actividades grupales o sociales."</label>
<select name="respuesta_39"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_40">
<label>"Me resulta natural expresarme con entusiasmo cuando algo me emociona."</label>
<select name="respuesta_40"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_41">
<label>"Necesito tiempo a solas para recargarme después de estar con otros."</label>
<select name="respuesta_41"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_42">
<label>"Me siento más cómodo observando que participando activamente."</label>
<select name="respuesta_42"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_43">
<label>"Prefiero pensar bien antes de hablar en una conversación."</label>
<select name="respuesta_43"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_44">
<label>"Me cuesta disfrutar reuniones con muchas personas al mismo tiempo."</label>
<select name="respuesta_44"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_45">
<label>"Encuentro valor en reflexionar profundamente antes de actuar."</label>
<select name="respuesta_45"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_46">
<label>"Me resulta más fácil escribir lo que pienso que decirlo en voz alta."</label>
<select name="respuesta_46"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_47">
<label>"Disfruto pasar tiempo en silencio o en actividades solitarias."</label>
<select name="respuesta_47"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_48">
<label>"Prefiero escuchar a los demás antes de compartir mi punto de vista."</label>
<select name="respuesta_48"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_49">
<label>"Prefiero hechos comprobables antes que teorías o suposiciones."</label>
<select name="respuesta_49"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_50">
<label>"Me concentro en lo que está ocurriendo en el presente."</label>
<select name="respuesta_50"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_51">
<label>"Confío más en la experiencia que en la intuición."</label>
<select name="respuesta_51"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_52">
<label>"Me fijo en los detalles cuando observo algo nuevo."</label>
<select name="respuesta_52"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_53">
<label>"Disfruto trabajar con información concreta y específica."</label>
<select name="respuesta_53"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_54">
<label>"Me siento cómodo siguiendo instrucciones paso a paso."</label>
<select name="respuesta_54"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_55">
<label>"Me gusta saber con precisión qué se espera de mí."</label>
<select name="respuesta_55"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_56">
<label>"Tiendo a basar mis decisiones en datos reales, no en corazonadas."</label>
<select name="respuesta_56"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_57">
<label>"Me gusta explorar nuevas ideas, incluso si no tienen aplicación inmediata."</label>
<select name="respuesta_57"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_58">
<label>"Frecuentemente veo conexiones entre cosas que parecen no estar relacionadas."</label>
<select name="respuesta_58"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_59">
<label>"Prefiero imaginar posibilidades futuras que enfocarme solo en el presente."</label>
<select name="respuesta_59"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_60">
<label>"Tiendo a pensar en el panorama general más que en los detalles."</label>
<select name="respuesta_60"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_61">
<label>"Confío en mis corazonadas cuando tomo decisiones importantes."</label>
<select name="respuesta_61"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_62">
<label>"Disfruto reflexionar sobre el significado profundo de las cosas."</label>
<select name="respuesta_62"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_63">
<label>"Tengo facilidad para proponer ideas creativas y poco convencionales."</label>
<select name="respuesta_63"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_64">
<label>"Me interesa más el potencial de algo que su situación actual."</label>
<select name="respuesta_64"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_65">
<label>"Tomo decisiones basándome en lógica más que en emociones."</label>
<select name="respuesta_65"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_66">
<label>"Puedo dar retroalimentación directa sin sentirme incómodo."</label>
<select name="respuesta_66"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_67">
<label>"Valoro más la justicia que la empatía en entornos profesionales."</label>
<select name="respuesta_67"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_68">
<label>"Prefiero resolver problemas con objetividad."</label>
<select name="respuesta_68"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_69">
<label>"Me concentro en lo que funciona, no en cómo se sienten los demás."</label>
<select name="respuesta_69"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_70">
<label>"Soy capaz de separar mis emociones cuando debo tomar decisiones importantes."</label>
<select name="respuesta_70"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_71">
<label>"Me interesa más que algo sea eficiente que emocionalmente satisfactorio."</label>
<select name="respuesta_71"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_72">
<label>"Evalúo los hechos antes de considerar las emociones involucradas."</label>
<select name="respuesta_72"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_73">
<label>"Tomo decisiones considerando cómo afectarán a los demás."</label>
<select name="respuesta_73"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_74">
<label>"Me esfuerzo por mantener la armonía en mis relaciones."</label>
<select name="respuesta_74"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_75">
<label>"A menudo me guío por lo que siento más que por lo que pienso."</label>
<select name="respuesta_75"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_76">
<label>"Me resulta difícil ser directo si eso puede herir a alguien."</label>
<select name="respuesta_76"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_77">
<label>"Valoro la empatía por encima de la lógica."</label>
<select name="respuesta_77"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_78">
<label>"Prefiero trabajar con personas que se preocupan por el bienestar del grupo."</label>
<select name="respuesta_78"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_79">
<label>"Mis emociones suelen influir en mis decisiones."</label>
<select name="respuesta_79"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_80">
<label>"Busco conectar con los demás a nivel emocional."</label>
<select name="respuesta_80"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_81">
<label>"Prefiero tener un plan definido antes de empezar algo."</label>
<select name="respuesta_81"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_82">
<label>"Me siento incómodo cuando no hay estructura clara."</label>
<select name="respuesta_82"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_83">
<label>"Disfruto cumplir con los plazos y metas propuestas."</label>
<select name="respuesta_83"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_84">
<label>"Organizo mis tareas por prioridades desde el inicio."</label>
<select name="respuesta_84"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_85">
<label>"Me gusta que las cosas estén bien ordenadas."</label>
<select name="respuesta_85"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_86">
<label>"Evito improvisar si puedo planear con anticipación."</label>
<select name="respuesta_86"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_87">
<label>"Necesito claridad sobre los pasos que debo seguir."</label>
<select name="respuesta_87"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_88">
<label>"Soy de quienes terminan las cosas antes del plazo límite."</label>
<select name="respuesta_88"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_89">
<label>"Me siento cómodo adaptándome sobre la marcha."</label>
<select name="respuesta_89"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_90">
<label>"Prefiero mantener mis opciones abiertas por si surge algo mejor."</label>
<select name="respuesta_90"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_91">
<label>"Disfruto improvisar en lugar de seguir un plan rígido."</label>
<select name="respuesta_91"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_92">
<label>"Me resulta fácil cambiar de rumbo si es necesario."</label>
<select name="respuesta_92"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_93">
<label>"No me estreso si los planes cambian en el último momento."</label>
<select name="respuesta_93"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_94">
<label>"Valoro la flexibilidad más que la organización estricta."</label>
<select name="respuesta_94"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_95">
<label>"Suelo dejar espacio para nuevas ideas incluso al final de un proyecto."</label>
<select name="respuesta_95"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_96">
<label>"Me siento productivo en ambientes espontáneos y dinámicos."</label>
<select name="respuesta_96"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_97">
<label>"Confío en mis decisiones incluso cuando otros dudan."</label>
<select name="respuesta_97"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_98">
<label>"Me mantengo firme en lo que creo, aunque haya oposición."</label>
<select name="respuesta_98"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_99">
<label>"Asumo la responsabilidad de mis errores sin problema."</label>
<select name="respuesta_99"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_100">
<label>"Me siento tranquilo bajo presión."</label>
<select name="respuesta_100"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_101">
<label>"Puedo expresar desacuerdo sin perder la calma."</label>
<select name="respuesta_101"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_102">
<label>"Me enfrento a los desafíos con determinación."</label>
<select name="respuesta_102"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_103">
<label>"Me siento seguro al tomar decisiones importantes."</label>
<select name="respuesta_103"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_104">
<label>"Soy constante en mis metas sin necesidad de aprobación externa."</label>
<select name="respuesta_104"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_105">
<label>"Suelo pensar en los riesgos antes de actuar."</label>
<select name="respuesta_105"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_106">
<label>"Me preocupa cometer errores que afecten a otros."</label>
<select name="respuesta_106"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_107">
<label>"Dudo antes de tomar decisiones importantes."</label>
<select name="respuesta_107"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_108">
<label>"Prefiero tener garantías antes de asumir un compromiso."</label>
<select name="respuesta_108"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_109">
<label>"Me esfuerzo por evitar conflictos o situaciones impredecibles."</label>
<select name="respuesta_109"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_110">
<label>"Pienso mucho en las consecuencias antes de hablar."</label>
<select name="respuesta_110"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_111">
<label>"Evito tomar decisiones apresuradas."</label>
<select name="respuesta_111"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>
<div class="modulo" id="modulo_112">
<label>"Reviso cuidadosamente todo antes de dar el siguiente paso."</label>
<select name="respuesta_112"="">
<option disabled="" selected="" value="">Selecciona una opción</option>
<option value="">ELIGE UNA OPCIÓN</option>
<option value="1">Nada de acuerdo</option>
<option value="2">En desacuerdo</option>
<option value="3">Neutral</option>
<option value="4">De acuerdo</option>
<option value="5">Totalmente de acuerdo</option>
</select>
</div>




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
<label style="font-size: 30px; display:block; margin-bottom:20px;">Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.</label>
<fieldset class="conflicto-bloque" data-bloque="1">
<legend style="display:none;">Responde las 5 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_161' required class='conflicto-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Cuando hay un conflicto, intento que se haga lo que considero correcto."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_164' required class='conflicto-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Busco soluciones donde todos ganen cuando hay un desacuerdo."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_167' required class='conflicto-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"A veces prefiero evitar conflictos para no generar tensión."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_170' required class='conflicto-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Prefiero ceder en una discusión si eso mantiene la armonía."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_173' required class='conflicto-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"En los conflictos, trato de negociar un punto medio."</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="conflicto_bloque_2">
<label style="font-size: 30px; display:block; margin-bottom:20px;">Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.</label>
<fieldset class="conflicto-bloque" data-bloque="2">
<legend style="display:none;">Responde las 5 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_162' required class='conflicto-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Me esfuerzo por defender mi posición, aunque otros no estén de acuerdo."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_165' required class='conflicto-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Me gusta escuchar con atención antes de proponer una solución."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_168' required class='conflicto-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Si el problema no es urgente, suelo dejarlo pasar."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_171' required class='conflicto-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Si alguien está molesto, hago lo posible por calmarlo, aunque no sea mi culpa."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_174' required class='conflicto-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Estoy dispuesto a ceder parcialmente para llegar a un acuerdo."</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="conflicto_bloque_3">
<label style="font-size: 30px; display:block; margin-bottom:20px;">Organiza las siguientes afirmaciones asignando un valor del 1 al 5, donde <strong>1</strong> representa el estilo de resolución de conflictos o manejo de retos en equipo <strong>más afín con tu personalidad</strong> y <strong>5</strong> el menos afín.</label>
<fieldset class="conflicto-bloque" data-bloque="3">
<legend style="display:none;">Responde las 5 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_163' required class='conflicto-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Suelo actuar con firmeza para proteger mis ideas o intereses."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_166' required class='conflicto-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Me esfuerzo por entender el punto de vista del otro, incluso si no estoy de acuerdo."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_169' required class='conflicto-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Evito discutir aunque sepa que tengo razón."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_172' required class='conflicto-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>"Pongo las necesidades de otros por encima de las mías para evitar confrontaciones."</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_175' required class='conflicto-selector' data-bloque='3' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Prefiero resolver los desacuerdos buscando una solución equilibrada, aunque implique ajustar mis expectativas.</span>
</td>
</tr>
</table>
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
</br></div>
<div class="modulo" id="modulo_177">
<label>"Prefiero leer instrucciones antes de hacer algo nuevo."</label>
<label><input name="respuesta_177"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_177" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_178">
<label>"Tomo apuntes detallados cuando asisto a una capacitación."</label>
<label><input name="respuesta_178"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_178" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_179">
<label>"Memorizo mejor cuando visualizo mentalmente la información."</label>
<label><input name="respuesta_179"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_179" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_180">
<label>"Cuando me explican algo, necesito ver cómo se escribe o se representa gráficamente."</label>
<label><input name="respuesta_180"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_180" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_181">
<label>"Recuerdo con facilidad lo que escucho, incluso si no lo anoto."</label>
<label><input name="respuesta_181"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_181" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_182">
<label>"Aprendo mejor cuando escucho a alguien explicar un tema."</label>
<label><input name="respuesta_182"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_182" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_183">
<label>"Prefiero participar en discusiones y sesiones habladas para entender bien."</label>
<label><input name="respuesta_183"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_183" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_184">
<label>"Me ayuda repetir en voz alta lo que quiero memorizar."</label>
<label><input name="respuesta_184"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_184" type="radio" value="0"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_185">
<label>"Prefiero que me expliquen oralmente los pasos antes de leerlos."</label>
<label><input name="respuesta_185"="" type="radio" value="Sí"> Correcto</input></label><br>
<label><input name="respuesta_185" type="radio" value="No"> Incorrecto</input></label>
</br></div>
<div class="modulo" id="modulo_186">
<label>"Necesito hacer las cosas por mí mismo para entenderlas bien."</label>
<label><input name="respuesta_186"="" type="radio" value="1"> Correcto</input></label><br>
<label><input name="respuesta_186" type="radio" value="0"> Incorrecto</input></label>
</br></div>
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
<label style="font-size: 30px; display:block; margin-bottom:20px;">Asigna un valor del 1 al 5 según el nivel de necesidad que hoy tiene más urgencia en tu vida laboral y personal. 
<strong>1</strong> representa la necesidad más urgente para ti en este momento, y <strong>5</strong> la menos prioritaria.</label>
<fieldset class="maslow-bloque" data-bloque="1">
<legend style="display:none;">Responde las 5 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_191' required class='maslow-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Dormir y alimentarme de forma que me sienta equilibrado.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_193' required class='maslow-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Sentirme emocional y económicamente estable para planear con tranquilidad.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_195' required class='maslow-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Sentirme parte de mi equipo, incluso más allá de lo laboral.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_197' required class='maslow-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Recibir reconocimiento por lo que hago.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_199' required class='maslow-selector' data-bloque='1' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Aprender, crecer y retarme para desarrollar mi máximo potencial.</span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="modulo" id="maslow_bloque_2">
<label style="font-size: 30px; display:block; margin-bottom:20px;">Asigna un valor del 1 al 5 según el nivel de necesidad que hoy tiene más urgencia en tu vida laboral y personal. 
<strong>1</strong> representa la necesidad más urgente para ti en este momento, y <strong>5</strong> la menos prioritaria.</label>
<fieldset class="maslow-bloque" data-bloque="2">
<legend style="display:none;">Responde las 5 afirmaciones:</legend>
<table>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_192' required class='maslow-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Tener energía física suficiente para sobrellevar mi rutina laboral.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_194' required class='maslow-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Sentirme protegido frente a cambios imprevistos en mi entorno laboral.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_196' required class='maslow-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Tener interacciones sociales que me hagan sentir conectado.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_198' required class='maslow-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Sentir que otros valoran mi trabajo.</span>
</td>
</tr>
<tr style='height: auto;'>
<td style='width:100px; text-align:right; vertical-align:middle;'>
<select name='respuesta_200' required class='maslow-selector' data-bloque='2' style='padding:10px; font-size:18px; width:70px; border-radius:6px; border:2px solid #888;'>
<option value=''>--</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option>
</select></td>
<td style='padding-left:20px; padding-top:25px; padding-bottom:25px;'>
<span style='color:#004758; font-size: 25px;'>Desarrollar mi máximo potencial personal y profesional.</span>
</td>
</tr>
</table>
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
<label>Hoy me resulta fácil ver cómo mi trabajo impacta más allá de mi tarea puntual.
</label>
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
<button id="enviar" style="display:none;" type="submit">Enviar</button>
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
Sortable.create(document.getElementById('sortable-disc-1'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-1 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-2'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-2 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-3'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-3 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-4'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-4 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-5'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-5 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-6'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-6 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-7'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-7 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


Sortable.create(document.getElementById('sortable-disc-8'), {
  animation: 150,
  onEnd: () => {
    const valores = [1.0, 0.8, 0.5, 0.2];
    document.querySelectorAll('#sortable-disc-8 li').forEach((el, index) => {
      const id = el.getAttribute('data-id');
      document.getElementById('input_' + id).value = valores[index];
    });
  }
});


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
</script></body>
</html>