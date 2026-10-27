# Guide de deploiement DirectAdmin/cPanel

Etat deploye le 2026-08-03:

- source active: `https://pilatesvitalite.webactiondemo.ca`;
- domaine final futur: `https://pilatesvitalite.ca`;
- panneau reel: DirectAdmin Evolution;
- racine du site Laravel:
  `/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/`;
- racine PWA:
  `/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/`.

Le passage au domaine final est une intervention distincte. Ne pas modifier les
URLs de source ou le manifest pendant une mise a jour ordinaire de la PWA demo.

Sauvegardes de la mise a niveau des notifications:

```text
Serveur: /home/pilatesvitalite/codex-backups/pwa-notification-upgrade-20260803
Local:   C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-notification-upgrade-20260803-161209.zip
```

Sauvegardes de la mise a niveau de l'accueil:

```text
Serveur: /home/pilatesvitalite/codex-backups/pwa-home-upgrade-20260803-172534
Local:   C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-home-course-login-20260803-172534.zip
```

Sauvegarde de la migration des 28 articles WordPress:

```text
Serveur: /home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15
Outils:  /home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15/migration-tools
```

Le rapport complet est dans `docs/BLOG_MIGRATION_20260803.md`.

Sauvegarde de la traduction du blogue et de l'optimisation PWA:

```text
Serveur: /home/pilatesvitalite/codex-backups/blog-ui-pwa-perf-20260803-225319
```

Cette sauvegarde contient aussi
`blog-item.blade.php.before-home-date-fix`, copie du composant partage avant la
traduction finale des dates de l'accueil.

Archive finale:

```text
Local:   C:\Users\marca\AppData\Local\Temp\pilatesvitalite-deploy-20260803-v3.zip
Staging: /home/pilatesvitalite/codex-staging/pwa-notification-upgrade-20260803/
Entrees: 25
SHA-256: 3207A9D8F19E3D1CF04382857799BD6FCDE8DE5A4411FA92C57B907ACB060B8E
```

Archive de la mise a niveau de l'accueil:

```text
Local:   C:\Users\marca\AppData\Local\Temp\pilatesvitalite-home-upgrade-20260803-172534.zip
Staging: /home/pilatesvitalite/codex-staging/pwa-home-upgrade-20260803-172534/
Entrees: 26
SHA-256: F1AD6AA5F1A1E416A72D144551078FD9593F23C05698731BC68503E7FAA707F0
```

## 1. Compiler le frontend

```bash
cd frontend
npm install
npm run build
```

Build valide et deploye le 2026-08-03:

```text
assets/index-BTcxeBIp.js
assets/index-BY6eFtrM.css
```

## 2. Uploader le frontend

