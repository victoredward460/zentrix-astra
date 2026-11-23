const installButtons = [...document.querySelectorAll('[data-pwa-install]')];
const installDialog = document.getElementById('pwa-install-help');
const iosInstructions = installDialog?.querySelector('[data-pwa-ios]');
const androidInstructions = installDialog?.querySelector('[data-pwa-android]');
const desktopInstructions = installDialog?.querySelector('[data-pwa-desktop]');
let installPrompt = null;

function isAppInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
}

const standaloneMode = window.matchMedia('(display-mode: standalone)');
standaloneMode.addEventListener?.('change', () => updateInstallButtons(isAppInstalled()));

function updateInstallButtons(installed) {
    installButtons.forEach((button) => {
        button.textContent = installed ? 'Installed' : 'Install App';
        button.disabled = installed;
        button.setAttribute('aria-label', installed ? 'ZENTRIX ASTRA is installed' : 'Install ZENTRIX ASTRA');
    });
}

function showInstallHelp() {
    if (!(installDialog instanceof HTMLDialogElement) || installDialog.open) {
        return;
    }

    const userAgent = navigator.userAgent;
    const isIos = /iphone|ipad|ipod/i.test(userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /android/i.test(userAgent);

    if (iosInstructions) iosInstructions.hidden = !isIos;
    if (androidInstructions) androidInstructions.hidden = !isAndroid;
    if (desktopInstructions) desktopInstructions.hidden = isIos || isAndroid;
    installDialog.showModal();
}

if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
        .catch((error) => console.error('ZENTRIX ASTRA service worker registration failed.', error));
}

updateInstallButtons(isAppInstalled());

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
});

window.addEventListener('appinstalled', () => {
    installPrompt = null;
    updateInstallButtons(true);
});

installButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        if (isAppInstalled()) {
            updateInstallButtons(true);
            return;
        }

        if (!installPrompt) {
            showInstallHelp();
            return;
        }

        const promptEvent = installPrompt;
        installPrompt = null;

        try {
            await promptEvent.prompt();
            const choice = await promptEvent.userChoice;
            if (choice.outcome === 'accepted') {
                updateInstallButtons(true);
            }
        } catch (error) {
            console.error('ZENTRIX ASTRA installation prompt failed.', error);
            showInstallHelp();
        }
    });
});
