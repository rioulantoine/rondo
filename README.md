# rondo

Le registre du restaurant : hygiène HACCP, pointage, planning, équipe, réservations. Version 2 de « Check ton resto » (`v1/`), séparée en backend et frontend, à la charte Rondo.

## Architecture

- **backend/** — FlightPHP (PHP 8.3+). Seul à parler à Firebase, par l'API REST (Auth + Firestore). Mêmes données que la v1 : collection `checkTaCuisineData`, mêmes clés (`config:{code}:team`, `log:{code}:{espace}:temp:{AAAA-MM}`…), donc v1 et v2 peuvent tourner sur la même base pendant la transition.
- **frontend/** — Vue 3 + Vite + vue-router. Ne parle qu'à `/api`. Jetons de la charte dans `src/styles/tokens.css`.
- Sécurité par rapport à la v1 : PIN vérifié côté serveur et jamais envoyé au navigateur, blocage 5 essais / 5 min ; permissions contrôlées côté serveur ; heure de pointage = heure du serveur ; tablette du restaurant reconnue par cookie serveur ; page de réservation publique sans aucune coordonnée client.
- `PRODUCT.md` et `DESIGN.md` : contexte produit et design (skill impeccable).

## Lancer en local (avec les émulateurs Firebase, jamais la prod)

```sh
npm install                     # à la racine : firebase-tools
(cd backend && composer install)
(cd frontend && npm install)

npm run emulators               # terminal 1 : Firestore + Auth émulés (Java requis)
npm run seed                    # établissement de démo « sumo-beach » / « rondo123 »
npm run backend:emu             # terminal 2 : API sur localhost:8000
npm run frontend                # terminal 3 : http://localhost:5173 (/api proxifié)
```

PIN de démo : Léa 1234, Tom 2345, Hugo 3456. Responsable : mot de passe de l'établissement.
Contre la vraie base : `npm run backend` (variables `FIREBASE_PROJECT_ID`, `FIREBASE_API_KEY`, valeurs v1 par défaut).

## Tests et CI

- `cd backend && composer test` — unitaires ; les tests d'intégration Firebase tournent si `FIRESTORE_EMULATOR_HOST` est défini.
- `cd frontend && npm test && npm run build`
- `.github/workflows/ci.yml` : lint PHP, PHPUnit sur émulateurs, Vitest, build, artefact `frontend-dist`. Branche de travail : `v2`.

## Avancement

| Module (cahier des charges) | État v2 |
|---|---|
| Connexion établissement, création, mot de passe oublié, choix de la personne + PIN | Fait |
| HACCP cuisine / salle / bar : températures, tâches, traçabilité et DLC, navigation mensuelle | Fait |
| Rapport PDF de contrôle sanitaire | Fait |
| Pointage : arrivée, départ, pauses, forfait jour, hors planning, signature, géoloc, tablette | Fait |
| Accueil : alertes, messages, service du jour, validations | Fait |
| Planning : semaine, créneaux, publication, alertes légales, « Mon planning » | Fait |
| Équipe et accès, réglages du pointage | Fait |
| Réservation : page publique, annulation, espace pro du jour | Fait |
| PWA : installable, hors ligne, mise à jour | Fait |
| HACCP : photos et non-conformités, allergènes (salle), étiquettes 60 × 40 mm | À faire |
| Pointage : file hors ligne, correction des heures, feuilles d'heures signées, archives | À faire |
| Planning : modèle de semaine, génération automatique, échanges de shifts, export .ics | À faire |
| RH : incidents / renvois, absences justifiées et non justifiées, registre du personnel | À faire |
| Pourboires, export comptable, tendances | À faire |
| Réservation : plan de salle, clients et fidélité, stats, réglages, e-mails | À faire |
| Couleurs par établissement, messages d'alerte (édition), sauvegardes, export, conservation | À faire |

Raccourcis assumés : `grep -rn "shortcut:" backend frontend/src`.
