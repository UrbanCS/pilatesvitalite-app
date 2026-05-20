import { ExternalLink, Mail, MapPin, Phone } from 'lucide-react';
import type { LatestResponse } from '../types';

export function ContactPage({ data }: { data: LatestResponse }) {
  const contact = data.contact;

  return (
    <main className="mx-auto max-w-md space-y-5 px-5 py-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-clay-500">Nous joindre</p>
        <h1 className="text-3xl font-bold text-ink">Contact</h1>
      </div>

      <section className="space-y-3 rounded-lg border border-leaf-100 bg-white p-5 shadow-sm">
        {contact.address && (
          <p className="flex gap-3 text-sm leading-relaxed text-stone-700">
            <MapPin className="mt-0.5 shrink-0 text-leaf-700" size={20} aria-hidden="true" />
            <span>{contact.address}</span>
          </p>
        )}
        {contact.email && (
          <a className="flex gap-3 text-sm font-semibold text-leaf-700" href={`mailto:${contact.email}`}>
            <Mail size={20} aria-hidden="true" />
            {contact.email}
          </a>
        )}
        {contact.phone && (
          <a className="flex gap-3 text-sm font-semibold text-leaf-700" href={`tel:${contact.phone.replace(/\D/g, '')}`}>
            <Phone size={20} aria-hidden="true" />
            {contact.phone}
          </a>
        )}
      </section>

      <a
        href={contact.sourceUrl}
        target="_blank"
        rel="noreferrer"
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-leaf-700 px-4 py-3 text-sm font-bold text-white shadow-soft"
      >
        Ouvrir la page contact originale
        <ExternalLink size={18} aria-hidden="true" />
      </a>

      <a
        href={data.site.fullSiteUrl}
        target="_blank"
        rel="noreferrer"
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-3 text-sm font-bold text-leaf-700"
      >
        Visiter le site complet
        <ExternalLink size={18} aria-hidden="true" />
      </a>
    </main>
  );
}

