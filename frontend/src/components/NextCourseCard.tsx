import { CalendarDays, ExternalLink } from 'lucide-react';
import type { NextCourse } from '../types';

function formatCourseDate(date: string) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
  if (!match) {
    return date;
  }

  const parsedDate = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
  return new Intl.DateTimeFormat('fr-CA', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC'
  }).format(parsedDate);
}

export function NextCourseCard({ course }: { course?: NextCourse | null }) {
  if (!course) {
    return (
      <div className="rounded-lg border border-dashed border-leaf-100 bg-white p-6 text-sm leading-relaxed text-stone-600">
        Aucun cours en studio à venir pour le moment.
      </div>
    );
  }

  const modeLabel = course.mode === 'online' ? 'Cours en ligne' : 'Cours en studio';
  const dateLabel = course.dateLabel || formatCourseDate(course.date);

  return (
    <article
      className={`overflow-hidden rounded-lg border border-leaf-100 bg-white shadow-sm ${
        course.imageUrl ? 'sm:grid sm:grid-cols-[8rem_1fr]' : ''
      }`}
    >
      {course.imageUrl && (
        <img src={course.imageUrl} alt="" className="h-40 w-full object-cover sm:h-full" loading="lazy" />
      )}
      <div className="space-y-3 p-5">
        <p className="text-xs font-semibold uppercase tracking-wide text-clay-500">{modeLabel}</p>
        <h3 className="text-xl font-bold leading-snug text-ink">{course.title}</h3>
        <p className="inline-flex items-center gap-2 text-sm font-semibold text-stone-700">
          <CalendarDays size={17} aria-hidden="true" />
          <time dateTime={course.date}>{dateLabel}</time>
        </p>
        {course.price && <p className="text-sm font-bold text-leaf-700">{course.price}</p>}
        <a
          href={course.url}
          target="_blank"
          rel="noreferrer"
          aria-label={`Voir le cours ${course.title} sur le site Pilates Vitalité dans un nouvel onglet`}
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-leaf-700 px-4 py-2 text-sm font-bold text-leaf-700 transition hover:bg-leaf-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:ring-offset-2"
        >
          Voir le cours
          <ExternalLink size={17} aria-hidden="true" />
        </a>
      </div>
    </article>
  );
}
