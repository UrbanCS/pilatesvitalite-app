import { Loader2, RefreshCw } from 'lucide-react';

export function LoadingBlock() {
  return (
    <div className="flex min-h-64 flex-col items-center justify-center gap-3 rounded-lg border border-leaf-100 bg-white p-8 text-center">
      <Loader2 className="animate-spin text-leaf-700" size={30} aria-hidden="true" />
      <p className="text-sm font-semibold text-stone-700">Chargement du contenu...</p>
    </div>
  );
}

interface ErrorBlockProps {
  message: string;
  onRetry: () => void;
}

export function ErrorBlock({ message, onRetry }: ErrorBlockProps) {
  return (
    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-5">
      <p className="text-sm font-semibold text-red-800">{message}</p>
      <button
        type="button"
        onClick={onRetry}
        className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white"
      >
        <RefreshCw size={18} aria-hidden="true" />
        Réessayer
      </button>
    </div>
  );
}

export function EmptyBlock({ label }: { label: string }) {
  return (
    <div className="rounded-lg border border-dashed border-leaf-100 bg-white p-6 text-center text-sm text-stone-600">
      {label}
    </div>
  );
}
