<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nombre, empresa, logo, proposito, cultura_empresa_tipo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$cultura_empresa_tipo = $usuario['cultura_empresa_tipo']; // ✅ extraerlo para mostrarlo más adelante

$stmt->close();

$stmt = $conn->prepare("SELECT titulo, descripcion FROM valores_marca WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$valores_resultado = $stmt->get_result();
$valores = $valores_resultado->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$proposito_definido = !empty($usuario['proposito']);
$valores_existentes = count($valores) > 0;
$cultura_definida = !empty($usuario['cultura_empresa_tipo']);
$mostrar_pregunta_cultura = $valores_existentes && !$cultura_definida;

$analisis_disponible = true; // Esto es un ejemplo, necesitas establecer esta variable basada en tu lógica de aplicación

$stmt = $conn->prepare("SELECT Analisis_DISC_Dominante, Analisis_DISC_Secundario, Analisis_Distancia_Poder, Analisis_Masculino_Femenino, Analisis_Individualismo_Colectivismo, Analisis_Tolerancia_Incertidumbre, Analisis_Corto_Largo_Plazo, Analisis_Indulgencia_Restrictivo, Analisis_Resumen FROM equipo WHERE id = ?");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$result = $stmt->get_result();



?>





<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    
    
    <!-- Hacer la web "instalable" como app -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Valírica">
<link rel="apple-touch-icon" href="/uploads/logo-192.png"> <!-- Asegúrate de tener este archivo -->

<!-- Recomendado para navegadores modernos -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#000000"> <!-- O el color de tu marca -->
    
    
    
    <style>
    
    
            @import url("https://use.typekit.net/qrv8fyz.css");
        body {
            font-family: "Gelica", sans-serif, Arial, Helvetica;
            background-color: #FFF4EE;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dashboard-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            max-width:100%;
        }   
            
        .company-logo {
        width: 200px;
        margin-right: 20px; /* Space between logo and text */
        clip-path: circle(50% at 50% 50%);
        object-fit:cover;
        }
        
        
        .form-section {
    margin-top: 30px;
    text-align: center;
    font-size:25px;
}

        
.input-field,
textarea,
input[type="text"],
input[type="email"] {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    margin-top: 30px;
    border: 1px solid var(--color-naranja);
    border-radius: 5px;
    font-size: 25px;
    background-color: var(--color-fondo);
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    font-family: 'Gelica', sans-serif;
    resize: vertical; /* para que el usuario pueda agrandarlo si quiere */
}
    
    
    



.radio-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top:50px;
  margin-bottom:50px;
  text-align: center;
}
.radio-card input[type="radio"] {
  display: block;
  margin: 0 auto 10px auto;  /* Centra horizontalmente y da espacio inferior */
  transform: scale(2);     /* Agranda el botón */
  cursor: pointer;
}
.radio-card label {
  font-size: 22px;
  color: #002135;
  cursor: pointer;
  line-height: 1.3;
  max-width: 500px;
  text-align: center;
}

.proposito-form,
.valores-form {
    margin-top: 20px;
    text-align: left;
}

.valor-descripcion,
.descripcion {
    font-size: 25px;
    color: var(--color-secundario);
    text-align: center;
}

.proposito-text {
    font-size: 25px;
    font-family: 'Gelica', sans-serif;
    font-style: italic;
    color: #002135;
}


.values-container {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid var(--color-naranja);
    border-radius: 5px;
    font-size: 25px;
    background-color: var(--color-fondo);
    font-family: 'Gelica', sans-serif;
        }

.valores-section h2,
.valor-titulo,
.mensaje-alerta {
    font-size: 18px;
    font-weight: bold;
    color: var(--color-naranja);
}

.valor-item,
.analisis-item,
.dimension-explanation {
    margin-top: 10px;
    text-align: center;
}


.btn-small {
    background-color: #fd7800;
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 25px;
    border-radius: 3px;
    margin-top: 5px;
    display: flex;
    cursor: pointer;
    font-family: 'Gelica', sans-serif;
}

.btn-small:hover {
    background-color: #003941;
}

    
    
    
    </style>
    
    
