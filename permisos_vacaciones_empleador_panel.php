<?php
/**
 * PANEL DE APROBACIÓN PARA EMPLEADORES
 * Incluir en a-desempeno-dashboard.php
 */

// Obtener solicitudes pendientes
$permisos_pendientes = [];
$vacaciones_pendientes = [];

$stmt_p = $conn->prepare("
    SELECT p.*, e.nombre_persona, e.cargo, tp.nombre as tipo_nombre, tp.color_hex
    FROM permisos p
    INNER JOIN equipo e ON p.persona_id = e.id
    INNER JOIN tipos_permisos tp ON p.tipo_permiso_id = tp.id
    WHERE p.usuario_id = ? AND p.estado = 'pendiente'
    ORDER BY p.created_at DESC
");
$stmt_p->bind_param("i", $user_id);
$stmt_p->execute();
$result_p = $stmt_p->get_result();
while ($row = $result_p->fetch_assoc()) {
    $permisos_pendientes[] = $row;
}
$stmt_p->close();

$stmt_v = $conn->prepare("
    SELECT v.*, e.nombre_persona, e.cargo
    FROM vacaciones v
    INNER JOIN equipo e ON v.persona_id = e.id
    WHERE v.usuario_id = ? AND v.estado = 'pendiente'
    ORDER BY v.created_at DESC
");
$stmt_v->bind_param("i", $user_id);
$stmt_v->execute();
$result_v = $stmt_v->get_result();
while ($row = $result_v->fetch_assoc()) {
    $vacaciones_pendientes[] = $row;
}
$stmt_v->close();

$total_pendientes = count($permisos_pendientes) + count($vacaciones_pendientes);
?>

<style>
.pv-admin-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #FFB020;
}

.pv-admin-card.permiso {
    border-left-color: #3B82F6;
}

.pv-admin-card.vacacion {
    border-left-color: #10B981;
}

.pv-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.pv-admin-buttons {
    display: flex;
    gap: 8px;
}

.pv-btn-aprobar {
    background: #10B981;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.pv-btn-rechazar {
    background: #EF4444;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.pv-section-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    color: #92400E;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    margin-left: 12px;
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.2);
}

.pv-section-badge-icon {
    font-size: 14px;
    animation: pulse-badge 2s ease-in-out infinite;
}
</style>

