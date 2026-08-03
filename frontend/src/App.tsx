import { useCallback, useEffect, useMemo, useState } from 'react';
import { BottomNav } from './components/BottomNav';
import { ErrorBlock, LoadingBlock } from './components/StateBlocks';
import { getLatestContent } from './lib/api';
import {
  pushIsSupported,
  readPushPreferences,
  readPushStatus,
  registerServiceWorker,
  subscribeToPush,
  unsubscribeFromPush,
  updatePushPreferences
} from './lib/push';
import { AboutPage } from './pages/AboutPage';
import { ArticleDetailPage } from './pages/ArticleDetailPage';
import { BlogPage } from './pages/BlogPage';
import { ContactPage } from './pages/ContactPage';
import { HomePage } from './pages/HomePage';
import type {
  BlogArticle,
  LatestResponse,
  NotificationCategory,
  NotificationPreferences,
  PushStatus,
  SectionName
} from './types';

const DEFAULT_NOTIFICATION_PREFERENCES: NotificationPreferences = {
  site_updates: true,
  blog: true,
  offers: true,
  newsletter: true
};

function hasSelectedPreference(preferences: NotificationPreferences) {
  return Object.values(preferences).some(Boolean);
}

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
  const [pushMessage, setPushMessage] = useState<string | undefined>();
  const [pushPreferences, setPushPreferences] = useState<NotificationPreferences>(() => ({
    ...DEFAULT_NOTIFICATION_PREFERENCES
  }));
  const [preferencesLoading, setPreferencesLoading] = useState(false);
  const [preferencesDirty, setPreferencesDirty] = useState(false);

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
    let cancelled = false;

    async function initializePush() {
      if (!pushIsSupported()) {
        if (!cancelled) {
          setPushStatus('unsupported');
        }
        return;
      }

      try {
        await registerServiceWorker();
        const status = await readPushStatus();
        if (cancelled) {
          return;
        }

        setPushStatus(status);
        if (status !== 'subscribed') {
          return;
        }

        setPreferencesLoading(true);
        try {
          const preferences = await readPushPreferences();
          if (!cancelled) {
            setPushPreferences(preferences);
            setPreferencesDirty(false);
          }
        } catch (preferencesError) {
          if (!cancelled) {
            setPushError(
              preferencesError instanceof Error
                ? preferencesError.message
                : 'Impossible de charger vos préférences de notifications.'
            );
          }
        } finally {
          if (!cancelled) {
            setPreferencesLoading(false);
          }
        }
      } catch (initializationError) {
        if (!cancelled) {
          setPushStatus(Notification.permission === 'denied' ? 'denied' : Notification.permission);
          setPushError(
            initializationError instanceof Error
              ? initializationError.message
              : "Impossible d'initialiser les notifications."
          );
        }
      }
    }

    initializePush();

    return () => {
      cancelled = true;
    };
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
    if (!hasSelectedPreference(pushPreferences)) {
      setPushError('Choisissez au moins une catégorie de notifications.');
      return;
    }

    setPushBusy(true);
    setPushError(undefined);
    setPushMessage(undefined);
    try {
      const result = await subscribeToPush(pushPreferences);
      setPushPreferences(result.preferences);
      setPushStatus(await readPushStatus());
      setPreferencesDirty(false);
      setPushMessage('Notifications activées selon vos choix.');
    } catch (subscribeError) {
      setPushError(subscribeError instanceof Error ? subscribeError.message : "Impossible d'activer les notifications.");
      try {
        setPushStatus(await readPushStatus());
      } catch {
        // Preserve the original subscription error when status reconciliation also fails.
      }
    } finally {
      setPushBusy(false);
    }
  };

  const changePushPreference = (category: NotificationCategory, enabled: boolean) => {
    setPushPreferences((current) => ({ ...current, [category]: enabled }));
    setPreferencesDirty(true);
    setPushError(undefined);
    setPushMessage(undefined);
  };

  const savePushPreferences = async () => {
    if (!hasSelectedPreference(pushPreferences)) {
      setPushError('Choisissez au moins une catégorie de notifications.');
      return;
    }

    setPushBusy(true);
    setPushError(undefined);
    setPushMessage(undefined);
    try {
      const preferences = await updatePushPreferences(pushPreferences);
      setPushPreferences(preferences);
      setPreferencesDirty(false);
      setPushMessage('Vos préférences ont été enregistrées.');
    } catch (preferencesError) {
      setPushError(
        preferencesError instanceof Error
          ? preferencesError.message
          : 'Impossible d’enregistrer vos préférences de notifications.'
      );
    } finally {
      setPushBusy(false);
    }
  };

  const unsubscribe = async () => {
    setPushBusy(true);
    setPushError(undefined);
    setPushMessage(undefined);
    try {
      await unsubscribeFromPush();
      setPushStatus(await readPushStatus());
      setPushPreferences({ ...DEFAULT_NOTIFICATION_PREFERENCES });
      setPreferencesDirty(false);
      setPushMessage('Les notifications ont été désactivées.');
    } catch (unsubscribeError) {
      setPushError(
        unsubscribeError instanceof Error ? unsubscribeError.message : 'Impossible de désactiver les notifications.'
      );
      try {
        setPushStatus(await readPushStatus());
      } catch {
        // Preserve the original unsubscription error when status reconciliation also fails.
      }
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
        pushMessage={pushMessage}
        pushPreferences={pushPreferences}
        preferencesLoading={preferencesLoading}
        preferencesDirty={preferencesDirty}
        onSubscribe={subscribe}
        onPreferenceChange={changePushPreference}
        onSavePreferences={savePushPreferences}
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
