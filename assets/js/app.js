// Base App JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Burger menu
    const burger = document.querySelector('.navbar-burger');
    const menu = document.querySelector('.navbar-menu');
    
    if (burger && menu) {
        burger.addEventListener('click', () => {
            burger.classList.toggle('is-active');
            menu.classList.toggle('is-active');
        });
    }
    
    // Flash messages
    const flashMessages = document.querySelectorAll('.notification .delete');
    flashMessages.forEach(button => {
        button.addEventListener('click', () => {
            button.parentNode.remove();
        });
    });
    
    // Copy buttons
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const text = button.getAttribute('data-copy');
            try {
                await navigator.clipboard.writeText(text);
                showNotification('In Zwischenablage kopiert!', 'is-success');
            } catch (err) {
                showNotification('Kopieren fehlgeschlagen', 'is-danger');
            }
        });
    });

    // Inline editor toggle (experimental)
    const inlineToggle = document.querySelector('#inline-editor-toggle');
    const inlineStorageKey = 'inlineEditorEnabled';

    const applyInlineState = (enabled) => {
        document.body.classList.toggle('inline-editor-enabled', enabled);
        if (inlineToggle) {
            inlineToggle.checked = enabled;
        }
        document.querySelectorAll('.js-inline-toggle').forEach((button) => {
            button.disabled = !enabled;
            if (!enabled) {
                button.classList.add('is-light');
            } else {
                button.classList.remove('is-light');
            }
        });
    };

    if (inlineToggle) {
        let initial = false;
        try {
            initial = localStorage.getItem(inlineStorageKey) === 'true';
        } catch (e) {
            initial = false;
        }
        applyInlineState(initial);

        inlineToggle.addEventListener('change', () => {
            const enabled = inlineToggle.checked;
            applyInlineState(enabled);
            try {
                localStorage.setItem(inlineStorageKey, enabled ? 'true' : 'false');
            } catch (e) {
                /* ignore persistence errors */
            }
            showNotification(`Inline-Editor ${enabled ? 'aktiviert' : 'deaktiviert'}`, 'is-info', 2000);
        });
    } else {
        applyInlineState(false);
    }

    // API Requests
    window.api = {
        async request(endpoint, data = {}) {
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
                
            } catch (error) {
                showNotification(
                    'Ein Fehler ist aufgetreten: ' + error.message,
                    'is-danger'
                );
                throw error;
            }
        }
    };
});

// Utility Functions
function showNotification(message, type = 'is-info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '1rem';
    notification.style.right = '1rem';
    notification.style.zIndex = '9999';
    
    const deleteButton = document.createElement('button');
    deleteButton.className = 'delete';
    deleteButton.addEventListener('click', () => notification.remove());
    
    notification.appendChild(deleteButton);
    notification.appendChild(document.createTextNode(message));
    
    document.body.appendChild(notification);
    
    if (duration > 0) {
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
}

function confirmDialog(message) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal is-active';
        modal.innerHTML = `
            <div class="modal-background"></div>
            <div class="modal-content">
                <div class="box">
                    <p class="mb-4">${message}</p>
                    <div class="buttons is-right">
                        <button class="button" data-action="cancel">Abbrechen</button>
                        <button class="button is-danger" data-action="confirm">Bestätigen</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            modal.remove();
            resolve(true);
        });
        
        const cancelAction = () => {
            modal.remove();
            resolve(false);
        };
        
        modal.querySelector('[data-action="cancel"]').addEventListener('click', cancelAction);
        modal.querySelector('.modal-background').addEventListener('click', cancelAction);
    });
}

// Form Validation
function validateForm(form, rules = {}) {
    const errors = [];
    
    for (const [field, rule] of Object.entries(rules)) {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) continue;
        
        const value = input.value.trim();
        
        if (rule.required && !value) {
            errors.push(`${rule.label || field} ist erforderlich`);
            input.classList.add('is-danger');
        }
        
        if (rule.pattern && !rule.pattern.test(value)) {
            errors.push(`${rule.label || field} hat ein ungültiges Format`);
            input.classList.add('is-danger');
        }
        
        if (rule.minLength && value.length < rule.minLength) {
            errors.push(
                `${rule.label || field} muss mindestens ${rule.minLength} Zeichen lang sein`
            );
            input.classList.add('is-danger');
        }
    }
    
    return errors;
}