Uploader le contenu de `frontend/dist/` dans le dossier public de la PWA:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/
```

L'app doit etre accessible en HTTPS, par exemple:

```text
https://pilatesvitalite.webactiondemo.ca/pwa/
```

Toujours uploader `index.html` et tout le dossier `assets/` depuis le meme
build Vite. Sinon, le navigateur peut charger un nom de fichier JavaScript qui
n'existe pas et afficher une page vide.

Ordre recommande dans DirectAdmin:

1. uploader les nouveaux fichiers hashes dans `assets/` sans supprimer les
   anciens;
2. uploader `.htaccess`, `sw.js`, `index.php`, le manifest et les icones;
3. uploader `index.html` en dernier;
4. verifier que les deux assets references retournent HTTP 200.

Le service worker deploye utilise le cache
`pilates-vitalite-shell-v20260803-2`. Le `.htaccess` live impose `no-cache`
aux fichiers d'entree et au service worker.

## 3. Uploader le backend

Uploader:

```text
api/      -> public_html/pwa/api/
backend/  -> public_html/pwa/backend/
```

Conserver `backend/.htaccess` pour bloquer l'acces web direct aux fichiers backend.

Pour la mise a niveau des notifications, appliquer la migration SQL avant les
nouveaux endpoints, puis uploader les classes avant `bootstrap.php`:

```text
database/migrations/20260803_push_subscription_preferences.sql
backend/lib/NotificationPreferences.php
backend/lib/SourceNewsletterRepository.php
backend/lib/SnapshotRepository.php
backend/lib/PushService.php
backend/lib/Scraper.php
backend/lib/bootstrap.php
api/subscribe.php
api/preferences.php
backend/cron/check-updates.php
```

Ne jamais ecraser `backend/config/config.php`. Le fichier live contient les
identifiants MySQL, les cles VAPID et les secrets.

La carte `Prochain cours` ajoute les fichiers suivants au deploiement cible:

```text
backend/lib/NextCourseCache.php
backend/lib/Scraper.php
backend/lib/SnapshotRepository.php
backend/lib/bootstrap.php
backend/cron/check-updates.php
api/latest.php
```

Le cron ecrit `backend/storage/next-course.json`. Ce cache n'est pas un
snapshot de notification et ne change pas le total attendu de 55 elements.
Une absence de cours studio futur est representee par `course: null`.

L'optimisation de chargement ajoute aussi:

```text
backend/lib/LatestContentCache.php
backend/lib/Scraper.php
backend/lib/bootstrap.php
backend/cron/check-updates.php
backend/config/config.example.php
api/latest.php
```

Le cron ecrit `backend/storage/latest-content.json` de facon atomique.
`latest.php` sert le cache pendant au plus 1 200 secondes et ne scrape en live
que si le cache est absent ou expire. Ne pas ajouter cet endpoint au cache du
service worker.

Apres la migration du blogue, deployer aussi ensemble les changements de:

```text
backend/lib/Scraper.php
backend/lib/bootstrap.php
backend/cron/check-updates.php
backend/config/config.example.php
```

Le scraper parcourt jusqu'a 10 pages du blogue, conserve jusqu'a 50 articles
et trie explicitement les dates anglaises ou francaises. La date du hash est
normalisee en `YYYY-MM-DD`, independamment du texte affiche. Le
`config.php` prive live doit garder `BLOG_BASELINE_MODE=false` en exploitation.

## 4. Creer ou migrer la base MySQL

Pour une nouvelle installation dans DirectAdmin/cPanel:

1. Creer une base MySQL.
2. Creer un utilisateur MySQL.
3. Associer l'utilisateur a la base avec tous les privileges.
4. Ouvrir phpMyAdmin.
5. Importer `database/schema.sql`.

Pour la production existante, ne pas reimporter `database/schema.sql`. Appliquer
uniquement:

```text
database/migrations/20260803_push_subscription_preferences.sql
```

Cette migration additive cree `push_subscription_preferences` et peut etre
rejouee sans recreer les abonnements.

Categories en production:

```text
site_updates
blog
offers
newsletter
```

Les abonnements historiques sans ligne de preference utilisent
`true/true/false/false` dans cet ordre. Les nouveaux utilisateurs voient les
quatre choix dans l'interface avant l'activation.

## 5. Configurer PHP

Copier:

```text
backend/config/config.example.php
```

vers:

```text
backend/config/config.php
```

Puis modifier:

```php
'DB_HOST' => 'localhost',
'DB_NAME' => 'votre_base',
'DB_USER' => 'votre_user',
'DB_PASS' => 'votre_mot_de_passe',
'APP_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca/pwa',
'FINAL_DOMAIN' => 'https://pilatesvitalite.ca',
'SOURCE_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca',
'SOURCE_APP_ENV_PATH' => '/chemin/absolu/prive/vers/le/.env',
'WATCH_URLS' => [
    'home' => 'https://pilatesvitalite.webactiondemo.ca/',
    'about' => 'https://pilatesvitalite.webactiondemo.ca/about-us',
    'blog' => 'https://pilatesvitalite.webactiondemo.ca/blogs',
    'contact' => 'https://pilatesvitalite.webactiondemo.ca/contact',
    'shop' => 'https://pilatesvitalite.webactiondemo.ca/shop',
    'membership' => 'https://pilatesvitalite.webactiondemo.ca/membership',
],
```

Au 2026-08-03, les URLs doivent rester sur:

```text
https://pilatesvitalite.webactiondemo.ca
```

Au lancement final seulement, remplacer `APP_BASE_URL`, `SOURCE_BASE_URL`,
`SOURCE_APP_ENV_PATH` et les URLs `WATCH_URLS` par leurs equivalents sous
`pilatesvitalite.ca`. Les abonnements Web Push sont lies a l'origine et les
utilisateurs devront normalement se reabonner.

## 6. Installer Web Push

Option A, si Composer est disponible sur cPanel:

```bash
cd public_html/pwa/backend
composer install --no-dev --optimize-autoloader
```

Option B, si Composer n'est pas disponible sur cPanel:

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Puis uploader le dossier genere:

```text
backend/vendor/
```

vers:

```text
public_html/pwa/backend/vendor/
```

## 7. Configurer les cles VAPID

Ajouter dans `backend/config/config.php`:

```php
'VAPID_PUBLIC_KEY' => '...',
'VAPID_PRIVATE_KEY' => '...',
'VAPID_SUBJECT' => 'mailto:info@pilatesvitalite.ca',
```

## 8. Configurer le cron DirectAdmin/cPanel

Dans DirectAdmin > Taches cron, ou dans cPanel > Cron Jobs:

```bash
/usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php
```

Frequence recommandee:

```text
*/10 * * * *
```

Le script utilise `flock` pour eviter deux executions simultanees. Suspendre la
tache pendant une mise a niveau backend et ne la reactiver qu'apres deux
passages manuels stables.

Etat historique avant la migration du blogue le 2026-08-03:

```text
ACTIVE_PWA=1
crontab: 5 lignes
passage automatique de 16:50:02:
changed=0 notifications=0 items=28
passages manuels de 17:35:56 et 17:36:29:
changed=0 notifications=0 items=28
passage automatique de 17:40:03 apres la mise a niveau Accueil:
changed=0 notifications=0 items=28
```

Etat courant apres traduction du blogue et optimisation PWA, confirme a
23:02:59:

```text
une seule entree cron PWA correspondante
28 articles de blogue distincts
changed=0 notifications=0 items=55
passage automatique 22:20:08: changed=0 notifications=0 items=55
passage manuel final 22:28:31: changed=0 notifications=0 items=55
amorcage silencieux des dates normalisees 23:02:35:
changed=28 notifications=0 items=55
passage normal 23:02:59: changed=0 notifications=0 items=55
passage automatique 23:10:07: changed=0 notifications=0 items=55
passage manuel apres correction de l'accueil 23:25:05:
changed=0 notifications=0 items=55
notification_logs: total=3, latest_id=3
abonnements actifs: 5
cache de contenu: 28 articles
```

## 9. Tester les endpoints

```text
https://pilatesvitalite.webactiondemo.ca/pwa/api/config.php
https://pilatesvitalite.webactiondemo.ca/pwa/api/latest.php
POST https://pilatesvitalite.webactiondemo.ca/pwa/api/preferences.php
```

`backend/config/config.php` doit rester inaccessible en HTTP avec un statut
403. `preferences.php` doit refuser les methodes non permises.

Etat mesure apres optimisation de `latest.php`:

```text
source=cache
28 articles
5 appels: 0,067 a 0,103 seconde au total
avant: 4,56 a 5,17 secondes
```

## 10. Tester une notification

L'endpoint de test contourne le filtrage par categorie et peut joindre tous les
abonnements actifs. Ne pas l'utiliser sur la production sans autorisation.

```bash
curl -X POST https://pilatesvitalite.webactiondemo.ca/pwa/api/notify-test.php \
  -H "Content-Type: application/json" \
  -H "X-Notify-Test-Secret: VOTRE_SECRET" \
  -d '{"title":"Pilates Vitalite","message":"Test de notification"}'
