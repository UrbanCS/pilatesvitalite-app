import { ArticleCard } from '../components/ArticleCard';
import { EmptyBlock } from '../components/StateBlocks';
import type { BlogArticle, LatestResponse } from '../types';

interface BlogPageProps {
  data: LatestResponse;
  onArticleOpen: (article: BlogArticle) => void;
}

export function BlogPage({ data, onArticleOpen }: BlogPageProps) {
  return (
    <main className="mx-auto max-w-md space-y-5 px-5 py-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Conseils et actualites</p>
        <h1 className="text-3xl font-bold text-ink">Blogue</h1>
      </div>

      {data.blog.articles.length > 0 ? (
        <div className="space-y-4">
          {data.blog.articles.map((article) => (
            <ArticleCard key={article.id} article={article} onOpen={onArticleOpen} />
          ))}
        </div>
      ) : (
        <EmptyBlock label="Aucun article n'est disponible pour le moment." />
      )}
    </main>
  );
}

