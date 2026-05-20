# Audit technique

Date de l'audit: 2026-05-13

Site audite: `https://pilatesvitalite.webactiondemo.ca/`

## 1. CMS ou plateforme

Le site ne ressemble pas a WordPress. Les indices techniques pointent vers Laravel ou un CMS PHP custom base sur Laravel:

- cookies `XSRF-TOKEN` et `laravel_session`;
- routes publiques HTML;
- assets sous `/website`, `/global`, `/uploads`;
- pages d'erreur Laravel-like.

## 2. API exploitable

Aucune API JSON exploitable n'a ete confirmee pendant l'audit.

Routes testees:

- `/wp-json`: retourne une page HTML/404;
- `/api`, `/api/blogs`, `/api/posts`, `/api/latest`: retournent HTML/404 ou une page non exploitable;
- `/sitemap.xml`: retourne HTML/404;
- `/feed`, `/rss`: pas de flux exploitable.

## 3. Recuperation des contenus

Contenus publics disponibles:

- Accueil: `https://pilatesvitalite.webactiondemo.ca/`
- A propos: `https://pilatesvitalite.webactiondemo.ca/about-us`
- Blogue: `https://pilatesvitalite.webactiondemo.ca/blogs`
- Detail article: `https://pilatesvitalite.webactiondemo.ca/blog/{slug}`
- Contact: `https://pilatesvitalite.webactiondemo.ca/contact`

## 4. Nature des contenus

- Accueil: page HTML publique avec sections statiques/modulees.
- A propos: page HTML publique avec blocs de contenu.
- Blogue: liste d'articles CMS avec slug, date, image et page detail.
- Contact: page publique avec adresse, courriel, telephone et formulaire original.

## 5. Approche evaluee

- API CMS: rejetee pour le MVP, aucune API publique fiable detectee.
- Lecture directe des pages publiques: retenue.
- Petit systeme manuel MySQL: utile comme fallback, mais pas choisi comme source primaire.
- Cron avec hash/detection de changement: retenu.

## 6. Architecture choisie

React/Vite statique + API PHP/MySQL + cron PHP. Le backend extrait les contenus publics, calcule des hashes et stocke les snapshots. Les notifications sont envoyees seulement quand un hash change ou quand un nouvel article apparait apres l'initialisation.

