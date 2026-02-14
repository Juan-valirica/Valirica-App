<!--
  ============================================
  INTERFAZ DE HORARIOS DE TRABAJO
  ============================================
  Integrar dentro del tab "time" en a-desempeno-dashboard.php
  Agregar DESPUÉS de las secciones existentes de asistencia
  (alrededor de la línea 6100, antes del cierre del tab)
  ============================================
-->

<!-- Sección: Gestión de Horarios de Trabajo -->
<section style="margin-top: var(--space-8); margin-bottom: var(--space-8);">

  <!-- Header con título y botón crear -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6);">
    <div>
      <h1 style="font-size: 28px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
        ⏰ Horarios de Trabajo
      </h1>
      <p style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
        Gestiona las jornadas laborales de tu equipo
      </p>
    </div>
    <button
      onclick="abrirModalCrearJornada()"
      style="
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
        transition: all 0.3s ease;
      "
      onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
      onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
    >
      ➕ Nueva Jornada
    </button>
  </div>

  <!-- KPIs Rápidos -->
  <div style="
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
  ">
    <!-- KPI: Total Jornadas -->
    <div style="
      background: linear-gradient(135deg, #184656 0%, #012133 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
    ">
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        Jornadas Activas
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
        <?= $jornadas_activas ?>
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        <?= $total_jornadas ?> total
      </div>
    </div>

    <!-- KPI: Empleados con Jornada -->
    <div style="
      background: linear-gradient(135deg, #00D98F 0%, #00D98F 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
    ">
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        Empleados Asignados
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
        <?= $total_empleados_con_jornada ?>
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        de <?= $total_personas ?> total
      </div>
    </div>
  </div>

  <!-- Mensaje si no hay jornadas -->
  <?php if (empty($jornadas_trabajo)): ?>
  <div style="
    background: white;
    border: 2px dashed var(--perf-border);
    border-radius: 16px;
    padding: var(--space-8);
    text-align: center;
    margin-bottom: var(--space-6);
  ">
    <div style="font-size: 48px; margin-bottom: var(--space-4); opacity: 0.3;">⏰</div>
    <h3 style="font-size: 20px; font-weight: 600; color: var(--c-secondary); margin: 0 0 var(--space-2);">
      No hay jornadas configuradas
    </h3>
    <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-4);">
      Crea tu primera jornada de trabajo para empezar a gestionar los horarios de tu equipo
    </p>
    <button
      onclick="abrirModalCrearJornada()"
      style="
        padding: 12px 24px;
        background: var(--c-accent);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
      "
    >
      ➕ Crear Primera Jornada
    </button>
  </div>
  <?php endif; ?>

  <!-- Listado de Jornadas (Cards) -->
  <div id="jornadas-container" style="
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--space-5);
  ">

    <?php foreach ($jornadas_trabajo as $jornada): ?>
    <div class="jornada-card" data-jornada-id="<?= $jornada['id'] ?>" style="
      background: white;
      border: 1px solid var(--perf-border);
      border-radius: 16px;
      padding: var(--space-5);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    ">

      <!-- Barra superior de color -->
      <div style="
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: <?= h($jornada['color_hex']) ?>;
      "></div>

      <!-- Header del card -->
      <div style="margin-bottom: var(--space-4);">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: var(--space-2);">
          <div style="flex: 1;">
            <h3 style="font-size: 18px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
              <?= h($jornada['nombre']) ?>
            </h3>
            <?php if (!empty($jornada['codigo_corto'])): ?>
            <span style="
              display: inline-block;
              padding: 4px 10px;
              background: <?= h($jornada['color_hex']) ?>20;
              color: <?= h($jornada['color_hex']) ?>;
              border-radius: 6px;
              font-size: 11px;
              font-weight: 600;
              text-transform: uppercase;
              letter-spacing: 0.5px;
            ">
              <?= h($jornada['codigo_corto']) ?>
            </span>
            <?php endif; ?>
          </div>

          <!-- Estado badge -->
          <?php if ($jornada['is_active']): ?>
          <span style="
            padding: 4px 12px;
            background: #00D98F20;
            color: #00D98F;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
          ">
            🟢 Activa
          </span>
          <?php else: ?>
          <span style="
            padding: 4px 12px;
            background: #94A3B820;
            color: #94A3B8;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
          ">
            ⚫ Archivada
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Info de la jornada -->
      <div style="margin-bottom: var(--space-4);">
        <!-- Tipo de jornada -->
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); font-size: 14px;">
          <span style="opacity: 0.6;">Tipo:</span>
          <span style="font-weight: 600; text-transform: capitalize;">
            <?= h($jornada['tipo_jornada']) ?>
          </span>
        </div>

        <!-- Días y horarios -->
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); font-size: 14px;">
          <span style="opacity: 0.6;">📅</span>
          <span style="font-weight: 500;">
            <?= format_dias_turno($jornada['turnos']) ?>:
            <?= rango_horas_turno($jornada['turnos']) ?>
            <?php if (tiene_turno_nocturno($jornada['turnos'])): ?>
              <span style="margin-left: 4px;">🌙</span>
            <?php endif; ?>
          </span>
        </div>

        <!-- Horas semanales -->
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); font-size: 14px;">
          <span style="opacity: 0.6;">⏱️</span>
          <span style="font-weight: 500;">
            <?= number_format($jornada['horas_semanales_esperadas'], 1) ?> hrs/semana
          </span>
        </div>

        <!-- Tolerancias -->
        <div style="display: flex; align-items: center; gap: var(--space-2); font-size: 13px;">
          <span style="opacity: 0.6;">🔔</span>
          <span style="opacity: 0.8;">
            Tolerancia: <?= $jornada['tolerancia_entrada_min'] ?> min entrada / <?= $jornada['tolerancia_salida_min'] ?> min salida
          </span>
        </div>
      </div>

      <!-- Empleados asignados -->
      <div style="
        padding: var(--space-3);
        background: var(--perf-bg);
        border-radius: 8px;
        margin-bottom: var(--space-4);
      ">
        <div style="font-size: 12px; font-weight: 600; color: var(--c-body); opacity: 0.6; margin-bottom: var(--space-1); text-transform: uppercase; letter-spacing: 0.5px;">
          Empleados
        </div>
        <div style="font-size: 24px; font-weight: 700; color: var(--c-accent);">
          <?= $jornada['empleados_asignados'] ?? 0 ?>
        </div>
        <div style="font-size: 12px; opacity: 0.7;">
          asignados actualmente
        </div>
      </div>

      <!-- Botones de acción -->
      <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <button
          onclick="verDetallesJornada(<?= $jornada['id'] ?>)"
          style="
            flex: 1;
            min-width: 100px;
            padding: 10px 16px;
            background: white;
            border: 1px solid var(--perf-border);
            border-radius: 8px;
            color: var(--c-body);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
          "
          onmouseover="this.style.borderColor='var(--c-accent)'; this.style.color='var(--c-accent)'"
          onmouseout="this.style.borderColor='var(--perf-border)'; this.style.color='var(--c-body)'"
          title="Ver detalles y gestionar empleados"
        >
          Editar
        </button>

        <button
          onclick="abrirModalAsignarEmpleados(<?= $jornada['id'] ?>, '<?= h($jornada['nombre']) ?>')"
          style="
            flex: 1;
            min-width: 100px;
            padding: 10px 16px;
            background: var(--c-accent);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
          "
          onmouseover="this.style.opacity='0.9'"
          onmouseout="this.style.opacity='1'"
          title="Asignar empleados a esta jornada"
        >
          Asignar
        </button>

        <!-- Botón eliminar -->
        <button
          onclick="confirmarEliminarJornada(<?= $jornada['id'] ?>, '<?= h($jornada['nombre']) ?>')"
          style="
            padding: 10px 14px;
            background: white;
            border: 1px solid #FF3B6D;
            border-radius: 8px;
            color: #FF3B6D;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
          "
          onmouseover="this.style.background='#FF3B6D'; this.style.color='white'"
          onmouseout="this.style.background='white'; this.style.color='#FF3B6D'"
          title="Eliminar jornada"
        >
          Eliminar
        </button>
      </div>
    </div>
    <?php endforeach; ?>

  </div>

