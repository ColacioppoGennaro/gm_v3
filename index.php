
<?php require_once __DIR__.'/_core/helpers.php'; ?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>gm_v3</title>
<link rel="manifest" href="assets/manifest.webmanifest"><meta name="theme-color" content="#111827"/>
<style>
:root{--bg:#0f172a;--panel:#111827;--fg:#e5e7eb;--muted:#94a3b8;--accent:#7c3aed;--ok:#10b981;--warn:#f59e0b;--danger:#ef4444;--card:#0b1220}
*{box-sizing:border-box}body{margin:0;font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--fg)}
.app{display:flex;min-height:100vh}aside{width:200px;background:#0b1220;border-right:1px solid #1f2937;padding:16px}
.logo{display:flex;align-items:center;gap:8px;font-weight:700}.nav a{display:block;padding:10px;border-radius:8px;color:var(--fg);text-decoration:none;margin:6px 0;background:#121a2e}.nav a.active{background:#1e293b}
main{flex:1;padding:20px 24px}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:var(--panel);border:1px solid #1f2937;border-radius:12px;padding:16px}
.drop{border:2px dashed #374151;border-radius:12px;height:180px;display:flex;align-items:center;justify-content:center;margin:12px 0;color:var(--muted)}
.btn{background:var(--accent);border:none;color:#fff;padding:10px 14px;border-radius:8px;cursor:pointer}.btn.warn{background:var(--warn)}.btn.del{background:var(--danger)}
.banner{background:#1f2937;border:1px solid #374151;border-left:4px solid var(--warn);padding:12px;border-radius:8px;color:#fbbf24;margin:10px 0}
.ads{height:60px;background:#0b1220;border:1px dashed #374151;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:12px}
.hidden{display:none}.login{max-width:360px;margin:10vh auto;background:#0b1220;padding:20px;border-radius:12px;border:1px solid #1f2937}
input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0b1220;color:#e5e7eb}h1{margin:0 0 8px 0}
table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #1f2937;padding:8px;text-align:left}
</style></head><body>
<div id="root"></div>
<script type="module">
// Very small SPA without build
const S = { user:null, docs:[], events:[] };
const api = (p, fd=null)=> fetch(p, {method: fd?'POST':'GET', body: fd}).then(r=>r.json());

function ui(){
  if(!S.user){ return login(); }
  return `
  <div class="app">
    <aside>
      <div class="logo">✨ <b>gm_v3</b></div>
      <div class="nav">
        <a href="#" data-route="dashboard" class="active">Dashboard</a>
        <a href="#" data-route="chat">Chat AI</a>
        <a href="#" data-route="calendar">Calendario</a>
      </div>
      <div style="margin-top:16px"><button class="btn warn" id="logoutBtn">Logout</button></div>
    </aside>
    <main>
      <h1>Dashboard</h1>
      <div class="banner">Stai usando il piano Free. Pubblicità di esempio. Passa a Pro per rimuoverla.</div>
      <div class="cards">
        <div class="card"><div>Documenti Archiviati</div><div><b id="docCount">0</b> / <span id="docLimit">5</span></div></div>
        <div class="card"><div>Domande AI oggi</div><div><b id="qCount">0</b> / <span id="qLimit">20</span></div></div>
        <div class="card"><div>Dimensione max. File</div><div><b>10 MB</b></div></div>
      </div>

      <section data-page="dashboard">
        <div class="card">
          <h3>Carica un nuovo documento</h3>
          <div class="drop" id="drop">Trascina qui o seleziona</div>
          <input type="file" id="file" class="hidden"/>
          <button class="btn" id="uploadBtn">Carica File</button>
        </div>
        <div class="ads">[Slot pubblicitario]</div>
        <div class="card">
          <h3>I tuoi documenti</h3>
          <table id="docsTable"><thead><tr><th>Nome</th><th>Label</th><th>Data</th><th></th></tr></thead><tbody></tbody></table>
        </div>
      </section>

      <section class="hidden" data-page="chat">
        <div class="card">
          <h3>Chat AI</h3>
          <div style="display:flex;gap:8px">
            <input id="q" placeholder="Fai una domanda ai tuoi documenti..."/>
            <select id="category"><option value="">(Free: tutti)</option></select>
            <button class="btn" id="askBtn">Chiedi</button>
          </div>
          <div id="chatLog"></div>
        </div>
      </section>

      <section class="hidden" data-page="calendar">
        <div class="card">
          <h3>Calendario</h3>
          <div style="display:flex;gap:8px;margin:8px 0">
            <input id="evTitle" placeholder="Titolo"/>
            <input id="evStart" type="datetime-local"/>
            <input id="evEnd" type="datetime-local"/>
            <button class="btn" id="addEv">Aggiungi</button>
          </div>
          <table id="evTable"><thead><tr><th>Quando</th><th>Titolo</th><th></th></tr></thead><tbody></tbody></table>
        </div>
      </section>
    </main>
  </div>`;
}

function login(){
  return `<div class="login">
    <h2>Accedi</h2>
    <input id="email" placeholder="Email"/>
    <input id="password" type="password" placeholder="Password"/>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn" id="loginBtn">Login</button>
      <button class="btn warn" id="regBtn">Registrati</button>
    </div>
  </div>`;
}

function mount(html){ document.getElementById('root').innerHTML=html; bind(); }
function route(r){
  document.querySelectorAll('.nav a').forEach(a=>a.classList.toggle('active', a.dataset.route===r));
  document.querySelectorAll('[data-page]').forEach(p=>p.classList.add('hidden'));
  document.querySelector(`[data-page="${r}"]`).classList.remove('hidden');
  if(r==='dashboard') loadDocs(); if(r==='calendar') loadEvents();
}

async function bind(){
  if(!S.user){
    document.getElementById('loginBtn').onclick=async()=>{
      const fd=new FormData(); fd.append('email',email.value); fd.append('password',password.value);
      const r=await api('api/auth.php?a=login',fd); if(r.success){ S.user={email:email.value,role:'free'}; mount(ui()); } else alert(r.message||'Errore');
    };
    document.getElementById('regBtn').onclick=async()=>{
      const fd=new FormData(); fd.append('email',email.value); fd.append('password',password.value);
      const r=await api('api/auth.php?a=register',fd); if(r.success){ alert('Registrato! Ora accedi.'); } else alert(r.message||'Errore'); };
    return;
  }
  document.getElementById('logoutBtn').onclick=async()=>{ await api('api/auth.php?a=logout'); S.user=null; mount(ui()); };
  document.querySelectorAll('.nav a').forEach(a=>a.onclick=(e)=>{e.preventDefault(); route(a.dataset.route);});
  document.getElementById('drop').onclick=()=>document.getElementById('file').click();
  document.getElementById('uploadBtn').onclick=uploadFile;
  document.getElementById('askBtn').onclick=ask;
  document.getElementById('addEv').onclick=createEvent;
  loadDocs();
}

async function loadDocs(){
  const r=await api('api/documents.php?a=list'); if(!r.success) return;
  const tb=document.querySelector('#docsTable tbody'); document.getElementById('docCount').textContent=r.data.length;
  tb.innerHTML=r.data.map(d=>`<tr><td>${d.file_name}</td><td>${d.label||'-'}</td><td>${new Date(d.created_at).toLocaleString()}</td><td><button class='btn del' data-id='${d.id}'>Elimina</button></td></tr>`).join('');
  tb.querySelectorAll('button[data-id]').forEach(b=>b.onclick=()=>delDoc(b.dataset.id));
}

async function uploadFile(){
  const f=document.getElementById('file').files[0]; if(!f) return alert('Seleziona un file');
  const fd=new FormData(); fd.append('file',f); const r=await api('api/documents.php?a=upload',fd);
  if(r.success) loadDocs(); else alert(r.message||'Errore');
}
async function delDoc(id){ const fd=new FormData(); fd.append('id',id); const r=await api('api/documents.php?a=delete',fd); if(r.success) loadDocs(); }

async function ask(){
  const fd=new FormData(); fd.append('q', document.getElementById('q').value); fd.append('category', document.getElementById('category').value);
  const r=await api('api/chat.php',fd); const log=document.getElementById('chatLog');
  if(r.success){ const badge=r.source==='docs'?'📄':'🤖'; log.innerHTML=`<div>${badge} ${r.answer}</div>`+log.innerHTML; } else alert(r.message||'Errore');
}

async function loadEvents(){
  const r=await api('api/calendar.php?a=list'); if(!r.success) return;
  const tb=document.querySelector('#evTable tbody');
  tb.innerHTML=r.data.map(e=>`<tr><td>${new Date(e.start).toLocaleString()}</td><td>${e.title}</td><td><button class='btn del' data-id='${e.id}'>Elimina</button></td></tr>`).join('');
  tb.querySelectorAll('button[data-id]').forEach(b=>b.onclick=()=>delEvent(b.dataset.id));
}
async function createEvent(){ const fd=new FormData(); fd.append('title',evTitle.value); fd.append('starts_at',evStart.value); fd.append('ends_at',evEnd.value); const r=await api('api/calendar.php?a=create',fd); if(r.success) loadEvents(); }
async function delEvent(id){ const fd=new FormData(); fd.append('id',id); const r=await api('api/calendar.php?a=delete',fd); if(r.success) loadEvents(); }

mount(ui());
if('serviceWorker' in navigator) navigator.serviceWorker.register('assets/service-worker.js');
</script>
</body></html>
