<?php
require 'config.php';
require 'header-section/header_logic.php';

$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
    echo "Usuario no especificado.";
    exit;
}

$stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo FROM usuarios WHERE id = ?");






$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_info = $result->fetch_assoc();
    $empresa = htmlspecialchars($user_info['empresa']);
    $logo = htmlspecialchars($user_info['logo']);
    $cultura_tipo = htmlspecialchars($user_info['cultura_empresa_tipo']);
} else {
    echo "No se encontraron datos del usuario.";
    exit;
}





// Manejo del formulario POST
$mensaje_confirmacion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta = trim($_POST['respuesta'] ?? '');
    if ($respuesta) {
        $stmt = $conn->prepare("INSERT INTO ProductFeed (usuario_id, nombre_marca, respuesta) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $empresa, $respuesta);
        $stmt->execute();
        $stmt->close();
        $mensaje_confirmacion = "¡Gracias por tu respuesta!";
    }
}



?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rituales de Marca</title>
    <link rel="stylesheet" href="styles.css">
      <style>
        @import url("https://use.typekit.net/qrv8fyz.css");
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
            max-width:80%;
        }
        .header-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            border-radius: 8px;
            width: fit-content;
        }
        .top-row {
            display: flex;
            align-items: center;
            width: 100%;
        }
        .company-logo {
        width: 200px;
        margin-right: 20px; /* Space between logo and text */
        clip-path: circle(50% at 50% 50%);
        object-fit:cover;
        }
        
        .company-info p {
            margin: 0;
        }
        .company-info h1 {
            font-size: 50px;
            color: #333;
        }
        .company-info p {
            font-size: 25px;
            color: #666;
            text-align: left;
        }
        .buttons-container {
            display: flex;
            width: 100%;
            justify-content: space-around;
            margin-top: 20px;
        }
        .button a {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            text-align:center;
            color: black;
            font-size:25px;
            margin-left:10px;
            margin-right:20px;
        }
        .button img {
            width: 120px;
            margin-bottom: 5px;
        }
        
               /* Estilos para los elementos de la lista */
    .perfil-container ul {
        list-style: none; /* Elimina los bullets predeterminados */
        padding: 0;
    }

    .perfil-container li {
        padding-left: 50px; /* Espacio para el ��cono */
        background: url('/uploads/icon-perfiles.png') no-repeat left center; /* Ajusta esta ruta al ��cono que quieras usar */
        background-size: 35px; /* Tama�0�9o del ��cono */
        margin-bottom: 10px; /* Espacio entre elementos de la lista */
        color: #FF7800; /* Color del texto de la lista */
    }

    .perfil-container li a {
        text-decoration: none; /* Elimina el subrayado de los enlaces */
        color: inherit; /* Hereda el color del elemento li padre */
        
        
    /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/ 
       /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/ 
          /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/
             /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/    /*PARA MOBIL*/ 
    }
        
        .question {
            margin-top: 30px;
            background: #FFFBF9;
            padding: 20px;
            border-radius: 8px;
            width: 100%;
        }
        textarea {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
            resize: vertical;
            background: #FFF;
            font-family: "Gelica", sans-serif, Arial, Helvetica;
            color: #333;
            box-sizing: border-box;
        }
        textarea:focus {
            border-color: #f28d5c;
            outline: none;
            box-shadow: 0 0 0 2px rgba(242, 141, 92, 0.2);
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            background-color: #FF7800;
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 18px;
            border: none;
            cursor: pointer;
        }
        .confirmation {
            margin-top: 20px;
            color: green;
            font-weight: bold;
        }
    </style>
</head>
<body style="margin:auto; align-content: center; background-color:#FFF4EE;">


<?php include 'header-section/header_dashboard.php'; ?>


    <!-- Pregunta -->
    <div class="question">
        <h1>Estamos construyendo una sección especial para ti, peo también nos encantaría escucharte y saber... ¿Qué te gustaría encontrar aquí?</h1>
        <form method="POST">
            <textarea name="respuesta" id="respuesta" rows="4" placeholder="Escribe tu respuesta aquí..." required></textarea>
            <button type="submit" class="btn">Enviar</button>
        </form>

        <?php if ($mensaje_confirmacion): ?>
            <div class="confirmation"><?= $mensaje_confirmacion ?></div>
        <?php endif; ?>
    </div>




</div>
</body>
</html>
