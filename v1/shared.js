/* ============================================================
   shared.js — Check ton resto
   Fonctions communes à check-ta-cuisine.html, check-ta-salle.html,
   check-ton-bar.html et planning.html : connexion à la base Firebase,
   authentification établissement + identification personnelle par poste.

   Chaque page hôte doit définir AVANT de charger ce fichier :
     - REQUIRED_PERM   (ex: 'cuisine')
     - MODULE_TITLE    (ex: 'Check ta cuisine')
   et définir, n'importe où dans son propre <script>, la fonction :
     - async function onIdentityGranted(){ ... }
   appelée une fois l'accès (établissement + personne) validé.
   ============================================================ */

// ---------- Firebase ----------
const firebaseConfig = {
  apiKey: "AIzaSyD4X3KyTYXQxULrhGKJdY9QeAaVtvfvZBQ",
  authDomain: "check-ta-cuisine-c752c.firebaseapp.com",
  projectId: "check-ta-cuisine-c752c",
  storageBucket: "check-ta-cuisine-c752c.firebasestorage.app",
  messagingSenderId: "403658922187",
  appId: "1:403658922187:web:39d08656eea9f3c993234e"
};
firebase.initializeApp(firebaseConfig);
const db = firebase.firestore();
const FS_COLLECTION = 'checkTaCuisineData';

/* ---------- cache local automatique (connexions faibles / instables) ----------
   Une fois qu'une donnée a été lue une première fois, Firestore la garde en cache sur
   l'appareil : les visites suivantes du même écran s'affichent instantanément, même sur
   une connexion très faible. Les écritures faites en connexion capricieuse sont mises en
   attente automatiquement et synchronisées dès que la connexion redevient stable, sans
   bloquer la personne qui utilise l'appli.
   Se dégrade sans casser l'appli si indisponible (ex. plusieurs onglets ouverts en même
   temps sur desktop) — l'appli fonctionne alors comme avant, juste sans ce coup de pouce. */
db.enablePersistence({synchronizeTabs:true}).catch(err=>{
  console.warn('Cache local Firestore indisponible :', err.code);
});

