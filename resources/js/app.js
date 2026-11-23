import './bootstrap';

document.documentElement.classList.replace('no-js', 'js');

const appShell = document.querySelector('[data-app-shell]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

function setSidebarOpen(isOpen) {
    if (!appShell || !sidebarToggle) {
        return;
    }

    appShell.classList.toggle('sidebar-open', isOpen);
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('menu-open', isOpen);

    if (isOpen) {
        appShell.querySelector('.app-sidebar a[href]')?.focus();
    } else {
        sidebarToggle.focus();
    }
}

sidebarToggle?.addEventListener('click', () => {
    setSidebarOpen(sidebarToggle.getAttribute('aria-expanded') !== 'true');
});

document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => {
    setSidebarOpen(false);
});

appShell?.querySelectorAll('.app-sidebar a[href]').forEach((link) => {
    link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) {
            setSidebarOpen(false);
        }
    });
});

const menuToggles = document.querySelectorAll('[data-menu-toggle]');

menuToggles.forEach((toggle) => {
    const menu = document.getElementById(toggle.dataset.menuToggle);

    if (!menu) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        menu.classList.toggle('is-open', !isOpen);
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            toggle.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
        });
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (appShell?.classList.contains('sidebar-open')) {
            setSidebarOpen(false);
        }

        menuToggles.forEach((toggle) => {
            const menu = document.getElementById(toggle.dataset.menuToggle);
            toggle.setAttribute('aria-expanded', 'false');
            menu?.classList.remove('is-open');
        });
    }
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const modalTrigger = event.target.closest('[data-modal-open]');
    if (modalTrigger) {
        const modal = document.getElementById(modalTrigger.dataset.modalOpen);
        if (modal instanceof HTMLDialogElement && !modal.open) {
            modal.showModal();
        }
    }

    const closeTrigger = event.target.closest('[data-modal-close]');
    if (closeTrigger) {
        closeTrigger.closest('dialog')?.close();
    }
});

document.querySelectorAll('dialog.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.close();
        }
    });
});

const toastRegion = document.getElementById('toast-region');

function showNotification(title, message) {
    if (!toastRegion) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');

    const mark = document.createElement('span');
    mark.className = 'toast-mark';
    mark.setAttribute('aria-hidden', 'true');
    mark.textContent = 'i';

    const copy = document.createElement('div');
    copy.className = 'toast-copy';

    const heading = document.createElement('strong');
    heading.textContent = title;

    const body = document.createElement('p');
    body.textContent = message;
    copy.append(heading, body);

    const close = document.createElement('button');
    close.className = 'toast-close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = 'x';
    close.addEventListener('click', () => toast.remove());

    toast.append(mark, copy, close);
    toastRegion.append(toast);
    window.setTimeout(() => toast.remove(), 5000);
}

const agentApp = document.querySelector('[data-agent-app]');
const agentHistoryToggle = agentApp?.querySelector('[data-agent-history-toggle]');
const agentHistoryCloseButtons = agentApp?.querySelectorAll('[data-agent-history-close]');
const agentMobileLayout = window.matchMedia('(max-width: 760px)');

function setAgentHistoryOpen(isOpen) {
    if (!agentApp || !agentHistoryToggle) {
        return;
    }

    if (agentMobileLayout.matches) {
        agentApp.classList.toggle('agent-history-open', isOpen);
    } else {
        agentApp.classList.toggle('agent-history-collapsed', !isOpen);
    }

    agentHistoryToggle.setAttribute('aria-expanded', String(isOpen));
}

if (agentApp && agentHistoryToggle) {
    agentHistoryToggle.setAttribute('aria-expanded', String(!agentMobileLayout.matches));
    agentHistoryToggle.addEventListener('click', () => {
        setAgentHistoryOpen(agentHistoryToggle.getAttribute('aria-expanded') !== 'true');
    });
    agentHistoryCloseButtons?.forEach((button) => {
        button.addEventListener('click', () => setAgentHistoryOpen(false));
    });
    agentMobileLayout.addEventListener('change', (event) => {
        agentApp.classList.remove('agent-history-open', 'agent-history-collapsed');
        agentHistoryToggle.setAttribute('aria-expanded', String(!event.matches));
    });
}

const agentInput = agentApp?.querySelector('[data-agent-input]');
const agentModelSelect = agentApp?.querySelector('[data-agent-model]');
const agentModelStatus = agentApp?.querySelector('[data-agent-model-status]');
const agentModelCurrent = agentApp?.querySelector('[data-agent-current-model]');
const agentSendButton = agentApp?.querySelector('.agent-send-button');
let agentModelSave = Promise.resolve();

