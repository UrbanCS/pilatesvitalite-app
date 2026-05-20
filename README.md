# Pilates Vitalite PWA

MVP de Progressive Web App mobile-first pour Pilates Vitalite. Le frontend est une app React/TypeScript/Vite statique. Le backend est une API PHP 8/MySQL compatible cPanel qui lit les pages publiques du site, stocke des snapshots et envoie des notifications Web Push quand un contenu important change.

## Audit de faisabilite

- Le site de developpement `https://pilatesvitalite.webactiondemo.ca/` repond comme une application Laravel ou un CMS PHP custom: cookies `laravel_session`, assets `/website`, `/global`, routes publiques HTML.
- Aucune API JSON exploitable n'a ete detectee pour le MVP. Les routes `/wp-json`, `/api`, `/api/blogs` retournent une page HTML/404 plutot qu'un flux structure.
- Les contenus utiles sont publics en HTML:
  - Accueil: `/`
  - A propos: `/about-us`
  - Blogue: `/blogs` et details `/blog/{slug}`
  - Contact: `/contact`
- L'approche retenue est donc: lecture directe des pages publiques + extraction HTML controlee + hash par section/item + cron PHP.

## Architecture finale

```text
frontend/                 React + TypeScript + Vite + Tailwind
api/                      Endpoints PHP publics
backend/config/           Configuration privee
backend/lib/              Scraper, DB, push, snapshots
backend/cron/             check-updates.php
database/schema.sql       Tables MySQL
docs/                     Guides cPanel, push, checklist
```

## Scope MVP

- PWA installable avec manifest, service worker, cache minimal et icone.
- Navigation mobile: Accueil, Blogue, A propos, Contact.
- Lecture live des pages publiques via `/api/latest.php`.
- Abonnement/desabonnement Web Push via `/api/subscribe.php` et `/api/unsubscribe.php`.
- Notification test protegee via `/api/notify-test.php`.
- Cron `/backend/cron/check-updates.php` avec detection de changements par hash.
- MySQL: `push_subscriptions`, `content_snapshots`, `notification_logs`.

## Prerequis local

- Node.js 18+ pour compiler le frontend.
- PHP 8+ pour valider/executer les scripts localement.
- Composer pour installer la librairie Web Push, ou installation locale puis upload de `backend/vendor/` sur cPanel.

## Installation frontend

```bash
cd frontend
npm install
npm run build
```

Le build statique est genere dans `frontend/dist`.

## Installation backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp config/config.example.php config/config.php
```

Modifier `backend/config/config.php` avec les identifiants MySQL, les URLs finales et les cles VAPID.

## API

- `POST /api/subscribe.php`
- `POST /api/unsubscribe.php`
- `GET /api/latest.php`
- `GET /api/config.php`
- `POST /api/notify-test.php`

## Cron cPanel

Commande recommandee:

```bash
/usr/local/bin/php /home/USER/public_html/app/backend/cron/check-updates.php
```

Frequence suggeree: toutes les 15 ou 30 minutes. Le premier passage initialise les snapshots sans envoyer de notifications, sauf si `NOTIFY_ON_FIRST_RUN` est mis a `true`.

## Securite

- Ne jamais exposer `backend/config/config.php`.
- Ne jamais mettre `VAPID_PRIVATE_KEY`, `DB_PASS`, `CRON_SECRET` ou `NOTIFY_TEST_SECRET` dans le frontend.
- Garder `backend/.htaccess` en place si le dossier est sous `public_html`.
- Servir l'app en HTTPS.
- Proteger `notify-test.php` avec `NOTIFY_TEST_SECRET`.

## Documentation

- [Guide cPanel](docs/CPANEL.md)
- [Notifications push](docs/PUSH_NOTIFICATIONS.md)
- [Audit technique](docs/AUDIT.md)
- [Checklist finale](docs/TEST_CHECKLIST.md)

