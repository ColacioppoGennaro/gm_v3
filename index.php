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
.btn.small{padding:6px 12px;font-size:12px}
.banner{background:#1f2937;border:1px solid #374151;border-left:4px solid var(--warn);padding:16px;border-radius:10px;color:#fbbf24;margin:20px 0;cursor:pointer;transition:all .2s}
.banner:hover{background:#252f3f;transform:translateX(4px)}
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
.modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-content{background:#0b1220;padding:32px;border-radius:16px;border:1px solid #1f2937;max-width:700px;width:100%;max-height:90vh;overflow-y:auto}
.badge-pro{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;display:inline-block;margin-left:8px}
.badge-category{background:#1f2937;color:var(--accent);padding:4px 8px;border-radius:6px;font-size:11px;font-weight:600;display:inline-block}
.loader{display:inline-block;width:14px;height:14px;border:2px solid #374151;border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
.category-tag{background:#0b1220;border:1px solid #374151;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:8px;font-size:13px;margin:4px}
.category-tag button{background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font-size:14px}
.category-tag button:hover{color:#ff6b6b}
.organize-item{background:#1f2937;padding:12px;border-radius:8px;margin:8px 0;display:flex;align-items:center;gap:12px}
.organize-item .filename{flex:1;font-size:13px;color:var(--fg)}
.organize-item select{width:200px;font-size:13px;padding:8px}
.organize-item input{width:200px;font-size:13px;padding:8px}
.filter-bar{background:#1f2937;padding:12px 16px;border-radius:10px;margin:16px 0;display:flex;gap:12px;align-items:center}
.filter-bar label{font-size:13px;color:var(--muted)}
.filter-bar select{width:auto;min-width:200px;padding:8px 12px;font-size:13px}
.category-select-cell{min-width:150px}
.category-select-cell select{width:100%;padding:6px 10px;font-size:13px}
.new-category-box{background:#0b1220;border:2px dashed #7c3aed;padding:16px;border-radius:10px;margin:16px 0}
.new-category-box h4{color:var(--accent);margin-bottom:12px;font-size:14px}
</style>
</head>
<body>
<div id="root"></div>
<script type="module">
const S = { 
  user: null, 
  docs: [], 
  events: [],
  categories: [],
  view: 'login',
  stats: {chatToday: 0, totalSize: 0},
  filterCategory: ''
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
  const isPro = S.user && S.user.role === 'pro';
  const maxDocs = isPro ? 200 : 5;
  const maxChat = isPro ? 200 : 20;
  const maxSize = isPro ? 150 : 50;
  
  return `
  <div class="app">
    <aside>
      <div class="logo">✨ <b>gm_v3</b> ${isPro ? '<span class="badge-pro">PRO</span>' : ''}</div>
      <div class="nav">
        <a href="#" data-route="dashboard" class="active">📊 Dashboard</a>
        <a href="#" data-route="chat">💬 Chat AI</a>
        <a href="#" data-route="calendar">📅 Calendario</a>
        <a href="#" data-route="account">👤 Account</a>
      </div>
    </aside>
    <main>
      <section data-page="dashboard">
        <h1>Dashboard</h1>
        ${!isPro ? '<div class="banner" id="upgradeBtn">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
        <div class="cards">
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Documenti Archiviati</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px"><span id="docCount">0</span> / ${maxDocs}</div>
          </div>
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Domande AI Oggi</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px"><span id="qCount">0</span> / ${maxChat}</div>
          </div>
          <div class="card">
            <div style="color:var(--muted);font-size:12px">Storage Usato</div>
            <div style="font-size:28px;font-weight:700;margin-top:8px"><span id="storageUsed">0</span> MB / ${maxSize} MB</div>
          </div>
        </div>

        <div class="card">
          <h3>📤 Carica Documento</h3>
          
          ${isPro ? `
          <div style="background:#1f2937;padding:16px;border-radius:10px;margin-bottom:16px">
            <h4 style="margin:0 0 12px 0;font-size:14px;color:var(--accent)">🏷️ Le tue categorie</h4>
            <div id="categoriesList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;min-height:32px;align-items:center"></div>
            <div style="display:flex;gap:8px">
              <input id="newCategoryName" placeholder="Nome nuova categoria (es. Fatture)" style="flex:1"/>
              <button class="btn" id="addCategoryBtn">+ Crea</button>
            </div>
          </div>
          <div class="form-group">
            <label>Categoria documento *</label>
            <select id="uploadCategory" style="width:100%">
              <option value="">-- Seleziona una categoria --</option>
            </select>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Devi scegliere una categoria prima di caricare</div>
          </div>
          ` : ''}
          
          <div class="drop" id="drop">
            <div style="text-align:center">
              <div style="font-size:48px;margin-bottom:8px">📁</div>
              <div>Trascina qui un file o clicca per selezionare</div>
              <div style="font-size:12px;color:#64748b;margin-top:4px">PDF, DOC, DOCX, TXT, CSV, XLSX, JPG, PNG (Max ${maxSize}MB)</div>
            </div>
          </div>
          <input type="file" id="file" class="hidden"/>
          <button class="btn" id="uploadBtn" style="width:100%">Carica File</button>
        </div>

        ${!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : ''}

        <div class="card">
          <h3>📚 I Tuoi Documenti</h3>
          
          ${isPro ? `
          <div class="filter-bar">
            <label>Filtra per categoria:</label>
            <select id="filterCategory">
              <option value="">Tutte le categorie</option>
            </select>
            <button class="btn secondary" id="organizeDocsBtn">🔧 Organizza Documenti</button>
          </div>
          ` : ''}
          
          <table id="docsTable">
            <thead>
              <tr>
                <th>Nome File</th>
                ${isPro ? '<th>Categoria</th>' : ''}
                <th>Dimensione</th>
                <th>Data</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>

      <section class="hidden" data-page="chat">
        <h1>Chat AI</h1>
        ${!isPro ? '<div class="banner" id="upgradeBtn2">Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
        ${!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : ''}
        <div class="card">
          <h3>Fai una domanda ai tuoi documenti</h3>
          
          <div style="background:#1f2937;padding:12px;border-radius:8px;margin-bottom:12px">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <label style="font-size:13px;color:var(--muted)">Aderenza:</label>
              <select id="adherence" style="width:auto;padding:6px 10px;font-size:13px">
                <option value="strict">Strettamente documenti</option>
                <option value="high">Alta aderenza</option>
                <option value="balanced" selected>Bilanciata</option>
                <option value="low">Bassa aderenza</option>
                <option value="free">Libera interpretazione</option>
              </select>
              
              <label style="margin-left:16px">
                <input type="checkbox" id="showRefs" checked style="width:auto;margin-right:4px"/>
                <span style="font-size:13px;color:var(--muted)">Mostra riferimenti pagine</span>
              </label>
            </div>
          </div>
          
          <div style="display:flex;gap:12px;margin-top:16px">
            <input id="q" placeholder="Es: Quando scade l'IMU?" style="flex:1"/>
            ${isPro ? `<select id="category" style="width:200px"><option value="">-- Seleziona categoria --</option></select>` : '<select id="category" style="width:180px"><option value="">(Free: tutti)</option></select>'}
            <button class="btn" id="askBtn">Chiedi ai documenti</button>
          </div>
          <div style="margin-top:8px;font-size:12px;color:var(--muted)">Domande oggi: <b id="qCountChat">0</b>/${maxChat}</div>
          <div id="chatLog" style="margin-top:24px"></div>
        </div>
      </section>

      <section class="hidden" data-page="calendar">
        <h1>📅 Calendario</h1>
        ${!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
        ${!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : ''}
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

      <section class="hidden" data-page="account">
        <h1>👤 Account</h1>
        
        <div class="cards">
          <div class="card">
            <h3>Piano Attuale</h3>
            <div style="font-size:32px;font-weight:700;margin:16px 0;color:var(--accent)">${isPro ? 'PRO' : 'FREE'}</div>
            <div style="color:var(--muted);font-size:13px">Email: <b id="accountEmail">...</b></div>
            <div style="color:var(--muted);font-size:13px;margin-top:4px">Membro da: <b id="accountSince">...</b></div>
          </div>
          
          <div class="card">
            <h3>Utilizzo</h3>
            <div style="font-size:13px;margin:8px 0">Documenti: <b id="usageDocs">0</b> / ${isPro ? '200' : '5'}</div>
            <div style="font-size:13px;margin:8px 0">Storage: <b id="usageStorage">0</b> MB / ${isPro ? '150' : '50'} MB</div>
            <div style="font-size:13px;margin:8px 0">Chat oggi: <b id="usageChat">0</b> / ${isPro ? '200' : '20'}</div>
            ${isPro ? '<div style="font-size:13px;margin:8px 0">Categorie: <b id="usageCategories">0</b></div>' : ''}
          </div>
        </div>

        ${!isPro ? `
        <div class="card">
          <h3>⚡ Upgrade a Pro</h3>
          <p style="color:var(--muted);margin-bottom:16px">Sblocca funzionalità avanzate e limiti aumentati</p>
          <div class="form-group">
            <label>Codice Promozionale</label>
            <input type="text" id="promoCodePage" placeholder="Inserisci codice"/>
          </div>
          <div id="upgradePageError" class="error hidden"></div>
          <div id="upgradePageSuccess" class="success hidden"></div>
          <button class="btn" id="activateProPage">Attiva Pro</button>
        </div>
        ` : `
        <div class="card">
          <h3>⬇️ Downgrade a Free</h3>
          <p style="color:var(--muted);margin-bottom:16px">Torna al piano gratuito. <b>ATTENZIONE:</b> Devi avere massimo 5 documenti.</p>
          <div id="downgradeError" class="error hidden"></div>
          <button class="btn warn" id="downgradeBtn">Downgrade a Free</button>
        </div>
        `}

        <div class="card">
          <h3>⚙️ Impostazioni</h3>
          <button class="btn secondary" id="logoutBtn" style="width:100%;margin-bottom:12px">🚪 Logout</button>
          <button class="btn del" id="deleteAccountBtn" style="width:100%">🗑️ Elimina Account</button>
        </div>
      </section>
    </main>
  </div>`;
}

function upgradeModal(){
  return `
  <div class="modal" id="upgradeModal">
    <div class="modal-content">
      <h2 style="margin-bottom:16px">🚀 Upgrade a Pro</h2>
      <p style="margin-bottom:24px;color:var(--muted)">Inserisci il codice promozionale per attivare il piano Pro</p>
      <div class="form-group">
        <label>Codice Promozionale</label>
        <input type="text" id="promoCode" placeholder="Inserisci codice"/>
      </div>
      <div id="upgradeError" class="error hidden"></div>
      <div id="upgradeSuccess" class="success hidden"></div>
      <div class="btn-group">
        <button class="btn secondary" id="closeModal">Annulla</button>
        <button class="btn" id="activateBtn">Attiva Pro</button>
      </div>
    </div>
  </div>`;
}

function organizeDocsModal(){
  const masterDocs = S.docs.filter(d => d.category === 'master');
  
  if (masterDocs.length === 0) {
    return `
    <div class="modal" id="organizeModal">
      <div class="modal-content">
        <h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>
        <p style="color:var(--ok);margin-bottom:16px">✓ Tutti i documenti sono già organizzati in categorie!</p>
        <button class="btn" onclick="document.getElementById('organizeModal').remove()">Chiudi</button>
      </div>
    </div>`;
  }
  
  return `
  <div class="modal" id="organizeModal">
    <div class="modal-content">
      <h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>
      <p style="color:var(--muted);margin-bottom:16px">Hai ${masterDocs.length} documento/i senza categoria specifica. Assegna una categoria a ciascuno:</p>
      
      <div class="new-category-box">
        <h4>➕ Crea Nuova Categoria</h4>
        <div style="display:flex;gap:8px">
          <input id="modalNewCategoryName" placeholder="Nome categoria (es. Lavoro, Fatture...)" style="flex:1"/>
          <button class="btn small" id="modalAddCategoryBtn">Crea</button>
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--muted)">Categorie esistenti: ${S.categories.length > 0 ? S.categories.map(c => c.name).join(', ') : 'nessuna'}</div>
      </div>
      
      <div id="organizeList">
        ${masterDocs.map(d => `
          <div class="organize-item" data-docid="${d.id}">
            <div class="filename">📄 ${d.file_name}</div>
            <select class="organize-select" data-docid="${d.id}">
              <option value="">-- Scegli categoria --</option>
              ${S.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('')}
            </select>
          </div>
        `).join('')}
      </div>
      
      <div class="btn-group" style="margin-top:24px">
        <button class="btn secondary" onclick="document.getElementById('organizeModal').remove()">Chiudi</button>
        <button class="btn" id="saveOrganizeBtn">Salva Organizzazione</button>
      </div>
    </div>
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
  if(r==='dashboard') {
    loadDocs();
    if(S.user && S.user.role === 'pro') loadCategories();
  }
  if(r==='calendar') loadEvents();
  if(r==='chat') {
    updateChatCounter();
    if(S.user && S.user.role === 'pro') loadCategories();
  }
  if(r==='account') {
    loadAccountInfo();
  }
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
    
    // Bind upgrade buttons
    ['upgradeBtn', 'upgradeBtn2', 'upgradeBtn3'].forEach(id => {
      const btn = document.getElementById(id);
      if(btn) btn.onclick = showUpgradeModal;
    });
    
    // Bind account page buttons
    const activateProPage = document.getElementById('activateProPage');
    const downgradeBtn = document.getElementById('downgradeBtn');
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    
    if(activateProPage) activateProPage.onclick = activateProFromPage;
    if(downgradeBtn) downgradeBtn.onclick = doDowngrade;
    if(deleteAccountBtn) deleteAccountBtn.onclick = doDeleteAccount;
    
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
    const askAIBtn = document.getElementById('askAIBtn');
    const addEv = document.getElementById('addEv');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const organizeDocsBtn = document.getElementById('organizeDocsBtn');
    const filterCategory = document.getElementById('filterCategory');
    
    if(uploadBtn) uploadBtn.onclick=uploadFile;
    if(askBtn) askBtn.onclick=ask;
    if(askAIBtn) askAIBtn.onclick=askAIDirect;
    if(addEv) addEv.onclick=createEvent;
    if(addCategoryBtn) addCategoryBtn.onclick=createCategory;
    if(organizeDocsBtn) organizeDocsBtn.onclick=showOrganizeModal;
    if(filterCategory) filterCategory.onchange=(e)=>{S.filterCategory=e.target.value; renderDocsTable();};
    
    loadDocs();
    loadStats();
    if(S.user.role === 'pro') {
      loadCategories();
    }
  }
}

async function loadCategories(){
  const r = await api('api/categories.php?a=list');
  if(!r.success) return;
  
  S.categories = r.data;
  
  // Aggiorna select upload
  const uploadCategory = document.getElementById('uploadCategory');
  if(uploadCategory) {
    uploadCategory.innerHTML = '<option value="">-- Seleziona una categoria --</option>' +
      S.categories.map(c=>`<option value="${c.name}">${c.name}</option>`).join('');
  }
  
  // Aggiorna select chat
  const category = document.getElementById('category');
  if(category) {
    category.innerHTML = '<option value="">-- Seleziona categoria --</option>' +
      S.categories.map(c=>`<option value="${c.name}">${c.name}</option>`).join('');
  }
  
  // Aggiorna filtro categoria
  const filterCategory = document.getElementById('filterCategory');
  if(filterCategory) {
    filterCategory.innerHTML = '<option value="">Tutte le categorie</option>' +
      S.categories.map(c=>`<option value="${c.name}">${c.name}</option>`).join('');
  }
  
  // Mostra lista categorie nella card upload
  const categoriesList = document.getElementById('categoriesList');
  if(categoriesList) {
    if(S.categories.length === 0) {
      categoriesList.innerHTML = '<p style="color:var(--muted);font-size:12px;padding:8px">Nessuna categoria. Creane una qui sotto!</p>';
    } else {
      categoriesList.innerHTML = S.categories.map(c=>`
        <span class="category-tag">
          🏷️ ${c.name}
          <button onclick="deleteCategory(${c.id})" title="Elimina categoria">✕</button>
        </span>
      `).join('');
    }
  }
}

async function createCategory(){
  const input = document.getElementById('newCategoryName');
  const btn = document.getElementById('addCategoryBtn');
  const name = input.value.trim();
  
  if(!name){
    alert('Inserisci un nome per la categoria');
    input.focus();
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';
  
  const fd = new FormData();
  fd.append('name', name);
  
  try {
    const r = await api('api/categories.php?a=create', fd);
    
    if(r.success){
      input.value = '';
      await loadCategories();
    } else {
      alert(r.message || 'Errore creazione categoria');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '+ Crea';
  }
}

async function createCategoryInModal(){
  const input = document.getElementById('modalNewCategoryName');
  const btn = document.getElementById('modalAddCategoryBtn');
  const name = input.value.trim();
  
  if(!name){
    alert('Inserisci un nome per la categoria');
    input.focus();
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';
  
  const fd = new FormData();
  fd.append('name', name);
  
  try {
    const r = await api('api/categories.php?a=create', fd);
    
    if(r.success){
      input.value = '';
      await loadCategories();
      
      // Ricarica il modal con le nuove categorie
      document.getElementById('organizeModal').remove();
      showOrganizeModal();
    } else {
      alert(r.message || 'Errore creazione categoria');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Crea';
  }
}

async function deleteCategory(id){
  if(!confirm('Eliminare questa categoria?\n\nATTENZIONE: Non puoi eliminare categorie che contengono documenti.')) return;
  
  const fd = new FormData();
  fd.append('id', id);
  
  const r = await api('api/categories.php?a=delete', fd);
  
  if(r.success){
    loadCategories();
    loadDocs();
  } else {
    alert(r.message || 'Errore eliminazione categoria');
  }
}

// Rendi deleteCategory globale per onclick inline
window.deleteCategory = deleteCategory;

function showUpgradeModal(){
  document.body.insertAdjacentHTML('beforeend', upgradeModal());
  document.getElementById('closeModal').onclick = ()=> document.getElementById('upgradeModal').remove();
  document.getElementById('activateBtn').onclick = activatePro;
}

function showOrganizeModal(){
  document.body.insertAdjacentHTML('beforeend', organizeDocsModal());
  const saveBtn = document.getElementById('saveOrganizeBtn');
  const modalAddBtn = document.getElementById('modalAddCategoryBtn');
  
  if(saveBtn) saveBtn.onclick = saveOrganization;
  if(modalAddBtn) modalAddBtn.onclick = createCategoryInModal;
}

async function saveOrganization(){
  const selects = document.querySelectorAll('.organize-select');
  const updates = [];
  
  selects.forEach(select => {
    const docid = select.dataset.docid;
    const category = select.value;
    if(category) {
      updates.push({docid: parseInt(docid), category});
    }
  });
  
  if(updates.length === 0){
    alert('Seleziona almeno una categoria per i documenti');
    return;
  }
  
  const saveBtn = document.getElementById('saveOrganizeBtn');
  saveBtn.disabled = true;
  saveBtn.innerHTML = 'Salvataggio... <span class="loader"></span>';
  
  let success = 0;
  let errors = 0;
  
  for(const update of updates){
    const fd = new FormData();
    fd.append('id', update.docid);
    fd.append('category', update.category);
    
    try {
      const r = await api('api/documents.php?a=change_category', fd);
      if(r.success) success++;
      else errors++;
    } catch(e) {
      errors++;
    }
  }
  
  document.getElementById('organizeModal').remove();
  
  if(errors === 0){
    alert(`✓ ${success} documento/i organizzato/i correttamente!`);
  } else {
    alert(`⚠ ${success} documento/i organizzato/i, ${errors} errore/i.`);
  }
  
  loadDocs();
}

async function activatePro(){
  const code = document.getElementById('promoCode').value.trim();
  const err = document.getElementById('upgradeError');
  const success = document.getElementById('upgradeSuccess');
  
  err.classList.add('hidden');
  success.classList.add('hidden');
  
  if(!code){
    err.textContent = 'Inserisci un codice';
    err.classList.remove('hidden');
    return;
  }
  
  const fd = new FormData();
  fd.append('code', code);
  
  const r = await api('api/upgrade.php', fd);
  
  if(r.success){
    success.textContent = '✓ Piano Pro attivato! Ricarico...';
    success.classList.remove('hidden');
    setTimeout(()=>{
      S.user.role = 'pro';
      document.getElementById('upgradeModal').remove();
      render();
      // Mostra modal organizzazione se ci sono documenti master
      setTimeout(() => {
        const masterDocs = S.docs.filter(d => d.category === 'master');
        if(masterDocs.length > 0) {
          showOrganizeModal();
        }
      }, 500);
    }, 1500);
  } else {
    err.textContent = r.message || 'Codice non valido';
    err.classList.remove('hidden');
  }
}

async function loadStats(){
  const r = await api('api/stats.php');
  if(r.success){
    S.stats = r.data;
    const qCount = document.getElementById('qCount');
    const qCountChat = document.getElementById('qCountChat');
    const storageUsed = document.getElementById('storageUsed');
    
    if(qCount) qCount.textContent = S.stats.chatToday || 0;
    if(qCountChat) qCountChat.textContent = S.stats.chatToday || 0;
    if(storageUsed) storageUsed.textContent = (S.stats.totalSize / (1024*1024)).toFixed(1);
  }
}

function updateChatCounter(){
  const qCountChat = document.getElementById('qCountChat');
  if(qCountChat) qCountChat.textContent = S.stats.chatToday || 0;
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
    S.user = {email, role: r.role || 'free'};
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
  
  S.docs = r.data;
  
  const docCount = document.getElementById('docCount');
  if(docCount) docCount.textContent = S.docs.length;
  
  renderDocsTable();
  loadStats();
}

function renderDocsTable(){
  const tb = document.querySelector('#docsTable tbody');
  if(!tb) return;
  
  const isPro = S.user && S.user.role === 'pro';
  
  // Filtra documenti se c'è un filtro attivo
  let filteredDocs = S.docs;
  if(S.filterCategory) {
    filteredDocs = S.docs.filter(d => d.category === S.filterCategory);
  }
  
  tb.innerHTML = filteredDocs.map(d=>`
    <tr>
      <td>${d.file_name}</td>
      ${isPro ? `
        <td class="category-select-cell">
          <select class="doc-category-select" data-docid="${d.id}" data-current="${d.category}">
            ${S.categories.map(c => `<option value="${c.name}" ${c.name === d.category ? 'selected' : ''}>${c.name}</option>`).join('')}
          </select>
        </td>
      ` : ''}
      <td>${(d.size/(1024*1024)).toFixed(2)} MB</td>
      <td>${new Date(d.created_at).toLocaleString('it-IT')}</td>
      <td>
        <a href="api/documents.php?a=download&id=${d.id}" class="btn small" style="margin-right:8px;text-decoration:none;display:inline-block">📥 Scarica</a>
        <button class='btn del' data-id='${d.id}'>Elimina</button>
      </td>
    </tr>
  `).join('');
  
  // Bind delete buttons
  tb.querySelectorAll('button[data-id]').forEach(b=>b.onclick=()=>delDoc(b.dataset.id));
  
  // Bind category selects (solo Pro)
  if(isPro) {
    tb.querySelectorAll('.doc-category-select').forEach(select => {
      select.onchange = async (e) => {
        const docid = e.target.dataset.docid;
        const oldCategory = e.target.dataset.current;
        const newCategory = e.target.value;
        
        if(oldCategory === newCategory) return;
        
        if(!confirm(`Spostare il documento nella categoria "${newCategory}"?\n\nIl documento verrà spostato anche su DocAnalyzer.`)) {
          e.target.value = oldCategory;
          return;
        }
        
        // Mostra loader
        e.target.disabled = true;
        const originalHTML = e.target.innerHTML;
        e.target.innerHTML = '<option>Spostamento...</option>';
        
        const fd = new FormData();
        fd.append('id', docid);
        fd.append('category', newCategory);
        
        try {
          const r = await api('api/documents.php?a=change_category', fd);
          
          if(r.success) {
            // Aggiorna dataset
            e.target.dataset.current = newCategory;
            // Ricarica documenti per essere sicuri
            await loadDocs();
          } else {
            alert(r.message || 'Errore spostamento');
            e.target.value = oldCategory;
          }
        } catch(err) {
          alert('Errore di connessione');
          e.target.value = oldCategory;
        } finally {
          e.target.disabled = false;
          e.target.innerHTML = originalHTML;
        }
      };
    });
  }
}

async function uploadFile(){
  const file = document.getElementById('file');
  const uploadBtn = document.getElementById('uploadBtn');
  const drop = document.getElementById('drop');
  const uploadCategory = document.getElementById('uploadCategory');
  const f = file.files[0];
  if(!f) return alert('Seleziona un file');
  
  // Pro deve selezionare categoria
  if(S.user.role === 'pro' && uploadCategory && !uploadCategory.value){
    alert('Seleziona una categoria prima di caricare il file');
    uploadCategory.focus();
    return;
  }
  
  // Mostra loader
  uploadBtn.disabled = true;
  const originalText = uploadBtn.innerHTML;
  uploadBtn.innerHTML = 'Caricamento... <span class="loader"></span>';
  
  if(drop) {
    drop.style.opacity = '0.5';
    drop.style.pointerEvents = 'none';
  }
  
  const fd = new FormData();
  fd.append('file', f);
  if(uploadCategory && uploadCategory.value) {
    fd.append('category', uploadCategory.value);
  }
  
  try {
    const r = await api('api/documents.php?a=upload', fd);
    
    if(r.success){
      loadDocs();
      file.value = '';
      if(uploadCategory) uploadCategory.value = '';
    } else {
      alert(r.message || 'Errore durante l\'upload');
    }
  } catch(e) {
    alert('Errore di connessione durante l\'upload');
  } finally {
    uploadBtn.disabled = false;
    uploadBtn.innerHTML = originalText;
    if(drop) {
      drop.style.opacity = '1';
      drop.style.pointerEvents = 'auto';
    }
  }
}

async function delDoc(id){
  if(!confirm('Eliminare questo documento?')) return;
  
  const btn = document.querySelector(`button[data-id="${id}"]`);
  if(btn){
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span>';
  }
  
  const fd = new FormData();
  fd.append('id', id);
  const r = await api('api/documents.php?a=delete', fd);
  
  if(r.success) {
    loadDocs();
  } else {
    if(btn){
      btn.disabled = false;
      btn.innerHTML = 'Elimina';
    }
  }
}

async function ask(){
  const q = document.getElementById('q');
  const category = document.getElementById('category');
  const askBtn = document.getElementById('askBtn');
  const adherence = document.getElementById('adherence');
  const showRefs = document.getElementById('showRefs');
  
  if(!q.value.trim()){
    alert('Inserisci una domanda');
    return;
  }
  
  // Pro DEVE selezionare categoria
  if(S.user.role === 'pro' && (!category.value || category.value === '')){
    alert('Seleziona una categoria prima di fare la domanda');
    category.focus();
    return;
  }
  
  // Mostra loader
  askBtn.disabled = true;
  askBtn.innerHTML = 'Cerco... <span class="loader"></span>';
  
  const fd = new FormData();
  fd.append('q', q.value);
  fd.append('category', category.value || '');
  fd.append('mode', 'docs');
  fd.append('adherence', adherence.value);
  fd.append('show_refs', showRefs.checked ? '1' : '0');
  
  try {
    const r = await api('api/chat.php', fd);
    const log = document.getElementById('chatLog');
    
    if(r.success){
      let badge = '';
      let sourceLabel = '';
      let bgColor = '#1f2937';
      
      if(r.source === 'docs'){
        badge = 'Risposta dai documenti';
        sourceLabel = 'DocAnalyzer';
        bgColor = '#1f2937';
      } else if(r.source === 'ai'){
        badge = 'Risposta AI Generica';
        sourceLabel = 'Google Gemini';
        bgColor = '#0b1220';
      } else if(r.source === 'none'){
        badge = 'Nessuna risposta trovata';
        bgColor = '#1e293b';
      }
      
      const item = document.createElement('div');
      item.style.cssText = `background:${bgColor};padding:16px;border-radius:10px;margin-bottom:12px;border-left:4px solid var(--accent)`;
      
      let html = `<div style="font-weight:600;margin-bottom:8px">${badge}</div>`;
      html += `<div style="white-space:pre-wrap">${r.answer}</div>`;
      
      // Se DocAnalyzer non ha trovato risposta, aggiungi bottone per chiedere a Gemini
      if(r.can_ask_ai){
        html += `<button class="btn" onclick="askAI('${q.value.replace(/'/g, "\\'")}', '${category.value}')" style="margin-top:12px">Chiedi a AI generica (Google Gemini)</button>`;
      }
      
      item.innerHTML = html;
      log.insertBefore(item, log.firstChild);
      
      if(r.source !== 'none'){
        q.value = '';
        S.stats.chatToday++;
        updateChatCounter();
        const qCount = document.getElementById('qCount');
        if(qCount) qCount.textContent = S.stats.chatToday;
      }
    } else {
      alert(r.message || 'Errore');
    }
  } finally {
    askBtn.disabled = false;
    askBtn.innerHTML = 'Chiedi ai documenti';
  }
}

async function askAI(question, category){
  const log = document.getElementById('chatLog');
  
  // Aggiungi loader
  const loader = document.createElement('div');
  loader.id = 'aiLoader';
  loader.style.cssText = 'background:#0b1220;padding:16px;border-radius:10px;margin-bottom:12px;border-left:4px solid #10b981';
  loader.innerHTML = '<div style="font-weight:600;margin-bottom:8px">Chiedendo a Google Gemini...</div><span class="loader"></span>';
  log.insertBefore(loader, log.firstChild);
  
  const fd = new FormData();
  fd.append('q', question);
  fd.append('category', category);
  fd.append('mode', 'ai');
  
  try {
    const r = await api('api/chat.php', fd);
    
    // Rimuovi loader
    document.getElementById('aiLoader')?.remove();
    
    if(r.success){
      const item = document.createElement('div');
      item.style.cssText = 'background:#0b1220;padding:16px;border-radius:10px;margin-bottom:12px;border-left:4px solid #10b981';
      item.innerHTML = `<div style="font-weight:600;margin-bottom:8px">Risposta AI Generica (Google Gemini)</div><div style="white-space:pre-wrap">${r.answer}</div>`;
      log.insertBefore(item, log.firstChild);
      
      S.stats.chatToday++;
      updateChatCounter();
      const qCount = document.getElementById('qCount');
      if(qCount) qCount.textContent = S.stats.chatToday;
    } else {
      alert(r.message || 'Errore AI');
    }
  } catch(e) {
    document.getElementById('aiLoader')?.remove();
    alert('Errore connessione AI');
  }
}

// Rendi askAI globale
window.askAI = askAI;

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

async function loadAccountInfo(){
  const r = await api('api/account.php?a=info');
  if(!r.success) return;
  
  const accountEmail = document.getElementById('accountEmail');
  const accountSince = document.getElementById('accountSince');
  const usageDocs = document.getElementById('usageDocs');
  const usageStorage = document.getElementById('usageStorage');
  const usageChat = document.getElementById('usageChat');
  const usageCategories = document.getElementById('usageCategories');
  
  if(accountEmail) accountEmail.textContent = r.account.email;
  if(accountSince) accountSince.textContent = new Date(r.account.created_at).toLocaleDateString('it-IT');
  if(usageDocs) usageDocs.textContent = r.usage.documents;
  if(usageStorage) usageStorage.textContent = (r.usage.storage_bytes / (1024*1024)).toFixed(1);
  if(usageChat) usageChat.textContent = r.usage.chat_today;
  if(usageCategories) usageCategories.textContent = r.usage.categories;
}

async function activateProFromPage(){
  const code = document.getElementById('promoCodePage').value.trim();
  const err = document.getElementById('upgradePageError');
  const success = document.getElementById('upgradePageSuccess');
  const btn = document.getElementById('activateProPage');
  
  err.classList.add('hidden');
  success.classList.add('hidden');
  
  if(!code){
    err.textContent = 'Inserisci un codice';
    err.classList.remove('hidden');
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = 'Attivazione... <span class="loader"></span>';
  
  const fd = new FormData();
  fd.append('code', code);
  
  const r = await api('api/upgrade.php', fd);
  
  if(r.success){
    success.textContent = '✓ Piano Pro attivato! Ricarico...';
    success.classList.remove('hidden');
    setTimeout(()=>{
      S.user.role = 'pro';
      render();
      // Mostra modal organizzazione se ci sono documenti master
      setTimeout(() => {
        const masterDocs = S.docs.filter(d => d.category === 'master');
        if(masterDocs.length > 0) {
          showOrganizeModal();
        }
      }, 500);
    }, 1500);
  } else {
    err.textContent = r.message || 'Codice non valido';
    err.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = 'Attiva Pro';
  }
}

async function doDowngrade(){
  if(!confirm('Sei sicuro di voler passare al piano Free?\n\nDevi avere massimo 5 documenti. Tutti i documenti saranno spostati nella categoria principale.')) return;
  
  const btn = document.getElementById('downgradeBtn');
  const err = document.getElementById('downgradeError');
  
  err.classList.add('hidden');
  btn.disabled = true;
  btn.innerHTML = 'Downgrade in corso... <span class="loader"></span>';
  
  const r = await api('api/account.php?a=downgrade', new FormData());
  
  if(r.success){
    alert('✓ ' + r.message);
    S.user.role = 'free';
    render();
  } else {
    err.textContent = r.message || 'Errore durante il downgrade';
    err.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = 'Downgrade a Free';
  }
}

async function doDeleteAccount(){
  // Prima conferma
  if(!confirm('⚠️ ATTENZIONE ⚠️\n\nVuoi eliminare il tuo account?\n\nQuesta azione eliminerà:\n- Tutti i tuoi documenti\n- Tutte le chat\n- Tutti gli eventi\n- Il tuo account\n\nQuesta azione è IRREVERSIBILE.')) return;
  
  // Seconda conferma
  if(!confirm('Confermi l\'eliminazione dell\'account?\n\nNon potrai più recuperare i tuoi dati.')) return;
  
  const btn = document.getElementById('deleteAccountBtn');
  btn.disabled = true;
  btn.innerHTML = 'Eliminazione... <span class="loader"></span>';
  
  const r = await api('api/account.php?a=delete', new FormData());
  
  if(r.success){
    alert('Account eliminato. Arrivederci.');
    S.user = null;
    S.view = 'login';
    render();
  } else {
    alert('Errore: ' + (r.message || 'Impossibile eliminare account'));
    btn.disabled = false;
    btn.innerHTML = '🗑️ Elimina Account';
  }
}

render();
if('serviceWorker' in navigator) navigator.serviceWorker.register('assets/service-worker.js');
</script>
</body>
</html>
