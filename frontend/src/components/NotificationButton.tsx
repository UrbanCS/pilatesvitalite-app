import { Bell, BellOff, Loader2 } from 'lucide-react';
import type { PushStatus } from '../types';

interface NotificationButtonProps {
  status: PushStatus;
  busy: boolean;
  error?: string;
  onSubscribe: () => void;
  onUnsubscribe: () => void;
}

export function NotificationButton({ status, busy, error, onSubscribe, onUnsubscribe }: NotificationButtonProps) {
  const subscribed = status === 'subscribed';
  const disabled = busy || status === 'unsupported' || status === 'denied';
  const label = subscribed ? 'Notifications actives' : 'Activer les notifications';

  return (
    <div className="space-y-2">
      <button
        type="button"
        disabled={disabled}
        onClick={subscribed ? onUnsubscribe : onSubscribe}
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-leaf-700 px-4 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-leaf-900 disabled:cursor-not-allowed disabled:bg-stone-300"
      >
        {busy ? (
          <Loader2 className="animate-spin" size={20} aria-hidden="true" />
        ) : subscribed ? (
          <BellOff size={20} aria-hidden="true" />
        ) : (
          <Bell size={20} aria-hidden="true" />
        )}
        <span>{busy ? 'Traitement...' : label}</span>
      </button>

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

      {error && <p className="text-xs leading-relaxed text-red-700">{error}</p>}
    </div>
  );
}