```

## 11. Tester le cron

Executer une fois depuis le terminal cPanel si disponible:

```bash
/usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php
```

Verifier ensuite:

```text
backend/storage/logs/
```

et les tables:

```text
content_snapshots
notification_logs
push_subscription_preferences
```

Etat valide le 2026-08-03:

```text
18 cours sous /shop
3 forfaits sous /membership
4 campagnes newsletter_campaigns avec status=sent
Second passage: changed=0 notifications=0 items=28
Passage automatique 16:50:02: changed=0 notifications=0 items=28
Passages apres la mise a niveau Accueil, 17:35:56 et 17:36:29:
changed=0 notifications=0 items=28
Passage automatique 17:40:03: changed=0 notifications=0 items=28
```

Etat valide apres la migration des articles:

```text
28 articles sous /blogs, repartis sur 4 pages: 9, 9, 9 et 1
Audit Laravel: 16/16 controles reussis
API PWA: 28 articles, source=live, plus recent en premier
Amorcage silencieux: changed=28 notifications=0 items=55
Passages normaux: changed=0 notifications=0 items=55
Passage automatique 22:20:08: changed=0 notifications=0 items=55
Passage manuel final 22:28:31: changed=0 notifications=0 items=55
```

Le premier amorcage a envoye une notification Accueil a 5 abonnes a cause
d'une formule de hash temporairement incompatible. La formule historique a ete
restauree et le snapshot transitoire retire. Aucun nouvel envoi n'a eu lieu au
second passage ni au passage automatique de 16:50:02. Le cron a ensuite ete
reactive.

Le premier controle manuel apres traduction des dates du blogue a aussi produit
un faux positif avant la normalisation: 28 notifications journalisees et 112
envois acceptes par le service push. Le cron etait suspendu. Les 28 journaux et
28 snapshots ont ete sauvegardes puis retires par transaction, l'abonnement
desactive pendant ce passage a ete restaure, puis un amorcage silencieux et un
passage normal ont ramene l'etat a 3 journaux, 5 abonnements actifs et
`changed=0 notifications=0 items=55`. Les dates des hashes sont maintenant en
`YYYY-MM-DD`. Les preuves sont dans la sauvegarde
`blog-ui-pwa-perf-20260803-225319`.

## 12. Rollback

1. suspendre le cron;
2. restaurer les fichiers backend/API depuis la sauvegarde serveur; pour la
   migration du blogue, utiliser
   `/home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15`;
3. restaurer `index.html` et les anciens assets conserves;
4. publier un `sw.js` de rollback avec un nouveau nom de cache;
5. laisser la table additive de preferences en place: l'ancien code l'ignore;
6. lancer le cron manuellement deux fois avant de le reactiver.
