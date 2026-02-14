<?php
session_start();
require_once 'config.php';
require 'header-section/header_logic.php';


// Validación del user_id
$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
    echo "Usuario no especificado.";
    exit;
}

// 🔹 DATOS DEL USUARIO (como en el dashboard)
include 'header-section/header_logic.php';


// 🔧 Función reutilizable para promediar dimensiones específicas de valores
function promedio_valores_marca(array $claves, mysqli $conn, int $user_id): float {
    $sumas = array_fill_keys($claves, 0);
    $count = 0;

    $campos = implode(', ', $claves);
    $stmt = $conn->prepare("SELECT $campos FROM valores_marca WHERE usuario_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($fila = $res->fetch_assoc()) {
        foreach ($claves as $clave) {
            $sumas[$clave] += intval($fila[$clave]);
        }
        $count++;
    }
    $stmt->close();

    if ($count === 0) return 0;

    $total = array_sum($sumas);
    return $total / ($count * count($claves));
}

// 🔹 Recuperar dimensiones del propósito con fallback (si alguna no existe)
$proposito_enfoque     = floatval($analisis['proposito_enfoque']     ?? 0);
$proposito_motivacion  = floatval($analisis['proposito_motivacion']  ?? 0);
$proposito_tiempo      = floatval($analisis['proposito_tiempo']      ?? 0);
$proposito_disrupcion  = floatval($analisis['proposito_disrupcion']  ?? 0);
$proposito_inmersion   = floatval($analisis['proposito_inmersion']   ?? 0);

// 🔹 Calcular eje X (Enfoque Estratégico)
$prom_x = promedio_valores_marca(['aplicacion', 'activador', 'proposito'], $conn, $user_id);
$ejeX = round(($proposito_enfoque + $proposito_motivacion + $prom_x) / 3, 2);

// 🔹 Calcular eje Y (Estilo de Ejecución)
$prom_y = promedio_valores_marca(['rol', 'institucional'], $conn, $user_id);
$ejeY = round(($proposito_disrupcion + $proposito_inmersion + $proposito_tiempo + $prom_y) / 4, 2);

