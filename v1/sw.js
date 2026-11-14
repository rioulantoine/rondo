/* =====================================================================
   Check ton resto — service worker
   ---------------------------------------------------------------------
   Rôle : garder les fichiers de l'appli sur l'appareil pour qu'elle
   s'ouvre instantanément, même avec un réseau lent ou capricieux.

   - Fichiers de l'appli (pages, shared.js, icônes) : servis depuis le
     cache d'abord (ouverture immédiate). En arrière-plan, on vérifie
     auprès du serveur s'il existe une nouvelle version ; si oui, on la
     met en cache d'un seul bloc (pour ne jamais mélanger deux versions)
     et on prévient la page, qui affiche "Nouvelle version disponible".
   - Bibliothèques externes (Firebase, jsPDF, EmailJS, polices Google) :
     versionnées par leur adresse, donc gardées en cache durablement.
   - Données (Firestore, authentification, e-mails) : JAMAIS interceptées.
     Elles passent par le réseau comme avant (Firestore a son propre
     cache local, cf. shared.js).

   Changer VERSION uniquement si ce fichier est modifié de façon à exiger
   de repartir de zéro : les anciens caches sont alors supprimés.
   ===================================================================== */
const VERSION = 'v1';
const CACHE_OWN = 'ctr-own-' + VERSION;
const CACHE_CDN = 'ctr-cdn-' + VERSION;

const SCOPE = self.registration.scope;
const abs = p => new URL(p, SCOPE).href;

// Indispensables : si l'un d'eux manque sur le serveur, on n'installe pas (l'appli reste simplement
// comme avant, sans cache) plutôt que de risquer un cache incomplet.
const CRITICAL = [
  'index.html', 'check-ta-cuisine.html', 'check-ta-salle.html', 'check-ton-bar.html',
  'planning.html', 'reservation.html', 'shared.js',
].map(abs);
// Utiles mais non bloquants.
const OPTIONAL = ['manifest.webmanifest', 'icon-192.png', 'icon-512.png', 'icon-512-maskable.png', 'apple-touch-icon.png'].map(abs);

const CDN_URLS = [
  'https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js',
  'https://www.gstatic.com/firebasejs/10.13.0/firebase-firestore-compat.js',
  'https://www.gstatic.com/firebasejs/10.13.0/firebase-auth-compat.js',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js',
  'https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js',
];
const FONT_CSS_URLS = [
  'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap',
  'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap',
];
// Hôtes externes qu'on a le droit de mettre en cache (et rien d'autre).
function isCacheableCdn(url){
  const h = url.hostname;
  if(h === 'www.gstatic.com') return url.pathname.startsWith('/firebasejs/');
  return h === 'fonts.googleapis.com' || h === 'fonts.gstatic.com' ||
         h === 'cdnjs.cloudflare.com' || h === 'cdn.jsdelivr.net';
}

/* ---------- utilitaires ---------- */
// Une réponse "redirigée" ne peut pas servir une navigation : on en refait une copie propre.
async function clean(resp){
  if(!resp.redirected) return resp;
  const body = await resp.blob();
  return new Response(body, { status: resp.status, statusText: resp.statusText, headers: resp.headers });
}
async function fetchFresh(url){
  const r = await fetch(new Request(url, { cache: 'reload' }));
  if(!r.ok) throw new Error('HTTP ' + r.status + ' ' + url);
  return clean(r);
}
async function fetchAndPut(cache, url){
  const r = await fetch(url, { mode: 'cors' });
  if(!r.ok) throw new Error('HTTP ' + r.status + ' ' + url);
  await cache.put(url, r);
}
async function signature(r){
  return r.headers.get('etag') || r.headers.get('last-modified') ||
         ('len:' + (await r.clone().arrayBuffer()).byteLength);
}

async function precacheFonts(cache){
  for(const cssUrl of FONT_CSS_URLS){
    try{
      const r = await fetch(cssUrl, { mode: 'cors' });
      if(!r.ok) continue;
      const copy = r.clone();
      await cache.put(cssUrl, r);
      const css = await copy.text();
      const files = [...css.matchAll(/url\((https:[^)]+)\)/g)].map(m => m[1]);
      await Promise.allSettled(files.map(u => fetchAndPut(cache, u)));
    }catch(e){ /* polices : au mieux, sans bloquer l'installation */ }
  }
}

