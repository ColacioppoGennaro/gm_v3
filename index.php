<?php
session_start();
require_once __DIR__.'/_core/helpers.php';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>gm_v3 - Assistente AI</title>
<link rel="manifest" href="assets/manifest.webmanifest">
<meta name="theme-color" content="#111827"/>
<style>
:root{--bg:#0f172a;--panel:#111827;--fg:#e5e7eb;--muted:#94a3b8;--accent:#7c3aed;--ok:#10b981;--warn:#f59e0b;--danger:#ef4444;--card:#0b1220}
*{box-sizing:border-box;margin:0;padding:0}
body{font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--fg);min-height:100vh}
.app{display:flex;min-height:100vh}
aside{width:220px;background:#0b1220;border-right:1px solid #1f2937;padding:20px;flex-shrink:0}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:18px;margin-bottom:24px}
.nav a{display:block;padding:12px 16px;border-radius:10px;color:var(--fg);text-decoration:none;margin:6px 0;background:#121a2e;transition:all .2s}
.nav a:hover{background:#1e293b;transform:translateX(4px)}
.nav a.active{background:var(--accent);color:#fff}
main{flex:1;padding:24px 32px;overflow-y:auto}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:20px 0}
.card{background:var(--panel);border:1px solid #1f2937;border-radius:14px;padding:20px}
.card h3{margin-bottom:16px;color:var(--accent)}
.drop{border:2px dashed #374151;border-radius:12px;min-height:180px;display:flex;align-items:center;justify-content:center;margin:16px 0;color:var(--muted);cursor:pointer;transition:all .3s}
.drop:hover{border-color:var(--accent);background:rgba(124,58,237,.05)}
.btn{background:var(--accent);border:none;color:#fff;padding:12px 20px;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;transition:all .2s}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(124,58,237,.4)}
.btn.secondary{background:#374151}
.btn.warn{background:var(--warn)}
.btn.del{background:var(--danger);padding:8px 14px;font-size:13px}
.banner{background:#1f2937;border:1px solid #374151;border-left:4px solid var(--warn);padding:16px;border-radius:10px;color:#fbbf24;margin:20px 0}
.ads{height:90px;background:linear-gradient(135deg,#1f2937,#0b1220);border:1px dashed #374151;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:13px;margin:20px 0}
.hidden{display:none}
.auth-container{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e293b);padding:20px}
.auth-box{background:#0b1220;padding:40px;border-radius:16px;border:1px solid #1f2937;box-shadow:0 20px 60px rgba(0,0,0,.5);max-width:440px;width:100%}
.auth-box h1{font-size:28px;margin-bottom:8px;background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.auth-box p{color:var(--muted);margin-bottom:32px;font-size:14px}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:500;font-size:13px;color:var(--muted)}
input,select{width:100%;padding:12px 16px;border-radius:10px;border:1px solid #374151;background:#0b1220;color:#e5e7eb;font-size:14px;transition:border .2s}
input:focus,select:focus{outline:none;border-color:var(--accent)}
input::placeholder{color:#4b5563}
.btn-group{display:flex;gap:12px;margin-top:24px}
.btn-group .btn{flex:1}
.link-btn{background:none;border:none;color:var(--accent);cursor:pointer;text-decoration:underline;font-size:13px;margin-top:16px;display:inline-block}
.link-btn:hover{color:#a78bfa}
.error{color:var(--danger);font-size:13px;margin-top:8px}
.success{color:var(--ok);font-size:13px;margin-top:8px}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{border-bottom:1px solid #1f2937;padding:12px 8px;text-align:left}
th{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase}
h1{margin-bottom:8px}
</style>
</head>
<body>
<div id="root"></div>
<script type="module">
const S = { 
  user: null, 
  docs: [], 
  events: [],
  view: 'login'
};

const api = (p, fd=null) => fetch(p, {method: fd?'POST':'GET', body: fd}).then(r=>r.json());

function loginView(){
  return `
  <div class="auth-container">
    <div class="auth-box">
      <h1>✨ Bentornato</h1>
      <p>Accedi al tuo assistente AI personale</p>
      <div class="form-group">
        <label>Email</label>
        <input type="email" id="email" placeholder="tua@email.com" autocomplete="email"/>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" id="password" placeholder="••••••••" autocomplete="current-password"/>
      </div>
      <div id="loginError" class="error hidden"></div>
      <div class="btn-group">
        <button class="btn" id="loginBtn">Accedi</button>
      </div>
      <button class="link-btn" id="goRegister">Non hai un account? Registrati</button>
      <button class="link-btn" id="goForgot" style="display:block;margin-top:8px">Password dimenticata?</button>
    </div>
  </div>`;
}

function registerView(){
  return `
  <div class="auth-container">
    <div class="auth-box">
      <h1>🚀 Crea Account</h1>
      <p>Inizia subito a usare il tuo assistente AI</p>
      <div class="form-group">
        <label>Email</label>
        <input type="email" id="regEmail" placeholder="tua@email.com" autocomplete="email"/>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" id="regPass" placeholder="Minimo 6 caratteri" autocomplete="new-password"/>
      </div>
      <div class="form-group">
        <label>Conferma Password</label>
        <input type="password" id="regPassConfirm" placeholder="Ripeti la password" autocomplete="new-password"/>
      </div>
      <div id="regError" class="error hidden"></div>
      <div id="regSuccess" class="success hidden"></div>
      <div class="btn-group">
        <button class="btn secondary" id="backToLogin">Annulla</button>
        <button class="btn" id="registerBtn">Registrati</button>
      </div>
    </div>
  </div>`;
}

function forgotView(){
  return `
  <div class="auth-container">
    <div class="auth-box">
      <h1>🔑 Password Dimenticata</h1>
      <p>Inserisci la tua email, ti invieremo un link per reimpostarla</p>
      <div class="form-group">
        <label>Email</label>
        <input type="email" id="forgotEmail" placeholder="tua@email.com"/>
      </div>
      <div id="forgotError" class="error hidden"></div>
      <div id="forgotSuccess" class="success hidden"></div>
      <div class="btn-group">
        <button class="btn secondary" id="backToLogin2">Annulla</button>
        <button class="btn" id="forgotBtn">Invia Link</button>
      </div>
    </div>
  </div>`;
}

function appView(){
  return `
  <div class="app">
    <aside>
      <div class="logo">✨ <b>gm_v3</b></div>
      <div class="nav">
        <a href="#" data-route="dashboard" class="active">📊 Dashboard</a>
        <a href="#" data-route="chat">💬 Chat AI</a>
        <a href="#" data-route="calendar">📅 Calendario</a>
      </div>
      <button class="btn warn" id="logoutBtn" style="margin-top:24px;width:100%">Logout</button>
    </aside>
    <main>
      <section data-page="dashboard">
        <h1>Dashboard</h1>
        <div class="banner">⚡ Stai usando il piano <b>Free</b>. Upgrade a Pro per funzionalità avanzate.</div>
        <div class="cards">
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Documenti Archiviati</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px"><span id="docCount">0</span> / <span id="docLimit">5</span></div>
          </div>
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Domande AI Oggi</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px"><span id="qCount">0</span> / <span id="qLimit">20</span></div>
          </div>
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Dimensione Max File</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px">10 MB</div>
          </div>
        </div>

        <div class="card">
          <h3>📤 Carica Documento</h3>
          <div class="drop" id="drop">
            <div style="text-align:center">
              <div style="font-size:48px;margin-bottom:8px">📁</div>
              <div>Trascina qui un file o clicca per selezionare</div>
              <div style="font-size:12px;color:#64748b;margin-top:4px">PDF, DOC, DOCX, TXT, CSV, XLSX, JPG, PNG</div>
            </div>
          </div>
          <input type="file" id="file" class="hidden"/>
          <button class="btn" id="uploadBtn" style="width:100%">Carica File</button>
        </div>

        <div class="ads">🎯 [Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>

        <div class="card">
          <h3>📚 I Tuoi Documenti</h3>
          <table id="docsTable">
            <thead><tr><th>Nome File</th><th>Categoria</th><th>Data</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>

      <section class="hidden" data-page="chat">
        <h1>💬 Chat AI</h1>
        <div class="card">
          <h3>Fai una domanda ai tuoi documenti</h3>
          <div style="display:flex;gap:12px;margin-top:16px">
            <input id="q" placeholder="Es: Quando scade l'IMU?" style="flex:1"/>
            <select id="category" style="width:180px"><option value="">(Free: tutti)</option></select>
            <button class="btn" id="askBtn">Chiedi</button>
          </div>
          <div id="chatLog" style="margin-top:24px"></div>
        </div>
      </section>

      <section class="hidden" data-page="calendar">
        <h1>📅 Calendario</h1>
        <div class="card">
          <h3>Aggiungi Evento</h3>
          <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;margin:16px 0">
            <input id="evTitle" placeholder="Titolo evento"/>
            <input id="evStart" type="datetime-local"/>
            <input id="evEnd" type="datetime-local"/>
            <button class="btn" id="addEv">Aggiungi</button>
          </div>
          <table id="evTable">
            <thead><tr><th>Data e Ora</th><th>Titolo</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>`;
}

function render(){
  const views = {
    login: loginView,
    register: registerView,
    forgot: forgotView,
    app: appView
  };
  document.getElementById('root').innerHTML = views[S.view]();
  bind();
}

function route(r){
  document.querySelectorAll('.nav a').forEach(a=>a.classList.toggle('active', a.dataset.route===r));
  document.querySelectorAll('[data-page]').forEach(p=>p.classList.add('hidden'));
  const page = document.querySelector(`[data-page="${r}"]`);
  if(page) page.classList.remove('hidden');
  if(r==='dashboard') loadDocs();
  if(r==='calendar') loadEvents();
}

function bind(){
  if(S.view === 'login'){
    const loginBtn = document.getElementById('loginBtn');
    const goRegister = document.getElementById('goRegister');
    const goForgot = document.getElementById('goForgot');
    
    if(loginBtn) loginBtn.onclick = doLogin;
    if(goRegister) goRegister.onclick = ()=>{S.view='register'; render();};
    if(goForgot) goForgot.onclick = ()=>{S.view='forgot'; render();};
    
    document.getElementById('password')?.addEventListener('keypress', e=>{
      if(e.key==='Enter') doLogin();
    });
  }
  
  if(S.view === 'register'){
    const registerBtn = document.getElementById('registerBtn');
    const backToLogin = document.getElementById('backToLogin');
    
    if(registerBtn) registerBtn.onclick = doRegister;
    if(backToLogin) backToLogin.onclick = ()=>{S.view='login'; render();};
  }
  
  if(S.view === 'forgot'){
    const forgotBtn = document.getElementById('forgotBtn');
    const backToLogin2 = document.getElementById('backToLogin2');
    
    if(forgotBtn) forgotBtn.onclick = doForgot;
    if(backToLogin2) backToLogin2.onclick = ()=>{S.view='login'; render();};
  }
  
  if(S.view === 'app'){
    const logoutBtn = document.getElementById('logoutBtn');
    if(logoutBtn) logoutBtn.onclick = async()=>{ 
      await api('api/auth.php?a=logout'); 
      S.user=null; 
      S.view='login'; 
      render(); 
    };
    
    document.querySelectorAll('.nav a').forEach(a=>{
      a.onclick=(e)=>{e.preventDefault(); route(a.dataset.route);};
    });
    
    const drop = document.getElementById('drop');
    const file = document.getElementById('file');
    if(drop && file){
      drop.onclick=()=>file.click();
      drop.ondragover=e=>{e.preventDefault(); drop.style.borderColor='var(--accent)';};
      drop.ondragleave=()=>drop.style.borderColor='#374151';
      drop.ondrop=e=>{e.preventDefault(); file.files=e.dataTransfer.files; uploadFile();};
    }
    
    const uploadBtn = document.getElementById('uploadBtn');
    const askBtn = document.getElementById('askBtn');
    const addEv = document.getElementById('addEv');
    
    if(uploadBtn) uploadBtn.onclick=uploadFile;
    if(askBtn) askBtn.onclick=ask;
    if(addEv) addEv.onclick=createEvent;
    
    loadDocs();
  }
}

async function doLogin(){
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const err = document.getElementById('loginError');
  
  err.classList.add('hidden');
  
  if(!email || !password){
    err.textContent = 'Inserisci email e password';
    err.classList.remove('hidden');
    return;
  }
  
  const fd = new FormData();
  fd.append('email', email);
  fd.append('password', password);
  
  const r = await api('api/auth.php?a=login', fd);
  
  if(r.success){
    S.user = {email, role:'free'};
    S.view = 'app';
    render();
  } else {
    err.textContent = r.message || 'Errore durante il login';
    err.classList.remove('hidden');
  }
}

async function doRegister(){
  const email = document.getElementById('regEmail').value;
  const pass = document.getElementById('regPass').value;
  const passConfirm = document.getElementById('regPassConfirm').value;
  const err = document.getElementById('regError');
  const success = document.getElementById('regSuccess');
  
  err.classList.add('hidden');
  success.classList.add('hidden');
  
  if(!email || !pass || !passConfirm){
    err.textContent = 'Compila tutti i campi';
    err.classList.remove('hidden');
    return;
  }
  
  if(pass !== passConfirm){
    err.textContent = 'Le password non coincidono';
    err.classList.remove('hidden');
    return;
  }
  
  if(pass.length < 6){
    err.textContent = 'La password deve essere di almeno 6 caratteri';
    err.classList.remove('hidden');
    return;
  }
  
  const fd = new FormData();
  fd.append('email', email);
  fd.append('password', pass);
  
  const r = await api('api/auth.php?a=register', fd);
  
  if(r.success){
    success.textContent = '✓ Registrazione completata! Ora puoi accedere.';
    success.classList.remove('hidden');
    setTimeout(()=>{S.view='login'; render();}, 2000);
  } else {
    err.textContent = r.message || 'Errore durante la registrazione';
    err.classList.remove('hidden');
  }
}

async function doForgot(){
  const email = document.getElementById('forgotEmail').value;
  const err = document.getElementById('forgotError');
  const success = document.getElementById('forgotSuccess');
  
  err.classList.add('hidden');
  success.classList.add('hidden');
  
  if(!email){
    err.textContent = 'Inserisci la tua email';
    err.classList.remove('hidden');
    return;
  }
  
  success.textContent = '✓ Se l\'email esiste, riceverai un link per reimpostare la password.';
  success.classList.remove('hidden');
}

async function loadDocs(){
  const r = await api('api/documents.php?a=list');
  if(!r.success) return;
  
  const docCount = document.getElementById('docCount');
  const tb = document.querySelector('#docsTable tbody');
  
  if(docCount) docCount.textContent = r.data.length;
  if(tb){
    tb.innerHTML = r.data.map(d=>`
      <tr>
        <td>${d.file_name}</td>
        <td>${d.label||'-'}</td>
        <td>${new Date(d.created_at).toLocaleString('it-IT')}</td>
        <td><button class='btn del' data-id='${d.id}'>Elimina</button></td>
      </tr>
    `).join('');
    tb.querySelectorAll('button[data-id]').forEach(b=>b.onclick=()=>delDoc(b.dataset.id));
  }
}

async function uploadFile(){
  const file = document.getElementById('file');
  const f = file.files[0];
  if(!f) return alert('Seleziona un file');
  
  const fd = new FormData();
  fd.append('file', f);
  const r = await api('api/documents.php?a=upload', fd);
  
  if(r.success){
    loadDocs();
    file.value = '';
  } else {
    alert(r.message || 'Errore durante l\'upload');
  }
}

async function delDoc(id){
  if(!confirm('Eliminare questo documento?')) return;
  const fd = new FormData();
  fd.append('id', id);
  const r = await api('api/documents.php?a=delete', fd);
  if(r.success) loadDocs();
}

async function ask(){
  const q = document.getElementById('q');
  const category = document.getElementById('category');
  const fd = new FormData();
  fd.append('q', q.value);
  fd.append('category', category.value);
  
  const r = await api('api/chat.php', fd);
  const log = document.getElementById('chatLog');
  
  if(r.success){
    const badge = r.source==='docs'?'📄':'🤖';
    const item = document.createElement('div');
    item.style.cssText = 'background:#1f2937;padding:16px;border-radius:10px;margin-bottom:12px;border-left:4px solid var(--accent)';
    item.innerHTML = `<div style="font-weight:600;margin-bottom:8px">${badge} ${r.source==='docs'?'Risposta dai documenti':'Risposta AI'}</div><div>${r.answer}</div>`;
    log.insertBefore(item, log.firstChild);
    q.value = '';
  } else {
    alert(r.message || 'Errore');
  }
}

async function loadEvents(){
  const r = await api('api/calendar.php?a=list');
  if(!r.success) return;
  
  const tb = document.querySelector('#evTable tbody');
  if(tb){
    tb.innerHTML = r.data.map(e=>`
      <tr>
        <td>${new Date(e.start).toLocaleString('it-IT')}</td>
        <td>${e.title}</td>
        <td><button class='btn del' data-id='${e.id}'>Elimina</button></td>
      </tr>
    `).join('');
    tb.querySelectorAll('button[data-id]').forEach(b=>b.onclick=()=>delEvent(b.dataset.id));
  }
}

async function createEvent(){
  const evTitle = document.getElementById('evTitle');
  const evStart = document.getElementById('evStart');
  const evEnd = document.getElementById('evEnd');
  
  if(!evTitle.value || !evStart.value){
    alert('Inserisci almeno titolo e data inizio');
    return;
  }
  
  const fd = new FormData();
  fd.append('title', evTitle.value);
  fd.append('starts_at', evStart.value);
  fd.append('ends_at', evEnd.value);
  
  const r = await api('api/calendar.php?a=create', fd);
  
  if(r.success){
    loadEvents();
    evTitle.value = '';
    evStart.value = '';
    evEnd.value = '';
  } else {
    alert(r.message || 'Errore');
  }
}

async function delEvent(id){
  if(!confirm('Eliminare questo evento?')) return;
  const fd = new FormData();
  fd.append('id', id);
  const r = await api('api/calendar.php?a=delete', fd);
  if(r.success) loadEvents();
}

render();
if('serviceWorker' in navigator) navigator.serviceWorker.register('assets/service-worker.js');
</script>
</body>
</html>
