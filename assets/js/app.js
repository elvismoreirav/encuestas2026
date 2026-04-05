window.ShalomApp = {
    init() {
        if (window.lucide) {
            window.lucide.createIcons();
        }

        this.flushQueuedToast();

        document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const target = document.getElementById(trigger.dataset.openModal);
                target?.classList.add('open');
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                trigger.closest('.modal')?.classList.remove('open');
            });
        });

        document.querySelectorAll('.modal').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.remove('open');
                }
            });
        });
    },

    ensureToastRegion() {
        let region = document.getElementById('adminToastRegion');
        if (!region) {
            region = document.createElement('div');
            region.id = 'adminToastRegion';
            region.className = 'admin-toast-region';
            document.body.appendChild(region);
        }

        return region;
    },

    escapeHtml(value = '') {
        return String(value).replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character] || character));
    },

    queueToast(payload = {}) {
        try {
            window.sessionStorage.setItem('shalom:toast', JSON.stringify(payload));
        } catch (error) {
            // Ignore storage issues and continue without persisted feedback.
        }
    },

    flushQueuedToast() {
        try {
            const rawPayload = window.sessionStorage.getItem('shalom:toast');
            if (!rawPayload) {
                return;
            }

            window.sessionStorage.removeItem('shalom:toast');
            const payload = JSON.parse(rawPayload);
            this.notify(payload.type, payload.title, payload.message);
        } catch (error) {
            window.sessionStorage.removeItem('shalom:toast');
        }
    },

    notify(type = 'info', title = '', message = '') {
        const region = this.ensureToastRegion();
        const icons = {
            success: '✓',
            error: '!',
            warning: '!',
            info: 'i',
        };

        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        toast.innerHTML = `
            <div class="admin-toast-icon">${icons[type] || 'i'}</div>
            <div class="admin-toast-copy">
                ${title ? `<strong>${this.escapeHtml(title)}</strong>` : ''}
                ${message ? `<p>${this.escapeHtml(message)}</p>` : ''}
            </div>
            <button class="admin-toast-close" type="button" aria-label="Cerrar">×</button>
        `;

        const removeToast = () => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 180);
        };

        toast.querySelector('.admin-toast-close')?.addEventListener('click', removeToast);
        region.appendChild(toast);
        window.setTimeout(removeToast, 4200);
    },

    confirm(options = {}) {
        const {
            title = 'Confirmar acción',
            message = '¿Desea continuar?',
            confirmText = 'Continuar',
            cancelText = 'Cancelar',
            confirmClass = 'btn btn-primary',
        } = options;

        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal open';
            modal.innerHTML = `
                <div class="modal-dialog admin-confirm-dialog" role="dialog" aria-modal="true" aria-label="${this.escapeHtml(title)}">
                    <div class="modal-header">
                        <div class="modal-copy">
                            <h3>${this.escapeHtml(title)}</h3>
                        </div>
                        <button class="btn btn-secondary" type="button" data-confirm-close>Cerrar</button>
                    </div>
                    <div class="modal-body admin-confirm-body">
                        <p>${this.escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer admin-confirm-footer">
                        <button class="btn btn-secondary" type="button" data-confirm-close>${this.escapeHtml(cancelText)}</button>
                        <button class="${confirmClass}" type="button" data-confirm-accept data-confirm-autofocus>${this.escapeHtml(confirmText)}</button>
                    </div>
                </div>
            `;

            const cleanup = (value) => {
                window.removeEventListener('keydown', onKeyDown);
                modal.classList.remove('open');
                window.setTimeout(() => modal.remove(), 160);
                resolve(value);
            };

            const onKeyDown = (event) => {
                if (event.key === 'Escape') {
                    cleanup(false);
                }
            };

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    cleanup(false);
                }
            });

            modal.querySelectorAll('[data-confirm-close]').forEach((button) => {
                button.addEventListener('click', () => cleanup(false));
            });

            modal.querySelector('[data-confirm-accept]')?.addEventListener('click', () => cleanup(true));

            document.body.appendChild(modal);
            window.addEventListener('keydown', onKeyDown);
            modal.querySelector('[data-confirm-autofocus]')?.focus();
        });
    },

    chipForStatus(status) {
        if (status === 'active' || status === 'closing_soon') {
            return 'chip chip-success';
        }

        if (status === 'scheduled') {
            return 'chip chip-warning';
        }

        if (status === 'closed') {
            return 'chip chip-danger';
        }

        return 'chip chip-muted';
    },

    formatDateTime(value) {
        if (!value) {
            return 'Sin fecha';
        }

        return new Date(value.replace(' ', 'T')).toLocaleString('es-EC', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    },
};

document.addEventListener('DOMContentLoaded', () => window.ShalomApp.init());
