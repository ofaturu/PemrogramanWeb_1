// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js')
      .then((registration) => {
        console.log('FTrans ServiceWorker registered with scope:', registration.scope);
      })
      .catch((error) => {
        console.log('FTrans ServiceWorker registration failed:', error);
      });
  });
}

// Handle PWA Installation Banner Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent Chrome 67 and earlier from automatically showing the prompt
  e.preventDefault();
  deferredPrompt = e;

  // Render floating PWA Install Banner on mobile/desktop
  showPwaInstallBanner();
});

function showPwaInstallBanner() {
  if (document.getElementById('pwa-install-banner')) return;

  const banner = document.createElement('div');
  banner.id = 'pwa-install-banner';
  banner.style.cssText = `
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 90%;
    max-width: 420px;
    background: #1e293b;
    color: #ffffff;
    padding: 14px 18px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 99999;
    animation: pwaSlideUp 0.4s ease-out forwards;
    border: 1px solid rgba(255, 255, 255, 0.1);
  `;

  banner.innerHTML = `
    <div style="display: flex; align-items: center; gap: 12px;">
      <img src="./assets/favicon/android-icon-96x96.png" alt="FTrans" style="width: 42px; height: 42px; border-radius: 10px; object-fit: cover;">
      <div>
        <div style="font-weight: 700; font-size: 0.95rem; line-height: 1.2;">Install FTrans App</div>
        <div style="font-size: 0.75rem; color: #94a3b8;">Akses cepat & tanpa browser</div>
      </div>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
      <button id="pwa-install-btn" style="
        background: #2563eb;
        color: white;
        border: none;
        padding: 8px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
      ">Install</button>
      <button id="pwa-close-btn" style="
        background: transparent;
        color: #94a3b8;
        border: none;
        font-size: 1.1rem;
        cursor: pointer;
        padding: 4px 8px;
      ">&times;</button>
    </div>
  `;

  // Inject animation keyframes if not exists
  if (!document.getElementById('pwa-keyframes')) {
    const style = document.createElement('style');
    style.id = 'pwa-keyframes';
    style.innerHTML = `
      @keyframes pwaSlideUp {
        from { transform: translate(-50%, 100px); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }

  document.body.appendChild(banner);

  document.getElementById('pwa-install-btn').addEventListener('click', () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          console.log('User accepted the PWA install prompt');
        }
        deferredPrompt = null;
        banner.remove();
      });
    }
  });

  document.getElementById('pwa-close-btn').addEventListener('click', () => {
    banner.remove();
  });
}

// Web Push Notification Permission Request Utility
window.requestNotificationPermission = function() {
  if (!('Notification' in window)) {
    console.log('Browser tidak mendukung notifikasi.');
    return;
  }
  if (Notification.permission === 'default') {
    Notification.requestPermission().then((permission) => {
      if (permission === 'granted') {
        console.log('Izin notifikasi diberikan.');
      }
    });
  }
};
