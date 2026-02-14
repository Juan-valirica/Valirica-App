/* =====================================================================
   UPGRADE UX/UI - SECCIÓN DE METAS
   dashboard_equipo.php - Versión 2.0

   Basado en:
   - Nielsen Norman Group - Visual Hierarchy & Microinteractions
   - Carbon Design System - Status Indicators
   - Material Design 3 - Spacing & Typography

   INSTRUCCIONES:
   1. Copia los estilos CSS y agrégalos dentro del <style> existente
   2. Reemplaza las funciones JavaScript indicadas
   3. El PHP no necesita cambios (solo CSS y JS)
   ===================================================================== */


/* ==========================================================================
   PARTE 1: ESTILOS CSS PARA METAS
   Agregar DESPUÉS de los estilos existentes dentro de <style>
   ========================================================================== */

const METAS_CSS_UPGRADE = `

/* =============================================
   METAS - SISTEMA DE DISEÑO UNIFICADO
   ============================================= */

/* Variables (ya deberían existir del upgrade de Proyectos) */
:root {
  --text-xs: 11px;
  --text-sm: 13px;
  --text-base: 15px;
  --text-lg: 18px;
  --text-xl: 22px;
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;
  --color-primary: #667EEA;
  --color-accent: #EF7F1B;
  --color-accent-dark: #d66f15;
  --color-success: #10b981;
  --color-success-bg: #ecfdf5;
  --color-warning: #f59e0b;
  --color-warning-bg: #fffbeb;
  --color-danger: #ef4444;
  --color-danger-bg: #fef2f2;
  --color-info: #3b82f6;
  --color-info-bg: #eff6ff;
  --color-neutral: #6b7280;
  --text-primary: #111827;
  --text-secondary: #4b5563;
  --text-tertiary: #6b7280;
  --text-muted: #9ca3af;
  --bg-primary: #ffffff;
  --bg-secondary: #f9fafb;
  --bg-tertiary: #f3f4f6;
  --border-color: #e5e7eb;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
  --transition-fast: 0.15s ease;
  --transition-normal: 0.25s ease;
}

/* =============================================
   CARD DE METAS - CONTENEDOR PRINCIPAL
   ============================================= */
#card-metas {
  background: var(--bg-primary);
  border-radius: 20px;
  padding: var(--space-5);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

#card-metas > h3 {
  font-size: var(--text-lg) !important;
  font-weight: 700 !important;
  color: var(--text-primary) !important;
  margin-bottom: var(--space-5) !important;
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

#card-metas > h3::before {
  content: '🎯';
  font-size: 24px;
}

/* =============================================
   GRID DE METAS - RESPONSIVE
   ============================================= */
.metas-grid {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: var(--space-5) !important;
}

@media (max-width: 900px) {
  .metas-grid {
    grid-template-columns: 1fr !important;
  }
}

/* =============================================
   SECCIÓN HEADERS
   ============================================= */
#meta-personal-title,
#meta-equipo-title {
  font-size: var(--text-base) !important;
  font-weight: 700 !important;
  color: var(--text-primary) !important;
  margin: 0 0 var(--space-4) 0 !important;
  padding-bottom: var(--space-3);
  border-bottom: 2px solid var(--bg-tertiary);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

#meta-personal-title::before {
  content: '';
  width: 4px;
  height: 20px;
  background: var(--color-accent);
  border-radius: 2px;
}

#meta-equipo-title::before {
  content: '';
  width: 4px;
  height: 20px;
  background: var(--color-primary);
  border-radius: 2px;
}

/* =============================================
   META ITEM - CARD INDIVIDUAL
   ============================================= */
.meta-item {
  padding: var(--space-4) !important;
  border-radius: 16px !important;
  border: 1px solid var(--border-color) !important;
  background: var(--bg-primary) !important;
  display: flex !important;
  flex-direction: column !important;
  gap: var(--space-3) !important;
  transition: all var(--transition-normal) !important;
  position: relative;
}

.meta-item:hover {
  box-shadow: var(--shadow-md);
  border-color: #d1d5db !important;
  transform: translateY(-2px);
}

/* Meta con ayuda solicitada */
.meta-item--help-requested {
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%) !important;
  border: 2px solid var(--color-accent) !important;
  animation: pulse-glow 2s ease-in-out infinite !important;
}

@keyframes pulse-glow {
  0%, 100% {
    box-shadow: 0 4px 12px rgba(239, 127, 27, 0.15);
  }
  50% {
    box-shadow: 0 4px 20px rgba(239, 127, 27, 0.3);
  }
}

/* =============================================
   META HEADER - TÍTULO Y BADGE
   ============================================= */
.meta-item > div:first-child {
  display: flex !important;
  justify-content: space-between !important;
  align-items: flex-start !important;
  gap: var(--space-3) !important;
}

.meta-item strong {
  font-size: var(--text-base) !important;
  font-weight: 600 !important;
  color: var(--text-primary) !important;
  line-height: 1.4 !important;
}

/* Badge de ayuda */
.meta-help-badge {
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
  padding: 4px 10px !important;
  background: var(--color-accent) !important;
  color: white !important;
  border-radius: 20px !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  white-space: nowrap !important;
  box-shadow: 0 2px 8px rgba(239, 127, 27, 0.3) !important;
}

.meta-help-icon {
  animation: ring 1s ease-in-out infinite !important;
}

@keyframes ring {
  0%, 100% { transform: rotate(0deg); }
  10%, 30% { transform: rotate(-10deg); }
  20%, 40% { transform: rotate(10deg); }
  50% { transform: rotate(0deg); }
}

/* Status text */
.meta-status {
  font-size: var(--text-xs) !important;
  font-weight: 600 !important;
  padding: 4px 10px !important;
  border-radius: 20px !important;
  white-space: nowrap !important;
}

/* Status colors via data attribute or content */
.meta-status:empty::after {
  content: 'Sin estado';
}

/* =============================================
   PROGRESS SECTION - BARRA Y INPUT
   ============================================= */
.progress-wrapper {
  display: flex !important;
  gap: var(--space-4) !important;
  align-items: center !important;
  padding: var(--space-3) !important;
  background: var(--bg-secondary) !important;
  border-radius: 12px !important;
}

/* Progress bar track */
.progress-wrapper .progress {
  flex: 1 !important;
  height: 12px !important;
  background: var(--bg-tertiary) !important;
  border-radius: 6px !important;
  overflow: hidden !important;
  position: relative !important;
}

/* Progress bar fill */
.progress-wrapper .progress-bar {
  height: 100% !important;
  border-radius: 6px !important;
  background: linear-gradient(90deg, var(--color-accent), var(--color-accent-dark)) !important;
  transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
  position: relative !important;
}

/* Shimmer effect on progress bar */
.progress-wrapper .progress-bar::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255,255,255,0.4),
    transparent
  );
  animation: shimmer 2s infinite;
  background-size: 200% 100%;
}

@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Progress input group */
.progress-input-group {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-2) !important;
  flex-shrink: 0 !important;
}

.progress-label {
  font-size: var(--text-xs) !important;
  font-weight: 600 !important;
  color: var(--text-tertiary) !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  display: none !important; /* Ocultar label, mostrar solo el input */
}

/* Progress number input */
.meta-percent {
  width: 72px !important;
  padding: 10px 8px !important;
  border-radius: 10px !important;
  border: 2px solid var(--border-color) !important;
  text-align: center !important;
  font-weight: 700 !important;
  font-size: var(--text-lg) !important;
  background: var(--bg-primary) !important;
  transition: all var(--transition-fast) !important;
  -moz-appearance: textfield !important;
}

.meta-percent::-webkit-outer-spin-button,
.meta-percent::-webkit-inner-spin-button {
  -webkit-appearance: none !important;
  margin: 0 !important;
}

.meta-percent:hover {
  border-color: var(--color-accent) !important;
}

.meta-percent:focus {
  outline: none !important;
  border-color: var(--color-accent) !important;
  box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.15) !important;
}

/* Progress level colors */
.meta-percent[data-progress-level="low"] {
  border-color: var(--color-danger) !important;
  color: var(--color-danger) !important;
}

.meta-percent[data-progress-level="medium"] {
  border-color: var(--color-warning) !important;
  color: #b45309 !important;
}

.meta-percent[data-progress-level="high"] {
  border-color: var(--color-success) !important;
  color: #047857 !important;
}

.meta-percent[data-progress-level="complete"] {
  border-color: var(--color-success) !important;
  color: white !important;
  background: var(--color-success) !important;
}

.percent-symbol {
  font-size: var(--text-base) !important;
  font-weight: 700 !important;
  color: var(--text-tertiary) !important;
}

/* =============================================
   BOTONES DE ESTADO
   ============================================= */
.meta-item > div:last-child {
  display: flex !important;
  gap: var(--space-2) !important;
  flex-wrap: wrap !important;
  margin-top: var(--space-2) !important;
}

.meta-btn {
  padding: 8px 14px !important;
  border-radius: 10px !important;
  border: 2px solid var(--border-color) !important;
  background: var(--bg-primary) !important;
  color: var(--text-secondary) !important;
  font-size: var(--text-sm) !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  transition: all var(--transition-fast) !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
}

.meta-btn:hover {
  border-color: var(--color-accent) !important;
  background: #fff7ed !important;
  transform: translateY(-1px) !important;
}

.meta-btn:active {
  transform: translateY(0) !important;
}

/* Status button - Pausada */
.status-btn[data-status="pause"] {
  --btn-color: #6b7280;
}

.status-btn[data-status="pause"]::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--btn-color);
}

.status-btn[data-status="pause"][aria-pressed="true"] {
  background: #f3f4f6 !important;
  border-color: var(--btn-color) !important;
  color: var(--btn-color) !important;
}

/* Status button - En desarrollo */
.status-btn[data-status="dev"] {
  --btn-color: var(--color-info);
}

.status-btn[data-status="dev"]::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--btn-color);
}

.status-btn[data-status="dev"][aria-pressed="true"] {
  background: var(--color-info-bg) !important;
  border-color: var(--color-info) !important;
  color: #1d4ed8 !important;
}

/* Status button - Finalizada */
.status-btn[data-status="done"] {
  --btn-color: var(--color-success);
}

.status-btn[data-status="done"]::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--btn-color);
}

.status-btn[data-status="done"][aria-pressed="true"] {
  background: var(--color-success-bg) !important;
  border-color: var(--color-success) !important;
  color: #047857 !important;
}

/* Panic button */
.panic-btn {
  margin-left: auto !important;
  border-color: rgba(239, 127, 27, 0.3) !important;
  color: var(--color-accent) !important;
}

.panic-btn:hover {
  background: var(--color-accent) !important;
  color: white !important;
  border-color: var(--color-accent) !important;
}

.panic-btn[style*="background:#EF7F1B"],
.panic-btn[style*="background: #EF7F1B"] {
  background: var(--color-accent) !important;
  color: white !important;
  border-color: var(--color-accent) !important;
}

/* =============================================
   METAS DE EQUIPO - READ ONLY
   ============================================= */
#lista-metas-equipo .meta-item {
  cursor: default;
}

#lista-metas-equipo .meta-item:hover {
  transform: none;
}

/* Team meta status badges */
#lista-metas-equipo .meta-status {
  background: var(--bg-tertiary);
  color: var(--text-secondary);
}

/* =============================================
   HELP SECTION - SOLICITUDES DE AYUDA
   ============================================= */
#equipo-help-section {
  padding: var(--space-4) !important;
  border: 2px dashed var(--color-accent) !important;
  border-radius: 16px !important;
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%) !important;
  margin-top: var(--space-4) !important;
}

#equipo-help-section > div:first-child {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-2) !important;
  margin-bottom: var(--space-4) !important;
  padding-bottom: var(--space-3) !important;
  border-bottom: 1px solid rgba(239, 127, 27, 0.2) !important;
}

#equipo-help-section > div:first-child strong {
  font-size: var(--text-base) !important;
  color: var(--color-accent-dark) !important;
}

/* Help request items */
#help-list {
  display: flex !important;
  flex-direction: column !important;
  gap: var(--space-3) !important;
}

#help-list > div {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-3) !important;
  padding: var(--space-3) !important;
  background: var(--bg-primary) !important;
  border-radius: 12px !important;
  border: 1px solid rgba(239, 127, 27, 0.2) !important;
  animation: slideInHelp 0.3s ease forwards !important;
}

@keyframes slideInHelp {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

#help-list > div:nth-child(1) { animation-delay: 0s; }
#help-list > div:nth-child(2) { animation-delay: 0.1s; }
#help-list > div:nth-child(3) { animation-delay: 0.15s; }

/* Help request info */
#help-list > div > div:first-child {
  flex: 1 !important;
}

#help-list > div > div:first-child > div:first-child {
  font-weight: 600 !important;
  font-size: var(--text-sm) !important;
  color: var(--text-primary) !important;
  margin-bottom: 4px !important;
}

#help-list > div > div:first-child > div:last-child {
  font-size: var(--text-xs) !important;
  color: var(--text-tertiary) !important;
  display: flex !important;
  align-items: center !important;
  gap: var(--space-2) !important;
}

/* Name badge in help request */
#help-list span[style*="background:#EF7F1B"] {
  background: var(--color-accent) !important;
  padding: 2px 8px !important;
  border-radius: 20px !important;
  font-size: 10px !important;
  font-weight: 700 !important;
}

/* Help button */
.btn-help-teammate {
  padding: 8px 14px !important;
  background: var(--color-success) !important;
  color: white !important;
  border: none !important;
  border-radius: 10px !important;
  font-size: var(--text-sm) !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  transition: all var(--transition-fast) !important;
  white-space: nowrap !important;
}

.btn-help-teammate:hover {
  background: #059669 !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
}

/* =============================================
   MODAL DE AYUDA - MEJORADO
   ============================================= */
.modal-overlay {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  background: rgba(17, 24, 39, 0.6) !important;
  backdrop-filter: blur(4px) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  z-index: 9999 !important;
  animation: fadeIn 0.2s ease forwards !important;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  background: var(--bg-primary) !important;
  border-radius: 20px !important;
  padding: var(--space-6) !important;
  max-width: 480px !important;
  width: 90% !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
  animation: scaleIn 0.25s ease forwards !important;
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* Help option in modal */
.help-option {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-3) !important;
  padding: var(--space-4) !important;
  border: 2px solid var(--border-color) !important;
  border-radius: 12px !important;
  cursor: pointer !important;
  transition: all var(--transition-fast) !important;
  background: var(--bg-primary) !important;
  margin-bottom: var(--space-3) !important;
}

.help-option:hover {
  border-color: var(--color-accent) !important;
  background: #fff7ed !important;
  transform: translateX(4px) !important;
}

.help-option input[type="radio"] {
  width: 20px !important;
  height: 20px !important;
  accent-color: var(--color-accent) !important;
}

.help-option label {
  font-weight: 600 !important;
  color: var(--text-primary) !important;
  cursor: pointer !important;
}

/* =============================================
   TOAST NOTIFICATIONS
   ============================================= */
.toast-metas {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 14px 20px;
  border-radius: 12px;
  font-weight: 600;
  font-size: var(--text-sm);
  z-index: 10000;
  animation: toastSlideIn 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.toast-metas.success {
  background: var(--color-success);
  color: white;
}

.toast-metas.error {
  background: var(--color-danger);
  color: white;
}

.toast-metas.info {
  background: var(--color-info);
  color: white;
}

@keyframes toastSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* =============================================
   EMPTY STATES
   ============================================= */
.metas-empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-muted);
}

.metas-empty-state-icon {
  font-size: 48px;
  margin-bottom: var(--space-3);
  opacity: 0.5;
}

.metas-empty-state-text {
  font-size: var(--text-sm);
}

/* =============================================
   SKELETON LOADING
   ============================================= */
.meta-skeleton {
  padding: var(--space-4);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  margin-bottom: var(--space-3);
}

.meta-skeleton-line {
  height: 16px;
  background: linear-gradient(90deg, var(--bg-tertiary) 25%, var(--bg-secondary) 50%, var(--bg-tertiary) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
  margin-bottom: var(--space-2);
}

.meta-skeleton-line.title {
  width: 70%;
  height: 20px;
}

.meta-skeleton-line.progress {
  width: 100%;
  height: 12px;
  margin-top: var(--space-3);
}

.meta-skeleton-line.buttons {
  width: 60%;
  height: 36px;
  margin-top: var(--space-3);
}
`;

