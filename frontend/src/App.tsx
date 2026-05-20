import { useCallback, useEffect, useMemo, useState } from 'react';
import { BottomNav } from './components/BottomNav';
import { ErrorBlock, LoadingBlock } from './components/StateBlocks';
import { getLatestContent } from './lib/api';
import { readPushStatus, registerServiceWorker, subscribeToPush, unsubscribeFromPush } from './lib/push';
import { AboutPage } from './pages/AboutPage';
import { ArticleDetailPage } from './pages/ArticleDetailPage';
import { BlogPage } from './pages/BlogPage';
import { ContactPage } from './pages/ContactPage';
import { HomePage } from './pages/HomePage';
import type { BlogArticle, LatestResponse, PushStatus, SectionName } from './types';

function parseHash() {
  const hash = window.location.hash.replace(/^#\/?/, '');
  const parts = hash.split('/').filter(Boolean);

  if (parts[0] === 'article' && parts[1]) {
    return { section: 'blog' as SectionName, articleId: decodeURIComponent(parts[1]) };
  }

  if (parts[0] === 'blog' || parts[0] === 'about' || parts[0] === 'contact') {
    return { section: parts[0] as SectionName, articleId: undefined };
  }

  return { section: 'home' as SectionName, articleId: undefined };
}

function setRoute(section: SectionName, articleId?: string) {
  window.location.hash = articleId ? `/article/${encodeURIComponent(articleId)}` : `/${section}`;
}

export default function App() {
  const [route, setRouteState] = useState(parseHash);
  const [data, setData] = useState<LatestResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pushStatus, setPushStatus] = useState<PushStatus>('default');
  const [pushBusy, setPushBusy] = useState(false);
  const [pushError, setPushError] = useState<string | undefined>();

  const refresh = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const latest = await getLatestContent();
      setData(latest);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'Impossible de charger le contenu.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
    registerServiceWorker()
      .then(() => readPushStatus())
      .then(setPushStatus)
      .catch(() => setPushStatus('unsupported'));
  }, [refresh]);

  useEffect(() => {
    const onHashChange = () => setRouteState(parseHash());
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  const currentArticle = useMemo(() => {
    if (!data || !route.articleId) {
      return undefined;
    }
    return data.blog.articles.find((article) => article.id === route.articleId);
  }, [data, route.articleId]);

  const openArticle = (article: BlogArticle) => {
    setRoute('blog', article.id);
  };

  const subscribe = async () => {
    setPushBusy(true);
    setPushError(undefined);
    try {
      await subscribeToPush();
      setPushStatus(await readPushStatus());
    } catch (subscribeError) {
      setPushError(subscribeError instanceof Error ? subscribeError.message : "Impossible d'activer les notifications.");
      setPushStatus(await readPushStatus());
    } finally {
      setPushBusy(false);
    }
  };

  const unsubscribe = async () => {
    setPushBusy(true);
    setPushError(undefined);
    try {
      await unsubscribeFromPush();
      setPushStatus(await readPushStatus());
    } catch (unsubscribeError) {
      setPushError(
        unsubscribeError instanceof Error ? unsubscribeError.message : 'Impossible de désactiver les notifications.'
      );
    } finally {
      setPushBusy(false);
    }
  };

  let content;
  if (loading) {
    content = (
      <main className="mx-auto max-w-md px-5 py-6">
        <LoadingBlock />
      </main>
    );
  } else if (error || !data) {
    content = (
      <main className="mx-auto max-w-md px-5 py-6">
        <ErrorBlock message={error || 'Aucun contenu disponible.'} onRetry={refresh} />
      </main>
    );
  } else if (route.articleId) {
    content = <ArticleDetailPage article={currentArticle} onBack={() => setRoute('blog')} />;
  } else if (route.section === 'blog') {
    content = <BlogPage data={data} onArticleOpen={openArticle} />;
  } else if (route.section === 'about') {
    content = <AboutPage data={data} />;
  } else if (route.section === 'contact') {
    content = <ContactPage data={data} />;
  } else {
    content = (
      <HomePage
        data={data}
        pushStatus={pushStatus}
        pushBusy={pushBusy}
        pushError={pushError}
        onSubscribe={subscribe}
        onUnsubscribe={unsubscribe}
        onArticleOpen={openArticle}
      />
    );
  }

  return (
    <div className="min-h-screen bg-leaf-50 pb-28 text-ink">
      {content}
      <BottomNav active={route.section} onNavigate={(section) => setRoute(section)} />
    </div>
  );
}
