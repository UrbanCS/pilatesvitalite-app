# Guide notifications push

## Fonctionnement

1. L'utilisateur ouvre la PWA en HTTPS.
2. Il clique sur `Activer les notifications`.
3. Le navigateur demande la permission.
4. Le service worker cree un abonnement Web Push.
5. `/api/subscribe.php` sauvegarde `endpoint`, `p256dh` et `auth` en MySQL.
6. Le cron detecte un changement et envoie une notification via VAPID.
7. Les abonnements invalides ou expires sont desactives automatiquement.

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

## Notification test

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
  "url": "https://pilatesvitalite.ca/app/"
}
```

## Anti-doublons

Le cron compare `content_hash` avec le dernier snapshot. Une notification est journalisee dans `notification_logs` avec `section`, `item_key` et `content_hash`. La meme notification ne sera pas envoyee deux fois.

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

