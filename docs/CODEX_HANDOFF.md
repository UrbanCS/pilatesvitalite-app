# Passation Codex - Pilates Vitalite PWA

Derniere mise a jour de cette passation: 2026-08-03.

Ce fichier est la source de contexte principale pour tout nouveau chat Codex.
Il documente les decisions prises, l'etat connu du projet, le deploiement cPanel
et les tests effectues. Les secrets ne sont volontairement pas inclus.

## 1. Objectif du produit

Construire une PWA legere pour Pilates Vitalite, sans application native et
sans reconstruire le site complet. Elle lit les contenus publics du site et
offre une interface mobile propre avec notifications Web Push.

Fonctions du MVP:

- Accueil, Blogue, A propos et Contact.
- Detail d'un article de blogue.
- Installation PWA sur Android/Windows.
- Instructions d'installation pour iPhone/iPad.
- Abonnement, desabonnement et choix des categories de notifications.
- Detection automatique des changements Accueil, A propos et Blogue.
- Detection des nouveaux cours, forfaits et campagnes infolettre envoyees.
- Carte du prochain cours en studio, acces a tous les cours et au compte client.
- Mise en avant de la nouvelle de blogue la plus recente sur l'accueil.
- Envoi Web Push avec VAPID.
- PHP/MySQL et cron compatibles DirectAdmin/cPanel.

## 2. Architecture retenue

```text
frontend/                 React 18 + TypeScript + Vite + Tailwind
frontend/public/          Manifest, service worker et icones PWA
api/                      Endpoints PHP publics
backend/config/           Configuration serveur
backend/lib/              DB, scraper, snapshots et Web Push
backend/cron/             Detection periodique des changements
database/schema.sql       Schema MySQL
database/migrations/      Migrations additives de production
docs/                     Documentation et passation
```

La production n'utilise pas Node.js. Node sert uniquement a generer le build
statique `frontend/dist/`.

## 3. Site source et extraction

Site actuellement surveille:

```text
https://pilatesvitalite.webactiondemo.ca/
```

Cette source demo est bien la source active de la PWA au 2026-08-03. Le
basculement vers le domaine final est une operation future distincte.

Domaine final prevu, mais pas encore utilise comme source:

```text
https://pilatesvitalite.ca/
```

Pages publiques configurees:

```text
Accueil:  https://pilatesvitalite.webactiondemo.ca/
A propos: https://pilatesvitalite.webactiondemo.ca/about-us
Blogue:   https://pilatesvitalite.webactiondemo.ca/blogs
Contact:  https://pilatesvitalite.webactiondemo.ca/contact
Cours:    https://pilatesvitalite.webactiondemo.ca/shop
Forfaits: https://pilatesvitalite.webactiondemo.ca/membership
```

Le site ressemble a une application Laravel ou un CMS PHP personnalise et ne
fournit pas d'API JSON publique exploitable. `backend/lib/Scraper.php` lit donc
le HTML public avec DOMDocument/DOMXPath.

Le cron calcule un hash pour:

- la page Accueil;
- la page A propos;
- chaque article de blogue trouve.
- chaque produit/cours trouve dans la boutique paginee;
- chaque forfait trouve dans la page des abonnements;
- chaque campagne infolettre dont le statut est `sent`.

Etat des sources additionnelles valide le 2026-08-03:

- 18 produits/cours uniques sous `/shop`;
- 3 forfaits uniques sous `/membership`;
- 4 campagnes infolettre envoyees dans `newsletter_campaigns`.

Les campagnes ne sont pas exposees par une archive HTML publique. Le cron lit
donc uniquement les colonnes `id`, `subject`, `status`, `sent_at` et
`updated_at` dans la base du site source. Le chemin de son fichier
d'environnement est configure par `SOURCE_APP_ENV_PATH` dans le
`config.php` prive. La requete est un `SELECT` en lecture seule et les
valeurs sensibles ne sont ni retournees par l'API ni ecrites dans les logs.

Le premier passage cree les snapshots sans envoyer de notification lorsque
`NOTIFY_ON_FIRST_RUN` vaut `false`. Les hashes et journaux evitent les doublons.

Le cron extrait aussi les variantes publiees dans les fiches de la categorie
`en-studio` et ecrit le prochain cours disponible dans
`backend/storage/next-course.json`. Cette donnee n'entre pas dans les hashes de
surveillance et ne declenche donc aucune notification. Aucune heure n'est
deduite des SKU, car ceux-ci ne concordent pas toujours avec les dates. Au
2026-08-03, aucun cours studio futur n'est publie et l'API retourne
`nextCourse: null`.

