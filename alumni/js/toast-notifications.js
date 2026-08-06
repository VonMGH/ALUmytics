/**
 * Modern Toast Notification System
 * Replaces default alert() with beautiful, non-blocking notifications
 */

const ToastNotification = {
    container: null,
    
    // Initialize the toast container
    init: function() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    // Show a toast notification
    show: function(message, type = 'info', duration = 5000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Icon based on type
        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm-2 15l-5-5 1.41-1.41L8 12.17l7.59-7.59L17 6l-9 9z" fill="currentColor"/></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z" fill="currentColor"/></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M1 17h18L10 1 1 17zm10-2H9v-2h2v2zm0-4H9V7h2v4z" fill="currentColor"/></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm1 15H9V9h2v6zm0-8H9V5h2v2z" fill="currentColor"/></svg>'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        `;
        
        this.container.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('toast-show'), 10);
        
        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('toast-show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    },
    
    // Convenience methods
    success: function(message, duration = 5000) {
        return this.show(message, 'success', duration);
    },
    
    error: function(message, duration = 7000) {
        return this.show(message, 'error', duration);
    },
    
    warning: function(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    },
    
    info: function(message, duration = 5000) {
        return this.show(message, 'info', duration);
    },
    
    // Show confirmation dialog (replacement for confirm())
    confirm: function(message, onConfirm, onCancel) {
        this.init();
        
        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        
        const dialog = document.createElement('div');
        dialog.className = 'toast-dialog';
        dialog.innerHTML = `
            <div class="toast-dialog-icon">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                    <path d="M24 4C12.96 4 4 12.96 4 24s8.96 20 20 20 20-8.96 20-20S35.04 4 24 4zm2 30h-4v-4h4v4zm0-8h-4V14h4v12z" fill="currentColor"/>
                </svg>
            </div>
            <div class="toast-dialog-message">${message}</div>
            <div class="toast-dialog-actions">
                <button class="toast-btn toast-btn-cancel">Cancel</button>
                <button class="toast-btn toast-btn-confirm">Confirm</button>
            </div>
        `;
        
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            overlay.classList.add('toast-overlay-show');
            dialog.classList.add('toast-dialog-show');
        }, 10);
        
        const remove = () => {
            overlay.classList.remove('toast-overlay-show');
            dialog.classList.remove('toast-dialog-show');
            setTimeout(() => overlay.remove(), 300);
        };
        
        dialog.querySelector('.toast-btn-confirm').onclick = () => {
            remove();
            if (onConfirm) onConfirm();
        };
        
        dialog.querySelector('.toast-btn-cancel').onclick = () => {
            remove();
            if (onCancel) onCancel();
        };
        
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                remove();
                if (onCancel) onCancel();
            }
        };
    }
};

// Make it globally accessible
window.Toast = ToastNotification;

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ToastNotification.init());
} else {
    ToastNotification.init();
}