</head>
<body>
    <div class="dashboard-container">
        
        <img style="width:250px;" src="<?= htmlspecialchars($usuario['logo']) ?>" alt="Logo de la Empresa" class="company-logo">
        <h1><?= htmlspecialchars($usuario['empresa']) ?></h1>

        <?php if (!$proposito_definido): ?>
            <h2 style="color:#004758; font-size:30px;">¡Acá inicia todo!, escribe tu propósito de marca</h2>
            <form action="guardar_proposito.php" method="post" style="width:100%;">
                <textarea name="proposito" required placeholder="Ingrese aquí el propósito de su marca..." class="input-field" ></textarea>
                <button type="submit" class="btn-small">Guardar Propósito</button>
            </form>
        <?php else: ?>
            <h2 style="color:#FF7800; font-size:30px;">El Propósito de tu Marca</h2>
            <blockquote style="font-size:30px; color:#002135;" class="proposito-text"><?= htmlspecialchars($usuario['proposito']) ?></blockquote>

            <?php if (!$valores_existentes): ?>
                <h2 style="color:#FF7800; font-size:30px;">Ingresa uno a uno los Valores de tu Marca</h2>
                <form action="guardar_valores.php" method="post">
                    <div class="values-container">
                        <div>
                            <input type="text" name="titulo[]" placeholder="Título del valor" required class="input-field">
                            <textarea name="descripcion[]" placeholder="Descripción del valor" required class="input-field"></textarea>
                        </div>
                    </div>
                    <button type="button" id="addMoreValues" style="margin-bottom:30px;" class="btn-small"><strong>+ Agrega Otro Valor</strong></button>
                    <p>Si ya escribiste todos los valores de tu amrca, </br><strong>haz click</strong> en "Guardar Valores" para avanzar</p><button type="submit" class="btn-small">Guardar Valores</button>
                </form>
                
                
                
                
                
                
                
                
            <?php else: ?>
                <h2 style="color:#FF7800; font-size:30px;">Los Valores de tu Marca</h2>
                <?php foreach ($valores as $valor): ?>
                    <div class="valor-item">
                        <h2 class="valor-titulo" style="color:#004758; font-size:30px text-align:center;"><?= htmlspecialchars($valor['titulo']) ?></h2>
                        <p class="valor-descripcion" style="text-align:center;"><?= htmlspecialchars($valor['descripcion']) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($mostrar_pregunta_cultura): ?>
                    <div class="form-section">
                        <h2 style="color:#FF7800; font-size:30px;">Elige la opción que mejor describa el tipo</br>de cultura que quieres construir</h2>
                        <form action="guardar_cultura.php" method="post" style="align-elements:center;">
                            
                            
<div class="radio-card">
  <input type="radio" name="cultura" value="Clan" id="clan">
  <label for="clan"><strong style="color:#004758; font-size:30px">"Aquí somos como una familia"</strong></br>Lo más importante es nuestro equipo, pues si internamente estamos bien, podemos entregar lo mejor de nosotros al mundo.</label>
</div>
<div class="radio-card">
  <input type="radio" name="cultura" value="Adhocracia" id="adhocracia">
  <label for="adhocracia"><strong style="color:#004758; font-size:30px">"Siempre estamos innovando"</strong></br>El cambio constante es lo que nos define, sabemos que para romper paradigmas hay que salir constantemente de nuestra zona de confort.</label>
</div>
<div class="radio-card">
  <input type="radio" name="cultura" value="Jerárquica" id="jerarquica">
  <label for="jerarquica"><strong style="color:#004758; font-size:30px">"Estructuras claras"</strong></br>Los procesos y estructuras bien definidas nos permiten construir con bases sólidas y duraderas, Avanzamos con determinación y precaución.</label>
</div>
<div class="radio-card">
  <input type="radio" name="cultura" value="Mercado" id="mercado">
  <label for="mercado"><strong style="color:#004758; font-size:30px">"Somos competitivos"</strong></br>La verdad está en los números, todo se mide, todo se optimiza, y lo que no genera valor, se descarta.</label>
</div>

                            
<div style="display: flex; justify-content: center; margin-top: 30px; margin-bottom: 50px;">
  <button type="submit" class="btn-small">Guardar y Continuar</button>
</div>
                        </form>
                    </div>
                    
                <?php elseif (!empty($cultura_empresa_tipo)): ?>
    <h2 style="color:#FF7800; font-size:30px;">Cultura de la Empresa:</h4>
    <p style="text-align:center; font-size:25px;"> <strong style="color:#004758; font-size:30px">"<?= "Buscas construir un tipo de</br>cultura orientado a " . htmlspecialchars($cultura_empresa_tipo) . ". </strong></br>Ya diste el primer paso, es momento de iniciar</br>esta aventura de construir tu cultura conscientemente" ?></p>
<?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
        




<?php if ($cultura_definida): ?>
  <div style="display: flex; justify-content: center; margin-top: 20px;">
    <a href="dashboard_2.php" class="btn-small" style="text-decoration: none; display: inline-block;">
      Finalizar e ir al dashboard
    </a>
  </div>
<?php endif; ?>

        
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    
<script>
$(document).ready(function() {
    $('#addMoreValues').click(function(e) {
        e.preventDefault();
        $('.values-container').append('<div class="value-entry"><input type="text" name="titulo[]" placeholder="Título del valor" required class="input-field"><textarea name="descripcion[]" placeholder="Descripción del valor" required class="input-field"></textarea><button type="button" class="remove-field btn-small"><strong>↑</strong> Borrar Este Valor</button></div>');
    });
    $(document).on('click', '.remove-field', function() {
        $(this).parent('.value-entry').remove();
    });
});
</script>

</body>
</html>
