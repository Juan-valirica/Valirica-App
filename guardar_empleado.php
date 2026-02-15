<?php
session_start();
require 'config.php';

function convertirRespuestaALetra($respuesta) {
    $conversion = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
    return isset($conversion[strtolower($respuesta)]) ? $conversion[strtolower($respuesta)] : 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_SANITIZE_NUMBER_INT);
    $nombre_persona = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_SPECIAL_CHARS);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);

    // Convertir todas las respuestas a valores numéricos
    $pregunta1  = convertirRespuestaALetra($_POST['pregunta1']);
    $pregunta2  = convertirRespuestaALetra($_POST['pregunta2']);
    $pregunta3  = convertirRespuestaALetra($_POST['pregunta3']);
    $pregunta4  = convertirRespuestaALetra($_POST['pregunta4']);
    $pregunta5  = convertirRespuestaALetra($_POST['pregunta5']);
    $pregunta6  = convertirRespuestaALetra($_POST['pregunta6']);
    $pregunta7  = convertirRespuestaALetra($_POST['pregunta7']);
    $pregunta8  = convertirRespuestaALetra($_POST['pregunta8']);
    $pregunta9  = convertirRespuestaALetra($_POST['pregunta9']);
    $pregunta10 = convertirRespuestaALetra($_POST['pregunta10']);
    $pregunta11 = convertirRespuestaALetra($_POST['pregunta11']);

    // Verificar si el ID de la empresa existe
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = stmt_get_result($stmt);

    if ($result->num_rows == 0) {
        echo "ID de la empresa no válido.";
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Insertar en la tabla equipo
    $stmt = $conn->prepare("INSERT INTO equipo (
        usuario_id, nombre_persona, correo,
        relacion_autoridad, trabajo_individual_equipo,
        estilo_cultura_laboral, manejo_incertidumbre, enfoque_temporal,
        ambiente_motivacion, prioridades_trabajo, manejo_desacuerdo,
        dificultades_equipo, eventos_sociales_trabajo, maslow
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("issiiiiiiiiiii",
        $usuario_id, $nombre_persona, $correo,
        $pregunta1, $pregunta2, $pregunta3, $pregunta4, $pregunta5,
        $pregunta6, $pregunta7, $pregunta8, $pregunta9, $pregunta10, $pregunta11
    );

    if ($stmt->execute()) {
        $id_nuevo = $stmt->insert_id;

        // Actualizar automáticamente Analisis_Resumen y Approach Style
        $update = "
            UPDATE equipo e
            JOIN (
                SELECT 
                    e1.id,
                    dp.`Descripción`,
                    dp.`Approach Style`
                FROM equipo e1
                JOIN descripciones_personalidades dp
                    ON (CASE WHEN e1.`relacion_autoridad` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Distancia  de Poder`
                    AND (CASE WHEN e1.`trabajo_individual_equipo` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Individualismo Vs Colectivismo`
                    AND (CASE WHEN e1.`estilo_cultura_laboral` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Masculinidad Vs Feminidad`
                    AND (CASE WHEN e1.`manejo_incertidumbre` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Evasión a la Incertidumbre`
                    AND (CASE WHEN e1.`enfoque_temporal` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Orientación a Objetivos`
                    AND (CASE WHEN e1.`ambiente_motivacion` IN (1, 2) THEN 1 ELSE 4 END) = dp.`Indulgencia Vs Restricción`
                WHERE e1.id = ?
            ) AS match_persona
            ON e.id = match_persona.id
            SET 
                e.Analisis_Resumen = match_persona.`Descripción`,
                e.`Approach Style` = match_persona.`Approach Style`";
        
        $stmt_update = $conn->prepare($update);
        $stmt_update->bind_param("i", $id_nuevo);
        $stmt_update->execute();
        $stmt_update->close();

        echo "✅ Empleado registrado correctamente. <a href='registro_empleado.html'>Registrar otro</a>";
    } else {
        echo "❌ Error al registrar el empleado: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Acceso no permitido.";
}
?>
