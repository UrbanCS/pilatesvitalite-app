import { ExternalLink } from 'lucide-react';
import type { LatestResponse } from '../types';

export function AboutPage({ data }: { data: LatestResponse }) {
  return (
    <main className="mx-auto max-w-md space-y-5 px-5 py-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Studio et approche</p>
        <h1 className="text-3xl font-bold text-ink">{data.about.title || 'À propos'}</h1>
      </div>

      {data.about.imageUrl && (
        <img src={data.about.imageUrl} alt="" className="h-48 w-full rounded-lg object-cover shadow-sm" loading="lazy" />
      )}

      <section className="rounded-lg border border-leaf-100 bg-white p-5 shadow-sm">
        <p className="text-base leading-relaxed text-stone-700">{data.about.summary}</p>
      </section>

      <div className="space-y-3">
        {data.about.sections.map((section) => (
          <section key={section.title} className="rounded-lg border border-leaf-100 bg-white p-5 shadow-sm">
            <h2 className="text-xl font-bold text-ink">{section.title}</h2>
            <p className="mt-2 text-sm leading-relaxed text-stone-700">{section.body}</p>
          </section>
        ))}
      </div>

      <a
        href={data.about.sourceUrl}
        target="_blank"
        rel="noreferrer"
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700"
      >
        Ouvrir la page originale
        <ExternalLink size={18} aria-hidden="true" />
      </a>
    </main>
  );
}