## 4. Etat actuel de l'interface

- Palette inspiree du site: bleu et orange.
- Le hero Accueil affiche le logo `icons/pilates-logo.png`.
- Le texte `Ameliorez - Soulagez - Musclez`, le resume et les trois
  benefices ont ete retires visuellement du haut de l'application.
- Le hero conserve le logo et les liens vers les cours.
- L'accueil affiche le prochain cours en studio ou un etat vide explicite.
- Les boutons `Tous les cours` et `Compte client` ouvrent respectivement
  `/shop` et `/login` sur le site source actif.
- Une seule carte, la nouvelle de blogue la plus recente, est affichee sur
  l'accueil; la page Blogue conserve la liste complete.
- Les liens Cours en ligne, Cours en studio et Cours prive ouvrent les pages du
  site complet.
- La carte `Mes notifications` permet de choisir quatre categories:
  mises a jour du site, blogue, cours et forfaits, infolettre.
- Le bouton d'installation utilise `beforeinstallprompt` sur Android/Windows.
- Sur iPhone/iPad, le bouton affiche les instructions Safari:
  Partager, Sur l'ecran d'accueil, Ajouter.
- La barre Accueil/Blogue/A propos/Contact est pleine largeur, fixee au bas et
  tient compte de la safe area iPhone. Elle ne doit pas devenir flottante.
- Le manifest utilise `/pwa/` comme `id`, `start_url` et `scope`.
- Les icones PNG 192, 512 et maskable sont dans `frontend/public/icons/`.

## 5. URLs live de la PWA

```text
Application: https://pilatesvitalite.webactiondemo.ca/pwa/
Config API:  https://pilatesvitalite.webactiondemo.ca/pwa/api/config.php
Contenu API: https://pilatesvitalite.webactiondemo.ca/pwa/api/latest.php
Preferences: https://pilatesvitalite.webactiondemo.ca/pwa/api/preferences.php
```

L'application, `config.php` et `latest.php` retournent HTTP 200 apres le
deploiement du 2026-08-03. `latest.php` expose la cle nullable `nextCourse` et
conserve `site.fullSiteUrl=https://pilatesvitalite.webactiondemo.ca`.
`preferences.php` est un endpoint POST et refuse
les methodes non permises. L'acces web direct a
`backend/config/config.php` retourne HTTP 403.

## 6. Emplacement DirectAdmin/cPanel reel

L'hebergement est administre avec l'interface DirectAdmin Evolution. Le terme
cPanel reste utilise dans certains documents historiques, mais le chemin
serveur suivant est la reference.

Le dossier correct est directement sous `public_html`:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/
```

Ce chemin est important. Une tentative precedente dans
`public_html/public/pwa/` produisait une page vide ou une mauvaise resolution de
route. Le deploiement fonctionnel est `public_html/pwa/`.

Structure live attendue:

```text
public_html/pwa/
|-- .htaccess
|-- index.html
|-- index.php
|-- manifest.webmanifest
|-- sw.js
|-- assets/
|-- icons/
|-- api/
`-- backend/
```

Sauvegardes creees avant la mise a niveau des notifications:

```text
Serveur: /home/pilatesvitalite/codex-backups/pwa-notification-upgrade-20260803
Local:   C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-notification-upgrade-20260803-161209.zip
```

Sauvegardes creees avant la mise a niveau de l'accueil et du prochain cours:

```text
Serveur: /home/pilatesvitalite/codex-backups/pwa-home-upgrade-20260803-172534
Local:   C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-home-course-login-20260803-172534.zip
```

Archive finale de deploiement:

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

## 7. Build et deploiement frontend

Depuis PowerShell ou un terminal avec Node.js:

```powershell
cd C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app\frontend
npm install
npm run build
```

Le build est genere dans:

```text
frontend/dist/
```

Uploader le CONTENU de `frontend/dist/`, pas le dossier `dist` lui-meme, vers:

```text
domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/
```

Fichiers/dossiers frontend a deployer:

```text
frontend/dist/.htaccess
frontend/dist/index.html
frontend/dist/index.php
frontend/dist/manifest.webmanifest
frontend/dist/sw.js
frontend/dist/assets/
frontend/dist/icons/
```

