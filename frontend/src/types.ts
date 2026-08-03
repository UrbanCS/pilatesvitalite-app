export type SectionName = 'home' | 'about' | 'blog' | 'contact';

export interface BlogArticle {
  id: string;
  title: string;
  url: string;
  date?: string;
  excerpt?: string;
  imageUrl?: string;
  content?: string;
}

export interface NextCourse {
  id: string;
  title: string;
  url: string;
  date: string;
  dateLabel?: string;
  price?: string;
  imageUrl?: string;
  mode?: 'studio' | 'online';
}

export interface HomeContent {
  title: string;
  summary: string;
  sourceUrl: string;
  imageUrl?: string;
  highlights: string[];
  benefits?: string[];
  benefitsIntro?: string;
  benefitsOutro?: string;
}

export interface AboutContent {
  title: string;
  summary: string;
  sourceUrl: string;
  imageUrl?: string;
  sections: Array<{
    title: string;
    body: string;
  }>;
}

export interface ContactContent {
  title: string;
  sourceUrl: string;
  phone?: string;
  email?: string;
  address?: string;
}

export interface LatestResponse {
  ok: boolean;
  source: 'live' | 'database';
  fetchedAt: string;
  site: {
    name: string;
    tagline: string;
    fullSiteUrl: string;
    finalDomain: string;
  };
  home: HomeContent;
  about: AboutContent;
  blog: {
    title: string;
    sourceUrl: string;
    articles: BlogArticle[];
  };
  nextCourse?: NextCourse | null;
  contact: ContactContent;
}

export interface PublicConfig {
  ok: boolean;
  vapidPublicKey: string;
  appBaseUrl: string;
  finalDomain: string;
  sourceUrls: Record<SectionName, string>;
}

export const NOTIFICATION_CATEGORIES = ['site_updates', 'blog', 'offers', 'newsletter'] as const;

export type NotificationCategory = (typeof NOTIFICATION_CATEGORIES)[number];

export type NotificationPreferences = Record<NotificationCategory, boolean>;

export interface NotificationPreferencesResponse {
  ok: boolean;
  preferences: NotificationPreferences;
}

export type PushStatus = 'unsupported' | 'default' | 'granted' | 'denied' | 'subscribed';
