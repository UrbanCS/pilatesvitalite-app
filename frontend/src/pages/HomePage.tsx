import { ExternalLink } from 'lucide-react';
import { ArticleCard } from '../components/ArticleCard';
import { InstallAppButton } from '../components/InstallAppButton';
import { NotificationButton } from '../components/NotificationButton';
import type { BlogArticle, LatestResponse, PushStatus } from '../types';

function courseUrl(label: string, siteUrl: string) {
  const baseUrl = siteUrl.replace(/\/$/, '');
  const normalized = label.toLocaleLowerCase('fr-CA');

  if (normalized.includes('ligne')) {
    return `${baseUrl}/shop?search=&category%5B%5D=en-ligne`;
  }

  if (normalized.includes('studio')) {
    return `${baseUrl}/shop?search=&category%5B%5D=en-studio`;
  }

  if (normalized.includes('priv')) {
    return `${baseUrl}/service`;
  }

  return baseUrl;
}

interface HomePageProps {
  data: LatestResponse;
  pushStatus: PushStatus;
  pushBusy: boolean;
  pushError?: string;
  onSubscribe: () => void;
  onUnsubscribe: () => void;
  onArticleOpen: (article: BlogArticle) => void;
}

export function HomePage({
  data,
  pushStatus,
  pushBusy,
  pushError,
  onSubscribe,
  onUnsubscribe,
  onArticleOpen
}: HomePageProps) {
  const articles = data.blog.articles.slice(0, 2);
  const benefits = data.home.benefits?.length
    ? data.home.benefits
    : [
        'De prévenir les blessures causées par la mauvaise posture.',
        'De soulager un grand nombre de douleurs.',
        "D'augmenter votre concentration mentale."
      ];
  const benefitsIntro = data.home.benefitsIntro || 'Faire du Pilates avec Pilates Vitalité vous permet :';
  const benefitsOutro = data.home.benefitsOutro || 'Et plus encore!';

  return (
    <div className="space-y-6">
      <section className="overflow-hidden border-t-8 border-clay-500 bg-leaf-900 text-white">
        <div className="mx-auto max-w-md px-5 py-8">
          <img
            src="./icons/pilates-logo.png"
            alt="Pilates Vitalité"
            className="h-14 w-auto rounded-md bg-white px-3 py-2 shadow-sm"
          />
          <h1 className="mt-5 text-4xl font-bold leading-tight">{data.home.title || data.site.name}</h1>
          <p className="mt-4 text-base leading-relaxed text-leaf-50">{data.home.summary || data.site.tagline}</p>
          {benefits.length > 0 && (
            <div className="mt-5 space-y-4">
              <p className="text-base leading-relaxed text-leaf-50">{benefitsIntro}</p>
              <div className="space-y-3">
                {benefits.slice(0, 3).map((benefit, index) => (
                  <div key={`${index}-${benefit}`} className="flex items-start gap-3">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/14 text-sm font-bold text-clay-100 ring-1 ring-white/15">
                      {index + 1}
                    </span>
                    <p className="pt-1 text-sm font-semibold leading-relaxed text-white">{benefit}</p>
                  </div>
                ))}
              </div>
              <p className="text-base leading-relaxed text-leaf-50">{benefitsOutro}</p>
            </div>
          )}
          {data.home.highlights.length > 0 && (
            <div className="mt-5 flex flex-wrap gap-2">
              {data.home.highlights.slice(0, 3).map((highlight) => (
                <a
                  key={highlight}
                  href={courseUrl(highlight, data.site.fullSiteUrl)}
                  target="_blank"
                  rel="noreferrer"
                  className="rounded-full bg-white/12 px-3 py-1 text-sm font-semibold transition hover:bg-clay-500 hover:text-white"
                >
                  {highlight}
                </a>
              ))}
            </div>
          )}
        </div>
      </section>

      <section className="mx-auto max-w-md space-y-4 px-5">
        <InstallAppButton />

        <NotificationButton
          status={pushStatus}
          busy={pushBusy}
          error={pushError}
          onSubscribe={onSubscribe}
          onUnsubscribe={onUnsubscribe}
        />

        <a
          href={data.site.fullSiteUrl}
          target="_blank"
          rel="noreferrer"
          className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700 transition hover:bg-leaf-50"
        >
          Visiter le site complet
          <ExternalLink size={18} aria-hidden="true" />
        </a>
      </section>

      <section className="mx-auto max-w-md space-y-4 px-5 pb-4">
        <div className="flex items-end justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Blogue</p>
            <h2 className="text-2xl font-bold text-ink">Derniers articles</h2>
          </div>
          <a href="#/blog" className="text-sm font-bold text-leaf-700">
            Tout voir
          </a>
        </div>
        {articles.length > 0 ? (
          <div className="space-y-4">
            {articles.map((article) => (
              <ArticleCard key={article.id} article={article} onOpen={onArticleOpen} />
            ))}
          </div>
        ) : (
          <p className="rounded-lg border border-dashed border-leaf-100 bg-white p-6 text-sm text-stone-600">
            Aucun article n'est disponible pour le moment.
          </p>
        )}
      </section>
    </div>
  );
}
