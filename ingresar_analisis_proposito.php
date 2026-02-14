<?php
session_start();
require 'config.php';

// **Activar la visualización de errores para depuración**
error_reporting(E_ALL);
ini_set('display_errors', 1);

// **Verificar si el usuario es superadmin**
$admin_emails = ["juan@valirica.com", "calderon10b@gmail.com"];
if (!isset($_SESSION['user_email']) || !in_array($_SESSION['user_email'], $admin_emails)) {
    die("❌ Acceso restringido. Debes ser administrador.");
}

// **Obtener todas las marcas registradas y verificar su estado**
$stmt = $conn->prepare("
    SELECT u.id, u.empresa, u.email, u.proposito,
           (SELECT COUNT(*) FROM valores_marca v WHERE v.usuario_id = u.id) AS total_valores,
           ap.interno_externo, ap.social_economico, ap.corto_largo, ap.tangible_intangible, ap.estabilidad_innovacion
    FROM usuarios u
    LEFT JOIN analisis_proposito ap ON u.id = ap.usuario_id
");
$stmt->execute();
$result = $stmt->get_result();
$empresas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Análisis</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Solicitudes de Análisis</h1>
        <p>Aquí puedes ver todas las marcas registradas y su estado.</p>

        <?php if (count($empresas) > 0): ?>
            <table border="1" width="100%" cellspacing="0" cellpadding="10">
                <tr>
                    <th>Empresa</th>
                    <th>Email</th>
                    <th>Propósito</th>
                    <th>Valores</th>
                    <th>Análisis de Propósito</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($empresas as $empresa): ?>
                    <?php 
                        $proposito_completado = !empty($empresa['proposito']);
                        $valores_completados = ($empresa['total_valores'] > 0);
                        $analisis_completado = !empty($empresa['interno_externo']); 
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($empresa['empresa']) ?></td>
                        <td><?= htmlspecialchars($empresa['email']) ?></td>
                        <td><?= $proposito_completado ? "✅ Completado" : "❌ Pendiente" ?></td>
                        <td><?= $valores_completados ? "✅ Completado" : "❌ Pendiente" ?></td>
                        <td><?= $analisis_completado ? "✅ Completado" : "❌ Pendiente" ?></td>
                        <td>
                            <?php if ($analisis_completado): ?>
                                ✅ Análisis subido
                            <?php elseif ($proposito_completado && $valores_completados): ?>
                                ❗ Listo para ingresar análisis
                            <?php else: ?>
                                ❌ Pendiente de completar
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$analisis_completado && $proposito_completado && $valores_completados): ?>
                                <a href="formulario_analisis.php?user_id=<?= $empresa['id'] ?>" class="btn">Ingresar Análisis</a>
                            <?php else: ?>
                                Sin acción
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No hay solicitudes de análisis pendientes.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn">Volver al Dashboard</a>
    </div>
</body>
</html>
