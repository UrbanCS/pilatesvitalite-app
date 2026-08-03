import { BookOpen, ExternalLink, UserRound } from 'lucide-react';
import { ArticleCard } from '../components/ArticleCard';
import { InstallAppButton } from '../components/InstallAppButton';
import { NextCourseCard } from '../components/NextCourseCard';
import { NotificationButton } from '../components/NotificationButton';
import type {
  BlogArticle,
  LatestResponse,
  NotificationCategory,
  NotificationPreferences,
  PushStatus
} from '../types';

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

function sitePageUrl(siteUrl: string, path: string) {
  return `${siteUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
}

interface HomePageProps {
  data: LatestResponse;
  pushStatus: PushStatus;
  pushBusy: boolean;
  pushError?: string;
  pushMessage?: string;
  pushPreferences: NotificationPreferences;
  preferencesLoading: boolean;
  preferencesDirty: boolean;
  onSubscribe: () => void;
  onPreferenceChange: (category: NotificationCategory, enabled: boolean) => void;
  onSavePreferences: () => void;
  onUnsubscribe: () => void;
  onArticleOpen: (article: BlogArticle) => void;
}

export function HomePage({
  data,
  pushStatus,
  pushBusy,
  pushError,
  pushMessage,
  pushPreferences,
  preferencesLoading,
  preferencesDirty,
  onSubscribe,
  onPreferenceChange,
  onSavePreferences,
  onUnsubscribe,
  onArticleOpen
}: HomePageProps) {
  const latestArticle = data.blog.articles[0];
  const allCoursesUrl = sitePageUrl(data.site.fullSiteUrl, '/shop');
  const accountUrl = sitePageUrl(data.site.fullSiteUrl, '/login');

  return (
    <div className="space-y-6">
      <section className="overflow-hidden border-t-8 border-clay-500 bg-leaf-900 text-white">
        <div className="mx-auto max-w-md px-5 py-8">
          <img
            src="./icons/pilates-logo.png"
            alt="Pilates Vitalité"
            className="h-14 w-auto rounded-md bg-white px-3 py-2 shadow-sm"
          />
          <h1 className="sr-only">{data.site.name}</h1>
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

      <section className="mx-auto max-w-md space-y-4 px-5" aria-labelledby="next-course-title">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Cours en studio</p>
          <h2 id="next-course-title" className="text-2xl font-bold text-ink">
            Prochain cours
          </h2>
        </div>

        <NextCourseCard course={data.nextCourse} />

        <nav className="grid grid-cols-1 gap-3 sm:grid-cols-2" aria-label="Accès rapides aux services Pilates Vitalité">
          <a
            href={allCoursesUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-leaf-700 px-4 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-leaf-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:ring-offset-2"
          >
            <BookOpen size={19} aria-hidden="true" />
            Tous les cours
            <span className="sr-only"> — ouvre le site Pilates Vitalité dans un nouvel onglet</span>
          </a>
          <a
            href={accountUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700 transition hover:bg-leaf-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:ring-offset-2"
          >
            <UserRound size={19} aria-hidden="true" />
            Compte client
            <span className="sr-only"> — ouvre le site Pilates Vitalité dans un nouvel onglet</span>
          </a>
        </nav>
      </section>

      <section className="mx-auto max-w-md space-y-4 px-5" aria-labelledby="latest-news-title">
        <div className="flex items-end justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Blogue</p>
            <h2 id="latest-news-title" className="text-2xl font-bold text-ink">
              Dernière nouvelle
            </h2>
          </div>
          <a
            href="#/blog"
            className="rounded text-sm font-bold text-leaf-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:ring-offset-2"
          >
            Tout voir
          </a>
        </div>
        {latestArticle ? (
          <ArticleCard article={latestArticle} onOpen={onArticleOpen} />
        ) : (
          <p className="rounded-lg border border-dashed border-leaf-100 bg-white p-6 text-sm text-stone-600">
            Aucun article n'est disponible pour le moment.
          </p>
        )}
      </section>

      <section className="mx-auto max-w-md space-y-4 px-5 pb-4" aria-label="Application et notifications">
        <InstallAppButton />

        <NotificationButton
          status={pushStatus}
          busy={pushBusy}
          error={pushError}
          message={pushMessage}
          preferences={pushPreferences}
          preferencesLoading={preferencesLoading}
          preferencesDirty={preferencesDirty}
          onPreferenceChange={onPreferenceChange}
          onSubscribe={onSubscribe}
          onSavePreferences={onSavePreferences}
          onUnsubscribe={onUnsubscribe}
        />

        <a
          href={data.site.fullSiteUrl}
          target="_blank"
          rel="noreferrer"
          className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700 transition hover:bg-leaf-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:ring-offset-2"
        >
          Visiter le site complet
          <ExternalLink size={18} aria-hidden="true" />
          <span className="sr-only"> — ouvre dans un nouvel onglet</span>
        </a>
      </section>
    </div>
  );
}
