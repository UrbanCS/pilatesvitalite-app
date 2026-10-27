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
- [x] Les dates du blogue sont en francais sur les listes, l'accueil et les
  fiches; aucune mention d'auteur n'est affichee sur une fiche.
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
- [x] `/api/latest.php` retourne les 28 articles, avec
  `cours-virtuel-pilates-pour-tous` en premier et `source=cache` apres
  prechauffage.
- [x] Cinq appels apres prechauffage prennent de 0,067 a 0,103 seconde au
  total, contre 4,56 a 5,17 secondes avant le cache serveur.
- [x] Le cache valide, le JSON corrompu et le cache expire ont ete testes; un
  cache expire de plus de 1 200 secondes est refuse.
- [x] `/api/preferences.php` est deploye et refuse les methodes non permises.
- [x] `backend/config/config.php` retourne HTTP 403 en acces web.
- [x] Les 28 articles WordPress sont importes dans Laravel, sur 4 pages de
  9, 9, 9 et 1 article.
- [x] Les images et MP3 importes sont locaux; aucun lien
  `wp-content/uploads` ne reste dans les contenus.
- [x] Le slug `la-sante-osseuse-pas-quune-affaire-de-femmes` est canonique.
- [x] L'audit de migration Laravel reussit 16 controles sur 16.
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
- [x] Apres la migration, l'amorcage silencieux retourne
  `changed=28 notifications=0 items=55`, puis
  `changed=0 notifications=0 items=55`.
- [x] Avec `BLOG_BASELINE_MODE=false`, deux passages manuels normaux retournent
  `changed=0 notifications=0 items=55`.
- [x] Apres correction du parseur de dates, le plus recent article est en
  premier et le cron demeure a `changed=0 notifications=0 items=55`.
- [x] Les compteurs restent inchanges: 3 journaux de notification,
  `latest_id=3` et 5 abonnements actifs.
- [x] Le cron PWA est reactive avec exactement une entree toutes les
  10 minutes.
- [x] Le premier passage automatique apres reactivation, a 22:20:08, retourne
  `changed=0 notifications=0 items=55`.
- [x] Le passage manuel final apres les correctifs de robustesse, a 22:28:31,
  retourne `changed=0 notifications=0 items=55`.
- [x] Les dates utilisees dans les hashes sont normalisees en `YYYY-MM-DD`;
  `07 June, 2023` et `07 juin 2023` donnent la meme valeur.
- [x] L'amorcage silencieux de 23:02:35 retourne
  `changed=28 notifications=0 items=55`, puis le passage normal de 23:02:59
  retourne `changed=0 notifications=0 items=55`.
- [x] Le premier passage automatique apres reactivation, a 23:10:07, retourne
  `changed=0 notifications=0 items=55` et conserve les compteurs.
- [x] Apres traduction du composant partage `blog-item.blade.php` utilise par
  `home-1`, l'accueil affiche les trois dates en francais et le passage manuel
  de 23:25:05 reste a `changed=0 notifications=0 items=55`.
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
- [x] Sauvegarde avant import des articles disponible sous
  `/home/pilatesvitalite/codex-backups/blog-import-20260804-014508-576bbd15`.
- [x] Sauvegarde avant traduction et optimisation disponible sous
  `/home/pilatesvitalite/codex-backups/blog-ui-pwa-perf-20260803-225319`, avec
  fichiers precedents, journaux de validation et sauvegarde corrective privee.
- [x] Les outils et archives de migration sont archives dans le sous-dossier
  prive `migration-tools/`; aucun artefact `codex-*` ni dossier `ops` ne reste
  dans `public_html`.
- [x] La source active demeure
  `https://pilatesvitalite.webactiondemo.ca`; aucune URL n'a ete basculee vers
  le domaine final.
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
- [x] Documenter le faux positif du premier controle apres traduction des
  dates: 28 notifications journalisees et 112 envois acceptes par le service
  push; les notifications deja remises ne peuvent pas etre rappelees.
- [x] Sauvegarder puis retirer en transaction uniquement les 28 journaux et 28
  snapshots du faux positif, et restaurer l'abonnement desactive pendant ce
  passage.
- [x] Confirmer le retour a `notification_logs=3`, `latest_id=3`, 5 abonnements
  actifs et un passage normal `changed=0 notifications=0 items=55`.
