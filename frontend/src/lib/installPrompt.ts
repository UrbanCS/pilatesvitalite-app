export interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

type Listener = (prompt: BeforeInstallPromptEvent | null) => void;

let deferredPrompt: BeforeInstallPromptEvent | null = null;
const listeners = new Set<Listener>();

function notifyListeners() {
  listeners.forEach((listener) => listener(deferredPrompt));
}

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  deferredPrompt = event as BeforeInstallPromptEvent;
  notifyListeners();
});

window.addEventListener('appinstalled', () => {
  deferredPrompt = null;
  notifyListeners();
});

export function getInstallPrompt() {
  return deferredPrompt;
}

export function clearInstallPrompt() {
  deferredPrompt = null;
  notifyListeners();
}

export function subscribeToInstallPrompt(listener: Listener) {
  listeners.add(listener);
  listener(deferredPrompt);

  return () => {
    listeners.delete(listener);
  };
}