</section>


<!-- ============================================ -->
<!-- MODALES                                      -->
<!-- ============================================ -->

<!-- Modal: Crear/Editar Jornada (Wizard 4 pasos) -->
<div id="modalJornadaWizard" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header del modal -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 id="wizard-title" style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          Crear Nueva Jornada
        </h2>
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-top: var(--space-2);">
          <span id="wizard-step-indicator" style="font-size: 12px; font-weight: 600; color: var(--c-body); opacity: 0.5;">
            Paso 1 de 4
          </span>
          <div style="flex: 1; height: 4px; background: var(--perf-border); border-radius: 2px; max-width: 200px;">
            <div id="wizard-progress-bar" style="height: 100%; background: var(--c-accent); border-radius: 2px; width: 25%; transition: width 0.3s ease;"></div>
          </div>
        </div>
      </div>
      <button
        onclick="cerrarModalJornada()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
          padding: 0;
          width: 32px;
          height: 32px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          transition: all 0.2s ease;
        "
        onmouseover="this.style.opacity='1'; this.style.background='var(--perf-bg)'"
        onmouseout="this.style.opacity='0.5'; this.style.background='none'"
      >
        ✕
      </button>
    </div>

    <!-- Contenido del wizard -->
    <div id="wizard-content" style="padding: var(--space-6);">
      <!-- Los pasos del wizard se cargarán dinámicamente con JavaScript -->
    </div>

    <!-- Footer con botones -->
    <div style="
      padding: var(--space-6);
      border-top: 1px solid var(--perf-border);
      display: flex;
      gap: var(--space-3);
      justify-content: flex-end;
    ">
      <button
        id="wizard-btn-back"
        onclick="wizardPrevStep()"
        style="
          display: none;
          padding: 12px 24px;
          background: white;
          border: 1px solid var(--perf-border);
          border-radius: 8px;
          color: var(--c-body);
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        ← Atrás
      </button>

      <button
        onclick="cerrarModalJornada()"
        style="
          padding: 12px 24px;
          background: white;
          border: 1px solid var(--perf-border);
          border-radius: 8px;
          color: var(--c-body);
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        Cancelar
      </button>

      <button
        id="wizard-btn-next"
        onclick="wizardNextStep()"
        style="
          padding: 12px 24px;
          background: var(--c-accent);
          border: none;
          border-radius: 8px;
          color: white;
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        Siguiente →
      </button>
    </div>
  </div>
