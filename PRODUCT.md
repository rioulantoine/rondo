# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Backend FlightPHP (PHP 8.3+) qui parle seul à Firebase (Auth + Firestore, API REST) ; frontend Vue 3 + Vite + vue-router, sans framework CSS. Données Firestore identiques à la v1 « Check ton resto » (collection `checkTaCuisineData`, mêmes clés) pour que les deux versions coexistent.

## Users

- **Propriétaire / direction** : tout gérer (équipe, accès, pointage, planning, paie, réglages), souvent sur ordinateur ou iPad.
- **Employé·e** (cuisine, salle, bar) : sur son téléphone ou la tablette du restaurant, debout, mains occupées, entre deux services. Relève une température, coche une tâche, badge, consulte son planning.
- **Tablette du restaurant (kiosque)** : appareil partagé où toute l'équipe badge.
- **Client du restaurant** : réserve une table depuis un lien public, sans compte.

## Product Purpose

Rondo tient le registre du restaurant : hygiène HACCP, pointage, planning, équipe, réservations. Il remplace les cahiers papier par un suivi daté, signé et exportable pour l'inspecteur, mesure le temps de travail réel et publie le planning. Succès : un contrôle sanitaire passe avec le seul export PDF, et l'équipe n'utilise plus de messagerie à côté.

## Positioning

Conçu par un restaurateur (Sumo Beach, Pornichet) à partir de besoins de terrain : chaque relevé est une entrée de registre numérotée, datée et visée, ce que l'inspecteur demande.

## Operating Context

Service du midi et du soir ; relevés avant le service du matin, après le midi, avant la fermeture. Safari iPhone/iPad en mode application (PWA) est la cible principale, puis Chrome Android et ordinateur. Lumière forte en cuisine, faible en salle le soir et au bar (thème sombre suivant le téléphone). Connexion parfois instable.

## Capabilities and Constraints

Voir `v1/Cahier des charges — Check ton resto.pdf`. Modules : HACCP (cuisine, salle, bar : températures, tâches, traçabilité/DLC, photos et non-conformités, allergènes), pointage, planning et échanges de shifts, équipe et RH, pourboires et export comptable, réservation en ligne et plan de salle, alertes et tendances, couleurs par établissement, sauvegardes. Français uniquement, droit du travail français, Europe/Paris. Seuils légaux à faire valider par un expert (non-HCR).

## Brand Commitments

Charte Rondo v2 (`v1/Charte Rondo.html`) : nom « rondo » en minuscules, symbole « o » posé sur sa ligne ; Schibsted Grotesk + IBM Plex Mono pour les chiffres contrôlés ; encre #1C2A4A, céladon #BFE3DC ; tutoiement, phrases courtes, verbe d'abord, chiffres exacts, jamais de reproche. La couleur de chaque restaurant remplace l'encre dans son espace.

## Evidence on Hand

Code v1 complet dans `v1/` (formats de données, valeurs par défaut, règles). Aucune capture d'utilisateur, aucun témoignage : ne pas en inventer.

## Product Principles

- Le registre fait foi : toute saisie est datée, attribuée à une personne identifiée, et retrouvable.
- On lit debout, les mains occupées : grandes cibles, 16 px minimum, une action principale par écran.
- Le serveur décide : permissions, PIN et heure de pointage sont contrôlés côté serveur, jamais par l'appareil.
- Dire quoi faire, pas reprocher : chaque alerte donne l'action suivante.

## Accessibility & Inclusion

Contraste AA minimum (charte : 6,3:1 à 15:1), états jamais signalés par la couleur seule (mot ou icône), cibles tactiles 48 px, champs 16 px pour éviter le zoom iOS, mouvement réduit respecté.
