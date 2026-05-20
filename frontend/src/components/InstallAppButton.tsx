import { Download, Plus, Share, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { clearInstallPrompt, getInstallPrompt, subscribeToInstallPrompt } from '../lib/installPrompt';
import type { BeforeInstallPromptEvent } from '../lib/installPrompt';

function isAppleDevice() {
  const userAgent = window.navigator.userAgent.toLowerCase();
  const platform = window.navigator.platform.toLowerCase();
  const iOSDevice = /iphone|ipad|ipod/.test(userAgent);
  const touchMac = platform.includes('mac') && window.navigator.maxTouchPoints > 1;

  return iOSDevice || touchMac;
}

function isStandalone() {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    (window.navigator as Navigator & { standalone?: boolean }).standalone === true
  );
}

export function InstallAppButton() {
  const [installPrompt, setInstallPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [guideOpen, setGuideOpen] = useState(false);
  const [installed, setInstalled] = useState(false);
  const appleDevice = useMemo(() => isAppleDevice(), []);

  useEffect(() => {
    setInstalled(isStandalone());
    setInstallPrompt(getInstallPrompt());

    const handleInstalled = () => {
      setInstalled(true);
      clearInstallPrompt();
    };

    const unsubscribe = subscribeToInstallPrompt(setInstallPrompt);
    window.addEventListener('appinstalled', handleInstalled);

    return () => {
      unsubscribe();
      window.removeEventListener('appinstalled', handleInstalled);
    };
  }, []);

  const handleInstall = async () => {
    if (appleDevice || !installPrompt) {
      setGuideOpen(true);
      return;
    }

    await installPrompt.prompt();
    const choice = await installPrompt.userChoice;
    if (choice.outcome === 'accepted') {
      setInstalled(true);
    }
    clearInstallPrompt();
  };

  if (installed && !appleDevice) {
    return null;
  }

  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={handleInstall}
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-clay-500 px-4 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-clay-600"
      >
        <Download size={20} aria-hidden="true" />
        <span>{appleDevice ? 'Voir comment installer' : "Télécharger l'application"}</span>
      </button>

      {guideOpen && (
        <div
          className="fixed inset-0 z-50 flex items-end bg-ink/45 px-4 pb-4 pt-10 sm:items-center sm:justify-center"
          role="dialog"
          aria-modal="true"
          aria-labelledby="install-guide-title"
        >
          <div className="w-full max-w-md rounded-lg bg-white p-5 shadow-soft">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Installation</p>
                <h2 id="install-guide-title" className="mt-1 text-xl font-bold text-ink">
                  Ajouter Pilates Vitalité
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setGuideOpen(false)}
                className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-leaf-100 text-leaf-900"
                aria-label="Fermer"
              >
                <X size={20} aria-hidden="true" />
              </button>
            </div>

            {appleDevice ? (
              <ol className="mt-5 space-y-4 text-sm leading-relaxed text-stone-700">
                <li className="flex gap-3">
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-leaf-50 text-leaf-700">
                    <Share size={17} aria-hidden="true" />
                  </span>
                  <span>
                    Touchez le bouton <strong>Partager</strong> dans Safari.
                  </span>
                </li>
                <li className="flex gap-3">
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-leaf-50 text-leaf-700">
                    <Plus size={17} aria-hidden="true" />
                  </span>
                  <span>
                    Choisissez <strong>Sur l'écran d'accueil</strong>.
                  </span>
                </li>
                <li className="flex gap-3">
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-leaf-50 text-leaf-700">
                    3
                  </span>
                  <span>
                    Touchez <strong>Ajouter</strong>. L'icône apparaîtra avec vos applications.
                  </span>
                </li>
              </ol>
            ) : (
              <div className="mt-5 space-y-3 text-sm leading-relaxed text-stone-700">
                <p>
                  Si le navigateur n'ouvre pas automatiquement la fenêtre d'installation, utilisez le menu du navigateur,
                  puis choisissez <strong>Installer l'application</strong> ou <strong>Ajouter à l'écran d'accueil</strong>.
                </p>
                <p>Cette option est disponible surtout dans Chrome, Edge et les navigateurs Android compatibles.</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