// ---------- storage helpers (Firebase Firestore) ----------
function encodeKey(key){ return key.replace(/\//g, '~'); }

/* ---------- heure serveur (anti-triche sur l'horloge de l'appareil) ----------
   Utilisée pour horodater les badgeages : on écrit un horodatage serveur (Firestore
   FieldValue.serverTimestamp, fixé par Google, jamais par l'appareil) dans un petit document
   "sonde", puis on le relit immédiatement pour connaître l'heure réelle. Si quelqu'un change
   l'heure de son téléphone pour tricher, ça n'a aucun effet sur l'heure ainsi obtenue.
   En cas d'échec (hors-ligne, etc.), on retombe sur l'heure de l'appareil — seul repère possible
   sans connexion. */
async function getServerTime(){
  const timeout = ms => new Promise(resolve => setTimeout(()=> resolve(null), ms));
  try{
    const fetchIt = (async ()=>{
      // clé au format habituel de l'appli (config:{code}:...) pour rester dans ce qu'autorisent
      // les règles de sécurité, plutôt qu'une clé "à part" qui pourrait être refusée
      const ref = db.collection(FS_COLLECTION).doc(encodeKey(`config:${RESTAURANT_ID}:servertime_probe`));
      await ref.set({ t: firebase.firestore.FieldValue.serverTimestamp() });
      // { source: 'server' } est indispensable : sans ça, la lecture juste après l'écriture renvoie
      // la version en attente dans le cache local (où serverTimestamp() n'est pas encore résolu),
      // et notre code de repli utilisait alors... l'heure de l'appareil — exactement ce qu'on veut éviter.
      const snap = await ref.get({ source: 'server' });
      const t = snap.data() && snap.data().t;
      return (t && typeof t.toDate === 'function') ? t.toDate() : null;
    })();
    // jamais plus de 4 secondes d'attente : au-delà, on préfère badger avec l'heure de l'appareil
    // plutôt que de bloquer complètement la personne devant l'écran
    const result = await Promise.race([fetchIt, timeout(4000)]);
    return result || new Date();
  }catch(e){
    console.error('getServerTime error', e);
    return new Date();
  }
}
// Lectures identiques simultanées = UNE seule requête réseau (ex. l'accueil relit l'équipe plusieurs fois).
// Aucune donnée n'est gardée au-delà de la requête en cours : toujours la version la plus récente.
const _inflightReads = new Map();
function getData(key, shared, fallback){
  let p = _inflightReads.get(key);
  if(!p){
    p = (async ()=>{
      try{
        const doc = await db.collection(FS_COLLECTION).doc(encodeKey(key)).get();
        if(!doc.exists) return { ok:true, v:undefined };
        const data = doc.data();
        return { ok:true, v: data && typeof data.value === 'string' ? JSON.parse(data.value) : undefined };
      }catch(e){ console.error('getData error', e); return { ok:false, v:undefined }; }
    })().finally(()=>{ _inflightReads.delete(key); });
    _inflightReads.set(key, p);
  }
  // chaque appelant reçoit sa PROPRE copie (le code modifie souvent la liste lue avant de la réécrire)
  return p.then(r => (r.v === undefined ? fallback : JSON.parse(JSON.stringify(r.v))));
}
let lastStorageError = null;
async function setData(key, value, shared){
  _inflightReads.delete(key);
  try{
    await db.collection(FS_COLLECTION).doc(encodeKey(key)).set({value: JSON.stringify(value), updatedAt: new Date().toISOString()});
    registerDataKey(key);
    return true;
  }catch(e){ console.error('setData error', e); lastStorageError = e.message || String(e); return false; }
}
/* ---------- ajout protégé contre les écritures simultanées ----------
   setData() classique lit toute la liste, ajoute un élément, réécrit toute la liste — si deux
   personnes font ça au même instant sur le même document (ex : plusieurs badgeages à la seconde
   près), la seconde écriture peut silencieusement effacer la première. runTransaction() fait relire
   et réessayer automatiquement par Firestore en cas de collision, donc aucun ajout n'est jamais perdu,
   même avec de nombreuses personnes actives en même temps. À utiliser pour tout ajout à une liste
   partagée à fort risque de simultanéité (pointage en tête). */
async function appendToJsonArray(key, item){
  _inflightReads.delete(key);
  try{
    const ref = db.collection(FS_COLLECTION).doc(encodeKey(key));
    await db.runTransaction(async (tx)=>{
      const doc = await tx.get(ref);
      const arr = (doc.exists && doc.data().value) ? JSON.parse(doc.data().value) : [];
      arr.push(item);
      tx.set(ref, {value: JSON.stringify(arr), updatedAt: new Date().toISOString()});
    });
    registerDataKey(key);
    return true;
  }catch(e){ console.error('appendToJsonArray error', e); lastStorageError = e.message || String(e); return false; }
}

/* ---------- registre des clés par établissement (les règles Firestore interdisent les requêtes "liste" —
   "allow list: if false" — donc pour retrouver "toutes les données d'un établissement" sans scanner la
   base, chaque écriture s'auto-enregistre ici ; c'est ce registre que lisent les sauvegardes et la
   suppression RGPD). Ne couvre que les préfixes propres à un établissement (config:/log:/reservation:) —
   pas restaurant:/errorLog: qui sont des clés uniques et déjà connues directement. */
const _registeredKeysCache = new Set();
function registerDataKey(fullKey){
  const m = fullKey.match(/^(config|log|reservation):([^:]+):/);
  if(!m || _registeredKeysCache.has(fullKey)) return;
  _registeredKeysCache.add(fullKey);
  const code = m[2];
  db.collection(FS_COLLECTION).doc(encodeKey(`keyIndex:${code}`)).set(
    { keys: firebase.firestore.FieldValue.arrayUnion(fullKey), updatedAt: new Date().toISOString() },
    { merge: true }
  ).catch(e=>{ _registeredKeysCache.delete(fullKey); console.error('registerDataKey error', e); });
}
async function getEstablishmentKeys(code){
  try{
    const doc = await db.collection(FS_COLLECTION).doc(encodeKey(`keyIndex:${code}`)).get();
    return (doc.exists && doc.data().keys) ? doc.data().keys : [];
  }catch(e){ console.error('getEstablishmentKeys error', e); return []; }
}
async function setRestaurantRecord(code, rec){
  _inflightReads.delete(`restaurant:${code}`);
  // écrit ownerUid comme vrai champ Firestore (pas seulement dans le blob JSON) pour que les règles de sécurité puissent le lire
  try{
    await db.collection(FS_COLLECTION).doc(encodeKey(`restaurant:${code}`)).set({
      value: JSON.stringify(rec), updatedAt: new Date().toISOString(), ownerUid: rec.ownerUid || null
    });
    return true;
  }catch(e){ console.error('setRestaurantRecord error', e); lastStorageError = e.message || String(e); return false; }
}

/* ---------- socle abonnement / facturation (terrain préparé pour Stripe, pas encore branché) ----------
   Aucune fonctionnalité n'est restreinte à ce stade : ces champs ne servent qu'à afficher le statut
   à l'établissement. Tant qu'il n'y a pas de vrai serveur (Cloud Functions ou équivalent) pour
   traiter les webhooks Stripe en toute sécurité, le paiement réel ne doit pas être branché ici —
   voir la conversation sur la commercialisation pour le détail de ce qui reste à faire. */
const TRIAL_DURATION_DAYS = 14;
function defaultSubscriptionFields(){
  const now = new Date();
  const trialEnd = new Date(now.getTime() + TRIAL_DURATION_DAYS*24*60*60*1000);
  return {
    plan: 'trial',
    subscriptionStatus: 'trialing',
    trialStartedAt: now.toISOString(),
    trialEndsAt: trialEnd.toISOString(),
    stripeCustomerId: null,
  };
}
function getSubscriptionStatus(rec){
  // comptes créés avant l'ajout de ce système : accès complet, pas de compte à rebours (aucune rupture pour l'existant)
  if(!rec || !rec.plan) return { plan:'legacy', status:'active', label:'Accès complet', trialDaysLeft:null };
  if(rec.plan==='trial'){
    const end = rec.trialEndsAt ? new Date(rec.trialEndsAt).getTime() : 0;
    const daysLeft = Math.max(0, Math.ceil((end - Date.now()) / (24*60*60*1000)));
    return { plan:'trial', status: daysLeft>0 ? 'trialing' : 'trial_expired', label: daysLeft>0 ? `Essai gratuit — ${daysLeft} jour${daysLeft>1?'s':''} restant${daysLeft>1?'s':''}` : 'Essai gratuit terminé', trialDaysLeft: daysLeft };
  }
  return { plan: rec.plan, status: rec.subscriptionStatus||'active', label: rec.plan==='complet' ? 'Formule Complet' : 'Formule Essentiel', trialDaysLeft:null };
}

/* ---------- suppression RGPD complète d'un établissement (droit à l'effacement) ----------
   N'utilise aucune requête "liste" (interdite par les règles Firestore : allow list: if false) —
   s'appuie sur le registre de clés (getEstablishmentKeys) tenu à jour automatiquement par setData. */
/* ---------- purge manuelle des anciennes données HACCP (durée de conservation choisie par l'établissement) ----------
   Ne touche qu'aux relevés HACCP (températures, traçabilité, photos, tâches) de cuisine/salle/bar —
   jamais aux données de paie/pointage, planning ou réservations, qui ont leurs propres obligations
   de conservation. Toujours déclenchée manuellement (jamais automatique en arrière-plan) : supprimer
   des preuves de conformité est une décision qui doit rester consciente et volontaire. */
function parseHaccpLogDate(key){
  const m = key.match(/^log:[^:]+:(cuisine|salle|bar):(temp|trace|photos|tasks|taskdefs):(\d{4}-\d{2}(?:-\d{2})?)$/);
  if(!m) return null;
  const datePart = m[3];
  if(datePart.length === 7){ // clé mensuelle (YYYY-MM) : le mois n'est considéré "clos" qu'à sa toute fin
    const [y,mo] = datePart.split('-').map(Number);
    return new Date(y, mo, 0);
  }
  return new Date(datePart+'T00:00:00');
}
async function countOldHaccpData(code, retentionYears){
  if(!retentionYears) return [];
  const cutoff = new Date(); cutoff.setFullYear(cutoff.getFullYear() - retentionYears);
  const keys = await getEstablishmentKeys(code);
  return keys.filter(k=>{ const d = parseHaccpLogDate(k); return d && d < cutoff; });
}
async function purgeOldHaccpData(code, retentionYears){
  const toDelete = await countOldHaccpData(code, retentionYears);
  for(let i=0;i<toDelete.length;i+=450){
    const batch = db.batch();
    toDelete.slice(i, i+450).forEach(k=>batch.delete(db.collection(FS_COLLECTION).doc(encodeKey(k))));
    await batch.commit();
  }
  if(toDelete.length){
    for(let i=0;i<toDelete.length;i+=450){
      await db.collection(FS_COLLECTION).doc(encodeKey(`keyIndex:${code}`)).set(
        { keys: firebase.firestore.FieldValue.arrayRemove(...toDelete.slice(i, i+450)) },
        { merge: true }
      );
    }
  }
  return toDelete.length;
}

/* ---------- alertes critiques par email (température hors norme, etc.) ----------
   Réutilise un compte EmailJS (le même que pour les notifications de réservation si l'établissement
   en a déjà configuré un — Service ID et Public Key identiques, seul le Template ID change puisque
   le contenu du message est différent). Configuré une seule fois pour tout l'établissement, dans
   index.html → Gestion → Notifications critiques. N'échoue jamais bruyamment : si non configuré,
   ne fait simplement rien (l'app continue de fonctionner normalement sans alerte). */
async function sendCriticalAlert(subject, message){
  try{
    const cfg = await getData(`config:${RESTAURANT_ID}:notifications`, true, null);
    if(!cfg || !cfg.notifyEmail || !cfg.serviceId || !cfg.templateId || !cfg.publicKey) return false;
    if(typeof emailjs === 'undefined') return false;
    await emailjs.send(cfg.serviceId, cfg.templateId, {
      to_email: cfg.notifyEmail,
      alert_subject: subject,
      alert_message: message,
      restaurant_name: RESTAURANT_NAME || '',
    }, {publicKey: cfg.publicKey});
    return true;
  }catch(e){ console.error('sendCriticalAlert error', e); return false; }
}

async function deleteEstablishmentData(code){
  let deletedCount = 0;
  const keys = await getEstablishmentKeys(code);
  for(let i=0;i<keys.length;i+=450){
    const batch = db.batch();
    keys.slice(i, i+450).forEach(k=>batch.delete(db.collection(FS_COLLECTION).doc(encodeKey(k))));
    await batch.commit();
    deletedCount += Math.min(450, keys.length - i);
  }
  const rec = await getData(`restaurant:${code}`, true, {});
  for(const stamp of (rec.backupDates || [])){ deletedCount += await deleteBackupSnapshot(code, stamp); }
  try{ await db.collection(FS_COLLECTION).doc(encodeKey(`restaurant:${code}`)).delete(); deletedCount++; }catch(e){}
  try{ await db.collection(FS_COLLECTION).doc(encodeKey(`errorLog:${code}`)).delete(); deletedCount++; }catch(e){}
  try{ await db.collection(FS_COLLECTION).doc(encodeKey(`keyIndex:${code}`)).delete(); deletedCount++; }catch(e){}
  // supprime aussi le compte Firebase Auth de l'établissement si c'est bien la session courante
  try{
    const user = firebase.auth().currentUser;
    if(user) await user.delete();
  }catch(e){
    // les données Firestore restent supprimées même si ça échoue (ex: reconnexion récente requise) ;
    // on journalise pour pouvoir nettoyer le compte Auth manuellement si besoin, sans bloquer l'opération
    if(typeof logAppError==='function') logAppError({message:'Suppression compte Auth échouée après suppression RGPD ('+code+') : '+e.message, stack: e.stack});
  }
  return deletedCount;
}

/* ---------- sauvegardes automatiques (copies quotidiennes dans le même projet Firestore) ----------
   Limite importante à connaître : ces sauvegardes protègent contre une erreur ou une suppression
   accidentelle DANS l'appli (bug, mauvaise manip, employé qui efface le mauvais truc) — pas contre
   la perte du projet Firebase lui-même (compte Google compromis, projet supprimé...). Une vraie
   sauvegarde externalisée demanderait d'activer l'export planifié Firestore → Cloud Storage depuis
   la console Firebase (plan Blaze requis) : c'est de la configuration d'infrastructure, hors de
   portée de ces fichiers (ni Claude ni ce code n'a accès à ta console Firebase/GCP).

   Aucune requête "liste" ici non plus : la liste des dates conservées vit directement sur la fiche
   établissement (rec.backupDates), et chaque sauvegarde a son propre petit "manifeste" (backupManifest:
   code:date) qui retient quelles clés elle couvre — c'est ce manifeste qui permet de la restaurer ou
   de la supprimer plus tard sans avoir besoin de retrouver ses documents par une recherche. */
const BACKUP_RETENTION = 10; // nombre de sauvegardes quotidiennes conservées par établissement

function todayStamp(){
  const d = new Date();
  return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}
async function readManyDocs(keys){
  const out = [];
  for(let i=0;i<keys.length;i+=20){
    const batch = keys.slice(i, i+20);
    const snaps = await Promise.all(batch.map(k=>db.collection(FS_COLLECTION).doc(encodeKey(k)).get()));
    snaps.forEach((snap,j)=>{ if(snap.exists) out.push({id: batch[j], data: snap.data()}); });
  }
  return out;
}
async function createBackupSnapshot(code){
  const rec = await getData(`restaurant:${code}`, true, null);
  if(!rec) return 0;
  const dataKeys = await getEstablishmentKeys(code);
  const allKeys = [...dataKeys, `restaurant:${code}`];
  const docs = await readManyDocs(allKeys);
  const stamp = todayStamp();
  for(let i=0;i<docs.length;i+=450){
    const batch = db.batch();
    docs.slice(i, i+450).forEach(({id,data})=>{
      batch.set(db.collection(FS_COLLECTION).doc(encodeKey(`backup:${code}:${stamp}:${id}`)), data);
    });
    await batch.commit();
  }
  await db.collection(FS_COLLECTION).doc(encodeKey(`backupManifest:${code}:${stamp}`))
    .set({ keys: docs.map(d=>d.id), createdAt: new Date().toISOString() });

  let dates = (rec.backupDates || []).filter(d=>d!==stamp);
  dates.unshift(stamp);
  const kept = dates.slice(0, BACKUP_RETENTION);
  const dropped = dates.slice(BACKUP_RETENTION);
  for(const oldStamp of dropped){ await deleteBackupSnapshot(code, oldStamp); }
  rec.backupDates = kept;
  rec.lastBackupDate = stamp;
  await setRestaurantRecord(code, rec);
  return docs.length;
}
async function listBackupDates(code){
  const rec = await getData(`restaurant:${code}`, true, {});
  return rec.backupDates || [];
}
async function deleteBackupSnapshot(code, stamp){
  const manifest = await getData(`backupManifest:${code}:${stamp}`, true, null);
  const keys = (manifest && manifest.keys) || [];
  const refs = keys.map(k=>db.collection(FS_COLLECTION).doc(encodeKey(`backup:${code}:${stamp}:${k}`)));
  for(let i=0;i<refs.length;i+=450){
    const batch = db.batch();
    refs.slice(i, i+450).forEach(ref=>batch.delete(ref));
    await batch.commit();
  }
  try{ await db.collection(FS_COLLECTION).doc(encodeKey(`backupManifest:${code}:${stamp}`)).delete(); }catch(e){}
  try{
    const rec = await getData(`restaurant:${code}`, true, {});
    if(rec.backupDates && rec.backupDates.includes(stamp)){
      rec.backupDates = rec.backupDates.filter(d=>d!==stamp);
      await setRestaurantRecord(code, rec);
    }
  }catch(e){ /* pas bloquant si la fiche établissement a déjà été supprimée par ailleurs */ }
  return refs.length;
}
async function restoreBackupSnapshot(code, stamp){
  const manifest = await getData(`backupManifest:${code}:${stamp}`, true, null);
  const keys = (manifest && manifest.keys) || [];
  const backupKeys = keys.map(k=>`backup:${code}:${stamp}:${k}`);
  const docs = await readManyDocs(backupKeys);
  let restored = 0;
  for(let i=0;i<docs.length;i+=450){
    const batch = db.batch();
    docs.slice(i, i+450).forEach(({id,data})=>{
      const originalId = id.slice(`backup:${code}:${stamp}:`.length);
      batch.set(db.collection(FS_COLLECTION).doc(encodeKey(originalId)), data);
      restored++;
    });
    await batch.commit();
  }
  return restored;
}
async function maybeRunAutoBackup(code){
  try{
    const rec = await getData(`restaurant:${code}`, true, null);
    if(!rec || rec.lastBackupDate === todayStamp()) return;
    await createBackupSnapshot(code); // met aussi à jour rec.lastBackupDate
  }catch(e){
    if(typeof logAppError==='function') logAppError({message:'Sauvegarde automatique échouée ('+code+') : '+e.message, stack: e.stack});
  }
}
function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function slugify(s){
  return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
}

// ---------- Authentification multi-établissements ----------
const AUTH_KEY = 'checkTaCuisine_authed';
const REST_ID_KEY = 'checkTaCuisine_restaurantId';
const REST_NAME_KEY = 'checkTaCuisine_restaurantName';
const USER_ID_KEY = 'checkTaCuisine_userId';
const USER_NAME_KEY = 'checkTaCuisine_userName';
const USER_PERMS_KEY = 'checkTaCuisine_userPerms';
const USER_OWNER_KEY = 'checkTaCuisine_userIsOwner';
let RESTAURANT_ID = null;
let RESTAURANT_NAME = null;
let CURRENT_USER_PERMS = [];
let CURRENT_USER_IS_OWNER = false;
let CURRENT_USER_NAME = null;
const ALL_PERMS = ['cuisine','salle','bar','reservation','planning','incidents','absences','validateSwaps'];

function normalizeMember(m){
  if(!m.permissions){
    if(m.zone==='cuisine') m.permissions=['cuisine'];
    else if(m.zone==='salle_bar') m.permissions=['salle','bar','reservation'];
    else m.permissions=[];
  }
  if(!m.postes){
    m.postes = m.permissions.filter(p=>['cuisine','salle','bar'].includes(p));
  }
  return m;
}

/* ---------- anti brute-force du code PIN à 4 chiffres ---------- */
const PIN_MAX_ATTEMPTS = 5;
const PIN_LOCKOUT_MS = 5 * 60 * 1000; // 5 minutes
async function registerPinFailure(memberId){
  const team = await getData(`config:${RESTAURANT_ID}:team`, true, []);
  const idx = team.findIndex(m=>m.id===memberId);
  if(idx===-1) return null;
  const m = team[idx];
  m.failedPinAttempts = (m.failedPinAttempts||0) + 1;
  let locked = false;
  if(m.failedPinAttempts >= PIN_MAX_ATTEMPTS){
    m.pinLockedUntil = Date.now() + PIN_LOCKOUT_MS;
    m.failedPinAttempts = 0;
    locked = true;
  }
  await setData(`config:${RESTAURANT_ID}:team`, team, true);
  return {attempts: m.failedPinAttempts, locked};
}
async function clearPinFailures(memberId){
  const team = await getData(`config:${RESTAURANT_ID}:team`, true, []);
  const idx = team.findIndex(m=>m.id===memberId);
  if(idx===-1) return;
  if(!team[idx].failedPinAttempts && !team[idx].pinLockedUntil) return; // rien à mettre à jour
  team[idx].failedPinAttempts = 0;
  team[idx].pinLockedUntil = null;
  await setData(`config:${RESTAURANT_ID}:team`, team, true);
}

function authInput(id, placeholder, type){
  return `<input id="${id}" type="${type||'text'}" placeholder="${placeholder}" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:13px;font-size:16px;margin-bottom:10px;box-sizing:border-box;">`;
}

/* ---------- identification personnelle (accès par emplacement) ---------- */
function identityIcon(){
  return `<div style="width:56px;height:56px;border-radius:16px;background:var(--sea-deep);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round"/></svg></div>`;
}
async function ensurePersonalAccess(requiredPerm, onGranted){
  loadThemeForCurrentRestaurant();   // couleurs du restaurant (en arrière-plan : ne retarde jamais l'accès)
  const savedOwner = sessionStorage.getItem(USER_OWNER_KEY) === 'true';
  const savedPermsRaw = sessionStorage.getItem(USER_PERMS_KEY);
  if(savedOwner || savedPermsRaw !== null){
    CURRENT_USER_IS_OWNER = savedOwner;
    CURRENT_USER_PERMS = savedPermsRaw ? JSON.parse(savedPermsRaw) : [];
    CURRENT_USER_NAME = sessionStorage.getItem(USER_NAME_KEY);
    if(savedOwner && RESTAURANT_ID) maybeRunAutoBackup(RESTAURANT_ID); // en arrière-plan, ne bloque pas l'accès
    if(requiredPerm===null || savedOwner || CURRENT_USER_PERMS.includes(requiredPerm)){ onGranted(); return; }
    window.location.href = 'index.html';
    return;
  }
  renderIdentityPicker(requiredPerm, onGranted);
}
async function renderIdentityPicker(requiredPerm, onGranted, opts){
  opts = opts || {};
  const root = document.getElementById('root');
  if(opts.ownerMode){
    root.innerHTML = `
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
      <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
        ${identityIcon()}
        <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">Accès responsable</h1>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 18px;">Ressaisis le mot de passe de l'établissement</p>
        ${authInput('owner-pwd','Mot de passe établissement','password')}
        ${opts.error?`<p style="color:#B2432B;font-size:12.5px;margin:0 0 14px;">${opts.error}</p>`:''}
        <button id="owner-submit" style="width:100%;background:var(--sea-deep);color:#fff;border:none;border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">Entrer</button>
        <button id="owner-back" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:14px;cursor:pointer;">← Retour</button>
      </div>
    </div>`;
    const submit = async ()=>{
      const pwd = document.getElementById('owner-pwd').value;
      const rec = await getData(`restaurant:${RESTAURANT_ID}`, true, null);
      if(!rec){ renderIdentityPicker(requiredPerm, onGranted, {ownerMode:true, error:'Mot de passe incorrect'}); return; }
      const authEmail = rec.recoveryEmail || slugToAuthEmail(RESTAURANT_ID);
      let ok = false;
      try{
        await firebase.auth().signInWithEmailAndPassword(authEmail, pwd);
        ok = true;
      }catch(authErr){
        // filet de sécurité pour un compte pas encore migré (ancien mot de passe encore présent)
        if(rec.password && rec.password === pwd) ok = true;
      }
      if(!ok){ renderIdentityPicker(requiredPerm, onGranted, {ownerMode:true, error:'Mot de passe incorrect'}); return; }
      CURRENT_USER_IS_OWNER = true; CURRENT_USER_PERMS = ALL_PERMS.slice(); CURRENT_USER_NAME = 'Responsable';
      sessionStorage.setItem(USER_OWNER_KEY,'true'); sessionStorage.setItem(USER_PERMS_KEY, JSON.stringify(ALL_PERMS));
      sessionStorage.setItem(USER_NAME_KEY,'Responsable'); sessionStorage.setItem(USER_ID_KEY,'owner');
      onGranted();
    };
    document.getElementById('owner-submit').onclick = submit;
    document.getElementById('owner-pwd').addEventListener('keydown', e=>{ if(e.key==='Enter') submit(); });
    document.getElementById('owner-back').onclick = ()=>renderIdentityPicker(requiredPerm, onGranted);
    return;
  }
  if(opts.pinFor){
    const member = opts.pinFor;
    const now = Date.now();
    if(member.pinLockedUntil && member.pinLockedUntil > now){
      const minsLeft = Math.ceil((member.pinLockedUntil - now) / 60000);
      root.innerHTML = `
      <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
        <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
          ${identityIcon()}
          <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">${escapeHtml(member.name)}</h1>
          <p style="font-size:12.5px;color:var(--alert);margin:0 0 18px;">🔒 Trop de tentatives incorrectes. Réessaie dans ${minsLeft} min.</p>
          <button id="member-back" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:4px;cursor:pointer;">← Retour</button>
        </div>
      </div>`;
      document.getElementById('member-back').onclick = ()=>renderIdentityPicker(requiredPerm, onGranted);
      return;
    }
    // rendu + focus() immédiats et synchrones (pas d'await avant) : sur Android, un délai réseau
    // avant focus() empêche souvent le clavier virtuel de s'ouvrir automatiquement. On revérifie
    // le statut de verrouillage à jour juste après, en arrière-plan, sans voler le focus au champ.
    root.innerHTML = `
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
      <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
        ${identityIcon()}
        <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">${escapeHtml(member.name)}</h1>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 18px;">Code personnel (4 chiffres)</p>
        <input id="member-pin" type="password" inputmode="numeric" maxlength="4" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:13px;font-size:22px;text-align:center;letter-spacing:8px;margin-bottom:10px;box-sizing:border-box;" placeholder="••••">
        ${opts.error?`<p style="color:#B2432B;font-size:12.5px;margin:0 0 14px;">${opts.error}</p>`:''}
        <button id="member-submit" style="width:100%;background:var(--sea-deep);color:#fff;border:none;border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">Entrer</button>
        <button id="member-back" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:14px;cursor:pointer;">← Retour</button>
      </div>
    </div>`;
    const pinInput = document.getElementById('member-pin');
    pinInput.focus();
    let localLocked = false;
    getData(`config:${RESTAURANT_ID}:team`, true, []).then(freshTeam=>{
      const fresh = freshTeam.find(x=>x.id===member.id);
      if(fresh && fresh.pinLockedUntil && fresh.pinLockedUntil > Date.now()){
        localLocked = true;
        renderIdentityPicker(requiredPerm, onGranted, {pinFor: fresh}); // verrouillé entre-temps (autre appareil) : on rebascule sur l'écran de blocage
      }
    });
    const submit = async ()=>{
      if(localLocked) return;
      if(pinInput.value !== member.pin){
        const result = await registerPinFailure(member.id);
        if(result && result.locked){
          member.pinLockedUntil = Date.now() + PIN_LOCKOUT_MS; // reflète localement sans nouvel aller-retour réseau
          renderIdentityPicker(requiredPerm, onGranted, {pinFor:member}); return;
        }
        const attemptsLeft = PIN_MAX_ATTEMPTS - ((result && result.attempts) || 0);
        renderIdentityPicker(requiredPerm, onGranted, {pinFor:member, error:`Code incorrect (${attemptsLeft} essai${attemptsLeft>1?'s':''} avant blocage temporaire)`});
        return;
      }
      await clearPinFailures(member.id);
      CURRENT_USER_IS_OWNER = false; CURRENT_USER_PERMS = member.permissions; CURRENT_USER_NAME = member.name;
      sessionStorage.setItem(USER_OWNER_KEY,'false'); sessionStorage.setItem(USER_PERMS_KEY, JSON.stringify(member.permissions));
      sessionStorage.setItem(USER_NAME_KEY, member.name); sessionStorage.setItem(USER_ID_KEY, member.id);
      if(requiredPerm===null || member.permissions.includes(requiredPerm)) onGranted(); else window.location.href = 'index.html';
    };
    document.getElementById('member-submit').onclick = submit;
    pinInput.addEventListener('keydown', e=>{ if(e.key==='Enter') submit(); });
    document.getElementById('member-back').onclick = ()=>renderIdentityPicker(requiredPerm, onGranted);
    return;
  }
  const team = (await getData(`config:${RESTAURANT_ID}:team`, true, [])).map(normalizeMember);
  root.innerHTML = `
  <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
    <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
      ${identityIcon()}
      <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">Qui es-tu ?</h1>
      <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 14px;">${escapeHtml(RESTAURANT_NAME||'')}</p>
      <button id="qet-badge-link" style="display:flex;align-items:center;gap:16px;width:100%;background:linear-gradient(135deg,var(--sea-deep),var(--sea));border:none;border-radius:18px;padding:20px;text-align:left;font:inherit;cursor:pointer;color:#fff;box-shadow:0 6px 16px rgba(18,59,58,0.25);margin-bottom:16px;box-sizing:border-box;">
        <div style="width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:28px;">⏱</div>
        <div style="flex:1;"><strong style="display:block;font-family:'Fraunces',serif;font-size:19px;font-weight:600;margin-bottom:2px;">Check la badgeuse</strong><span style="font-size:12.5px;color:rgba(255,255,255,0.85);">Pointer une arrivée, une pause ou un départ</span></div>
        <div style="margin-left:auto;font-size:20px;opacity:0.7;flex-shrink:0;">→</div>
      </button>
      <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;color:var(--ink-soft);font-size:12px;"><span style="flex:1;height:1px;background:var(--line);"></span>ou choisis ton prénom<span style="flex:1;height:1px;background:var(--line);"></span></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;width:100%;margin-bottom:14px;">
        ${team.map(m=>`<button style="position:relative;display:flex;align-items:center;gap:9px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;color:var(--ink);font-weight:700;background:#fff;cursor:pointer;font-size:13.5px;text-align:left;width:100%;" data-mid="${m.id}"><span style="width:30px;height:30px;border-radius:50%;background:var(--sea-deep);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:13px;font-weight:700;flex-shrink:0;">${escapeHtml(m.name.charAt(0).toUpperCase())}</span><span>${escapeHtml(m.name)}</span>${m.pendingSignatureRequest?'<span style="position:absolute;top:6px;right:8px;width:9px;height:9px;border-radius:50%;background:var(--alert);" title="Récapitulatif à signer"></span>':''}</button>`).join('') || '<p style="font-size:12.5px;color:var(--ink-soft);">Aucun employé enregistré pour le moment.</p>'}
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--ink-soft);font-size:12px;"><span style="flex:1;height:1px;background:var(--line);"></span>ou<span style="flex:1;height:1px;background:var(--line);"></span></div>
      <button id="owner-link" style="width:100%;background:#fff;color:var(--sea-deep);border:1.5px solid var(--sea-deep);border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">👤 Responsable</button>
      <button id="identity-logout-link" style="background:none;border:none;color:var(--alert);font-weight:700;font-size:12.5px;margin-top:16px;cursor:pointer;">Se déconnecter de cet établissement</button>
    </div>
  </div>`;
  root.querySelectorAll('[data-mid]').forEach(btn=>{
    btn.onclick = ()=>{
      const m = team.find(x=>x.id===btn.dataset.mid);
      renderIdentityPicker(requiredPerm, onGranted, {pinFor:m});
    };
  });
  document.getElementById('qet-badge-link').onclick = ()=>{ window.location.href = 'index.html?kiosk=badge'; };
  document.getElementById('owner-link').onclick = ()=>renderIdentityPicker(requiredPerm, onGranted, {ownerMode:true});
  document.getElementById('identity-logout-link').onclick = ()=>{
    performLogout();
    renderLoginScreen();
  };
}
function switchPerson(){
  sessionStorage.removeItem(USER_ID_KEY); sessionStorage.removeItem(USER_NAME_KEY);
  sessionStorage.removeItem(USER_PERMS_KEY); sessionStorage.removeItem(USER_OWNER_KEY);
  CURRENT_USER_PERMS = []; CURRENT_USER_IS_OWNER = false; CURRENT_USER_NAME = null;
  ensurePersonalAccess(REQUIRED_PERM, onIdentityGranted);
}

/* ---------- connexion établissement ---------- */
function renderLoginScreen(opts){
  opts = opts || {};
  const root = document.getElementById('root');
  const cardTop = `
    <div style="width:56px;height:56px;border-radius:16px;background:var(--sea-deep);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;">${icon('lock',26)}</div>`;
  if(opts.mode === 'create'){
    root.innerHTML = `
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
      <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
        ${cardTop}
        <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">Nouvel établissement</h1>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 18px;">Crée l'accès pour un restaurant du groupe</p>
        ${authInput('create-name','Nom du restaurant')}
        ${authInput('create-code','Code (ex : cannes)')}
        ${authInput('create-email',"Ton email (pour récupérer l'accès en cas d'oubli)",'email')}
        ${authInput('create-password','Mot de passe','password')}
        <div style="margin-bottom:${opts.error?'6px':'14px'};">${authInput('create-password-confirm','Confirmer le mot de passe','password')}</div>
        <label style="display:flex;align-items:center;gap:8px;text-align:left;font-size:12.5px;color:var(--ink-soft);margin-bottom:16px;cursor:pointer;">
          <input type="checkbox" id="create-remember" style="width:16px;height:16px;flex-shrink:0;">
          Se souvenir de cet établissement sur cet appareil
        </label>
        <label style="display:flex;align-items:flex-start;gap:8px;text-align:left;font-size:11.5px;color:var(--ink-soft);margin-bottom:16px;cursor:pointer;">
          <input type="checkbox" id="create-cgu" style="width:16px;height:16px;flex-shrink:0;margin-top:1px;">
          <span>J'accepte les Conditions Générales d'Utilisation et la Politique de confidentialité</span>
        </label>
        ${opts.error?`<p style="color:#B2432B;font-size:12.5px;margin:0 0 14px;">${opts.error}</p>`:''}
        <button id="create-submit" style="width:100%;background:var(--sea-deep);color:#fff;border:none;border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">Créer et se connecter</button>
        <button id="create-back" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:14px;cursor:pointer;">← Retour à la connexion</button>
      </div>
    </div>`;
    document.getElementById('create-submit').onclick = attemptCreateRestaurant;
    document.getElementById('create-back').onclick = ()=>renderLoginScreen({mode:'login'});
    document.getElementById('create-name').focus();
  } else if(opts.mode === 'forgot'){
    root.innerHTML = `
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
      <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
        ${cardTop}
        <h1 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px;color:var(--sea-deep);">Mot de passe oublié</h1>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 18px;">Indique le code de ton établissement, un lien de réinitialisation sera envoyé à l'email de récupération enregistré.</p>
        <div style="margin-bottom:${opts.error||opts.info?'6px':'14px'};">${authInput('forgot-code','Code établissement')}</div>
        ${opts.error?`<p style="color:#B2432B;font-size:12.5px;margin:0 0 14px;">${opts.error}</p>`:''}
        ${opts.info?`<p style="color:var(--sea);font-size:12.5px;margin:0 0 14px;">${opts.info}</p>`:''}
        <button id="forgot-submit" style="width:100%;background:var(--sea-deep);color:#fff;border:none;border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">Envoyer le lien</button>
        <button id="forgot-back" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:14px;cursor:pointer;">← Retour à la connexion</button>
      </div>
    </div>`;
    document.getElementById('forgot-submit').onclick = attemptPasswordReset;
    document.getElementById('forgot-back').onclick = ()=>renderLoginScreen({mode:'login'});
    document.getElementById('forgot-code').focus();
  } else {
    root.innerHTML = `
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--sand);">
      <div style="max-width:340px;width:100%;background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px 24px;text-align:center;">
        ${cardTop}
        <h1 style="font-family:'Fraunces',serif;font-size:20px;margin:0 0 4px;color:var(--sea-deep);">${MODULE_TITLE}</h1>
        <p style="font-size:13px;color:var(--ink-soft);margin:0 0 20px;">Connexion à votre établissement</p>
        ${authInput('login-code','Code établissement')}
        <div style="margin-bottom:${opts.error?'6px':'14px'};">${authInput('login-password','Mot de passe','password')}</div>
        ${opts.error?`<p style="color:#B2432B;font-size:12.5px;margin:0 0 14px;">${opts.error}</p>`:''}
        <label style="display:flex;align-items:center;gap:8px;text-align:left;font-size:12.5px;color:var(--ink-soft);margin-bottom:16px;cursor:pointer;">
          <input type="checkbox" id="login-remember" style="width:16px;height:16px;flex-shrink:0;">
          Se souvenir de cet établissement sur cet appareil
        </label>
        <button id="login-submit" style="width:100%;background:var(--sea-deep);color:#fff;border:none;border-radius:12px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">Entrer</button>
        <button id="login-forgot-link" style="background:none;border:none;color:var(--ink-soft);font-weight:600;font-size:12.5px;margin-top:14px;cursor:pointer;display:block;width:100%;">Mot de passe oublié ?</button>
        <button id="login-create-link" style="background:none;border:none;color:var(--sea);font-weight:700;font-size:13px;margin-top:8px;cursor:pointer;">Nouvel établissement du groupe ?</button>
      </div>
    </div>`;
    document.getElementById('login-submit').onclick = attemptLogin;
    document.getElementById('login-forgot-link').onclick = ()=>renderLoginScreen({mode:'forgot'});
    document.getElementById('login-create-link').onclick = ()=>renderLoginScreen({mode:'create'});
    const pwInput = document.getElementById('login-password');
    pwInput.addEventListener('keydown', e=>{ if(e.key==='Enter') attemptLogin(); });
    document.getElementById('login-code').focus();
  }
}

async function attemptPasswordReset(){
  const codeRaw = document.getElementById('forgot-code').value.trim();
  if(!codeRaw){ renderLoginScreen({mode:'forgot', error:'Indique le code établissement'}); return; }
  const code = slugify(codeRaw);
  const rec = await getData(`restaurant:${code}`, true, null);
  if(!rec){ renderLoginScreen({mode:'forgot', error:'Code établissement introuvable'}); return; }
  if(!rec.recoveryEmail){
    renderLoginScreen({mode:'forgot', error:"Aucun email de récupération n'est enregistré pour cet établissement. Demande à un responsable de le configurer dans \"Gérer l'équipe\", ou contacte le support."});
    return;
  }
  try{
    await firebase.auth().sendPasswordResetEmail(rec.recoveryEmail);
    renderLoginScreen({mode:'forgot', info: `Email envoyé à ${rec.recoveryEmail.replace(/^(.{2}).+(@.+)$/,'$1···$2')}. Vérifie ta boîte mail (et les spams).`});
  }catch(e){
    renderLoginScreen({mode:'forgot', error: "Erreur lors de l'envoi : "+(e.message||'réessaie')});
  }
}

function slugToAuthEmail(slug){ return slug + '@resto.checktonresto.local'; }
function isValidEmail(s){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s); }

/* ---------- persistance établissement : session (par défaut) ou permanente si "se souvenir" coché ---------- */
function persistEstablishmentSession(code, name, remember){
  const store = remember ? localStorage : sessionStorage;
  const other = remember ? sessionStorage : localStorage;
  store.setItem(REST_ID_KEY, code);
  store.setItem(REST_NAME_KEY, name);
  store.setItem(AUTH_KEY, 'true');
  other.removeItem(REST_ID_KEY); other.removeItem(REST_NAME_KEY); other.removeItem(AUTH_KEY);
}
function getEstablishmentSession(){
  const id = sessionStorage.getItem(REST_ID_KEY) || localStorage.getItem(REST_ID_KEY);
  const name = sessionStorage.getItem(REST_NAME_KEY) || localStorage.getItem(REST_NAME_KEY);
  const authed = sessionStorage.getItem(AUTH_KEY) === 'true' || localStorage.getItem(AUTH_KEY) === 'true';
  return {id, name, authed};
}
function clearEstablishmentSession(){
  [REST_ID_KEY, REST_NAME_KEY, AUTH_KEY].forEach(k=>{ sessionStorage.removeItem(k); localStorage.removeItem(k); });
}

async function attemptLogin(){
  const codeRaw = document.getElementById('login-code').value.trim();
  const pwd = document.getElementById('login-password').value;
  if(!codeRaw || !pwd){ renderLoginScreen({error:'Renseigne le code et le mot de passe'}); return; }
  const code = slugify(codeRaw);
  const rec = await getData(`restaurant:${code}`, true, null);
  if(!rec){ renderLoginScreen({error:'Code ou mot de passe incorrect'}); return; }
  const authEmail = rec.recoveryEmail || slugToAuthEmail(code);
  let authedUid = null;

  try{
    const cred = await firebase.auth().signInWithEmailAndPassword(authEmail, pwd);
    authedUid = cred.user.uid;
  }catch(authErr){
    // Les versions récentes de Firebase Auth renvoient "auth/invalid-credential" à la fois pour
    // "mauvais mot de passe" et "compte pas encore migré" (anti-énumération de comptes côté Firebase).
    // On tente donc la migration dans les deux cas ; on ne refuse que si la création du compte
    // sécurisé échoue parce qu'il existe déjà (preuve que c'était bien un mauvais mot de passe).
    if(authErr.code === 'auth/invalid-credential' || authErr.code === 'auth/wrong-password' || authErr.code === 'auth/user-not-found'){
      if(rec.password !== pwd){ renderLoginScreen({error:'Code ou mot de passe incorrect'}); return; }
      try{
        const cred = await firebase.auth().createUserWithEmailAndPassword(authEmail, pwd);
        authedUid = cred.user.uid;
      }catch(createErr){
        if(createErr.code === 'auth/email-already-in-use'){
          renderLoginScreen({error:'Code ou mot de passe incorrect'}); return;
        }
        if(typeof logAppError==='function') logAppError({message:'Migration auth échouée : '+createErr.message, stack: createErr.stack});
      }
    } else {
      // erreur vraiment inattendue (réseau, etc.) : ne bloque pas l'accès pendant la période de transition
      if(typeof logAppError==='function') logAppError({message:'Erreur auth inattendue : '+authErr.message, stack: authErr.stack});
      if(rec.password !== pwd){ renderLoginScreen({error:'Code ou mot de passe incorrect'}); return; }
    }
  }
  if(authedUid){
    // garde le champ Firestore "ownerUid" synchronisé à chaque connexion (auto-réparation pour les comptes déjà migrés à l'ancienne)
    // et supprime le mot de passe en clair, désormais inutile une fois la connexion sécurisée établie
    rec.ownerUid = authedUid;
    delete rec.password;
    await setRestaurantRecord(code, rec);
  }

  RESTAURANT_ID = code; RESTAURANT_NAME = rec.name;
  loadThemeForCurrentRestaurant();
  const remember = document.getElementById('login-remember') ? document.getElementById('login-remember').checked : false;
  persistEstablishmentSession(code, rec.name, remember);
  ensurePersonalAccess(REQUIRED_PERM, onIdentityGranted);
}

async function attemptCreateRestaurant(){
  const name = document.getElementById('create-name').value.trim();
  const codeRaw = document.getElementById('create-code').value.trim();
  const email = document.getElementById('create-email').value.trim();
  const pwd = document.getElementById('create-password').value;
  const pwd2 = document.getElementById('create-password-confirm').value;
  if(!name || !codeRaw || !email || !pwd){ renderLoginScreen({mode:'create', error:'Complète tous les champs'}); return; }
  if(!isValidEmail(email)){ renderLoginScreen({mode:'create', error:'Adresse email invalide'}); return; }
  if(pwd !== pwd2){ renderLoginScreen({mode:'create', error:'Les mots de passe ne correspondent pas'}); return; }
  if(!document.getElementById('create-cgu').checked){ renderLoginScreen({mode:'create', error:"Merci d'accepter les Conditions Générales d'Utilisation pour continuer"}); return; }
  const code = slugify(codeRaw);
  if(!code){ renderLoginScreen({mode:'create', error:'Code invalide'}); return; }
  const existing = await getData(`restaurant:${code}`, true, null);
  if(existing){ renderLoginScreen({mode:'create', error:'Ce code est déjà utilisé, choisis-en un autre'}); return; }

  let ownerUid = null;
  try{
    const cred = await firebase.auth().createUserWithEmailAndPassword(email, pwd);
    ownerUid = cred.user.uid;
  }catch(authErr){
    const msg = authErr.code === 'auth/email-already-in-use' ? 'Cet email est déjà utilisé pour un autre établissement.' : (authErr.message||'réessaie');
    renderLoginScreen({mode:'create', error:'Erreur lors de la création du compte sécurisé : '+msg});
    return;
  }

  const saved = await setRestaurantRecord(code, {name, ownerUid, recoveryEmail:email, cguAcceptedAt: new Date().toISOString(), ...defaultSubscriptionFields()});
  if(!saved){ renderLoginScreen({mode:'create', error:'Erreur de création, réessaie'}); return; }
  RESTAURANT_ID = code; RESTAURANT_NAME = name;
  const remember = document.getElementById('create-remember') ? document.getElementById('create-remember').checked : false;
  persistEstablishmentSession(code, name, remember);
  ensurePersonalAccess(REQUIRED_PERM, onIdentityGranted);
}

/* ---------- point d'entrée : à appeler depuis chaque page hôte ---------- */
function waitForAuthReady(){
  return new Promise(resolve=>{
    let unsub;
    unsub = firebase.auth().onAuthStateChanged(user=>{ if(unsub) unsub(); resolve(user); });
  });
}
async function initAuth(){
  await waitForAuthReady(); // Firebase met un instant à rétablir la session après un rechargement : on l'attend avant de lire quoi que ce soit
  const {id: savedId, name: savedName, authed} = getEstablishmentSession();
  if(authed && savedId){
    RESTAURANT_ID = savedId; RESTAURANT_NAME = savedName;
    loadThemeForCurrentRestaurant();
    ensurePersonalAccess(REQUIRED_PERM, onIdentityGranted);
  } else {
    renderLoginScreen();
  }
}

function performLogout(){
  clearEstablishmentSession();
  sessionStorage.removeItem(USER_ID_KEY);
  sessionStorage.removeItem(USER_NAME_KEY);
  sessionStorage.removeItem(USER_PERMS_KEY);
  sessionStorage.removeItem(USER_OWNER_KEY);
  RESTAURANT_ID = null; RESTAURANT_NAME = null; CURRENT_USER_PERMS = []; CURRENT_USER_IS_OWNER = false; CURRENT_USER_NAME = null;
}

/* ---------- suivi d'erreurs (journal consultable depuis l'accueil) ---------- */
async function logAppError(info){
  try{
    const rid = (typeof RESTAURANT_ID !== 'undefined' && RESTAURANT_ID) ? RESTAURANT_ID : 'inconnu';
    const entry = {
      ts: new Date().toISOString(),
      message: String(info.message||'').slice(0,500),
      stack: String(info.stack||'').slice(0,1000),
      page: location.pathname.split('/').pop() || 'index.html',
      user: (typeof CURRENT_USER_NAME !== 'undefined' && CURRENT_USER_NAME) ? CURRENT_USER_NAME : null,
    };
    const arr = await getData(`errorLog:${rid}`, true, []);
    arr.push(entry);
    if(arr.length > 200) arr.splice(0, arr.length - 200);
    await setData(`errorLog:${rid}`, arr, true);
  }catch(e){ /* on ne journalise jamais en boucle une erreur de journalisation */ }
}
window.addEventListener('error', (e)=>{
  logAppError({message: e.message, stack: e.error && e.error.stack});
});
window.addEventListener('unhandledrejection', (e)=>{
  const reason = e.reason;
  logAppError({message: 'Promesse non gérée : ' + (reason && reason.message ? reason.message : String(reason)), stack: reason && reason.stack});
});

/* ---------- bandeau "pas de connexion" ---------- */
function initOfflineBanner(){
  if(document.getElementById('offline-banner')) return;
  const el = document.createElement('div');
  el.id = 'offline-banner';
  el.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#B2432B;color:#fff;text-align:center;padding:9px 12px;font-size:12.5px;font-weight:700;z-index:99999;display:none;';
  el.textContent = "⚠️ Pas de connexion internet — certaines actions ne seront pas enregistrées tant qu'elle n'est pas rétablie.";
  document.body.appendChild(el);
  function update(){ el.style.display = navigator.onLine ? 'none' : 'block'; }
  window.addEventListener('online', update);
  window.addEventListener('offline', update);
  update();
}
initOfflineBanner();


/* ---------- appli installable + ouverture instantanée (service worker) ----------
   Le service worker (sw.js) garde les fichiers de l'appli sur l'appareil. Quand une nouvelle version
   est détectée en arrière-plan, on affiche une petite bannière plutôt que de recharger de force :
   on ne coupe jamais quelqu'un en pleine saisie (signature, relevé, badgeage…). */
(function(){
  if(!('serviceWorker' in navigator)) return;
  const secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if(!secure) return;
  const hadController = !!navigator.serviceWorker.controller;

  function showUpdateBanner(){
    if(document.getElementById('ctr-update-banner') || !document.body) return;
    const bar = document.createElement('div');
    bar.id = 'ctr-update-banner';
    bar.setAttribute('role', 'status');
    bar.style.cssText = 'position:fixed;left:12px;right:12px;top:calc(env(safe-area-inset-top,0px) + 10px);z-index:99998;' +
      'max-width:420px;margin:0 auto;background:var(--sea-deep);color:var(--sand);border-radius:14px;padding:11px 14px;' +
      'display:flex;align-items:center;gap:10px;font:600 13px/1.3 Inter,system-ui,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.25);';
    bar.innerHTML = '<span style="flex:1;">Nouvelle version disponible</span>' +
      '<button type="button" id="ctr-update-go" style="background:var(--sand);color:var(--sea-deep);border:none;border-radius:9px;padding:7px 12px;font:700 12.5px Inter,system-ui,sans-serif;cursor:pointer;">Actualiser</button>' +
      '<button type="button" id="ctr-update-x" aria-label="Plus tard" style="background:none;border:none;color:var(--sand);font-size:18px;line-height:1;padding:2px 4px;cursor:pointer;opacity:.8;">✕</button>';
    document.body.appendChild(bar);
    document.getElementById('ctr-update-go').onclick = () => location.reload();
    document.getElementById('ctr-update-x').onclick = () => bar.remove();
  }

  navigator.serviceWorker.addEventListener('message', e => {
    if(e.data && e.data.type === 'APP_UPDATED') showUpdateBanner();
  });
  // Nouveau service worker qui prend la main alors qu'un ancien était actif = mise à jour du système de cache.
  navigator.serviceWorker.addEventListener('controllerchange', () => { if(hadController) showUpdateBanner(); });

  // Retour au premier plan (iPhone/tablette : l'appli reste en mémoire sans se recharger) → on regarde s'il y a du nouveau.
  document.addEventListener('visibilitychange', () => {
    if(document.visibilityState === 'visible' && navigator.serviceWorker.controller){
      navigator.serviceWorker.controller.postMessage({ type: 'CHECK_UPDATE' });
    }
  });

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(err => console.warn('Service worker non enregistré :', err));
  });
})();