<section style="margin-bottom: var(--space-8);">
    <h2 style="font-size: 22px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-5); display: flex; align-items: center;">
        📝 Permisos y Vacaciones
        <?php if ($total_pendientes > 0): ?>
            <span class="pv-section-badge">
                <span class="pv-section-badge-icon">🔔</span>
                <span><?= $total_pendientes ?> pendiente<?= $total_pendientes > 1 ? 's' : '' ?> de aprobación</span>
            </span>
        <?php endif; ?>
    </h2>

    <div style="padding: 24px;">
        <!-- PERMISOS PENDIENTES -->
        <h3 style="margin-bottom: 16px; font-size: 18px; font-weight: 700; color: #1F2937;">
            Permisos (<?= count($permisos_pendientes) ?>)
        </h3>
        <?php if (empty($permisos_pendientes)): ?>
            <p style="color: #9CA3AF; text-align: center; padding: 20px;">No hay solicitudes de permisos pendientes</p>
        <?php else: ?>
            <?php foreach ($permisos_pendientes as $permiso): ?>
                <div class="pv-admin-card permiso">
                    <div class="pv-admin-header">
                        <div>
                            <h4 style="margin: 0 0 4px 0; color: #1F2937;">
                                <?= htmlspecialchars($permiso['titulo']) ?>
                            </h4>
                            <p style="margin: 0; color: #6B7280; font-size: 14px;">
                                <strong><?= htmlspecialchars($permiso['nombre_persona']) ?></strong>
                                (<?= htmlspecialchars($permiso['cargo']) ?>)
                                - <?= htmlspecialchars($permiso['tipo_nombre']) ?>
                            </p>
                        </div>
                        <span style="background: <?= $permiso['color_hex'] ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                            <?= $permiso['tipo_nombre'] ?>
                        </span>
                    </div>

                    <p style="margin: 12px 0; color: #374151;">
                        <?= nl2br(htmlspecialchars($permiso['descripcion'])) ?>
                    </p>

                    <p style="margin: 12px 0; color: #6B7280; font-size: 14px;">
                        📅 <strong>Del <?= date('d/m/Y', strtotime($permiso['fecha_inicio'])) ?>
                        al <?= date('d/m/Y', strtotime($permiso['fecha_fin'])) ?></strong>
                        (<?= $permiso['dias_solicitados'] ?> días)
                    </p>

                    <?php if ($permiso['documento_path']): ?>
                        <p style="margin: 12px 0;">
                            📎 <a href="<?= $permiso['documento_path'] ?>" target="_blank" style="color: #3B82F6;">
                                Ver documento adjunto
                            </a>
                        </p>
                    <?php endif; ?>

                    <p style="margin: 12px 0; color: #9CA3AF; font-size: 13px;">
                        Solicitado el <?= date('d/m/Y H:i', strtotime($permiso['created_at'])) ?>
                    </p>

                    <div class="pv-admin-buttons">
                        <button class="pv-btn-aprobar" onclick="decidirPermiso(<?= $permiso['id'] ?>, 'aprobar')">
                            ✅ Aprobar
                        </button>
                        <button class="pv-btn-rechazar" onclick="decidirPermisoConMotivo(<?= $permiso['id'] ?>)">
                            ❌ Rechazar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- VACACIONES PENDIENTES -->
        <h3 style="margin: 32px 0 16px 0; font-size: 18px; font-weight: 700; color: #1F2937;">
            Vacaciones (<?= count($vacaciones_pendientes) ?>)
        </h3>
        <?php if (empty($vacaciones_pendientes)): ?>
            <p style="color: #9CA3AF; text-align: center; padding: 20px;">No hay solicitudes de vacaciones pendientes</p>
        <?php else: ?>
            <?php foreach ($vacaciones_pendientes as $vacacion): ?>
                <div class="pv-admin-card vacacion">
                    <div class="pv-admin-header">
                        <div>
                            <h4 style="margin: 0 0 4px 0; color: #1F2937;">
                                🏖️ Solicitud de Vacaciones
                            </h4>
                            <p style="margin: 0; color: #6B7280; font-size: 14px;">
                                <strong><?= htmlspecialchars($vacacion['nombre_persona']) ?></strong>
                                (<?= htmlspecialchars($vacacion['cargo']) ?>)
                            </p>
                        </div>
                        <span style="background: #10B981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                            <?= $vacacion['dias_solicitados'] ?> días laborables
                        </span>
                    </div>

                    <?php if ($vacacion['motivo']): ?>
                        <p style="margin: 12px 0; color: #374151;">
                            💭 <?= nl2br(htmlspecialchars($vacacion['motivo'])) ?>
                        </p>
                    <?php endif; ?>

                    <p style="margin: 12px 0; color: #6B7280; font-size: 14px;">
                        📅 <strong>Del <?= date('d/m/Y', strtotime($vacacion['fecha_inicio_programada'])) ?>
                        al <?= date('d/m/Y', strtotime($vacacion['fecha_fin_programada'])) ?></strong>
                    </p>

                    <p style="margin: 12px 0; color: #9CA3AF; font-size: 13px;">
                        Solicitado el <?= date('d/m/Y H:i', strtotime($vacacion['created_at'])) ?>
                    </p>

                    <div class="pv-admin-buttons">
                        <button class="pv-btn-aprobar" onclick="decidirVacacion(<?= $vacacion['id'] ?>, 'aprobar')">
                            ✅ Aprobar
                        </button>
                        <button class="pv-btn-rechazar" onclick="decidirVacacionConMotivo(<?= $vacacion['id'] ?>)">
                            ❌ Rechazar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script>
// Aprobar permiso
async function decidirPermiso(permisoId, decision) {
    if (!confirm('¿Estás seguro de aprobar este permiso?')) return;

    const formData = new FormData();
    formData.append('action', 'decidir_permiso');
    formData.append('permiso_id', permisoId);
    formData.append('decision', decision);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Rechazar permiso con motivo
async function decidirPermisoConMotivo(permisoId) {
    const motivo = prompt('Escribe el motivo del rechazo:');
    if (!motivo || motivo.trim() === '') {
        alert('Debes proporcionar un motivo para rechazar');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'decidir_permiso');
    formData.append('permiso_id', permisoId);
    formData.append('decision', 'rechazar');
    formData.append('motivo_rechazo', motivo);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Aprobar vacación
async function decidirVacacion(vacacionId, decision) {
    if (!confirm('¿Estás seguro de aprobar estas vacaciones?')) return;

    const formData = new FormData();
    formData.append('action', 'decidir_vacacion');
    formData.append('vacacion_id', vacacionId);
    formData.append('decision', decision);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Rechazar vacación con motivo
async function decidirVacacionConMotivo(vacacionId) {
    const motivo = prompt('Escribe el motivo del rechazo:');
    if (!motivo || motivo.trim() === '') {
        alert('Debes proporcionar un motivo para rechazar');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'decidir_vacacion');
    formData.append('vacacion_id', vacacionId);
    formData.append('decision', 'rechazar');
    formData.append('motivo_rechazo', motivo);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>