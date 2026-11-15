// Pages légales. Le contenu décrit ce que l'application fait réellement.
// À faire relire par un juriste avant la mise en ligne commerciale.

const VIDE = 'À compléter'

/** Identité de l'éditeur : à remplir une fois, les trois pages s'en servent. */
export const EDITEUR = {
  nom: VIDE, // raison sociale ou nom et prénom de l'entrepreneur
  forme: VIDE, // SAS, SARL, entreprise individuelle…
  capital: VIDE,
  siege: VIDE, // adresse postale complète
  siret: VIDE,
  rcs: VIDE, // ville d'immatriculation
  tva: VIDE, // numéro de TVA intracommunautaire
  directeur: VIDE, // directeur de la publication
  email: VIDE,
  telephone: VIDE,
  hebergeur: VIDE, // nom, adresse et téléphone de l'hébergeur du site
  regionDonnees: VIDE, // région Firestore du projet Firebase
  mediateur: VIDE, // médiateur de la consommation, si vente à des consommateurs
}

export const incomplet = Object.values(EDITEUR).includes(VIDE)

export const MAJ = '15 novembre 2026'

const e = EDITEUR

export const PAGES = {
  'mentions-legales': {
    title: 'Mentions légales',
    sections: [
      { h: 'Éditeur du site', p: [
        `Rondo est édité par ${e.nom}, ${e.forme} au capital de ${e.capital}, dont le siège est situé ${e.siege}.`,
        `SIRET : ${e.siret}. Immatriculation au registre du commerce et des sociétés de ${e.rcs}. TVA intracommunautaire : ${e.tva}.`,
        `Directeur de la publication : ${e.directeur}.`,
        `Contact : ${e.email}, ${e.telephone}.`,
      ] },
      { h: 'Hébergement', p: [
        `Le site est hébergé par ${e.hebergeur}.`,
        `Les données des établissements sont enregistrées dans Firebase, un service de Google Cloud EMEA Limited, 70 Sir John Rogerson's Quay, Dublin 2, Irlande. Région de stockage : ${e.regionDonnees}.`,
      ] },
      { h: 'Propriété intellectuelle', p: [
        'Le nom Rondo, son logo, l’interface et les textes du site appartiennent à l’éditeur. Toute reproduction sans accord écrit est interdite.',
        'Les données saisies par un établissement (relevés, plannings, réservations) restent sa propriété.',
      ] },
      { h: 'Signaler un problème', p: [
        `Pour signaler un contenu, une erreur ou une faille de sécurité, écris à ${e.email}.`,
      ] },
    ],
  },

  conditions: {
    title: 'Conditions générales d’utilisation et de vente',
    sections: [
      { h: '1. Objet', p: [
        `Les présentes conditions encadrent l’utilisation de Rondo, application de gestion pour restaurants éditée par ${e.nom}. Elles s’appliquent à tout établissement qui crée un compte, et aux personnes de son équipe qui utilisent l’application.`,
      ] },
      { h: '2. Le service', p: [
        'Rondo permet de tenir les registres d’hygiène (températures, nettoyage, traçabilité), de pointer les heures de travail, de publier un planning, de gérer une équipe et de recevoir des réservations en ligne.',
        'Le service est accessible depuis un navigateur, sur téléphone, tablette et ordinateur. Il peut être ajouté à l’écran d’accueil de l’appareil.',
      ] },
      { h: '3. Compte et accès', p: [
        'L’établissement crée un compte avec un code, une adresse e-mail de récupération et un mot de passe. Chaque membre de l’équipe s’identifie ensuite avec un code personnel à quatre chiffres.',
        'L’établissement est responsable de la confidentialité de son mot de passe et des codes de son équipe, ainsi que des droits qu’il attribue à chacun.',
      ] },
      { h: '4. Essai et abonnement', p: [
        'La création d’un compte ouvre une période d’essai gratuite de 14 jours, sans carte bancaire, pendant laquelle toutes les fonctions sont accessibles.',
        'Les tarifs des offres Essentiel, Équipe et Complet ne sont pas encore fixés et le paiement en ligne n’est pas ouvert. Aucune somme n’est due tant que l’établissement n’a pas accepté un tarif qui lui aura été communiqué.',
      ] },
      { h: '5. Ce que Rondo ne remplace pas', p: [
        'Rondo est un outil d’enregistrement. Il ne dispense pas l’établissement de respecter la réglementation sanitaire, ni de vérifier lui-même la conformité de ses pratiques.',
        'Les alertes sur la durée du travail (10 heures par jour, 48 heures par semaine, 11 heures de repos, pause après 6 heures) reprennent les règles générales du Code du travail. Elles ne tiennent compte ni de la convention collective des hôtels, cafés et restaurants, ni des accords d’entreprise. Elles sont indicatives et ne constituent pas un conseil juridique.',
      ] },
      { h: '6. Obligations de l’établissement', p: [
        'L’établissement s’engage à saisir des informations exactes, à informer ses salariés de l’usage de l’application (pointage, géolocalisation au moment du pointage, signature) et à n’utiliser Rondo que pour ses besoins professionnels.',
        'Il informe ses clients de l’usage fait des coordonnées laissées lors d’une réservation.',
      ] },
      { h: '7. Disponibilité', p: [
        'L’éditeur fait ses meilleurs efforts pour que le service soit accessible en continu. Des interruptions peuvent survenir pour maintenance ou du fait des prestataires techniques. L’établissement reste responsable de conserver, par l’export en PDF, les registres qu’il doit pouvoir présenter lors d’un contrôle.',
      ] },
      { h: '8. Responsabilité', p: [
        'La responsabilité de l’éditeur ne peut être engagée en cas de sanction liée au non-respect, par l’établissement, de ses obligations sanitaires ou sociales, ni en cas de saisie erronée ou incomplète.',
      ] },
      { h: '9. Données personnelles', p: [
        'Le traitement des données est décrit dans la politique de confidentialité. Pour les données de ses salariés et de ses clients, l’établissement est responsable de traitement et l’éditeur agit comme sous-traitant.',
      ] },
      { h: '10. Fin du contrat', p: [
        `L’établissement peut cesser d’utiliser le service à tout moment. Il peut demander la suppression de son compte et de ses données en écrivant à ${e.email}. Il lui appartient d’exporter auparavant les registres qu’il doit conserver.`,
      ] },
      { h: '11. Droit applicable', p: [
        'Les présentes conditions sont soumises au droit français. À défaut d’accord amiable, le litige est porté devant les tribunaux compétents.',
        `Médiation de la consommation : ${e.mediateur}.`,
      ] },
    ],
  },

  confidentialite: {
    title: 'Politique de confidentialité',
    sections: [
      { h: 'Qui est responsable de quoi', p: [
        `Pour les données du compte d’un établissement (nom, adresse e-mail de récupération), le responsable de traitement est ${e.nom}.`,
        'Pour les données que l’établissement enregistre sur son équipe et ses clients, le responsable de traitement est l’établissement. Rondo les traite pour son compte, comme sous-traitant.',
      ] },
      { h: 'Les données traitées', p: [
        'Établissement : nom, code de connexion, adresse e-mail de récupération, position du restaurant si elle a été enregistrée.',
        'Équipe : nom, postes et droits d’accès, téléphone et adresse e-mail s’ils sont renseignés, heures contractuelles, formation à l’hygiène, pointages avec leur heure, leur signature et la position de l’appareil au moment du pointage, créneaux de planning.',
        'Registres d’hygiène : relevés, tâches et entrées de traçabilité, avec le nom de la personne qui les a saisis.',
        'Clients qui réservent : prénom, nom, téléphone, adresse e-mail, nombre de personnes, date, heure et remarque éventuelle.',
      ] },
      { h: 'Pourquoi', p: [
        'Tenir les registres exigés par la réglementation sanitaire, mesurer le temps de travail, organiser le planning, gérer les réservations et sécuriser l’accès à l’application.',
        'La position n’est relevée qu’au moment d’un pointage, pour signaler un pointage effectué loin de l’établissement. Il n’y a aucun suivi continu.',
      ] },
      { h: 'Durée de conservation', p: [
        'Les registres d’hygiène sont conservés sans limite par défaut, pour rester présentables lors d’un contrôle. Les autres données sont conservées tant que le compte de l’établissement est ouvert.',
        'Les durées précises par catégorie de données restent à fixer par l’éditeur et par chaque établissement.',
      ] },
      { h: 'Qui y a accès', p: [
        'Le responsable de l’établissement accède à toutes ses données. Chaque membre de l’équipe n’accède qu’aux espaces qui lui sont ouverts. Les coordonnées des clients ne sont jamais visibles depuis la page publique de réservation.',
        `Les données sont hébergées par Google Cloud EMEA Limited (Firebase), en ${e.regionDonnees}. Les polices d’écriture sont chargées depuis Google Fonts, ce qui transmet l’adresse IP du visiteur à Google.`,
        'Les données ne sont ni vendues ni utilisées à des fins publicitaires.',
      ] },
      { h: 'Cookies', p: [
        'Rondo dépose uniquement des cookies nécessaires au fonctionnement : un cookie de session pour rester connecté, et un cookie qui reconnaît la tablette du restaurant quand le responsable l’a déclarée. Ils ne servent à aucune mesure d’audience ni à aucune publicité, et ne nécessitent donc pas de consentement.',
      ] },
      { h: 'Vos droits', p: [
        'Vous disposez d’un droit d’accès, de rectification, d’effacement, de limitation et d’opposition, ainsi que du droit à la portabilité de vos données.',
        `Salariés et clients d’un restaurant : adressez-vous d’abord à l’établissement concerné. Vous pouvez aussi écrire à ${e.email}.`,
        'Vous pouvez introduire une réclamation auprès de la CNIL, 3 place de Fontenoy, 75007 Paris, ou sur cnil.fr.',
      ] },
    ],
  },
}

export const LIENS = [
  { to: '/mentions-legales', label: 'Mentions légales' },
  { to: '/conditions', label: 'Conditions générales' },
  { to: '/confidentialite', label: 'Confidentialité' },
]