/* =====================================================================
   THÈME : couleurs personnalisables par restaurant
   ---------------------------------------------------------------------
   Le propriétaire choisit 2 couleurs : la couleur PRINCIPALE (en-têtes, boutons) et la couleur de FOND.
   Tout le reste (variantes, textes, bordures, bandeaux…) en est déduit ici, avec des garanties de lisibilité :
     - la principale est assombrie si besoin pour que du texte blanc reste lisible dessus (contraste ≥ 4,5) ;
     - le fond reste clair (l'appli n'a pas de mode sombre) ;
     - les textes secondaires restent lisibles sur le fond choisi.
   Les couleurs "de sens" ne changent JAMAIS : rouge = alerte, vert = OK, orange = attention.
   Enregistré dans la clé publique reservation:{code}:theme, car la page de réservation des clients
   doit pouvoir la lire sans être connecté. Sans enregistrement : couleurs d'origine, rien ne change.
   ===================================================================== */
const THEME_DEFAULT = { primary: '#123B3A', bg: '#F7F1E6' };
const THEME_PRESETS = [
  { id: 'vert-creme',      name: 'Vert & crème',        primary: '#123B3A', bg: '#F7F1E6' },
  { id: 'bleu-blanc',      name: 'Bleu & blanc',        primary: '#1D4E89', bg: '#F4F8FD' },
  { id: 'bleu-creme',      name: 'Bleu & crème',        primary: '#1F4E79', bg: '#F7F1E6' },
  { id: 'ocean-soleil',    name: 'Océan & soleil',      primary: '#0B4F6C', bg: '#FFF3CF' },
  { id: 'rose-jaune',      name: 'Rose & jaune',        primary: '#C2185B', bg: '#FFF4C7' },
  { id: 'corail-creme',    name: 'Corail & crème',      primary: '#B8442A', bg: '#FFF6EB' },
  { id: 'turquoise-sable', name: 'Turquoise & sable',   primary: '#0F766E', bg: '#FFF9F0' },
  { id: 'bordeaux-creme',  name: 'Bordeaux & crème',    primary: '#6D1F2C', bg: '#F8F1EA' },
  { id: 'violet-lavande',  name: 'Violet & lavande',    primary: '#4B2A7B', bg: '#F4EFFA' },
  { id: 'orange-creme',    name: 'Orange & crème',      primary: '#A8430C', bg: '#FBF1DE' },
  { id: 'noir-blanc',      name: 'Noir & blanc cassé',  primary: '#1F2326', bg: '#F4F2EE' },
];
const THEME_VAR_NAMES = ['--sea-deep','--sea','--sea-light','--sand','--sand-dark','--line','--ink','--ink-soft',
  '--tint','--tint-strong','--on-dark-soft','--brand','--brand-dark','--brand-tint','--paper','--paper-dim'];
