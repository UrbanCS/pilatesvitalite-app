import { ArrowLeft, CalendarDays, ExternalLink } from 'lucide-react';
import type { BlogArticle } from '../types';

interface ArticleDetailPageProps {
  article?: BlogArticle;
  onBack: () => void;
}

export function ArticleDetailPage({ article, onBack }: ArticleDetailPageProps) {
  if (!article) {
    return (
      <main className="mx-auto max-w-md space-y-4 px-5 py-6">
        <button type="button" onClick={onBack} className="inline-flex items-center gap-2 text-sm font-bold text-leaf-700">
          <ArrowLeft size={18} aria-hidden="true" />
          Retour
        </button>
        <div className="rounded-lg border border-dashed border-leaf-100 bg-white p-6 text-sm text-stone-600">
          Article introuvable.
        </div>
      </main>
    );
  }

  return (
    <main className="mx-auto max-w-md space-y-5 px-5 py-6">
      <button type="button" onClick={onBack} className="inline-flex items-center gap-2 text-sm font-bold text-leaf-700">
        <ArrowLeft size={18} aria-hidden="true" />
        Retour
      </button>

      {article.imageUrl && <img src={article.imageUrl} alt="" className="h-52 w-full rounded-lg object-cover shadow-sm" />}

      <article className="space-y-4 rounded-lg border border-leaf-100 bg-white p-5 shadow-sm">
        {article.date && (
          <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-clay-500">
            <CalendarDays size={15} aria-hidden="true" />
            {article.date}
          </p>
        )}
        <h1 className="text-3xl font-bold leading-tight text-ink">{article.title}</h1>
        <p className="whitespace-pre-line text-base leading-relaxed text-stone-700">
          {article.content || article.excerpt || 'Le contenu complet est disponible sur le site.'}
        </p>
      </article>

      <a
        href={article.url}
        target="_blank"
        rel="noreferrer"
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700"
      >
        Ouvrir sur le site
        <ExternalLink size={18} aria-hidden="true" />
      </a>
    </main>
  );
}

