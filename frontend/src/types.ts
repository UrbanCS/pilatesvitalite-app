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
  contact: ContactContent;
}

export interface PublicConfig {
  ok: boolean;
  vapidPublicKey: string;
  appBaseUrl: string;
  finalDomain: string;
  sourceUrls: Record<SectionName, string>;
}

export type PushStatus = 'unsupported' | 'default' | 'granted' | 'denied' | 'subscribed';