Regle critique Vite:

- Toujours uploader `index.html` et tout le dossier `assets/` ensemble.
- `index.html` reference des fichiers hashes comme `index-ABC123.js`.
- Si le nouvel `index.html` est en ligne sans le JS correspondant, la PWA
  affiche seulement un fond vide.
- Si seuls les assets sont changes sans le nouvel `index.html`, l'ancienne
  version reste chargee.

Ne pas supprimer ni ecraser les dossiers `api/` et `backend/` pendant un simple
deploiement frontend.

Apres deploiement, fermer/reouvrir la PWA sur mobile. Le service worker peut
garder une ancienne version pendant un court moment. En diagnostic, verifier
que le JS reference dans le HTML retourne HTTP 200.

Build actuellement deploye le 2026-08-03:

```text
frontend/dist/assets/index-BTcxeBIp.js
frontend/dist/assets/index-BY6eFtrM.css
```

Le cache du service worker est
`pilates-vitalite-shell-v20260803-2`. Il ne supprime que les anciens caches
portant le prefixe Pilates. `.htaccess` impose `no-cache` a `sw.js`,
`index.html`, `index.php` et `manifest.webmanifest`.

## 8. Deploiement backend PHP

Correspondance locale vers cPanel:

```text
api/      -> public_html/pwa/api/
backend/  -> public_html/pwa/backend/
```

Pour un changement cible, uploader seulement le fichier PHP modifie au meme
emplacement relatif.

Fichiers ajoutes par la mise a niveau du 2026-08-03:

```text
api/preferences.php
backend/lib/NotificationPreferences.php
backend/lib/SourceNewsletterRepository.php
database/migrations/20260803_push_subscription_preferences.sql
```

Fichier ajoute par la mise a niveau de l'accueil:

```text
backend/lib/NextCourseCache.php
```

Le cron actualise ce cache toutes les 10 minutes. Une erreur d'extraction
conserve le dernier cache valide et n'interrompt pas la surveillance des 28
contenus existants.

La migration SQL est appliquee avec phpMyAdmin ou le terminal avant le nouveau
backend. Ne jamais uploader ou reimporter `database/schema.sql` dans une base
de production existante.

Ne jamais ecraser ce fichier live:

```text
public_html/pwa/backend/config/config.php
```

Il contient les identifiants MySQL, les cles VAPID et les secrets. Le depot ne
contient que `backend/config/config.example.php`.

La librairie Composer Web Push est installee dans `backend/vendor/` sur le
serveur. Si elle doit etre reinstallee localement:

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Puis uploader `backend/vendor/` vers le serveur. Le dossier vendor n'est pas
versionne dans Git.

## 9. Base MySQL

Etat de deploiement connu:

```text
Hote:        localhost
Base:        pilatesvitalite_pwa
Utilisateur: pilatesvitalite_pwa
```

Le mot de passe ne doit jamais etre ajoute au depot ou a cette passation.

Schema:

```text
database/schema.sql
```

Tables:

- `push_subscriptions`: abonnements Web Push actifs/inactifs.
- `push_subscription_preferences`: choix par abonnement et categorie.
- `content_snapshots`: versions et hashes des contenus surveilles.
- `notification_logs`: journal et cle anti-doublon des notifications.

Le schema a deja ete importe sur le serveur. Ne pas le reimporter aveuglement
sur une base de production contenant des donnees.

Migration additive appliquee pour les preferences:

```text
database/migrations/20260803_push_subscription_preferences.sql
```

## 10. Configuration serveur

Fichier modele:

```text
backend/config/config.example.php
```

Fichier live prive:

```text
public_html/pwa/backend/config/config.php
```

Valeurs importantes:

- `APP_BASE_URL`: URL de la PWA, actuellement le domaine demo avec `/pwa`.
- `FINAL_DOMAIN`: `https://pilatesvitalite.ca`.
- `SOURCE_BASE_URL`: site dont le contenu est lu, actuellement le domaine
  demo.
- `SOURCE_APP_ENV_PATH`: chemin absolu prive du `.env` de l'application
  source, utilise uniquement pour la lecture des campagnes infolettre.
- `WATCH_URLS`: Accueil, A propos, Blogue, Contact, Boutique et Forfaits.
- `VAPID_PUBLIC_KEY` et `VAPID_PRIVATE_KEY`.
- `VAPID_SUBJECT`.
- `CRON_SECRET` et `NOTIFY_TEST_SECRET`.
- `NOTIFY_ON_FIRST_RUN`: normalement `false`.
- `ENABLE_PUSH_SEND`: normalement `true`.