/* ---------- installation ---------- */
self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const own = await caches.open(CACHE_OWN);
    await Promise.all(CRITICAL.map(async u => own.put(u, await fetchFresh(u))));
    await Promise.allSettled(OPTIONAL.map(async u => own.put(u, await fetchFresh(u))));
    const cdn = await caches.open(CACHE_CDN);
    await Promise.allSettled(CDN_URLS.map(u => fetchAndPut(cdn, u)));
    await precacheFonts(cdn);
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys
      .filter(k => k.startsWith('ctr-') && k !== CACHE_OWN && k !== CACHE_CDN)
      .map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

/* ---------- vérification de nouvelle version (toutes les 5 min au plus) ---------- */
let lastCheck = 0;
async function checkForUpdate(){
  const now = Date.now();
  if(now - lastCheck < 5 * 60 * 1000) return;
  lastCheck = now;
  try{
    const cache = await caches.open(CACHE_OWN);
    const fresh = [];
    let changed = false;
    await Promise.all(CRITICAL.map(async u => {
      const r = await fetch(new Request(u, { cache: 'no-cache' }));
      if(!r.ok) throw new Error('HTTP ' + r.status);
      const old = await cache.match(u, { ignoreVary: true });
      if(!old || (await signature(old)) !== (await signature(r))) changed = true;
      fresh.push([u, r]);
    }));
    if(!changed) return;
    // Toutes les pages ont répondu : on remplace le lot d'un coup, jamais fichier par fichier.
    for(const [u, r] of fresh) await cache.put(u, await clean(r));
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(c => c.postMessage({ type: 'APP_UPDATED' }));
  }catch(e){ /* hors ligne ou serveur indisponible : on garde la version en cache */ }
}

// La page demande une vérification quand l'appli repasse au premier plan (une appli installée reste en
// mémoire : à la réouverture il n'y a pas de "navigation", donc rien ne déclencherait la vérification).
self.addEventListener('message', event => {
  if(event.data && event.data.type === 'CHECK_UPDATE') event.waitUntil(checkForUpdate());
});

/* ---------- interception des requêtes ---------- */
self.addEventListener('fetch', event => {
  const req = event.request;
  if(req.method !== 'GET') return;
  const url = new URL(req.url);
  if(url.origin === self.location.origin){
    if(url.pathname.endsWith('/sw.js')) return;
    event.respondWith(handleOwn(event, req, url));
  }else if(isCacheableCdn(url)){
    event.respondWith(handleCdn(req));
  }
  // tout le reste (Firestore, authentification, e-mails…) : réseau direct, sans intervention
});

async function handleOwn(event, req, url){
  const cache = await caches.open(CACHE_OWN);
  let hit = await cache.match(req, { ignoreSearch: true, ignoreVary: true });
  if(!hit && req.mode === 'navigate'){
    if(url.pathname.endsWith('/')) hit = await cache.match(abs('index.html'), { ignoreVary: true });
    else if(!/\.[a-z0-9]+$/i.test(url.pathname)) hit = await cache.match(abs(url.pathname.replace(/^\//, '') + '.html'), { ignoreVary: true });
  }
  if(hit){
    if(req.mode === 'navigate') event.waitUntil(checkForUpdate());
    return hit;
  }
  try{
    const net = await fetch(req);
    if(net.ok && net.type === 'basic' && req.mode !== 'navigate') cache.put(req, net.clone()).catch(() => {});
    return net;
  }catch(e){
    if(req.mode === 'navigate'){
      const home = await cache.match(abs('index.html'), { ignoreVary: true });
      if(home) return home;
    }
    throw e;
  }
}

async function handleCdn(req){
  const cache = await caches.open(CACHE_CDN);
  const hit = await cache.match(req, { ignoreVary: true });
  if(hit) return hit;
  const net = await fetch(req);
  if(net.ok || net.type === 'opaque') cache.put(req, net.clone()).catch(() => {});
  return net;
}
