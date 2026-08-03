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
- Abonnement et desabonnement aux notifications.
- Detection automatique des changements Accueil, A propos et Blogue.
- Envoi Web Push avec VAPID.
- PHP/MySQL et cron compatibles cPanel.

## 2. Architecture retenue

```text
frontend/                 React 18 + TypeScript + Vite + Tailwind
frontend/public/          Manifest, service worker et icones PWA
api/                      Endpoints PHP publics
backend/config/           Configuration serveur
backend/lib/              DB, scraper, snapshots et Web Push
backend/cron/             Detection periodique des changements
database/schema.sql       Schema MySQL
docs/                     Documentation et passation
```

La production n'utilise pas Node.js. Node sert uniquement a generer le build
statique `frontend/dist/`.

## 3. Site source et extraction

Site actuellement surveille:

```text
https://pilatesvitalite.webactiondemo.ca/
```

Domaine final prevu:

```text
https://pilatesvitalite.ca/
```

Pages publiques configurees:

```text
Accueil:  https://pilatesvitalite.webactiondemo.ca/
A propos: https://pilatesvitalite.webactiondemo.ca/about-us
Blogue:   https://pilatesvitalite.webactiondemo.ca/blogs
Contact:  https://pilatesvitalite.webactiondemo.ca/contact
```

Le site ressemble a une application Laravel ou un CMS PHP personnalise et ne
fournit pas d'API JSON publique exploitable. `backend/lib/Scraper.php` lit donc
le HTML public avec DOMDocument/DOMXPath.

Le cron calcule un hash pour:

- la page Accueil;
- la page A propos;
- chaque article de blogue trouve.

Le premier passage cree les snapshots sans envoyer de notification lorsque
`NOTIFY_ON_FIRST_RUN` vaut `false`. Les hashes et journaux evitent les doublons.

## 4. Etat actuel de l'interface

- Palette inspiree du site: bleu et orange.
- Le hero Accueil affiche le logo `icons/pilates-logo.png`.
- Le hero affiche le titre, le resume, la phrase d'introduction, les trois
  benefices numerotes et `Et plus encore!`.
- Les liens Cours en ligne, Cours en studio et Cours prive ouvrent les pages du
  site complet.
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
```

Ces trois URLs retournaient HTTP 200 lors de la mise a jour de cette passation
le 2026-08-03.

## 6. Emplacement cPanel reel

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

## 7. Build et deploiement frontend

Depuis PowerShell ou un terminal avec Node.js:

```powershell
cd C:\Users\marca\OneDrive\Desktop\pilatesvitalite-app\frontend
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

## 8. Deploiement backend PHP

Correspondance locale vers cPanel:

```text
api/      -> public_html/pwa/api/
backend/  -> public_html/pwa/backend/
```

Pour un changement cible, uploader seulement le fichier PHP modifie au meme
emplacement relatif.

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
- `content_snapshots`: versions et hashes des contenus surveilles.
- `notification_logs`: journal et cle anti-doublon des notifications.

Le schema a deja ete importe sur le serveur. Ne pas le reimporter aveuglement
sur une base de production contenant des donnees.

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
- `SOURCE_BASE_URL`: site dont le contenu est lu.
- `WATCH_URLS`: Accueil, A propos, Blogue et Contact.
- `VAPID_PUBLIC_KEY` et `VAPID_PRIVATE_KEY`.
- `VAPID_SUBJECT`.
- `CRON_SECRET` et `NOTIFY_TEST_SECRET`.
- `NOTIFY_ON_FIRST_RUN`: normalement `false`.
- `ENABLE_PUSH_SEND`: normalement `true`.

## 11. Cron cPanel

Frequence configuree: toutes les 10 minutes.

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

Sortie normale sans changement:

```text
Unchanged: home/home
Unchanged: about/about
Unchanged: blog/{slug}
Finished content check. changed=0 notifications=0 items=3
```

Les logs sont ecrits dans:

```text
backend/storage/logs/cron-YYYY-MM.log
```

## 12. Notifications Web Push

Flux:

1. L'utilisateur clique `Activer les notifications`.
2. Le service worker cree un abonnement navigateur.
3. `api/subscribe.php` l'enregistre dans MySQL.
4. Le cron detecte un changement de hash.
5. `PushService` envoie la notification avec les cles VAPID.
6. Les abonnements invalides sont desactives.

Texte des notifications automatiques:

- Blogue nouveau: `Nouvel article Pilates Vitalite` avec le titre de l'article.
- Blogue modifie: `Article mis a jour`.
- A propos: `Mise a jour - A propos`.
- Accueil: `Mise a jour - Accueil`.

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

## 14. Test historique du cron

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
   `APP_BASE_URL`, `FINAL_DOMAIN`, `SOURCE_BASE_URL` et `WATCH_URLS`.
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
php -l backend/lib/Scraper.php
php -l backend/cron/check-updates.php
```

Ne pas considerer une tache terminee si un build frontend demande n'a pas ete
execute ou si un fichier PHP modifie n'a pas ete valide quand PHP est disponible.

## 18. Demarrage d'un nouveau chat Codex

Ouvrir le dossier suivant comme workspace:

```text
C:\Users\marca\OneDrive\Desktop\pilatesvitalite-app
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

