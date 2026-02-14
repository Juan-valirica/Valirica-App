<!-- ========================================================================== -->
<!-- SISTEMA DE TOAST NOTIFICATIONS PARA EMPLEADOR -->
<!-- Notifica en tiempo real cuando llega una nueva solicitud de permiso/vacación -->
<!-- ========================================================================== -->

<style>
/* Toast Container */
#toastContainer {
    position: fixed;
    top: 80px;
    right: 24px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
}

/* Toast Notification */
.toast-notification {
    background: white;
    border-left: 4px solid #FFB020;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15), 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 320px;
    max-width: 400px;
    pointer-events: auto;
    animation: slideInRight 0.3s ease-out;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.toast-notification.success {
    border-left-color: #10B981;
}

.toast-notification.error {
    border-left-color: #EF4444;
}

.toast-notification.info {
    border-left-color: #3B82F6;
}

.toast-notification.warning {
    border-left-color: #FFB020;
}

/* Toast Icon */
.toast-icon {
    font-size: 24px;
    flex-shrink: 0;
    line-height: 1;
}

/* Toast Content */
.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-title {
    font-size: 14px;
    font-weight: 700;
    color: #1F2937;
    margin: 0 0 4px 0;
}

.toast-message {
    font-size: 13px;
    color: #6B7280;
    margin: 0;
    line-height: 1.4;
}

/* Toast Actions */
.toast-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.toast-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.toast-btn-primary {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
}

.toast-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.toast-btn-secondary {
    background: #F3F4F6;
    color: #6B7280;
}

.toast-btn-secondary:hover {
    background: #E5E7EB;
}

/* Toast Close Button */
.toast-close {
    background: none;
    border: none;
    color: #9CA3AF;
    font-size: 20px;
    font-weight: 300;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: color 0.2s;
}

.toast-close:hover {
    color: #EF4444;
}

/* Animations */
@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.toast-notification.removing {
    animation: slideOutRight 0.2s ease-in forwards;
}

/* Progress Bar */
.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #FFB020, #EF7F1B);
    border-radius: 0 0 12px 12px;
    transform-origin: left;
    animation: progressBar 8s linear forwards;
}

@keyframes progressBar {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}
</style>

<!-- Toast Container -->
<div id="toastContainer"></div>

<script>
// ========================================================================
// SISTEMA DE TOAST NOTIFICATIONS
// ========================================================================

const ToastManager = {
    container: null,
    lastCheckTime: Date.now(),
    checkInterval: 15000, // Check every 15 seconds
    currentCount: <?= $solicitudes_pendientes_count ?? 0 ?>,

    init() {
        this.container = document.getElementById('toastContainer');
        // Start polling for new requests
        this.startPolling();
    },

    startPolling() {
        // Check immediately
        setTimeout(() => this.checkForNewRequests(), 2000);

        // Then check periodically
        setInterval(() => this.checkForNewRequests(), this.checkInterval);
    },

    async checkForNewRequests() {
        try {
            const response = await fetch('permisos_vacaciones_backend.php?action=obtener_solicitudes_pendientes_count');
            const data = await response.json();

            if (data.success) {
                const newCount = data.count;

                // Si hay más solicitudes que antes, mostrar toast
                if (newCount > this.currentCount) {
                    const diff = newCount - this.currentCount;
                    this.showToast({
                        type: 'warning',
                        title: diff === 1 ? 'Nueva solicitud recibida' : `${diff} nuevas solicitudes recibidas`,
                        message: diff === 1
                            ? 'Un empleado ha solicitado un permiso o vacaciones'
                            : `Tienes ${diff} nuevas solicitudes de permisos/vacaciones`,
                        actions: [
                            {
                                label: 'Ver ahora',
                                class: 'toast-btn-primary',
                                onClick: () => {
                                    if (window.location.search.includes('tab=time')) {
                                        window.location.reload();
                                    } else {
                                        window.location.href = '?tab=time';
                                    }
                                }
                            }
                        ]
                    });

                    // Update badge
                    this.updateBadge(newCount);
                }

                this.currentCount = newCount;
            }
        } catch (error) {
            console.error('Error checking for new requests:', error);
        }
    },

    updateBadge(count) {
        const badge = document.getElementById('solicitudesPendientesBadge');
        if (badge) {
            badge.textContent = count > 9 ? '9+' : count;
            if (count === 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = 'flex';
            }
        } else if (count > 0) {
            // Create badge if it doesn't exist
            const timeTab = document.querySelector('a[href="?tab=time"]');
            if (timeTab && !timeTab.querySelector('#solicitudesPendientesBadge')) {
                const newBadge = document.createElement('span');
                newBadge.id = 'solicitudesPendientesBadge';
                newBadge.textContent = count > 9 ? '9+' : count;
                newBadge.style.cssText = `
                    position: absolute;
                    top: 8px;
                    right: -8px;
                    background: linear-gradient(135deg, #EF4444, #DC2626);
                    color: white;
                    font-size: 10px;
                    font-weight: 700;
                    min-width: 18px;
                    height: 18px;
                    border-radius: 9px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 5px;
                    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
                    animation: pulse-badge 2s ease-in-out infinite;
                `;
                timeTab.style.position = 'relative';
                timeTab.appendChild(newBadge);
            }
        }
    },

    showToast({ type = 'info', title, message, duration = 8000, actions = [] }) {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;

        const icons = {
            success: '✅',
            error: '❌',
            warning: '🔔',
            info: 'ℹ️'
        };

        let actionsHTML = '';
        if (actions.length > 0) {
            actionsHTML = '<div class="toast-actions">';
            actions.forEach((action, index) => {
                actionsHTML += `<button class="toast-btn ${action.class || 'toast-btn-secondary'}" data-action="${index}">${action.label}</button>`;
            });
            actionsHTML += '</div>';
        }

        toast.innerHTML = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
                ${actionsHTML}
            </div>
            <button class="toast-close" onclick="ToastManager.removeToast(this.parentElement)">×</button>
            <div class="toast-progress"></div>
        `;

        // Add event listeners to action buttons
        if (actions.length > 0) {
            setTimeout(() => {
                actions.forEach((action, index) => {
                    const btn = toast.querySelector(`[data-action="${index}"]`);
                    if (btn && action.onClick) {
                        btn.addEventListener('click', () => {
                            action.onClick();
                            this.removeToast(toast);
                        });
                    }
                });
            }, 100);
        }

        this.container.appendChild(toast);

        // Auto remove after duration
        setTimeout(() => this.removeToast(toast), duration);

        return toast;
    },

    removeToast(toast) {
        if (!toast || !toast.parentElement) return;

        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 200);
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    ToastManager.init();
});
</script>