const THEME_ORIGINAL_VARS = { '--sea-deep':'#123B3A', '--sea':'#1C5C57', '--sea-light':'#2E8079', '--sand':'#F7F1E6',
  '--sand-dark':'#EDE3D0', '--line':'#E1D8C4', '--ink':'#20302E', '--ink-soft':'#5A6B67', '--tint':'#EAF2EE',
  '--tint-strong':'#B9D6D1', '--on-dark-soft':'#9FC2BC' };
const THEME_CACHE_KEY = 'ctr_theme_cache';
// couleurs utilisées par les PDF (jsPDF veut des valeurs RVB) ; mises à jour quand le thème change
const THEME_RGB = { primary:[18,59,58], ink:[32,48,46], inkSoft:[90,107,103], bg:[247,241,230], onDark:[200,215,212] };

/* ---- mathématique des couleurs ---- */
function normHex(h, fallback){
  const m = /^#?([0-9a-f]{6})$/i.exec(String(h || '').trim());
  return m ? '#' + m[1].toUpperCase() : (fallback || '#000000');
}
function hexToRgb(h){ const n = parseInt(normHex(h).slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
function rgbToHex(r, g, b){ return '#' + [r, g, b].map(v => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('').toUpperCase(); }
function rgbToHsl(r, g, b){
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b); let h = 0, s = 0; const l = (max + min) / 2;
  if(max !== min){
    const d = max - min; s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if(max === r) h = (g - b) / d + (g < b ? 6 : 0); else if(max === g) h = (b - r) / d + 2; else h = (r - g) / d + 4;
    h *= 60;
  }
  return [h, s, l];
}
function hslToRgb(h, s, l){
  h = (((h % 360) + 360) % 360) / 360;
  if(s === 0){ const v = l * 255; return [v, v, v]; }
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
  const f = t => { if(t < 0) t += 1; if(t > 1) t -= 1; if(t < 1/6) return p + (q - p) * 6 * t; if(t < 1/2) return q; if(t < 2/3) return p + (q - p) * (2/3 - t) * 6; return p; };
  return [f(h + 1/3) * 255, f(h) * 255, f(h - 1/3) * 255];
}
function relLuminance(rgb){
  const c = rgb.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
}
function contrastRatio(a, b){
  const la = relLuminance(a), lb = relLuminance(b);
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}
function mixRgb(a, b, t){ return a.map((v, i) => v + (b[i] - v) * t); }   // t = part de b
function roundRgb(a){ return a.map(v => Math.round(v)); }   // couleur telle qu'elle sera réellement affichée (8 bits)

/* ---- déduit toutes les couleurs de l'appli à partir des 2 couleurs choisies ---- */
function deriveTheme(input){
  const WHITE = [255, 255, 255];
  const notes = [];
  const inPrimary = normHex(input && input.primary, THEME_DEFAULT.primary);
  const inBg = normHex(input && input.bg, THEME_DEFAULT.bg);
  const isDefault = inPrimary === THEME_DEFAULT.primary && inBg === THEME_DEFAULT.bg;

  // 1. couleur principale : doit porter du texte blanc lisible
  let [h, s, l] = rgbToHsl(...hexToRgb(inPrimary));
  let darkened = false;
  while(contrastRatio(roundRgb(hslToRgb(h, s, l)), WHITE) < 4.5 && l > 0.03){ l -= 0.01; darkened = true; }
  if(darkened) notes.push("Ta couleur principale a été légèrement assombrie pour que le texte blanc reste bien lisible dessus.");
  const P = roundRgb(hslToRgb(h, s, l));
  // variante un peu plus claire (dégradés), sans perdre la lisibilité du blanc
  let lSea = l; while(lSea < l + 0.07 && lSea < 0.5 && contrastRatio(roundRgb(hslToRgb(h, s, lSea + 0.01)), WHITE) >= 4.0) lSea += 0.01;
  const sea = roundRgb(hslToRgb(h, s, lSea)), seaLight = roundRgb(hslToRgb(h, s, Math.min(lSea + 0.07, 0.6)));

  // 2. fond : l'appli est en mode clair
  let [bh, bs, bl] = rgbToHsl(...hexToRgb(inBg));
  if(bl < 0.88){ bl = 0.93; notes.push("Le fond a été éclairci : l'application reste en mode clair pour que les textes restent lisibles."); }
  bs = Math.min(bs, 0.85);
  const sand = roundRgb(hslToRgb(bh, bs, bl));
  const sandDark = roundRgb(hslToRgb(bh, bs * 0.75, Math.max(bl - 0.055, 0.6)));
  const line = roundRgb(hslToRgb(bh, bs * 0.6, Math.max(bl - 0.10, 0.55)));

  // 3. textes : teintés de la couleur principale, toujours lisibles sur le fond
  const ink = roundRgb(hslToRgb(h, Math.min(s, 0.25), 0.15));
  let lSoft = 0.40; const softAt = () => roundRgb(hslToRgb(h, Math.min(s, 0.12), lSoft));
  while(contrastRatio(softAt(), sand) < 4.5 && lSoft > 0.1) lSoft -= 0.01;
  const inkSoft = softAt();

  // 4. teintes claires de la principale (bandeaux, textes posés sur l'en-tête)
  const tint = roundRgb(mixRgb(P, WHITE, 0.92)), tintStrong = roundRgb(mixRgb(P, WHITE, 0.75));
  let tOn = 0.62; while(contrastRatio(roundRgb(mixRgb(P, WHITE, tOn)), P) < 4.5 && tOn < 1) tOn = Math.min(tOn + 0.03, 1);
  const onDarkSoft = roundRgb(mixRgb(P, WHITE, tOn)), onDark = roundRgb(mixRgb(P, WHITE, Math.max(tOn, 0.8)));

  const hex = a => rgbToHex(...a);
  const vars = {
    '--sea-deep': hex(P), '--sea': hex(sea), '--sea-light': hex(seaLight),
    '--sand': hex(sand), '--sand-dark': hex(sandDark), '--line': hex(line),
    '--ink': hex(ink), '--ink-soft': hex(inkSoft),
    '--tint': hex(tint), '--tint-strong': hex(tintStrong), '--on-dark-soft': hex(onDarkSoft),
    // page de réservation des clients
    '--brand': hex(P), '--brand-dark': hex(hslToRgb(h, s, Math.max(l - 0.06, 0.02))), '--brand-tint': hex(mixRgb(P, WHITE, 0.90)),
    '--paper': hex(sand), '--paper-dim': hex(sandDark),
  };
  let rgb = { primary: P, ink, inkSoft, bg: sand, onDark };
  if(isDefault){ Object.assign(vars, THEME_ORIGINAL_VARS); rgb = { primary:[18,59,58], ink:[32,48,46], inkSoft:[90,107,103], bg:[247,241,230], onDark:[200,215,212] }; }
  Object.keys(rgb).forEach(k => { rgb[k] = rgb[k].map(v => Math.round(v)); });
  return { vars, rgb, notes, primary: vars['--sea-deep'], bg: vars['--sand'] };
}

/* ---- application ---- */
function applyThemeVars(vars){
  const root = document.documentElement;
  THEME_VAR_NAMES.forEach(n => root.style.removeProperty(n));    // aucun reliquat d'un ancien thème
  if(vars) Object.keys(vars).forEach(k => root.style.setProperty(k, vars[k]));
  const meta = document.querySelector('meta[name="theme-color"]');
  if(meta) meta.setAttribute('content', (vars && vars['--sea-deep']) || THEME_DEFAULT.primary);
}
function updateThemeRgb(rgb){
  const src = rgb || { primary:[18,59,58], ink:[32,48,46], inkSoft:[90,107,103], bg:[247,241,230], onDark:[200,215,212] };
  Object.keys(src).forEach(k => { THEME_RGB[k] = src[k]; });
}
// valeur réelle d'une variable (pour le canvas, qui ne comprend pas var(--x))
function themeVar(name){
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || THEME_ORIGINAL_VARS[name] || '#000000';
}
let _themeRecord = null, _themeLoadedFor = null;
function applyThemeRecord(rec, code){
  _themeRecord = rec || null;
  if(!rec || !rec.primary || !rec.bg){
    applyThemeVars(null); updateThemeRgb(null);
    try{ localStorage.removeItem(THEME_CACHE_KEY); }catch(e){}
    return null;
  }
  const d = deriveTheme(rec);
  applyThemeVars(d.vars); updateThemeRgb(d.rgb);
  try{ localStorage.setItem(THEME_CACHE_KEY, JSON.stringify({ code: code || null, vars: d.vars, rgb: d.rgb })); }catch(e){}
  return d;
}
// aperçu en direct (écran Apparence) : applique sans enregistrer
function previewTheme(theme){ const d = deriveTheme(theme); applyThemeVars(d.vars); updateThemeRgb(d.rgb); return d; }
function revertThemePreview(){ applyThemeRecord(_themeRecord, RESTAURANT_ID); }
function getSavedThemeRecord(){ return _themeRecord; }

// Lecture du thème : renvoie {ok:true, rec} (rec = null si le restaurant n'en a vraiment pas) ou {ok:false} si la lecture a ÉCHOUÉ
// (réseau, droits Firestore…). Un échec ne doit JAMAIS effacer les couleurs déjà connues.
async function readThemeRecord(code){
  const get = async key => {
    const doc = await db.collection(FS_COLLECTION).doc(encodeKey(key)).get();
    if(!doc.exists) return { exists:false };
    const data = doc.data();
    return { exists:true, value: data && typeof data.value === 'string' ? JSON.parse(data.value) : null };
  };
  let failed = false;
  try{ const r = await get(`reservation:${code}:theme`); if(r.exists) return { ok:true, rec:r.value }; }catch(e){ failed = true; }
  // 2e emplacement : copie du thème dans la fiche publique du restaurant (lisible par la page de réservation)
  try{ const r = await get(`restaurant:${code}`); if(r.exists && r.value && r.value.theme) return { ok:true, rec:r.value.theme }; if(r.exists && !failed) return { ok:true, rec:null }; }catch(e){ failed = true; }
  return failed ? { ok:false } : { ok:true, rec:null };
}
async function loadThemeForCurrentRestaurant(force){
  try{
    if(!RESTAURANT_ID) return;
    if(!force && _themeLoadedFor === RESTAURANT_ID) return;
    const res = await readThemeRecord(RESTAURANT_ID);
    if(!res.ok){ console.warn('Thème : lecture impossible, couleurs actuelles conservées'); return; }
    _themeLoadedFor = RESTAURANT_ID;
    applyThemeRecord(res.rec, RESTAURANT_ID);
  }catch(e){ console.warn('Thème non chargé (couleurs actuelles conservées) :', e); }
}
// theme = {primary, bg, preset} ; null = revenir aux couleurs d'origine
async function saveThemeForRestaurant(theme){
  const rec = theme ? { primary: normHex(theme.primary, THEME_DEFAULT.primary), bg: normHex(theme.bg, THEME_DEFAULT.bg),
                        preset: theme.preset || null, updatedAt: new Date().toISOString() } : null;
  const ok = await setData(`reservation:${RESTAURANT_ID}:theme`, rec, true);
  if(ok){
    applyThemeRecord(rec, RESTAURANT_ID);
    // copie dans la fiche publique du restaurant (meilleure chance d'être lisible par les clients) — sans bloquer si refusé
    try{
      const r = await getData(`restaurant:${RESTAURANT_ID}`, true, null);
      if(r){ if(rec) r.theme = rec; else delete r.theme; await setRestaurantRecord(RESTAURANT_ID, r); }
    }catch(e){}
  }
  return ok;
}
// au chargement : retrouver les couleurs des PDF depuis le cache (les variables CSS, elles, sont déjà posées par le <head>)
(function(){ try{ const c = JSON.parse(localStorage.getItem(THEME_CACHE_KEY) || 'null'); if(c && c.rgb) updateThemeRgb(c.rgb); }catch(e){} })();

/* ---------- confort de saisie + grands écrans / paysage (toutes les pages) ----------
   - champs (dates, montants, textes, listes) plus grands et plus lisibles, partout ;
   - 16 px minimum : évite aussi le zoom automatique d'iOS quand on touche un champ ;
   - en paysage / tablette / ordinateur : contenu plus large au lieu d'une colonne étroite. */
(function(){
  if(document.getElementById('ctr-ui-style')) return;
  const st = document.createElement('style'); st.id = 'ctr-ui-style';
  st.textContent = `
  input:not([type=checkbox]):not([type=radio]):not([type=color]):not([type=range]):not([type=file]):not([type=hidden]),
  select, textarea{
    font-size:17px !important; min-height:48px; padding:11px 14px; line-height:1.3;
    border:1.5px solid var(--line); border-radius:12px; background-color:#fff; color:var(--ink);
    font-family:inherit; max-width:100%; box-sizing:border-box;
  }
  textarea{min-height:96px;}
  input[type=date], input[type=time], input[type=month], input[type=datetime-local]{display:block; min-width:0; text-align:left;}
  input::-webkit-date-and-time-value{text-align:left; min-height:1.3em;}
  .field{min-width:0;}
  .field > input, .field > select, .field > textarea{width:100%; min-width:0;}
  input::placeholder, textarea::placeholder{font-size:16px; color:#8C8F8E;}
  :where(label){font-size:14px; font-weight:600; color:var(--ink-soft);}
  :where(.field){margin-bottom:14px;}
  :where(.field) > label{display:block; margin-bottom:6px;}
  html{padding-left:env(safe-area-inset-left); padding-right:env(safe-area-inset-right);}
  @media (min-width:760px){
    .cards,.badge-hero,.menu-list,.menu-group-label,.dash-card{max-width:720px !important;}
    .cards{display:grid !important; grid-template-columns:1fr 1fr; gap:14px;}
    .auth-card[style*="max-width"], #root div[style*="max-width:360px"]{max-width:720px !important;}
    body:not(.kiosk-mode){padding-left:40px; padding-right:40px;}
    #root{max-width:1200px !important;}
    main{padding-left:28px !important; padding-right:28px !important;}
  }
  @media (max-height:520px) and (orientation:landscape){
    body{justify-content:flex-start;}
  }`;
  document.head.appendChild(st);
})();
