import { CalendarDays } from 'lucide-react';
import type { BlogArticle } from '../types';

interface ArticleCardProps {
  article: BlogArticle;
  onOpen: (article: BlogArticle) => void;
}

export function ArticleCard({ article, onOpen }: ArticleCardProps) {
  return (
    <article className="overflow-hidden rounded-lg border border-leaf-100 bg-white shadow-sm">
      {article.imageUrl && (
        <button type="button" className="block w-full" onClick={() => onOpen(article)} aria-label={article.title}>
          <img src={article.imageUrl} alt="" className="h-40 w-full object-cover" loading="lazy" />
        </button>
      )}
      <div className="space-y-3 p-4">
        {article.date && (
          <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-clay-500">
            <CalendarDays size={15} aria-hidden="true" />
            {article.date}
          </p>
        )}
        <h3 className="text-lg font-bold leading-snug text-ink">{article.title}</h3>
        {article.excerpt && <p className="line-clamp-3 text-sm leading-relaxed text-stone-700">{article.excerpt}</p>}
        <button
          type="button"
          className="inline-flex min-h-11 items-center rounded-lg border border-leaf-700 px-4 py-2 text-sm font-bold text-leaf-700 transition hover:bg-leaf-50"
          onClick={() => onOpen(article)}
        >
          Lire l'article
        </button>
      </div>
    </article>
  );
}

