# Migration du blogue WordPress vers le site Laravel

Date d'execution: 2026-08-03 (America/Toronto).

## Resultat

Les 28 articles publies de l'ancien blogue WordPress
`https://pilatesvitalite.ca/blogue/` ont ete copies dans le nouveau site
Laravel sous `https://pilatesvitalite.webactiondemo.ca/blogs`.

L'ancien site WordPress n'a pas ete modifie. La PWA et son cron continuent de
lire le domaine de demonstration. Le passage a `https://pilatesvitalite.ca`
reste une operation future distincte.

Etat final verifie:

- 28 articles Laravel uniques, repartis sur 4 pages: 9, 9, 9 et 1;
- ordre chronologique decroissant, avec `cours-virtuel-pilates-pour-tous` en
  premier;
- slug corrige et canonique:
  `la-sante-osseuse-pas-quune-affaire-de-femmes`;
- 28 images principales locales et tous les medias integres localises;
- 4 references d'images integrees et 4 references audio vers 2 MP3 uniques;
- aucun lien media restant vers `wp-content/uploads`;
- les 3 articles les plus recents sont marques pour l'accueil;
- les dates publiques sont en francais et la fiche ne montre plus d'auteur;
- l'API PWA retourne 28 articles, normalement `source=cache`, et
  `site.fullSiteUrl=https://pilatesvitalite.webactiondemo.ca`;
- le cache serveur reduit cinq appels mesures de 4,56-5,17 secondes a
  0,067-0,103 seconde;
- le cron traite 55 elements au total et termine avec
  `changed=0 notifications=0`;
- les journaux de notification sont restes a 3, avec `latest_id=3`;
- 5 abonnements push actifs sont restes intacts.

## Emplacements serveur

Racine du nouveau site Laravel:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html
```

Racine de la PWA:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa
```

Medias importes:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/public/uploads/wordpress-blog/
```

Manifest d'import Laravel:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/storage/app/imports/wordpress-blog-20260804-015754-2fd3c382.json
```

Le manifest contient aussi les correspondances de redirection SEO. Ces
redirections ne doivent etre activees qu'au basculement du domaine final.

## Sauvegarde et retour arriere

Sauvegarde privee creee avant toute modification applicative:

```text
/home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15
```

Elle contient les exports compresses des bases Laravel et PWA, les fichiers
applicatifs touches et les outils de migration archives sous
`migration-tools/`. Aucun outil ou fichier temporaire `codex-*` ne demeure
dans `public_html`.

Empreintes des sauvegardes principales:

```text
Laravel DB: cb1fb77a43a17e2e448c57ecb40a1547c5e859db47336982f14f3af5eb1db3be
PWA DB:     8ab033d90891fb70c30581863204fcab240c9dbd786d524973f9857b6c43a7da
Manifest:   8b2863908c382510236accfa90810dbf2325c4f7609073aaf325318710ac4e31
```

Une copie du controleur Laravel avant correction se trouve aussi ici:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/storage/app/codex-blog-import/WebsiteController.php.before-20260804-015728.bak
```

Le correctif du tri des dates PWA possede sa propre copie de sauvegarde:

```text
/home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15/bootstrap.php.before-date-sort-hotfix
```

Le repli d'image pour les futurs articles sans image principale possede aussi
sa copie avant correctif:

```text
/home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15/Scraper.php.before-image-fallback-hotfix
```

## Validation executee

L'audit d'import a reussi 16 controles sur 16. Son rapport est conserve dans:

```text
/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/storage/app/codex-blog-import/audit-after-import.json
```

L'amorcage silencieux des nouveaux snapshots du blogue a produit:

```text
Premier passage: changed=28 notifications=0 items=55
Deuxieme passage: changed=0 notifications=0 items=55
```

Le mode d'amorcage a ensuite ete desactive. Trois passages manuels normaux,
dont le dernier a 22:28:31 apres les correctifs de robustesse, ont chacun
produit:

```text
changed=0 notifications=0 items=55
```

Le cron PWA a ete reactive avec exactement une entree:

```text
*/10 * * * * /usr/local/bin/php /home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/backend/cron/check-updates.php >> /dev/null 2>&1
```

Le premier passage automatique apres reactivation, a 22:20:08, a confirme:

```text
changed=0 notifications=0 items=55
```

## Basculement futur vers pilatesvitalite.ca

Quand le nouveau site sera publie sur `https://pilatesvitalite.ca`:

1. deplacer ou redeployer la PWA sous l'origine finale;
2. mettre a jour `APP_BASE_URL`, `SOURCE_BASE_URL`, `SOURCE_APP_ENV_PATH` et
   toutes les entrees `WATCH_URLS` dans le `config.php` prive;
3. adapter le manifest et le service worker si le chemin `/pwa/` change;
4. activer les redirections permanentes de `/blogue/{ancien-slug}/` vers les
   nouvelles routes `/blog/{slug}` a partir du manifest d'import;
5. amorcer silencieusement les snapshots si les URL ou le HTML changent;
6. executer deux passages manuels avec `changed=0 notifications=0` avant de
   reactiver le cron;
7. demander aux utilisateurs de se reabonner aux notifications, car les
   abonnements Web Push sont lies a l'origine du site.