if (agentModelSelect instanceof HTMLSelectElement && agentInput instanceof HTMLTextAreaElement) {
    agentModelSelect.addEventListener('change', () => {
        const selectedModel = agentModelSelect.value;
        const previousModel = agentModelSelect.dataset.savedModel ?? selectedModel;
        const token = agentInput.form?.querySelector('input[name="_token"]');
        const updateUrl = agentModelSelect.dataset.updateUrl;

        if (!(token instanceof HTMLInputElement) || !updateUrl) {
            agentModelSelect.value = previousModel;
            if (agentModelStatus) {
                agentModelStatus.textContent = 'Could not save this model selection. Reload and try again.';
            }
            return;
        }

        agentModelSelect.dataset.saving = 'true';
        agentModelSelect.disabled = true;
        if (agentSendButton instanceof HTMLButtonElement) {
            agentSendButton.disabled = true;
        }
        if (agentModelStatus) {
            agentModelStatus.textContent = 'Checking availability and saving…';
        }

        agentModelSave = (async () => {
            try {
                const response = await fetch(updateUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token.value,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ model: selectedModel }),
                    credentials: 'same-origin',
                });
                let result;
                try {
                    result = await response.json();
                } catch {
                    throw new Error('The model service returned an unreadable response.');
                }

                if (!response.ok || result.model !== selectedModel || typeof result.label !== 'string') {
                    throw new Error(typeof result.message === 'string'
                        ? result.message
                        : 'This model could not be selected.');
                }

                agentModelSelect.dataset.savedModel = selectedModel;
                if (agentModelCurrent) {
                    agentModelCurrent.textContent = result.label;
                }
                if (agentModelStatus) {
                    agentModelStatus.textContent = `Saved · ${result.label}`;
                }
            } catch (error) {
                agentModelSelect.value = previousModel;
                const message = error instanceof Error
                    ? error.message
                    : 'This model could not be selected.';
                if (agentModelStatus) {
                    agentModelStatus.textContent = message;
                }
                showNotification('Model was not changed', message);
            } finally {
                delete agentModelSelect.dataset.saving;
                agentModelSelect.disabled = false;
                if (agentSendButton instanceof HTMLButtonElement) {
                    agentSendButton.disabled = agentSendButton.dataset.sendAllowed !== 'true';
                }
            }
        })();
    });
}

function resizeAgentInput() {
    if (!agentInput) {
        return;
    }

    agentInput.style.height = 'auto';
    agentInput.style.height = `${Math.min(agentInput.scrollHeight, 180)}px`;
}

if (agentInput) {
    resizeAgentInput();
    agentInput.addEventListener('input', resizeAgentInput);
    agentInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
            return;
        }

        event.preventDefault();
        agentInput.form?.requestSubmit();
    });

    agentApp.querySelectorAll('[data-agent-suggestion]').forEach((suggestion) => {
        suggestion.addEventListener('click', () => {
            if (!(suggestion instanceof HTMLButtonElement) || agentInput.disabled) {
                return;
            }

            agentInput.value = suggestion.dataset.agentSuggestion ?? '';
            resizeAgentInput();
            agentInput.focus();
        });
    });

    agentApp.querySelectorAll('[data-agent-retry]').forEach((retry) => {
        retry.addEventListener('click', () => {
            if (!(retry instanceof HTMLButtonElement) || agentInput.disabled) {
                return;
            }

            agentInput.value = retry.dataset.agentRetry ?? '';
            resizeAgentInput();
            const consent = agentApp.querySelector('[name="provider_disclosure_confirmed"]');
            if (consent instanceof HTMLInputElement) {
                consent.checked = false;
            }
            agentInput.focus();
            showNotification('Review before retrying', 'Confirm the provider disclosure before resending your question.');
        });
    });

    agentInput.form?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement) || form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        if (agentModelSelect?.dataset.saving === 'true') {
            event.preventDefault();
            agentModelSave.then(() => form.requestSubmit());
            return;
        }

        form.dataset.submitting = 'true';
        const sendButton = form.querySelector('button[type="submit"]');
        const sendLabel = form.querySelector('.agent-send-label');
        if (sendButton instanceof HTMLButtonElement) {
            sendButton.disabled = true;
        }
        if (sendLabel) {
            sendLabel.textContent = 'Sending…';
        }

        const thinking = agentApp.querySelector('[data-agent-thinking]');
        if (thinking instanceof HTMLElement) {
            thinking.hidden = false;
        }

        const transcript = agentApp.querySelector('[data-agent-transcript]');
        transcript?.scrollTo({ top: transcript.scrollHeight, behavior: 'smooth' });
    });
}

agentApp?.querySelector('[data-agent-latest]')?.scrollIntoView({ block: 'end' });
agentApp?.querySelectorAll('[data-agent-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const content = document.getElementById(button.dataset.agentCopy ?? '');
        if (!content) {
            showNotification('Could not copy response', 'The response content was not available.');
            return;
        }

        try {
            await navigator.clipboard.writeText(content.innerText);
            showNotification('Response copied', 'The AI response was copied to your clipboard.');
        } catch {
            showNotification('Could not copy response', 'Clipboard access is unavailable in this browser context.');
        }
    });
});

document.querySelectorAll('[data-toast-demo]').forEach((button) => {
    button.addEventListener('click', () => {
        showNotification('Interface preview', 'Notifications are ready for future platform workflows.');
    });
});

document.querySelector('[data-print-report]')?.addEventListener('click', () => {
    window.print();
});

const healthLoading = document.getElementById('database-health-loading');
const healthReady = document.getElementById('database-health-ready');
const healthFailed = document.getElementById('database-health-failed');
const healthAnnouncement = document.getElementById('database-health-announcement');

if (healthLoading && healthReady && healthFailed && healthAnnouncement) {
    fetch('/health/ready', {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
        credentials: 'same-origin',
    })
        .then(async (response) => ({
            ok: response.ok,
            body: await response.json(),
        }))
        .then(({ ok, body }) => {
            healthLoading.hidden = true;

            if (!ok || body?.checks?.database !== 'ok') {
                healthFailed.hidden = false;
                healthAnnouncement.textContent = 'Database unavailable';
                return;
            }

            healthReady.hidden = false;
            healthAnnouncement.textContent = 'Database connected';
        })
        .catch(() => {
            healthLoading.hidden = true;
            healthFailed.hidden = false;
            healthAnnouncement.textContent = 'Database unavailable';
        });
}
