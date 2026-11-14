# Design — Rondo

Source d'autorité : charte Rondo v2 (`v1/Charte Rondo.html`). Ce fichier en reprend ce que le code applique ; les jetons vivent dans `frontend/src/styles/tokens.css`.

## Mode

Operate. L'outil s'efface derrière la tâche ; la marque vit dans les détails.

## Monde

Le registre papier du restaurant, rendu net. Fond blanc, texte bleu encre, une seule teinte pâle (céladon) pour ce qui est actif ou sélectionné, jamais plus.

**Signature** : chaque saisie est une **entrée de registre** — numéro `N° 0142` en IBM Plex Mono, heure, valeur alignée en chiffres tabulaires, visa (initiales de la personne) dans une pastille. Les listes du jour sont réglées par des lignes `--line`, comme un cahier.

## Couleurs

| Rôle | Clair | Sombre |
|---|---|---|
| Encre (actions, logo, liens) `--sea-deep` | #1C2A4A | #BFE3DC (texte #0E1422) |
| Encre claire `--sea` | #2B3D66 | #A9D6CD |
| Céladon `--tint-strong` / pâle `--tint` | #BFE3DC / #EAF5F2 | #2C5452 / #1F3A3B |
| Céladon profond `--accent-deep` | #1F5F57 | — |
| Fond `--sand` / secondaire `--sand-dark` | #FFFFFF / #F7F8FA | #0E1422 / #161D2E |
| Réglure `--line` | #DCDFE5 | #27314A |
| Texte `--ink` / doux `--ink-soft` | #23272F / #5A606B | #E8EAEF / #9AA3B5 |
| Conforme `--ok` | #2A7449 sur #E7F3EC | #7FD1A3 sur #15301F |
| À surveiller `--warn` | #9A5B00 sur #FBF0DC | #F2B45A sur #33260F |
| Hors bornes `--alert` | #B42318 sur #FCE9E7 | #FF9B8E sur #3A1A1A |

Une couleur d'état est toujours accompagnée d'un mot ou d'une icône. La couleur du restaurant remplace `--sea-deep`.

## Typographie

Schibsted Grotesk (400–700) pour tout ; IBM Plex Mono pour °C, heures, lots, DLC, numéros d'entrée. Échelle fixe, rapport 1,2 : titre écran 28/32, section 20/26, corps 16/24, libellé 14/20, légende 13/18, donnée 20/24. Logo : minuscules, approche −0,045 em.

## Formes et mouvement

Rayons 6 / 10 / 14 px. Retours tactiles en 160 ms, `cubic-bezier(.23,1,.32,1)`, pression à 97 %. Aucune animation sur les gestes répétés cent fois par jour (pointage, cases à cocher).

## Icônes

Lucide, trait 1,7, grille 24, extrémités arrondies, sans remplissage.

## Navigation téléphone

Barre du bas : Accueil, Registres, Planning, Pointer. Sur tablette et ordinateur, la même navigation devient un rail latéral.
