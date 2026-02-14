<?php
session_start();
require 'config.php';

$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
    echo "Usuario no especificado.";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM analisis_proposito WHERE usuario_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$analisis = $stmt->get_result()->fetch_assoc();
$stmt->close();


$stmt = $conn->prepare("SELECT cultura_empresa_tipo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();
$cultura_tipo = $user_info['cultura_empresa_tipo'];

$stmt->close();

if (!$analisis) {
    echo "<head><meta charset='UTF-8'><title>Ver Análisis del Propósito</title><link rel='stylesheet' href='styles.css'></head><body><div class='dashboard-container'><h1>El análisis se está cocinando, nos tomamos muy en serio este momento, apenas esté listo te avisamos.</h1></div></body>";
    exit;
}

$dimensiones = [
    "interno_externo" => "Interno - Externo",
    "social_economico" => "Social - Económico",
    "corto_largo" => "Corto Plazo - Largo Plazo",
    "tangible_intangible" => "Tangible - Intangible",
    "estabilidad_innovacion" => "Estabilidad - Innovación"
];

$fit_disc = [
    "interno_externo" => "D, I",
    "social_economico" => "S, C",
    "corto_largo" => "C, D",
    "tangible_intangible" => "I, S",
    "estabilidad_innovacion" => "D, S"
];

$fit_arquetipo = [
    "interno_externo" => "El Héroe",
    "social_economico" => "El Cuidador",
    "corto_largo" => "El Sabio",
    "tangible_intangible" => "El Explorador",
    "estabilidad_innovacion" => "El Gobernante"
];

// Agregar aquí la lógica para contar frecuencias de FIT DISC y Arquetipo

function getDiscArquetipo($dimension, $puntuacion) {
    $mapping = [
        'interno_externo' => [
            ['range' => [1, 2], 'DISC' => 'D, C', 'Arquetipo' => 'El Gobernante', 'Descripcion' => 'Establece orden y control efectivos con una mano firme.'],
            ['range' => [3, 5], 'DISC' => 'D, I', 'Arquetipo' => 'El Héroe', 'Descripcion' => 'Impulsa el liderazgo proactivo y motivacional.'],
            ['range' => [6, 8], 'DISC' => 'S, I', 'Arquetipo' => 'La Persona corriente', 'Descripcion' => 'Crea un liderazgo accesible y relatable.'],
            ['range' => [9, 10], 'DISC' => 'S, I', 'Arquetipo' => 'El Cuidador', 'Descripcion' => 'Fortalece el cuidado y soporte en un liderazgo firme.']
        ],
        'social_economico' => [
            ['range' => [1, 2], 'DISC' => 'C, D', 'Arquetipo' => 'El Sabio', 'Descripcion' => 'Promueve un enfoque metódico y bien informado para resolver problemas.'],
            ['range' => [3, 5], 'DISC' => 'D, S', 'Arquetipo' => 'El Héroe', 'Descripcion' => 'Fomenta un liderazgo práctico y orientado a resultados.'],
            ['range' => [6, 8], 'DISC' => 'I, S', 'Arquetipo' => 'El Amante', 'Descripcion' => 'Prioriza las emociones y las relaciones personales.'],
            ['range' => [9, 10], 'DISC' => 'S, I', 'Arquetipo' => 'El Cuidador', 'Descripcion' => 'Enfatiza la precisión y el cuidado en la toma de decisiones.']
        ],
        'corto_largo' => [
            ['range' => [1, 2], 'DISC' => 'I, D', 'Arquetipo' => 'El Bufón', 'Descripcion' => 'Añade un toque de humor y ligereza al ambiente laboral.'],
            ['range' => [3, 5], 'DISC' => 'D, I', 'Arquetipo' => 'El Rebelde', 'Descripcion' => 'Introduce ideas disruptivas en un entorno amigable.'],
            ['range' => [6, 8], 'DISC' => 'C, S', 'Arquetipo' => 'El Creador', 'Descripcion' => 'Cultiva la creatividad y la expresión en las dinámicas sociales.'],
            ['range' => [9, 10], 'DISC' => 'S, C', 'Arquetipo' => 'El Sabio', 'Descripcion' => 'Combina el pensamiento profundo con la interacción social.']
        ],
        'tangible_intangible' => [
            ['range' => [1, 2], 'DISC' => 'C, S', 'Arquetipo' => 'El Inocente', 'Descripcion' => 'Promueve la autenticidad y la naturalidad en las comunicaciones.'],
            ['range' => [3, 5], 'DISC' => 'S, I', 'Arquetipo' => 'La Persona corriente', 'Descripcion' => 'Refuerza la conexión y el sentido de comunidad.'],
            ['range' => [6, 8], 'DISC' => 'D, I', 'Arquetipo' => 'El Héroe', 'Descripcion' => 'Enfatiza la influencia y el carisma en el liderazgo interactivo.'],
            ['range' => [9, 10], 'DISC' => 'C, D', 'Arquetipo' => 'El Mago', 'Descripcion' => 'Añade un toque de magia e innovación a las interacciones.']
        ],
        'estabilidad_innovacion' => [
            ['range' => [1, 2], 'DISC' => 'S, C', 'Arquetipo' => 'El Gobernante', 'Descripcion' => 'Combina análisis detallado con habilidades interpersonales.'],
            ['range' => [3, 5], 'DISC' => 'S, I', 'Arquetipo' => 'La Persona corriente', 'Descripcion' => 'Alienta la confiabilidad y accesibilidad en el análisis y la planificación.'],
            ['range' => [6, 8], 'DISC' => 'I, D', 'Arquetipo' => 'El Explorador', 'Descripcion' => 'Estimula la exploración de nuevas áreas con un enfoque metódico.'],
            ['range' => [9, 10], 'DISC' => 'D, I', 'Arquetipo' => 'El Creador', 'Descripcion' => 'Incentiva la innovación sistemática y estructurada.']
        ]
    ];

    if (!isset($mapping[$dimension])) {
        return ['DISC' => 'N/A', 'Arquetipo' => 'N/A', 'Descripcion' => 'No aplicable']; // Retorna valores predeterminados si la dimensión no está definida
    }

    foreach ($mapping[$dimension] as $item) {
        if ($puntuacion >= $item['range'][0] && $puntuacion <= $item['range'][1]) {
            return $item; // Devuelve todo el item, incluyendo la descripción
        }
    }

    return ['DISC' => 'N/A', 'Arquetipo' => 'N/A', 'Descripcion' => 'No aplicable']; // En caso de que no se encuentre un rango válido
}



// Inicializar arrays para almacenar todos los fits DISC y Arquetipo
$all_fits_disc = [];
$all_fits_arquetipo = [];

foreach ($dimensiones as $key => $label) {
    if (isset($analisis[$key])) {
        $puntuacion = $analisis[$key];
        $fit = getDiscArquetipo($key, $puntuacion);
        // Almacenar cada fit en los arrays
        $all_fits_disc[] = $fit['DISC'];
        $all_fits_arquetipo[] = $fit['Arquetipo'];
    }
}



// Contar las frecuencias de cada fit DISC y Arquetipo
$disc_count = array_count_values($all_fits_disc);
$arquetipo_count = array_count_values($all_fits_arquetipo);

// Ordenar para obtener los más comunes
arsort($disc_count);
arsort($arquetipo_count);
$fit_ideal_disc = key($disc_count);
$fit_ideal_arquetipo = key($arquetipo_count);


$fit_ajustable_disc = array_keys($disc_count)[1] ?? "N/A";
$fit_ajustable_arquetipo = array_keys($arquetipo_count)[1] ?? "N/A";

// Usar reset y end para obtener el menos común
reset($disc_count);  // Resetea el puntero al inicio para seguridad
end($disc_count);    // Mueve el puntero al final
$fit_desalineado_disc = key($disc_count);

reset($arquetipo_count);
end($arquetipo_count);
$fit_desalineado_arquetipo = key($arquetipo_count);


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Análisis del Propósito</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="dashboard-container">
    <h2>DIMENSIONES DEL PROPÓSITO</h2>
  
  
<?php foreach ($dimensiones as $key => $label): 
    if (!isset($analisis[$key])) {
        echo "<p>La dimensión '{$label}' no está disponible.</p>";
        continue;
    }
    $puntuacion = $analisis[$key];
    $fit = getDiscArquetipo($key, $puntuacion);
?>
    <div style="text-align: left;">
        <h2><?= $label ?></h2>
        <h3>Fit Perfect DISC "<?= $fit['DISC'] ?>"</h3>
        <h3>Fit Perfecto Arquetipo "<?= $fit['Arquetipo'] ?>"</h3>
        <p><?= $fit['Descripcion'] ?></p> <!-- Muestra la descripción aquí -->
        <div class="line-container">
            <span class="line-label left"><?= explode(" - ", $label)[0] ?></span>
            <div class="line-background">
                <div class="circle" style="left: <?= $puntuacion * 10 ?>%;"></div>
            </div>
            <span class="line-label right"><?= explode(" - ", $label)[1] ?></span>
        </div>
    </div>
<?php endforeach; ?>


<?php
// Asegúrate de que todo el bloque de código PHP esté bien estructurado
echo "<h2>Resumen de Alineación</h2>";
echo "<p>Fit Ideal: DISC '" . $fit_ideal_disc . "' - Arquetipo '" . $fit_ideal_arquetipo . "'</p>";
echo "<p>Fit Ajustable: DISC '" . $fit_ajustable_disc . "' - Arquetipo '" . $fit_ajustable_arquetipo . "'</p>";
echo "<p>Fit Desalineado: DISC '" . $fit_desalineado_disc . "' - Arquetipo '" . $fit_desalineado_arquetipo . "'</p>";
?>

    
    

    <h2>Tipo de Cultura de la Empresa</h2>
    <p><?= htmlspecialchars($cultura_tipo) ?></p></br>

<a href="dimension_cultura_hofstede.php?user_id=<?= $user_id ?>" class="btn">VER DIMENSIONES CULTURALES</a>
    
    <a href="dashboard.php" class="btn">Volver al Dashboard</a>
       
    
</div>
</body>
</html>

