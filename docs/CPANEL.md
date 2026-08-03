# Guide de deploiement cPanel

## 1. Compiler le frontend

```bash
cd frontend
npm install
npm run build
```

## 2. Uploader le frontend

Uploader le contenu de `frontend/dist/` dans le dossier public de la PWA:

```text
domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/
```

L'app doit etre accessible en HTTPS, par exemple:

```text
https://pilatesvitalite.webactiondemo.ca/pwa/
```

Toujours uploader `index.html` et tout le dossier `assets/` depuis le meme
build Vite. Sinon, le navigateur peut charger un nom de fichier JavaScript qui
n'existe pas et afficher une page vide.

## 3. Uploader le backend

Uploader:

```text
api/      -> public_html/pwa/api/
backend/  -> public_html/pwa/backend/
```

Conserver `backend/.htaccess` pour bloquer l'acces web direct aux fichiers backend.

## 4. Creer la base MySQL

Dans cPanel:

1. Creer une base MySQL.
2. Creer un utilisateur MySQL.
3. Associer l'utilisateur a la base avec tous les privileges.
4. Ouvrir phpMyAdmin.
5. Importer `database/schema.sql`.

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
```

Pendant le developpement, les URLs peuvent rester sur:

```text
https://pilatesvitalite.webactiondemo.ca
```

Au lancement, remplacer les URLs `WATCH_URLS` par le domaine final.

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

## 8. Configurer le cron

Dans cPanel > Cron Jobs:

```bash
/usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php
```

Frequence recommandee:

```text
*/10 * * * *
```

## 9. Tester les endpoints

```text
https://pilatesvitalite.webactiondemo.ca/pwa/api/config.php
https://pilatesvitalite.webactiondemo.ca/pwa/api/latest.php
```

## 10. Tester une notification

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
```