</div>


<!-- Modal: Asignar Empleados -->
<div id="modalAsignarEmpleados" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          Asignar Empleados
        </h2>
        <p id="asignar-jornada-nombre" style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          <!-- Se llenará con JS -->
        </p>
      </div>
      <button
        onclick="cerrarModalAsignar()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
        "
      >
        ✕
      </button>
    </div>

    <!-- Contenido -->
    <div style="padding: var(--space-6);">
      <form id="formAsignarEmpleados" onsubmit="submitAsignarEmpleados(event)">
        <input type="hidden" id="asignar-jornada-id" name="jornada_id">

        <!-- Búsqueda -->
        <input
          type="text"
          id="buscar-empleado"
          placeholder="🔍 Buscar empleado..."
          style="
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--perf-border);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: var(--space-4);
          "
          oninput="filtrarEmpleados(this.value)"
        >

        <!-- Lista de empleados -->
        <div id="lista-empleados-asignar" style="max-height: 400px; overflow-y: auto;">
          <!-- Se llenará dinámicamente con JS -->
        </div>

        <!-- Vigencia -->
        <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--perf-bg); border-radius: 8px;">
          <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
            Vigencia de la asignación:
          </label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
            <div>
              <label style="display: block; font-size: 12px; opacity: 0.7; margin-bottom: var(--space-1);">Desde:</label>
              <input
                type="date"
                id="asignar-fecha-inicio"
                value="<?= date('Y-m-d') ?>"
                style="width: 100%; padding: 8px 12px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
              >
            </div>
            <div>
              <label style="display: block; font-size: 12px; opacity: 0.7; margin-bottom: var(--space-1);">Hasta (opcional):</label>
              <input
                type="date"
                id="asignar-fecha-fin"
                style="width: 100%; padding: 8px 12px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
              >
            </div>
          </div>
          <p style="font-size: 11px; opacity: 0.6; margin: var(--space-2) 0 0;">
            💡 Dejar "Hasta" vacío para asignación indefinida
          </p>
        </div>

        <!-- Botones -->
        <div style="display: flex; gap: var(--space-3); margin-top: var(--space-5);">
          <button
            type="button"
            onclick="cerrarModalAsignar()"
            style="
              flex: 1;
              padding: 12px;
              background: white;
              border: 1px solid var(--perf-border);
              border-radius: 8px;
              font-size: 14px;
              font-weight: 600;
              cursor: pointer;
            "
          >
            Cancelar
          </button>
          <button
            type="submit"
            style="
              flex: 1;
              padding: 12px;
              background: var(--c-accent);
              border: none;
              border-radius: 8px;
              color: white;
              font-size: 14px;
              font-weight: 600;
              cursor: pointer;
            "
          >
            Asignar Seleccionados
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Detalles y Editar Jornada -->
<div id="modalDetallesJornada" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 id="detalles-jornada-titulo" style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          Detalles de Jornada
        </h2>
        <p style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          Visualiza y gestiona los empleados asignados
        </p>
      </div>
      <button
        onclick="cerrarModalDetalles()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
        "
      >
        &times;
      </button>
    </div>

    <!-- Contenido del modal -->
    <div id="detalles-jornada-content" style="padding: var(--space-6);">
      <!-- Se carga dinámicamente -->
      <div style="text-align: center; padding: var(--space-8);">
        <div class="spinner" style="
          width: 40px;
          height: 40px;
          border: 3px solid var(--perf-border);
          border-top-color: var(--c-accent);
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin: 0 auto var(--space-4);
        "></div>
        <p>Cargando detalles...</p>
      </div>
    </div>

    <!-- Footer -->
    <div style="
      padding: var(--space-6);
      border-top: 1px solid var(--perf-border);
      display: flex;
      gap: var(--space-3);
      justify-content: flex-end;
    ">
      <button
        onclick="cerrarModalDetalles()"
        style="
          padding: 12px 24px;
          background: white;
          border: 1px solid var(--perf-border);
          border-radius: 8px;
          color: var(--c-body);
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        Cerrar
      </button>
    </div>
  </div>
</div>

<style>
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<!-- El JavaScript se incluirá en un archivo separado -->
