// Offres d'abonnement. Prix non fixés : affichés « à venir » (le paiement en ligne n'est pas branché).
// N'y lister que des fonctions livrées.
export const FEATURES = [
  { id: 'haccp', label: 'Registres HACCP cuisine, salle et bar', detail: 'Températures, nettoyage, traçabilité et alertes DLC' },
  { id: 'pdf', label: 'Rapport PDF pour le contrôle sanitaire', detail: 'Relevés numérotés, datés et visés' },
  { id: 'equipe', label: 'Équipe, accès et codes PIN', detail: 'Chacun ne voit que ses espaces' },
  { id: 'pwa', label: 'Sur téléphone, tablette et ordinateur', detail: 'Sans installation, ajout à l’écran d’accueil' },
  { id: 'pointage', label: 'Pointage avec signature', detail: 'Heure du serveur, hors planning justifié' },
  { id: 'planning', label: 'Planning publié à l’équipe', detail: 'Alertes 10 h, 48 h et 11 h de repos' },
  { id: 'resa', label: 'Réservation en ligne', detail: 'Page client à ton nom, service du jour' },
]

export const OFFRES = [
  { id: 'essentiel', name: 'Essentiel', pitch: 'L’hygiène tenue sans papier.', features: ['haccp', 'pdf', 'equipe', 'pwa'] },
  { id: 'equipe', name: 'Équipe', pitch: 'Plus le temps de travail et le planning.', features: ['haccp', 'pdf', 'equipe', 'pwa', 'pointage', 'planning'], highlight: true },
  { id: 'complet', name: 'Complet', pitch: 'Plus les réservations de la salle.', features: FEATURES.map((f) => f.id) },
]

export const TRIAL_DAYS = 14
