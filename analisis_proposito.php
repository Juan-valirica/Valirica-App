<?php
session_start();
require 'config.php';

// **Mostrar errores para depuración**
error_reporting(E_ALL);
ini_set('display_errors', 1);

// **Verificar si el usuario es superadmin**
$admin_emails = ["juan@valirica.com", "calderon10b@gmail.com"];
if (!isset($_SESSION['user_email']) || !in_array($_SESSION['user_email'], $admin_emails)) {
    die("❌ Acceso restringido. Debes ser administrador.");
}

// **Obtener todas las marcas registradas**
$stmt = $conn->prepare("SELECT id, empresa, email, proposito, pdf_analisis, pdf_valores FROM usuarios");
$stmt->execute();
$result = $stmt->get_result();
$empresas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Análisis</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Gestión de Análisis</h1>
        <p>Sube los análisis de propósito y valores para cada marca registrada.</p>

        <?php if (count($empresas) > 0): ?>
            <table border="1" width="100%" cellspacing="0" cellpadding="10">
                <tr>
                    <th>Empresa</th>
                    <th>Email</th>
                    <th>Propósito</th>
                    <th>Análisis de Propósito</th>
                    <th>Análisis de Valores</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($empresas as $empresa): ?>
                    <tr>
                        <td><?= htmlspecialchars($empresa['empresa']) ?></td>
                        <td><?= htmlspecialchars($empresa['email']) ?></td>
                        <td><?= htmlspecialchars($empresa['proposito']) ?: "❌ No registrado" ?></td>
                        <td>
                            <?php if ($empresa['pdf_analisis']): ?>
                                <a href="<?= htmlspecialchars($empresa['pdf_analisis']) ?>" target="_blank">📄 Ver PDF</a>
                            <?php else: ?>
                                <span>❌ No disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($empresa['pdf_valores']): ?>
                                <a href="<?= htmlspecialchars($empresa['pdf_valores']) ?>" target="_blank">📄 Ver PDF</a>
                            <?php else: ?>
                                <span>❌ No disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="guardar_analisis.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="user_id" value="<?= $empresa['id'] ?>">
                                <label>Subir Análisis de Propósito (PDF):</label>
                                <input type="file" name="pdf_analisis" accept="application/pdf" required>
                                <button type="submit">Subir</button>
                            </form>
                            <form action="guardar_analisis_valores.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="user_id" value="<?= $empresa['id'] ?>">
                                <label>Subir Análisis de Valores (PDF):</label>
                                <input type="file" name="pdf_valores" accept="application/pdf" required>
                                <button type="submit">Subir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No hay empresas registradas aún.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn">Volver al Dashboard</a>
    </div>
</body>
</html>