// Inyectar estilos si no existen
if (!document.getElementById('metas-ux-styles')) {
  const styleEl = document.createElement('style');
  styleEl.id = 'metas-ux-styles';
  styleEl.textContent = METAS_CSS_UPGRADE;
  document.head.appendChild(styleEl);
}


/* ==========================================================================
   PARTE 2: FUNCIONES JAVASCRIPT MEJORADAS
   ========================================================================== */

/**
 * Toast notification para metas
 */
function showMetasToast(message, type = 'success') {
  document.querySelectorAll('.toast-metas').forEach(t => t.remove());

  const toast = document.createElement('div');
  toast.className = `toast-metas ${type}`;
  toast.innerHTML = `
    ${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}
    <span>${message}</span>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}


/**
 * Actualizar progreso con feedback visual mejorado
 * REEMPLAZA la función updateProgress existente
 */
function updateProgress(elItem, pct) {
  pct = Math.max(0, Math.min(100, parseInt(pct) || 0));

  const bar = elItem.querySelector('.progress-bar');
  const progress = elItem.querySelector('.progress');
  const input = elItem.querySelector('.meta-percent');

  if (bar) {
    bar.style.width = pct + '%';
    // Cambiar color según nivel
    if (pct >= 100) {
      bar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
    } else if (pct >= 75) {
      bar.style.background = 'linear-gradient(90deg, #10b981, #34d399)';
    } else if (pct >= 40) {
      bar.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
    } else {
      bar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
    }
  }

  if (progress) {
    progress.setAttribute('aria-valuenow', pct);
  }

  if (input) {
    input.value = pct;
    // Actualizar nivel visual del input
    let level = 'low';
    if (pct >= 100) level = 'complete';
    else if (pct >= 75) level = 'high';
    else if (pct >= 40) level = 'medium';
    input.setAttribute('data-progress-level', level);
  }

  // Enviar al backend
  const metaId = elItem.dataset.metaId;
  if (metaId) {
    sendMetaAjax('update_progress', { meta_id: metaId, progress: pct })
      .then(() => {
        showMetasToast(`Progreso actualizado: ${pct}%`, 'success');
      })
      .catch(() => {
        showMetasToast('Error al actualizar progreso', 'error');
      });
  }
}


/**
 * Cambiar estado con feedback visual
 * REEMPLAZA la función setStatus existente
 */
function setStatus(elItem, status) {
  const buttons = elItem.querySelectorAll('.status-btn');
  const statusEl = elItem.querySelector('.meta-status');

  // Actualizar botones
  buttons.forEach(btn => {
    const isActive = btn.dataset.status === status;
    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });

  // Actualizar texto de status
  const statusText = {
    'pause': 'Pausada',
    'dev': 'En desarrollo',
    'done': 'Finalizada',
    'help': 'Solicita ayuda'
  };

  if (statusEl) {
    statusEl.textContent = statusText[status] || status;
    // Actualizar color del badge
    statusEl.style.background =
      status === 'done' ? 'var(--color-success-bg)' :
      status === 'dev' ? 'var(--color-info-bg)' :
      status === 'help' ? 'var(--color-warning-bg)' : 'var(--bg-tertiary)';
    statusEl.style.color =
      status === 'done' ? '#047857' :
      status === 'dev' ? '#1d4ed8' :
      status === 'help' ? '#b45309' : 'var(--text-secondary)';
  }

  // Enviar al backend
  const metaId = elItem.dataset.metaId;
  if (metaId) {
    sendMetaAjax('update_status', { meta_id: metaId, status: status })
      .then(() => {
        showMetasToast(`Estado cambiado: ${statusText[status]}`, 'success');
      })
      .catch(() => {
        showMetasToast('Error al cambiar estado', 'error');
      });
  }
}


/**
 * AJAX helper para metas
 */
async function sendMetaAjax(action, data) {
  const formData = new FormData();
  formData.append('action', action);
  Object.keys(data).forEach(key => formData.append(key, data[key]));

  const response = await fetch('ajax_metas.php', {
    method: 'POST',
    body: formData
  });

  if (!response.ok) throw new Error('Network error');

  const result = await response.json();
  if (result.error) throw new Error(result.error);

  return result;
}


/**
 * Modal de ayuda mejorado
 * REEMPLAZA createHelpModal existente
 */
function createHelpModal(metaId, teammates, liElement) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = 'help-modal-' + metaId;

  const teammatesOptions = teammates.map(t => `
    <option value="${t.id}">${t.nombre_persona}</option>
  `).join('');

  overlay.innerHTML = `
    <div class="modal-content">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--bg-tertiary);">
        <span style="font-size:32px;">🎉</span>
        <div>
          <h3 style="margin:0;font-size:18px;font-weight:800;color:var(--text-primary);">¡Meta completada!</h3>
          <p style="margin:4px 0 0;font-size:13px;color:var(--text-tertiary);">¿Cómo lograste esta meta?</p>
        </div>
      </div>

      <div style="margin-bottom:24px;">
        <label class="help-option">
          <input type="radio" name="help-type-${metaId}" value="solo" checked>
          <div>
            <div style="font-weight:600;">La completé solo/a</div>
            <div style="font-size:12px;color:var(--text-muted);">Sin ayuda de compañeros</div>
          </div>
        </label>

        <label class="help-option">
          <input type="radio" name="help-type-${metaId}" value="teammate">
          <div>
            <div style="font-weight:600;">Recibí ayuda de un compañero</div>
            <div style="font-size:12px;color:var(--text-muted);">Selecciona quién te ayudó</div>
          </div>
        </label>

        <div id="teammate-select-${metaId}" class="teammate-select-wrapper" style="display:none;margin-top:12px;padding:12px;background:var(--bg-secondary);border-radius:10px;">
          <label style="display:block;font-size:11px;font-weight:600;color:var(--text-tertiary);margin-bottom:6px;text-transform:uppercase;">
            Compañero que ayudó
          </label>
          <select id="teammate-dropdown-${metaId}" style="width:100%;padding:10px;border-radius:8px;border:2px solid var(--border-color);font-size:14px;">
            <option value="">Selecciona...</option>
            ${teammatesOptions}
          </select>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <button onclick="closeHelpModal(${metaId})" style="
          padding:10px 20px;
          border-radius:10px;
          font-size:14px;
          font-weight:600;
          cursor:pointer;
          border:none;
          background:var(--bg-tertiary);
          color:var(--text-secondary);
        ">Cancelar</button>
        <button onclick="confirmHelp(${metaId}, this)" style="
          padding:10px 20px;
          border-radius:10px;
          font-size:14px;
          font-weight:600;
          cursor:pointer;
          border:none;
          background:linear-gradient(135deg, var(--color-accent), var(--color-accent-dark));
          color:white;
        ">Confirmar</button>
      </div>
    </div>
  `;

  // Event listeners para radio buttons
  overlay.querySelectorAll(`input[name="help-type-${metaId}"]`).forEach(radio => {
    radio.addEventListener('change', () => {
      const selectWrapper = document.getElementById(`teammate-select-${metaId}`);
      if (radio.value === 'teammate' && radio.checked) {
        selectWrapper.style.display = 'block';
      } else {
        selectWrapper.style.display = 'none';
      }
    });
  });

  // Cerrar con click fuera
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeHelpModal(metaId);
  });

  // Cerrar con ESC
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeHelpModal(metaId);
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
  return overlay;
}

window.closeHelpModal = function(metaId) {
  const modal = document.getElementById('help-modal-' + metaId);
  if (modal) {
    modal.style.opacity = '0';
    setTimeout(() => modal.remove(), 200);
  }
};

window.confirmHelp = async function(metaId, btn) {
  const modal = document.getElementById('help-modal-' + metaId);
  const helpType = modal.querySelector(`input[name="help-type-${metaId}"]:checked`).value;
  const teammateId = document.getElementById(`teammate-dropdown-${metaId}`)?.value;

  if (helpType === 'teammate' && !teammateId) {
    showMetasToast('Selecciona un compañero', 'error');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    await sendMetaAjax('complete_with_help', {
      meta_id: metaId,
      help_type: helpType,
      teammate_id: teammateId || ''
    });

    closeHelpModal(metaId);
    showMetasToast('¡Meta completada exitosamente!', 'success');

    // Recargar página después de 1 segundo
    setTimeout(() => location.reload(), 1000);

  } catch (err) {
    showMetasToast('Error al guardar', 'error');
    btn.disabled = false;
    btn.textContent = 'Confirmar';
  }
};


/**
 * Inicializar mejoras de metas
 * Llamar esto al cargar la página
 */
function initMetasUpgrade() {
  // Inyectar estilos si no existen
  if (!document.getElementById('metas-ux-styles')) {
    const styleEl = document.createElement('style');
    styleEl.id = 'metas-ux-styles';
    styleEl.textContent = METAS_CSS_UPGRADE;
    document.head.appendChild(styleEl);
  }

  console.log('✅ Metas UX/UI upgrade initialized');
}

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMetasUpgrade);
} else {
  initMetasUpgrade();
}
