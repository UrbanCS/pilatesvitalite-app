# Checklist finale de test

## Frontend

- [ ] `npm install` fonctionne dans `frontend/`.
- [ ] `npm run build` genere `frontend/dist/`.
- [ ] La PWA s'ouvre sur mobile.
- [ ] Les 4 onglets fonctionnent: Accueil, Blogue, A propos, Contact.
- [ ] La page detail d'un article fonctionne.
- [ ] Les etats loading, erreur et vide sont lisibles.
- [ ] Le bouton `Visiter le site complet` ouvre le site original.
- [ ] Le manifest est visible dans DevTools.
- [ ] Le service worker est installe.
- [ ] L'app peut etre ajoutee a l'ecran d'accueil.

## Backend

- [ ] `database/schema.sql` est importe dans MySQL.
- [ ] `backend/config/config.php` existe et contient les bonnes valeurs.
- [ ] `/api/config.php` retourne JSON.
- [ ] `/api/latest.php` retourne JSON avec `home`, `about`, `blog`, `contact`.
- [ ] `/api/subscribe.php` sauvegarde un abonnement.
- [ ] `/api/unsubscribe.php` desactive un abonnement.
- [ ] `/api/notify-test.php` refuse sans secret.
- [ ] `/api/notify-test.php` envoie avec le bon secret.

## Cron

- [ ] `check-updates.php` s'execute en CLI.
- [ ] Le premier run cree des snapshots sans notification.
- [ ] Un changement de contenu cree un nouveau snapshot.
- [ ] Une notification est journalisee une seule fois par hash.
- [ ] Les logs sont ecrits dans `backend/storage/logs/`.

## Production

- [ ] HTTPS actif.
- [ ] `SOURCE_BASE_URL`, `WATCH_URLS`, `APP_BASE_URL` pointent vers le domaine final.
- [ ] Les cles VAPID sont configurees.
- [ ] `backend/.htaccess` protege le dossier backend.
- [ ] Le cron cPanel tourne toutes les 10 minutes.