## 11. Cron DirectAdmin/cPanel

Frequence configuree: toutes les 10 minutes.

Etat final confirme le 2026-08-03:

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

Expression:

```text
*/10 * * * *
```

Commande live:

```bash
/usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php >> /dev/null 2>&1
```

Commande manuelle avec sortie visible:

```bash
/usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php
```

Sortie stable validee au second passage du deploiement 2026-08-03:

```text
Unchanged: home/home
Unchanged: about/about
Unchanged: blog/{slug}
Unchanged: course/product:{slug}
Unchanged: package/plan:{id}
Unchanged: newsletter/campaign:{id}
Finished content check. changed=0 notifications=0 items=28
```

Les logs sont ecrits dans:

```text
backend/storage/logs/cron-YYYY-MM.log
```

Le premier amorcage de cette mise a niveau a envoye une notification Accueil a
5 abonnes parce qu'une formule de hash elargie n'etait pas compatible avec le
snapshot historique. La formule historique a ete retablie et le snapshot
transitoire a ete retire. Le second passage a confirme
`changed=0 notifications=0 items=28`, sans nouvel envoi. Ne pas elargir une
formule de hash existante sans plan de migration des snapshots.

## 12. Notifications Web Push

Flux:

1. L'utilisateur choisit ses categories et clique
   `Activer les notifications`.
2. Le service worker cree un abonnement navigateur.
3. `api/subscribe.php` enregistre l'abonnement et ses choix dans MySQL.
4. Le cron detecte un changement de hash.
5. `PushService` filtre les destinataires selon la categorie et envoie la
   notification avec les cles VAPID.
6. Les abonnements invalides sont desactives.

Categories:

- `site_updates`: Accueil et A propos;
- `blog`: nouveaux articles et articles modifies;
- `offers`: nouveaux cours et nouveaux forfaits;
- `newsletter`: nouvelles campagnes dont le statut devient `sent`.

Pour les abonnements historiques sans choix explicite, les valeurs par defaut
sont `true/true/false/false` dans l'ordre ci-dessus. Les nouveaux utilisateurs
voient les quatre choix dans l'interface et peuvent les modifier avant
l'activation. `api/preferences.php` permet ensuite de lire et sauvegarder les
choix.

Texte des notifications automatiques:

- Blogue nouveau: `Nouvel article Pilates Vitalite` avec le titre de l'article.
- Blogue modifie: `Article mis a jour`.
- A propos: `Mise a jour - A propos`.
- Accueil: `Mise a jour - Accueil`.
- Cours: `Nouveau cours Pilates Vitalite`.
- Forfait: `Nouveau forfait Pilates Vitalite`.
- Infolettre: `Nouvelle infolettre Pilates Vitalite`.

Le service worker ouvre la page correspondante dans la PWA lorsque
l'utilisateur touche la notification.

Compatibilite:

- Android Chrome/Edge: installation et notifications bien supportees.
- Windows Chrome/Edge: installation PWA via le prompt du navigateur.
- iPhone/iPad: la PWA doit generalement etre ajoutee a l'ecran d'accueil avant
  l'utilisation des notifications Web Push. HTTPS est obligatoire.

## 13. Test de notification

Endpoint:

```text
POST https://pilatesvitalite.webactiondemo.ca/pwa/api/notify-test.php
```

Commande depuis le terminal cPanel:

```bash
curl -X POST "https://pilatesvitalite.webactiondemo.ca/pwa/api/notify-test.php" \
  -H "Content-Type: application/json" \
  -H "X-Notify-Test-Secret: VOTRE_SECRET_NOTIFY_TEST" \
  -d '{"title":"Pilates Vitalite","message":"Test de notification","url":"https://pilatesvitalite.webactiondemo.ca/pwa/"}'
```

Lire le vrai secret dans le fichier de configuration live; ne pas le copier
dans Git ou dans un chat public.

Test historique confirme le 2026-05-15:

```json
{"ok":true,"result":{"sent":2,"failed":0,"errors":[]}}
```

La notification a ete recue immediatement sur telephone.

## 14. Tests du cron

Test manuel confirme le 2026-05-15:

```text
Starting content check.
Unchanged: home/home
Unchanged: about/about
Unchanged: blog/la-sant-osseuse-pas-quune-affaire-de-femmes
Finished content check. changed=0 notifications=0 items=3
```

Cela confirme historiquement la lecture du site, la connexion MySQL, la
comparaison des hashes et l'absence de notification en double. Refaire les tests
pour confirmer l'etat courant si une intervention serveur est demandee.

Validation de la mise a niveau du 2026-08-03:

```text
18 cours + 3 forfaits + 4 campagnes infolettre
Second passage: changed=0 notifications=0 items=28
Passage automatique 16:50:02: changed=0 notifications=0 items=28
Mise a niveau Accueil, passages 17:35:56 et 17:36:29:
changed=0 notifications=0 items=28
Passage automatique 17:40:03: changed=0 notifications=0 items=28
Cron reactive: ACTIVE_PWA=1, crontab de 5 lignes
```

Incident connu du premier passage:

```text
1 notification Accueil envoyee a 5 abonnes
Cause: formule de hash Accueil temporairement elargie
Correction: formule historique restauree et snapshot transitoire retire
Verification: aucun nouvel envoi au second passage
```

## 15. Diagnostic rapide

### Ecran vide apres un upload

Cause la plus frequente: `index.html` et `assets/` ne proviennent pas du meme
build.

Verifier le script reference:

```bash
curl -s https://pilatesvitalite.webactiondemo.ca/pwa/ | grep assets
```

Puis verifier qu'il existe:

```bash
curl -I https://pilatesvitalite.webactiondemo.ca/pwa/assets/NOM_DU_FICHIER.js
```

Re-uploader le `index.html` et tout `assets/` depuis le meme `frontend/dist/`.

### Mobile garde l'ancienne version

- Uploader `sw.js` avec le build.
- Fermer completement Safari/Chrome ou la PWA installee.
- Reouvrir la page.
- Au besoin, vider les donnees du site ou desinstaller/reinstaller la PWA.

### Test push retourne `sent=0`

- Verifier `push_subscriptions.is_active = 1`.
- Recliquer `Activer les notifications` sur l'appareil.
- Verifier les cles VAPID et `backend/vendor/autoload.php`.

### Cron refuse MySQL

- Verifier les valeurs `DB_NAME`, `DB_USER`, `DB_PASS`.
- Verifier que l'utilisateur MySQL a tous les privileges sur la base.

## 16. Passage au domaine final

Quand `https://pilatesvitalite.ca/` sera lance:

1. Deployer la PWA sous `https://pilatesvitalite.ca/pwa/` ou confirmer un autre
   chemin avant de modifier le manifest.
2. Mettre a jour dans le config live:
   `APP_BASE_URL`, `FINAL_DOMAIN`, `SOURCE_BASE_URL`,
   `SOURCE_APP_ENV_PATH` et `WATCH_URLS`.
3. Si le chemin change, mettre a jour `id`, `start_url` et `scope` dans
   `frontend/public/manifest.webmanifest`, puis rebuilder.
4. Conserver les memes cles VAPID si l'on souhaite maintenir la configuration,
   mais les abonnements push sont lies a l'origine web. Un changement de domaine
   exigera normalement une nouvelle inscription des utilisateurs.
5. Refaire les tests API, abonnement, notification test et cron.

## 17. Commandes de verification locale

Frontend:

```bash
cd frontend
npm install
npm run build
```

PHP, selon l'environnement:

```bash
php -l api/subscribe.php
php -l api/preferences.php
php -l backend/lib/Scraper.php
php -l backend/lib/SourceNewsletterRepository.php
php -l backend/cron/check-updates.php
```

Ne pas considerer une tache terminee si un build frontend demande n'a pas ete
execute ou si un fichier PHP modifie n'a pas ete valide quand PHP est disponible.

## 18. Demarrage d'un nouveau chat Codex

Ouvrir le dossier suivant comme workspace:

```text
C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app
```

Puis envoyer ce message:

```text
Lis AGENTS.md et docs/CODEX_HANDOFF.md au complet avant de travailler. Resume-moi
l'etat du projet et confirme le chemin de deploiement cPanel. Ne modifie rien
avant d'avoir lu ces deux fichiers.
```

`AGENTS.md` est charge automatiquement par Codex lorsqu'il travaille dans ce
depot. Ce document detaille complete la passation et remplace le besoin de
recopier tout l'ancien chat.
