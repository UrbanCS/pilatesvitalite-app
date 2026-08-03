# Consignes Codex - Pilates Vitalite

Avant toute modification, lire `docs/CODEX_HANDOFF.md`. Ce document contient
l'architecture, l'etat du deploiement cPanel, les tests deja realises et les
pieges connus.

## Regles importantes

- La PWA live est servie depuis `/pwa/`, pas `/app/` et pas
  `/public/pwa/`.
- Le chemin cPanel live est
  `/home/pilatesvitalite/domains/pilatesvitalite.webactiondemo.ca/public_html/pwa/`.
- Pour un changement frontend, executer `npm run build` dans `frontend/`.
- Toujours deployer `frontend/dist/index.html` et le dossier
  `frontend/dist/assets/` ensemble. Vite genere des noms de fichiers hashes;
  un decalage entre les deux produit un ecran vide.
- Ne jamais ecraser `backend/config/config.php` sur cPanel.
- Ne jamais exposer les mots de passe MySQL, la cle VAPID privee, le secret du
  cron ou le secret de notification test.
- Conserver l'interface mobile-first, la palette bleue/orange et la barre de
  navigation pleine largeur fixee en bas (elle ne doit pas flotter).
- Verifier les changements frontend avec `npm run build`.
- Verifier les changements PHP avec `php -l` lorsque PHP est disponible.

## Emplacements

- Frontend React/Vite: `frontend/`
- API PHP publique: `api/`
- Backend PHP: `backend/`
- Schema MySQL: `database/schema.sql`
- Passation complete: `docs/CODEX_HANDOFF.md`
- Deploiement cPanel: `docs/CPANEL.md`

