# Checklist finale de test

## Frontend

- [x] Les dependances frontend ont ete installees avec le runtime Node bundle.
- [x] Le typecheck TypeScript et le build Vite de production reussissent.
- [x] `frontend/dist/` contient `index-BTcxeBIp.js` et
  `index-BY6eFtrM.css`, tous deux references par `index.html`.
- [x] Le texte superieur Accueil, le resume et les benefices sont retires;
  le logo et les liens de cours restent visibles.
- [x] La carte `Mes notifications` affiche `site_updates`, `blog`, `offers`
  et `newsletter`.
- [x] L'accueil affiche `Prochain cours`, `Tous les cours`, `Compte client`
  et une seule `Derniere nouvelle`.
- [x] En l'absence de seance studio future, l'etat vide est visible sans
  afficher une ancienne seance ni inventer une heure.
- [x] Le service worker utilise `pilates-vitalite-shell-v20260803-2` et ne
  supprime que les anciens caches Pilates.
- [ ] La PWA s'ouvre sur mobile.
- [ ] Les 4 onglets fonctionnent: Accueil, Blogue, A propos, Contact.
- [ ] La page detail d'un article fonctionne.
- [ ] Les etats loading, erreur et vide sont lisibles.
- [ ] Le bouton `Visiter le site complet` ouvre le site original.
- [ ] Le manifest est visible dans DevTools.
- [ ] Le service worker est installe.
- [ ] L'app peut etre ajoutee a l'ecran d'accueil.

## Backend

- [x] La migration
  `database/migrations/20260803_push_subscription_preferences.sql` est
  appliquee sans reimporter `database/schema.sql`.
- [x] `push_subscription_preferences` existe.
- [x] `backend/config/config.php` conserve les secrets live et contient les
  URLs demo, `/shop`, `/membership` et `SOURCE_APP_ENV_PATH`.
- [x] `/api/config.php` retourne HTTP 200 et du JSON.
- [x] `/api/latest.php` retourne HTTP 200 avec `home`, `about`, `blog` et
  `contact`, ainsi que la cle nullable `nextCourse`.
- [x] `/api/latest.php` conserve
  `site.fullSiteUrl=https://pilatesvitalite.webactiondemo.ca`.
- [x] `/api/preferences.php` est deploye et refuse les methodes non permises.
- [x] `backend/config/config.php` retourne HTTP 403 en acces web.
- [ ] `/api/subscribe.php` sauvegarde un abonnement et ses preferences sur un
  appareil de test.
- [ ] `/api/preferences.php` relit et modifie les quatre choix sur cet appareil.
- [ ] `/api/unsubscribe.php` desactive un abonnement.
- [ ] `/api/notify-test.php` refuse sans secret.
- [ ] `/api/notify-test.php` envoie avec le bon secret.

## Cron

- [x] `check-updates.php` s'execute en CLI et utilise `flock`.
- [x] 18 cours, 3 forfaits et 4 campagnes `sent` sont detectes.
- [x] Le premier passage initialise les nouvelles sections sans notification
  pour leurs contenus existants.
- [x] La formule de hash Accueil historique a ete restauree apres l'incident
  d'amorcage et le snapshot transitoire a ete retire.
- [x] Le second passage manuel retourne
  `changed=0 notifications=0 items=28`.
- [x] Les deux passages manuels apres la mise a niveau Accueil, a 17:35:56 et
  17:36:29, retournent `changed=0 notifications=0 items=28`.
- [x] Le passage automatique de 17:40:03 apres la mise a niveau Accueil
  retourne `changed=0 notifications=0 items=28`.
- [x] Le cache du prochain cours est actualise avec `course: null` sans
  notification lorsqu'aucun cours studio futur n'est publie.
- [x] Le passage automatique de 16:50:02 le 2026-08-03 retourne
  `changed=0 notifications=0 items=28`.
- [x] Le cron PWA est actif: `ACTIVE_PWA=1` et crontab de 5 lignes.
- [x] Les logs sont ecrits dans `backend/storage/logs/`.
- [ ] Ajouter un cours de test autorise et confirmer qu'un seul avis
  `offers` est envoye aux abonnes ayant active cette categorie.
- [ ] Envoyer une campagne infolettre de test autorisee et confirmer qu'un
  seul avis `newsletter` est envoye aux abonnes correspondants.

## Production

- [x] HTTPS est actif sur `pilatesvitalite.webactiondemo.ca/pwa/`.
- [x] `APP_BASE_URL`, `SOURCE_BASE_URL` et `WATCH_URLS` pointent vers la
  source demo active.
- [x] Les cles VAPID sont configurees sans etre exposees.
- [x] `backend/.htaccess` protege le dossier backend.
- [x] Le cron DirectAdmin/cPanel tourne toutes les 10 minutes.
- [x] Sauvegarde serveur disponible sous
  `/home/pilatesvitalite/codex-backups/pwa-notification-upgrade-20260803`.
- [x] Archive locale avant mise a niveau disponible sous
  `C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-notification-upgrade-20260803-161209.zip`.
- [x] Sauvegarde de la mise a niveau Accueil disponible sous
  `/home/pilatesvitalite/codex-backups/pwa-home-upgrade-20260803-172534` et
  archive locale avant modification disponible sous
  `C:\Users\marca\Downloads\pilatesvitalite-app\pilatesvitalite-app-before-home-course-login-20260803-172534.zip`.
- [x] Archive de deploiement Accueil disponible sous
  `C:\Users\marca\AppData\Local\Temp\pilatesvitalite-home-upgrade-20260803-172534.zip`
  avec SHA-256
  `F1AD6AA5F1A1E416A72D144551078FD9593F23C05698731BC68503E7FAA707F0`.
- [x] Archive finale v3 de 25 entrees disponible sous
  `C:\Users\marca\AppData\Local\Temp\pilatesvitalite-deploy-20260803-v3.zip`
  avec SHA-256
  `3207A9D8F19E3D1CF04382857799BD6FCDE8DE5A4411FA92C57B907ACB060B8E`.
- [x] L'archive finale v3 est aussi staging sous
  `/home/pilatesvitalite/codex-staging/pwa-notification-upgrade-20260803/`.
- [ ] Au lancement final, basculer separement vers
  `https://pilatesvitalite.ca` et refaire l'inscription Web Push des
  utilisateurs.

## Incident connu du deploiement 2026-08-03

- [x] Documenter qu'une notification Accueil a ete envoyee a 5 abonnes au
  premier amorcage.
- [x] Documenter la cause: formule de hash temporairement elargie et
  incompatible avec le snapshot historique.
- [x] Documenter la correction: formule historique restauree et snapshot
  transitoire retire.
- [x] Confirmer qu'aucun nouvel envoi ne s'est produit aux passages manuel et
  automatique suivants.
