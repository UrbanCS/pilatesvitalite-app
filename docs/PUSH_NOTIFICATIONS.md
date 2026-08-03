# Guide notifications push

## Fonctionnement

1. L'utilisateur ouvre la PWA en HTTPS.
2. Il choisit les categories souhaitees et clique sur
   `Activer les notifications`.
3. Le navigateur demande la permission.
4. Le service worker cree un abonnement Web Push.
5. `/api/subscribe.php` sauvegarde `endpoint`, `p256dh`, `auth` et les
   preferences en MySQL.
6. `/api/preferences.php` permet de relire et modifier les choix.
7. Le cron detecte un changement, filtre les destinataires selon la categorie
   et envoie une notification via VAPID.
8. Les abonnements invalides ou expires sont desactives automatiquement.

## Categories et valeurs par defaut

Les categories serveur sont:

- `site_updates`: Accueil et A propos;
- `blog`: nouveaux articles et articles modifies;
- `offers`: nouveaux cours et nouveaux forfaits;
- `newsletter`: nouvelles campagnes infolettre envoyees.

Les abonnements historiques qui ne possedent encore aucune ligne de preference
utilisent `true/true/false/false` dans cet ordre. Ils continuent donc a recevoir
les mises a jour historiques sans etre inscrits automatiquement aux nouvelles
categories commerciales.

Pour un nouvel abonnement, l'interface affiche les quatre cases et l'utilisateur
peut les modifier avant d'accorder la permission. Au moins une categorie doit
rester active, sinon l'utilisateur peut choisir
`Desactiver toutes les notifications`.

Les preferences sont stockees dans:

```text
push_subscription_preferences
```

Migration de production:

```text
database/migrations/20260803_push_subscription_preferences.sql
```

## Sources surveillees

La source active au 2026-08-03 est
`https://pilatesvitalite.webactiondemo.ca`. Le futur passage a
`https://pilatesvitalite.ca` est une migration distincte.

- `/shop`: 18 cours/produits uniques trouves sur les pages de boutique;
- `/membership`: 3 forfaits uniques, dedupliques par `data-plan_id`;
- base source: 4 campagnes `newsletter_campaigns` avec `status=sent`.

Les campagnes infolettre sont lues par `SourceNewsletterRepository` a partir du
chemin absolu prive `SOURCE_APP_ENV_PATH`. La requete utilise uniquement
`SELECT id, subject, status, sent_at, updated_at`. Les identifiants de la base,
le contenu du `.env` et les erreurs SQL ne doivent jamais etre exposes par une
API ou copies dans les logs.

## Cles VAPID

La cle publique est exposee au frontend via:

```text
/api/config.php
```

La cle privee reste uniquement dans:

```text
backend/config/config.php
```

Avec `minishlink/web-push`, vous pouvez generer les cles en PHP avec la documentation officielle de la librairie, ou utiliser un generateur VAPID fiable cote serveur. Ne jamais generer ni exposer la cle privee dans React.

## Android

Android Chrome supporte tres bien les PWA installees et les notifications Web Push, si le site est en HTTPS et que l'utilisateur accorde la permission.

## iPhone / iOS

iOS supporte les PWA installees sur l'ecran d'accueil. Les notifications Web Push sur iPhone exigent notamment:

- iOS compatible avec Web Push pour apps web;
- PWA ajoutee a l'ecran d'accueil;
- HTTPS;
- permission demandee apres une action utilisateur;
- service worker fonctionnel.

Un utilisateur iPhone peut devoir installer la PWA avant que les notifications soient disponibles.

## Service worker et navigation

Le service worker deploye utilise:

```text
pilates-vitalite-shell-v20260803-2
```

Il valide les URLs de notification en HTTPS et limite la navigation aux
domaines Pilates autorises. Son activation ne supprime que les anciens caches
portant le prefixe `pilates-vitalite-shell-`. Le `.htaccess` impose
`no-cache` au service worker et aux fichiers d'entree de l'application.

## Notification test

Attention: l'endpoint de test n'applique pas le filtrage par categorie. Il peut
envoyer a tous les abonnements actifs et ne doit etre utilise en production
qu'avec une autorisation explicite.

Endpoint:

```text
POST /api/notify-test.php
```

Headers:

```text
Content-Type: application/json
X-Notify-Test-Secret: votre_secret
```

Body:

```json
{
  "title": "Pilates Vitalite",
  "message": "Notification test",
  "url": "https://pilatesvitalite.webactiondemo.ca/pwa/"
}
```

## Anti-doublons

Le cron compare `content_hash` avec le dernier snapshot. Une notification est journalisee dans `notification_logs` avec `section`, `item_key` et `content_hash`. La meme notification ne sera pas envoyee deux fois.

`flock` empeche deux executions simultanees du cron. Les cours, forfaits et
campagnes existants sont initialises sans notification; seuls les nouveaux
elements sont ensuite notifies.

Validation stable du 2026-08-03:

```text
changed=0 notifications=0 items=28
passage automatique de 16:50:02: changed=0 notifications=0 items=28
cron actif: ACTIVE_PWA=1, crontab de 5 lignes
```

Au premier amorcage de la mise a niveau, une formule de hash Accueil
temporairement elargie a provoque une notification a 5 abonnes. La formule
historique a ete restauree et le snapshot transitoire retire. Le second passage
n'a produit aucun nouvel envoi. Le passage automatique de 16:50:02 a confirme
le meme resultat avant la confirmation finale du cron actif.

## Composer non disponible sur cPanel

Installer les dependances localement:

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Uploader ensuite:

```text
backend/vendor/
```

sur cPanel.
