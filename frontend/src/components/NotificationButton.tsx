import { Bell, BellOff, CheckCircle2, Loader2, Save } from 'lucide-react';
import type { NotificationCategory, NotificationPreferences, PushStatus } from '../types';

const CATEGORY_OPTIONS: Array<{
  id: NotificationCategory;
  label: string;
  description: string;
}> = [
  {
    id: 'site_updates',
    label: 'Mises à jour du site',
    description: 'Changements importants sur les pages Accueil et À propos.'
  },
  {
    id: 'blog',
    label: 'Blogue',
    description: 'Nouveaux articles et mises à jour du blogue.'
  },
  {
    id: 'offers',
    label: 'Cours et forfaits',
    description: 'Nouveaux cours en ligne, en studio ou nouveaux forfaits.'
  },
  {
    id: 'newsletter',
    label: 'Infolettre',
    description: 'Nouvelle infolettre envoyée par Pilates Vitalité.'
  }
];

interface NotificationButtonProps {
  status: PushStatus;
  busy: boolean;
  error?: string;
  message?: string;
  preferences: NotificationPreferences;
  preferencesLoading: boolean;
  preferencesDirty: boolean;
  onPreferenceChange: (category: NotificationCategory, enabled: boolean) => void;
  onSubscribe: () => void;
  onSavePreferences: () => void;
  onUnsubscribe: () => void;
}

export function NotificationButton({
  status,
  busy,
  error,
  message,
  preferences,
  preferencesLoading,
  preferencesDirty,
  onPreferenceChange,
  onSubscribe,
  onSavePreferences,
  onUnsubscribe
}: NotificationButtonProps) {
  const subscribed = status === 'subscribed';
  const unavailable = status === 'unsupported' || status === 'denied';
  const controlsDisabled = busy || preferencesLoading || unavailable;
  const hasSelection = Object.values(preferences).some(Boolean);

  return (
    <section className="space-y-4 rounded-lg border border-leaf-100 bg-white p-5 shadow-sm" aria-labelledby="notifications-title">
      <div className="flex items-start gap-3">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-leaf-50 text-leaf-700">
          <Bell size={21} aria-hidden="true" />
        </span>
        <div>
          <h2 id="notifications-title" className="text-lg font-bold text-ink">
            Mes notifications
          </h2>
          <p className="mt-1 text-sm leading-relaxed text-stone-600">
            Choisissez les nouvelles de Pilates Vitalité que vous souhaitez recevoir.
          </p>
        </div>
      </div>

      <fieldset className="space-y-2" disabled={controlsDisabled}>
        <legend className="sr-only">Catégories de notifications</legend>
        {CATEGORY_OPTIONS.map((option) => (
          <label
            key={option.id}
            className="flex cursor-pointer items-start gap-3 rounded-lg border border-leaf-100 bg-leaf-50/60 p-3 transition hover:border-leaf-500 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
          >
            <input
              type="checkbox"
              checked={preferences[option.id]}
              onChange={(event) => onPreferenceChange(option.id, event.target.checked)}
              className="mt-0.5 h-5 w-5 shrink-0 accent-leaf-700"
            />
            <span>
              <span className="block text-sm font-bold text-ink">{option.label}</span>
              <span className="mt-0.5 block text-xs leading-relaxed text-stone-600">{option.description}</span>
            </span>
          </label>
        ))}
      </fieldset>

      {preferencesLoading && (
        <p className="inline-flex items-center gap-2 text-xs font-semibold text-stone-600" aria-live="polite">
          <Loader2 className="animate-spin" size={15} aria-hidden="true" />
          Chargement de vos préférences…
        </p>
      )}

      {!hasSelection && !preferencesLoading && (
        <p className="text-xs font-semibold leading-relaxed text-red-700" role="alert">
          Choisissez au moins une catégorie, ou désactivez toutes les notifications.
        </p>
      )}

      {subscribed ? (
        <div className="space-y-2">
          <button
            type="button"
            disabled={controlsDisabled || !preferencesDirty || !hasSelection}
            onClick={onSavePreferences}
            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-leaf-700 px-4 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-leaf-900 disabled:cursor-not-allowed disabled:bg-stone-300"
          >
            {busy ? <Loader2 className="animate-spin" size={20} aria-hidden="true" /> : <Save size={19} aria-hidden="true" />}
            <span>{busy ? 'Traitement…' : preferencesDirty ? 'Enregistrer mes choix' : 'Préférences enregistrées'}</span>
          </button>
          <button
            type="button"
            disabled={busy || preferencesLoading}
            onClick={onUnsubscribe}
            className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-stone-300 px-4 py-2 text-sm font-bold text-stone-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <BellOff size={18} aria-hidden="true" />
            Désactiver toutes les notifications
          </button>
        </div>
      ) : (
        <button
          type="button"
          disabled={controlsDisabled || !hasSelection}
          onClick={onSubscribe}
          className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-leaf-700 px-4 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-leaf-900 disabled:cursor-not-allowed disabled:bg-stone-300"
        >
          {busy ? <Loader2 className="animate-spin" size={20} aria-hidden="true" /> : <Bell size={20} aria-hidden="true" />}
          <span>{busy ? 'Traitement…' : 'Activer les notifications'}</span>
        </button>
      )}

      {status === 'unsupported' && (
        <p className="text-xs leading-relaxed text-stone-600">
          Les notifications push ne sont pas disponibles dans ce navigateur.
        </p>
      )}

      {status === 'denied' && (
        <p className="text-xs leading-relaxed text-stone-600">
          Les notifications sont bloquées. Vous pouvez les réactiver dans les réglages du navigateur.
        </p>
      )}

      {message && (
        <p className="inline-flex items-center gap-2 text-xs font-semibold leading-relaxed text-leaf-700" aria-live="polite">
          <CheckCircle2 size={15} aria-hidden="true" />
          {message}
        </p>
      )}
      {error && (
        <p className="text-xs leading-relaxed text-red-700" role="alert">
          {error}
        </p>
      )}
    </section>
  );
}
