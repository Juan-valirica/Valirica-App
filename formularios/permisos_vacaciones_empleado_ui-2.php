<?php
/**
 * UI: MÓDULO DE PERMISOS Y VACACIONES PARA EMPLEADOS
 * Este archivo puede incluirse en dashboard_equipo.php
 * Variables requeridas: $empleado_id
 */

// Obtener balance de vacaciones del empleado
$balance_vacaciones = null;
$stmt_balance = $conn->prepare("
    SELECT * FROM balance_vacaciones
    WHERE persona_id = ? AND anio = YEAR(CURDATE())
");
$stmt_balance->bind_param("i", $empleado_id);
$stmt_balance->execute();
$balance_vacaciones = stmt_get_result($stmt_balance)->fetch_assoc();
$stmt_balance->close();

// Obtener notificaciones no leídas
$notificaciones_empleado = [];
$stmt_notif = $conn->prepare("
    SELECT * FROM notificaciones
    WHERE usuario_destino_id = ? AND tipo_destino = 'empleado' AND leida = FALSE
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt_notif->bind_param("i", $empleado_id);
$stmt_notif->execute();
$result_notif = stmt_get_result($stmt_notif);
while ($row = $result_notif->fetch_assoc()) {
    $notificaciones_empleado[] = $row;
}
$stmt_notif->close();

// Obtener permisos/vacaciones aprobados próximos (para recordatorios)
$proximos_permisos = [];
$stmt_prox = $conn->prepare("
    SELECT 'permiso' as tipo, p.id, p.titulo as descripcion, p.fecha_inicio, p.fecha_fin
    FROM permisos p
    WHERE p.persona_id = ? AND p.estado = 'aprobado' AND p.fecha_inicio >= CURDATE()
    UNION ALL
    SELECT 'vacacion' as tipo, v.id, 'Vacaciones' as descripcion, v.fecha_inicio_programada as fecha_inicio, v.fecha_fin_programada as fecha_fin
    FROM vacaciones v
    WHERE v.persona_id = ? AND v.estado = 'aprobado' AND v.fecha_inicio_programada >= CURDATE()
    ORDER BY fecha_inicio ASC
    LIMIT 5
");
$stmt_prox->bind_param("ii", $empleado_id, $empleado_id);
$stmt_prox->execute();
$result_prox = stmt_get_result($stmt_prox);
while ($row = $result_prox->fetch_assoc()) {
    $proximos_permisos[] = $row;
}
$stmt_prox->close();
?>

<!-- ========================================================================== -->
<!-- ESTILOS -->
<!-- ========================================================================== -->
<style>
.pv-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.2s;
}

.pv-modal-content {
    background: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 16px;
    max-width: 600px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.pv-modal-header {
    padding: 24px;
    border-bottom: 1px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pv-modal-header h2 {
    margin: 0;
    font-size: 24px;
    color: #1F2937;
}

.pv-close {
    font-size: 32px;
    font-weight: 300;
    color: #9CA3AF;
    cursor: pointer;
    transition: color 0.2s;
}

.pv-close:hover {
    color: #EF4444;
}

.pv-modal-body {
    padding: 24px;
}

.pv-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid #E5E7EB;
}

.pv-tab {
    padding: 12px 24px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    color: #6B7280;
    transition: all 0.2s;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
}

.pv-tab.active {
    color: #3B82F6;
    border-bottom-color: #3B82F6;
}

.pv-tab:hover {
    color: #3B82F6;
}

.pv-tab-content {
    display: none;
}

.pv-tab-content.active {
    display: block;
}

.pv-form-group {
    margin-bottom: 20px;
}

.pv-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.pv-form-group input,
.pv-form-group select,
.pv-form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.pv-form-group input:focus,
.pv-form-group select:focus,
.pv-form-group textarea:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.pv-btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    width: 100%;
}

.pv-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.pv-balance-card {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.pv-balance-card h3 {
    margin: 0 0 16px 0;
    font-size: 18px;
}

.pv-balance-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.pv-balance-item:last-child {
    border-bottom: none;
}

.pv-notification {
    background: #FEF3C7;
    border-left: 4px solid #F59E0B;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 12px;
}

.pv-notification h4 {
    margin: 0 0 8px 0;
    color: #92400E;
    font-size: 16px;
}

.pv-notification p {
    margin: 0 0 12px 0;
    color: #78350F;
    font-size: 14px;
}

.pv-notification.success {
    background: #D1FAE5;
    border-left-color: #10B981;
}

.pv-notification.success h4 {
    color: #065F46;
}

.pv-notification.success p {
    color: #047857;
}

.pv-notification.error {
    background: #FEE2E2;
    border-left-color: #EF4444;
}

.pv-notification.error h4 {
    color: #991B1B;
}

.pv-notification.error p {
    color: #B91C1C;
}

.pv-btn-small {
    padding: 6px 12px;
    font-size: 13px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
}

.pv-btn-mark-read {
    background: #3B82F6;
    color: white;
}

.pv-reminder-card {
    background: #EFF6FF;
    border-left: 4px solid #3B82F6;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}

.pv-reminder-card h4 {
    margin: 0 0 4px 0;
    color: #1E40AF;
    font-size: 14px;
}

.pv-reminder-card p {
    margin: 0;
    color: #1E3A8A;
    font-size: 13px;
}

.pv-file-upload {
    border: 2px dashed #D1D5DB;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.pv-file-upload:hover {
    border-color: #3B82F6;
    background: #EFF6FF;
}

.pv-file-upload input[type="file"] {
    display: none;
}
</style>

<!-- ========================================================================== -->
<!-- MODAL DE PERMISOS Y VACACIONES -->
<!-- ========================================================================== -->
<div id="pvModal" class="pv-modal">
    <div class="pv-modal-content">
        <div class="pv-modal-header">
            <h2>Permisos y Vacaciones</h2>
            <span class="pv-close" onclick="closePermisosVacacionesModal()">&times;</span>
        </div>

        <div class="pv-modal-body">
            <!-- TABS -->
            <div class="pv-tabs">
                <button class="pv-tab active" onclick="switchPVTab('permiso')">Solicitar Permiso</button>
                <button class="pv-tab" onclick="switchPVTab('vacacion')">Solicitar Vacaciones</button>
                <button class="pv-tab" onclick="switchPVTab('notificaciones')">
                    Notificaciones
                    <?php if (count($notificaciones_empleado) > 0): ?>
                        (<?= count($notificaciones_empleado) ?>)
                    <?php endif; ?>
                </button>
                <button class="pv-tab" onclick="switchPVTab('proximos')">Próximos</button>
            </div>

            <!-- TAB: SOLICITAR PERMISO -->
            <div id="tab-permiso" class="pv-tab-content active">
                <form id="formSolicitarPermiso" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="solicitar_permiso">
                    <input type="hidden" name="empleado_id" value="<?= $empleado_id ?>">

                    <div class="pv-form-group">
                        <label>Tipo de Permiso *</label>
                        <select name="tipo_permiso_id" id="tipoPermisoSelect" required>
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>

                    <div class="pv-form-group">
                        <label>Título del Permiso *</label>
                        <input type="text" name="titulo" placeholder="Ej: Cita médica, Asunto familiar..." required>
                    </div>

                    <div class="pv-form-group">
                        <label>Descripción / Justificación *</label>
                        <textarea name="descripcion" rows="4" placeholder="Describe brevemente el motivo..." required></textarea>
                    </div>

                    <div class="pv-form-group">
                        <label>Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" required>
                    </div>

                    <div class="pv-form-group">
                        <label>Fecha de Fin *</label>
                        <input type="date" name="fecha_fin" required>
                    </div>

                    <div class="pv-form-group">
                        <label>Documento Justificativo (opcional)</label>
                        <div class="pv-file-upload" onclick="document.getElementById('docPermiso').click()">
                            <p>📎 Haz clic para adjuntar un archivo</p>
                            <small id="nombreArchivoPermiso" style="color: #10B981;"></small>
                        </div>
                        <input type="file" id="docPermiso" name="documento" accept=".pdf,.jpg,.jpeg,.png" onchange="mostrarNombreArchivo('docPermiso', 'nombreArchivoPermiso')">
                    </div>

                    <button type="submit" class="pv-btn-primary">Enviar Solicitud</button>
                </form>
            </div>

            <!-- TAB: SOLICITAR VACACIONES -->
            <div id="tab-vacacion" class="pv-tab-content">
                <!-- Balance de vacaciones -->
                <?php if ($balance_vacaciones): ?>
                    <div class="pv-balance-card">
                        <h3>📊 Tu Balance de Vacaciones <?= date('Y') ?></h3>
                        <div class="pv-balance-item">
                            <span>Días Totales:</span>
                            <strong><?= $balance_vacaciones['dias_totales'] ?> días</strong>
                        </div>
                        <div class="pv-balance-item">
                            <span>Días Usados:</span>
                            <strong><?= $balance_vacaciones['dias_usados'] ?> días</strong>
                        </div>
                        <div class="pv-balance-item">
                            <span>Días Pendientes:</span>
                            <strong><?= $balance_vacaciones['dias_pendientes'] ?> días</strong>
                        </div>
                        <div class="pv-balance-item">
                            <span>Días Disponibles:</span>
                            <strong style="font-size: 20px;"><?= $balance_vacaciones['dias_disponibles'] ?> días</strong>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="formSolicitarVacaciones">
                    <input type="hidden" name="action" value="solicitar_vacaciones">
                    <input type="hidden" name="empleado_id" value="<?= $empleado_id ?>">

                    <div class="pv-form-group">
                        <label>Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" id="vacacionInicio" required>
                    </div>

                    <div class="pv-form-group">
                        <label>Fecha de Fin *</label>
                        <input type="date" name="fecha_fin" id="vacacionFin" required>
                    </div>

                    <div id="diasCalculados" style="background: #EFF6FF; padding: 12px; border-radius: 8px; margin-bottom: 16px; display: none;">
                        <strong style="color: #1E40AF;">Días laborables: <span id="diasLaborables">0</span></strong>
                    </div>

                    <div class="pv-form-group">
                        <label>Motivo (opcional)</label>
                        <textarea name="motivo" rows="3" placeholder="Ej: Viaje familiar, descanso..."></textarea>
                    </div>

                    <button type="submit" class="pv-btn-primary">Solicitar Vacaciones</button>
                </form>
            </div>

            <!-- TAB: NOTIFICACIONES -->
            <div id="tab-notificaciones" class="pv-tab-content">
                <?php if (empty($notificaciones_empleado)): ?>
                    <p style="text-align: center; color: #9CA3AF; padding: 40px 20px;">
                        No tienes notificaciones pendientes
                    </p>
                <?php else: ?>
                    <?php foreach ($notificaciones_empleado as $notif): ?>
                        <div class="pv-notification <?= strpos($notif['tipo'], 'aprobado') !== false ? 'success' : (strpos($notif['tipo'], 'rechazado') !== false ? 'error' : '') ?>">
                            <h4><?= htmlspecialchars($notif['titulo']) ?></h4>
                            <p><?= htmlspecialchars($notif['mensaje']) ?></p>
                            <small style="opacity: 0.7;"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></small>
                            <br><br>
                            <button class="pv-btn-small pv-btn-mark-read" onclick="marcarNotificacionLeida(<?= $notif['id'] ?>)">
                                Marcar como leída
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- TAB: PRÓXIMOS PERMISOS/VACACIONES -->
            <div id="tab-proximos" class="pv-tab-content">
                <?php if (empty($proximos_permisos)): ?>
                    <p style="text-align: center; color: #9CA3AF; padding: 40px 20px;">
                        No tienes permisos o vacaciones aprobados próximos
                    </p>
                <?php else: ?>
                    <?php foreach ($proximos_permisos as $proximo): ?>
                        <div class="pv-reminder-card">
                            <h4><?= $proximo['tipo'] === 'permiso' ? '🔔 Permiso' : '🏖️ Vacaciones' ?>: <?= htmlspecialchars($proximo['descripcion']) ?></h4>
                            <p>
                                📅 Del <?= date('d/m/Y', strtotime($proximo['fecha_inicio'])) ?>
                                al <?= date('d/m/Y', strtotime($proximo['fecha_fin'])) ?>
                            </p>
                            <small style="opacity: 0.7;">
                                <?php
                                $dias_hasta = (strtotime($proximo['fecha_inicio']) - strtotime(date('Y-m-d'))) / 86400;
                                if ($dias_hasta == 0) {
                                    echo '¡Hoy!';
                                } elseif ($dias_hasta == 1) {
                                    echo 'Mañana';
                                } else {
                                    echo "En {$dias_hasta} días";
                                }
                                ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================== -->
<!-- JAVASCRIPT -->
<!-- ========================================================================== -->
<script>
// Badge de notificaciones en el tab Permisos
(function() {
    const pvCount = <?= count($notificaciones_empleado) ?>;
    if (pvCount > 0) {
        const tabBtn = document.querySelector('[data-tab="permisos"]');
        if (tabBtn && !tabBtn.querySelector('.tab-notif')) {
            const badge = document.createElement('span');
            badge.className = 'tab-notif';
            badge.textContent = pvCount;
            tabBtn.appendChild(badge);
        }
    }
})();

// Abrir/Cerrar Modal
function openPermisosVacacionesModal() {
    document.getElementById('pvModal').style.display = 'block';
    cargarTiposPermisos();
}

function closePermisosVacacionesModal() {
    document.getElementById('pvModal').style.display = 'none';
}

// Cambiar entre tabs
function switchPVTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('.pv-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.pv-tab').forEach(btn => {
        btn.classList.remove('active');
    });

    // Mostrar tab seleccionado
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// Cargar tipos de permisos
async function cargarTiposPermisos() {
    try {
        const response = await fetch('permisos_vacaciones_backend.php?action=obtener_tipos_permisos');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('tipoPermisoSelect');
            select.innerHTML = '<option value="">Seleccionar...</option>';

            data.tipos.forEach(tipo => {
                const option = document.createElement('option');
                option.value = tipo.id;
                option.textContent = tipo.nombre;
                option.dataset.anticipacion = tipo.dias_anticipacion_minima;
                option.dataset.requiere = tipo.requiere_justificante;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar tipos de permisos:', error);
    }
}

// Mostrar nombre de archivo seleccionado
function mostrarNombreArchivo(inputId, spanId) {
    const input = document.getElementById(inputId);
    const span = document.getElementById(spanId);
    if (input.files.length > 0) {
        span.textContent = '✓ ' + input.files[0].name;
    }
}

// Calcular días laborables para vacaciones
document.addEventListener('DOMContentLoaded', function() {
    const inicioInput = document.getElementById('vacacionInicio');
    const finInput = document.getElementById('vacacionFin');

    if (inicioInput && finInput) {
        [inicioInput, finInput].forEach(input => {
            input.addEventListener('change', function() {
                if (inicioInput.value && finInput.value) {
                    const inicio = new Date(inicioInput.value);
                    const fin = new Date(finInput.value);

                    let dias = 0;
                    for (let d = new Date(inicio); d <= fin; d.setDate(d.getDate() + 1)) {
                        const diaSemana = d.getDay();
                        if (diaSemana !== 0 && diaSemana !== 6) { // No contar sábados ni domingos
                            dias++;
                        }
                    }

                    document.getElementById('diasCalculados').style.display = 'block';
                    document.getElementById('diasLaborables').textContent = dias;
                }
            });
        });
    }
});

// Enviar solicitud de permiso
document.getElementById('formSolicitarPermiso').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message);
            closePermisosVacacionesModal();
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error al enviar la solicitud: ' + error.message);
    }
});

// Enviar solicitud de vacaciones
document.getElementById('formSolicitarVacaciones').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message + '\nDías solicitados: ' + data.dias_solicitados);
            closePermisosVacacionesModal();
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (error) {
        alert('Error al enviar la solicitud: ' + error.message);
    }
});

// Marcar notificación como leída
async function marcarNotificacionLeida(notificacionId) {
    try {
        const formData = new FormData();
        formData.append('action', 'marcar_notificacion_leida');
        formData.append('notificacion_id', notificacionId);
        formData.append('empleado_id', <?= $empleado_id ?>);

        const response = await fetch('permisos_vacaciones_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            location.reload();
        }
    } catch (error) {
        console.error('Error al marcar notificación:', error);
    }
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('pvModal');
    if (event.target === modal) {
        closePermisosVacacionesModal();
    }
}
</script>