// 🔹 Obtener valores individuales con sus 5 dimensiones
$stmt = $conn->prepare("SELECT titulo, aplicacion, activador, proposito, rol, institucional FROM valores_marca WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_valores_individuales = $stmt->get_result();

$valores_puntos = [];
while ($valor = $res_valores_individuales->fetch_assoc()) {
    $aplicacion   = is_numeric($valor['aplicacion']) ? floatval($valor['aplicacion']) : 0;
    $activador    = is_numeric($valor['activador']) ? floatval($valor['activador']) : 0;
    $proposito    = is_numeric($valor['proposito']) ? floatval($valor['proposito']) : 0;
    $rol          = is_numeric($valor['rol']) ? floatval($valor['rol']) : 0;
    $institucional= is_numeric($valor['institucional']) ? floatval($valor['institucional']) : 0;

    $x_valor = round(($aplicacion + $activador + $proposito) / 3, 2);
    $y_valor = round(($rol + $institucional) / 2, 2);
    $nombre = htmlspecialchars($valor['titulo']);

    if ($nombre !== '') {
        $valores_puntos[] = [
            'x' => $x_valor,
            'y' => $y_valor,
            'label' => $nombre
        ];
    }
}
$stmt->close();


// 🔹 Obtener dimensiones del propósito
$stmt = $conn->prepare("SELECT proposito_enfoque, proposito_motivacion, proposito_tiempo, proposito_disrupcion, proposito_inmersion FROM cultura_ideal WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$datos_proposito = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 🔸 Construir array de puntos de propósito con peso 1.3
$proposito_puntos = [
  ['x' => $datos_proposito['proposito_enfoque'],    'y' => $datos_proposito['proposito_motivacion']],
  ['x' => $datos_proposito['proposito_disrupcion'], 'y' => $datos_proposito['proposito_inmersion']],
  ['x' => $datos_proposito['proposito_enfoque'],    'y' => $datos_proposito['proposito_tiempo']]
];

// 🔹 Combinar con puntos de valores
$peso_total = 0;
$suma_x = 0;
$suma_y = 0;

// ➤ Agregar puntos de valores (peso = 1)
foreach ($valores_puntos as $p) {
    $peso = 1;
    $suma_x += $p['x'] * $peso;
    $suma_y += $p['y'] * $peso;
    $peso_total += $peso;
}

// ➤ Agregar puntos de propósito (peso = 1.3)
foreach ($proposito_puntos as $p) {
    $peso = 1.3;
    $suma_x += $p['x'] * $peso;
    $suma_y += $p['y'] * $peso;
    $peso_total += $peso;
}

// 🔹 Calcular promedio ponderado final del punto ideal
$ejeX = round($suma_x / $peso_total, 2);
$ejeY = round($suma_y / $peso_total, 2);


// 🔹 Asegurar valores
$proposito_enfoque     = floatval($datos_proposito['proposito_enfoque'] ?? 0);
$proposito_motivacion  = floatval($datos_proposito['proposito_motivacion'] ?? 0);
$proposito_tiempo      = floatval($datos_proposito['proposito_tiempo'] ?? 0);
$proposito_disrupcion  = floatval($datos_proposito['proposito_disrupcion'] ?? 0);
$proposito_inmersion   = floatval($datos_proposito['proposito_inmersion'] ?? 0);

// 🔹 Calcular coordenadas para el punto de propósito
$proposito_punto = [
  'x' => ($proposito_enfoque + $proposito_motivacion) / 2,
  'y' => ($proposito_disrupcion + $proposito_inmersion + $proposito_tiempo) / 3
];


// 🔹 Determinar tipo de cultura según ubicación del punto
$cultura_tipo = '';
if ($ejeX < 0 && $ejeY > 0) $cultura_tipo = 'Clan';
elseif ($ejeX >= 0 && $ejeY > 0) $cultura_tipo = 'Adhocracia';
elseif ($ejeX < 0 && $ejeY <= 0) $cultura_tipo = 'Jerárquica';
elseif ($ejeX >= 0 && $ejeY <= 0) $cultura_tipo = 'Mercado';


// Actualizar cultura_empresa_tipo en la tabla usuarios
$stmt = $conn->prepare("UPDATE usuarios SET cultura_empresa_tipo = ? WHERE id = ?");
$stmt->bind_param("si", $cultura_tipo, $user_id);
$stmt->execute();
$stmt->close();


// 🔹 Diccionario con título visible, subtítulo y descripción cultural


$info_culturas = [
    'Clan' => [
        'nombre' => 'Cultura de Cuidado',
        'subtitulo' => 'Colaboración, equipo, comunidad',
        'descripcion' => 'En esta cultura, lo más importante son las personas. El trabajo en equipo, el sentido de comunidad y el cuidado mutuo no son frases bonitas, son la base de cómo se toman decisiones y se construyen relaciones. Se valora más la calidad de los vínculos que la velocidad de los resultados.</br></br>

Es ideal para quienes encuentran motivación en sentirse parte de algo más grande, en construir entornos empáticos, y en trabajar hombro a hombro con un equipo donde la confianza no se negocia. Si crees que un buen ambiente laboral no es un lujo sino una estrategia, aquí vas a encajar perfectamente.'
    ],
    'Adhocracia' => [
        'nombre' => 'Cultura de Cambio',
        'subtitulo' => 'Innovación, disrupción, cambio',
        'descripcion' => 'Esta cultura es puro movimiento. Aquí las ideas vuelan rápido, los cambios son bienvenidos y la innovación no es una fase, es el estado natural. El foco está en crear, iterar y desafiar lo establecido, incluso si eso significa vivir en la incomodidad del “no saber aún”.</br></br>

Si eres una persona curiosa, valiente, que se aburre rápido de lo predecible y busca espacios donde pueda experimentar con libertad, esta cultura te va a encantar. Te sentirás inspirado en cada reto, motivado por la autonomía y empujado a dejar huella con lo que haces.'
    ],
    'Jerárquica' => [
        'nombre' => 'Cultura de Orden',
        'subtitulo' => 'Estructura, procesos, estabilidad',
        'descripcion' => 'Nada queda al azar. En esta cultura, la claridad es poder: hay estructuras bien definidas, procesos que funcionan y reglas que ayudan a mantener todo bajo control. Se valora la precisión, la estabilidad y la capacidad de prever antes que improvisar.</br></br>

Es perfecta para personas organizadas, comprometidas y metódicas, que rinden mejor en entornos donde cada quien tiene claro su rol y las metas no cambian todos los días. Si disfrutas transformar el caos en estructura y sentir que todo fluye porque hay un plan, este es tu espacio.'
    ],
    'Mercado' => [
        'nombre' => 'Cultura de Impacto',
        'subtitulo' => 'Metas, competencia, resultados',
        'descripcion' => 'Aquí todo gira alrededor del logro. Se respira ambición, enfoque y resultados concretos. Esta cultura premia la iniciativa, mide lo que importa y siempre tiene la mira puesta en el próximo objetivo. No se trata de moverse por moverse, sino de avanzar con intención.</br></br>

Ideal para personas orientadas a metas, que disfrutan los desafíos y necesitan sentir que su trabajo genera impacto real. Si te motivan los retos medibles, la posibilidad de destacar y el ritmo exigente de quien no se conforma con el promedio, esta cultura está diseñada para ti.'
    ]
];


// 🔹 Variables ya preparadas para el HTML
$orientacion_cultura = $info_culturas[$cultura_tipo]['nombre'];
$subtitulo_cultura = $info_culturas[$cultura_tipo]['subtitulo'];
$descripcion_cultura = $info_culturas[$cultura_tipo]['descripcion'];


?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Análisis del Propósito</title>
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
            margin-top:30px;
            padding: 20px;
            max-width:90%;
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
        
    </style>
</head>
<body style="background-color:#FFF4EE; margin-bottom:100px;">

<div class="dashboard-container">
<?php include 'header-section/header_dashboard.php'; ?>



<?php
// Verifica si tiene propósito y valores definidos
$stmt = $conn->prepare("SELECT proposito FROM cultura_ideal WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$datos_cultura = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT titulo, descripcion FROM valores_marca WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_valores = $stmt->get_result();

$valores = [];
while ($valor = $result_valores->fetch_assoc()) {
    if (!empty($valor['titulo']) && !empty($valor['descripcion'])) {
        $valores[] = [
            'titulo' => $valor['titulo'],
            'descripcion' => $valor['descripcion']
        ];
    }
}
$stmt->close();

$proposito = trim($datos_cultura['proposito'] ?? '');





if (!$proposito && empty($valores)) {
    // No hay propósito ni valores → mostrar botón
    echo "
    <div style='text-align: center; margin-top: 150px;'>
        <a href='https://app.valirica.com/cultura_ideal.php?usuario_id=$user_id'
           style='background-color:#FF7800; color:white; font-size:30px; padding:25px 50px; border-radius:12px; text-decoration:none; font-weight:bold; box-shadow:0 5px 15px rgba(0,0,0,0.15);'>
           🌟 Diligenciar Propósito y Valores de Marca
        </a>
    </div>";
} else {
    // Mostrar propósito y valores

    if ($proposito) {
        echo "<h1 style='font-size:50px; text-align:left; margin-top:10px;'>$proposito</h1>";
    }

    if (!empty($valores)) {
        foreach ($valores as $valor) {
            $titulo = htmlspecialchars($valor['titulo'] ?? '');
            $descripcion = htmlspecialchars($valor['descripcion'] ?? '');
            echo "
            <div style='margin-top:30px; width:100%; margin-left:auto; margin-right:auto;'>
                <h2 style='color:#FF7800; font-size:35px;'>$titulo</h2>
                <p style='font-size:25px; color:#333;'>$descripcion</p>
            </div>
            ";
        }
    }
}
?>





</div>





<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.1.0"></script>

<div class="dashboard-container" style="
  background: #FFF;
  border-radius: 15px;
  padding-top: 40px;
  padding-bottom: 40px;
  box-shadow: 0 0 15px rgba(0,0,0,0.07);
  width: 100%;
  margin-top: 30px;
  margin-bottom: 30px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  height: 700px;
">

  <h2 style="text-align:center; font-size:35px; color:#004758; margin-top: 80px;">Mapa de Orientación Cultural Según Propósito y Valores</h2>
  <canvas id="cuadranteCultural" style="height:100%; width:100%;margin-bottom:80px;"></canvas>
</div>

<script>
const ctx = document.getElementById('cuadranteCultural').getContext('2d');
const x = <?= $ejeX ?>;
const y = <?= $ejeY ?>;
const valoresData = <?= json_encode($valores_puntos) ?>;
const propositoData = <?= json_encode($proposito_punto) ?>;

// 🔶 1. Determinar en qué cuadrante cae el punto final (x, y)
let cuadranteDestacado = -1;
if (x < 0 && y > 0) cuadranteDestacado = 0;        // Clan
else if (x >= 0 && y > 0) cuadranteDestacado = 1;   // Adhocracia
else if (x < 0 && y <= 0) cuadranteDestacado = 2;   // Jerárquica
else if (x >= 0 && y <= 0) cuadranteDestacado = 3;  // Mercado

// 🔶 2. Asignar color solo a ese cuadrante
const coloresCuadrantes = ['transparent', 'transparent', 'transparent', 'transparent'];
if (cuadranteDestacado !== -1) {
  coloresCuadrantes[cuadranteDestacado] = 'rgba(255,120,0,0.1)';
}





// 🔶 3. Renderizar el gráfico
new Chart(ctx, {
  type: 'scatter',
  data: {
    datasets: [
      {
        label: 'Cultura Ideal (promedio)',
        data: [{ x: x, y: y }],
       
      },
      {
        label: 'Propósito',
        data: [propositoData],
        backgroundColor: '#00C48C',
        pointRadius: 12,
        borderColor: '#004758',
        borderWidth: 2
      },
      {
        label: 'Valores',
        data: valoresData,
        backgroundColor: '#FF7800',
        pointRadius: 8
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    
    
    scales: {
  x: {
    min: -5, max: 5,
    title: {
      display: true,
      text: '← Interno | Externo →',
      font: {
        size: 20,
        weight: 'bold'
      },
      color: '#004758'
    },
    ticks: { display: false },
    grid: {
      drawTicks: false,
      borderColor: 'transparent', // 🔸 elimina el marco
      color: function(context) {
        return context.tick.value === 0 ? '#004758' : 'transparent';
      },
      lineWidth: function(context) {
        return context.tick.value === 0 ? 2.5 : 0; // 🔸 grosor de línea central
      }
    }
  },
  y: {
    min: -5, max: 5,
    title: {
      display: true,
      text: '← Controlado | Flexible →',
      font: {
        size: 20,
        weight: 'bold'
      },
      color: '#004758'
    },
    ticks: { display: false },
    grid: {
      drawTicks: false,
      borderColor: 'transparent', // 🔸 elimina el marco
      color: function(context) {
        return context.tick.value === 0 ? '#004758' : 'transparent';
      },
      lineWidth: function(context) {
        return context.tick.value === 0 ? 2.5 : 0; // 🔸 grosor de línea central
      }
    }
  }
}
,




    plugins: {
      annotation: {
        annotations: {
          cuadrante1: {
            type: 'box', xMin: -5, xMax: 0, yMin: 0, yMax: 5,
            backgroundColor: coloresCuadrantes[0],
            label: { content: 'Clan', enabled: true }
          },
          cuadrante2: {
            type: 'box', xMin: 0, xMax: 5, yMin: 0, yMax: 5,
            backgroundColor: coloresCuadrantes[1],
            label: { content: 'Adhocracia', enabled: true }
          },
          cuadrante3: {
            type: 'box', xMin: -5, xMax: 0, yMin: -5, yMax: 0,
            backgroundColor: coloresCuadrantes[2],
            label: { content: 'Jerárquica', enabled: true }
          },
          cuadrante4: {
            type: 'box', xMin: 0, xMax: 5, yMin: -5, yMax: 0,
            backgroundColor: coloresCuadrantes[3],
            label: { content: 'Mercado', enabled: true }
          }
        }
      },
      legend: { display: false }
    }
  }
});
</script>

<div class="dashboard-container">

<div style="text-align:left; margin-top:50px;">
  <strong style="color: #FF7800; font-size: 35px;"><?= $orientacion_cultura ?></strong><br>
  <span style="font-size: 24px;"><?= $subtitulo_cultura ?></span>

  <div style="margin-top: 30px;">
    <p style="font-size: 25px; max-width: 100%; margin: auto;"><?= $descripcion_cultura ?></p>
  </div>
</div>
</div>






</body>
</html>
