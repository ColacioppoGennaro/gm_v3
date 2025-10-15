/**
 * assets/js/ui.js (MODIFICATO)
 * Aggiunto: Filtro Area + Bottone Organizza
 */

import { checkSession, getInitialView, doLogin, doRegister, doForgot, doLogout } from './auth.js';
import { loadDocs, renderDocsTable, uploadFile, loadCategories, createCategoryFromDashboard, setupUploadDropzone, showOrganizeModal } from './documents.js';
import { askDocs, askAI, updateChatCounter } from './chat.js';
import { renderCalendar } from './calendar.js';
import { loadAccountInfo, showUpgradeModal, activateProFromPage, doDowngrade, doDeleteAccount } from './account.js';
import { renderEventsWidget } from './dashboard-events.js';
import { openAssistantModal } from './assistant.js';
import { openOrganizeModal } from './settings.js'; // ✨ NUOVO

const LS_ROUTE_KEY = 'gmv3_route';

/**
 * VIEW TEMPLATES
 */

function loginView() {
  return `<div class="auth-container">
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

function registerView() {
  return `<div class="auth-container">
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

function forgotView() {
  return `<div class="auth-container">
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

function appView() {
  const isPro = window.S.user && window.S.user.role === 'pro';
  const maxDocs = isPro ? 200 : 5;
  const maxChat = isPro ? 200 : 20;
  const maxSize = isPro ? 150 : 50;

  return `<div class="app">
    <aside>
      <div class="logo">✨ <b>gm_v3</b> ${isPro ? '<span class="badge-pro">PRO</span>' : ''}</div>
      <div class="nav">
        <a href="#/dashboard" data-route="dashboard" class="active">📊 Dashboard</a>
        <a href="#/chat" data-route="chat">💬 Chat AI</a>
        <a href="#/calendar" data-route="calendar">📅 Calendario</a>
        <a href="#/account" data-route="account">👤 Account</a>
      </div>
    </aside>
    <main>
      ${dashboardSection(isPro, maxDocs, maxChat, maxSize)}
      ${chatSection(isPro, maxChat)}
      <section class="hidden" data-page="calendar"></section>
      ${accountSection(isPro, maxDocs, maxChat, maxSize)}
    </main>
  </div>`;
}

function dashboardSection(isPro, maxDocs, maxChat, maxSize) {
  return `<section data-page="dashboard">
    <h1>Dashboard</h1>
    ${!isPro ? '<div class="banner" id="upgradeBtn">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
    
    <div class="card" style="background:linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);color:white;margin-bottom:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <div>
          <h3 style="color:white;margin:0 0 8px 0">🤖 Assistente AI</h3>
          <p style="margin:0;opacity:0.9;font-size:14px">
            Crea eventi calendario tramite dialogo naturale
          </p>
        </div>
        <button class="btn" id="btnOpenAssistant" style="background:white;color:#7c3aed;font-weight:600">
          ✨ Avvia Assistente
        </button>
      </div>
    </div>
    
    <div id="eventsWidget"></div>
    <div class="cards stats">
      <div class="card"><div class="stat-label">Documenti Archiviati</div><div class="stat-number"><span id="docCount">0</span> / ${maxDocs}</div></div>
      <div class="card"><div class="stat-label">Domande AI Oggi</div><div class="stat-number"><span id="qCount">0</span> / ${maxChat}</div></div>
      <div class="card"><div class="stat-label">Storage Usato</div><div class="stat-number"><span id="storageUsed">0</span> MB / ${maxSize} MB</div></div>
    </div>
    <div class="card">
      <h3>📤 Carica Documento</h3>
      ${isPro ? `<div style="background:#1f2937;padding:16px;border-radius:10px;margin-bottom:16px">
        <h4 style="margin:0 0 12px 0;font-size:14px;color:var(--accent)">🏷️ Le tue categorie</h4>
        <div id="categoriesList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;min-height:32px;align-items:center"></div>
        <div style="display:flex;gap:8px">
          <input id="newCategoryName" placeholder="Nome nuova categoria (es. Fatture)" style="flex:1"/>
          <button class="btn" id="addCategoryBtn">+ Crea</button>
        </div>
      </div>
      <div class="form-group">
        <label>Categoria documento *</label>
        <select id="uploadCategory" style="width:100%"><option value="">-- Seleziona una categoria --</option></select>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">Devi scegliere una categoria prima di caricare</div>
      </div>` : ''}
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
      ${isPro ? `<div class="filter-bar">
        <label>Area:</label>
        <select id="filterArea"><option value="">Tutte</option></select>
        <label>Tipo:</label>
        <select id="filterTipo"><option value="">Tutti</option></select>
        <label>Categoria:</label>
        <select id="filterCategory"><option value="">Tutte</option></select>
        <button class="btn secondary" id="organizeBtn" style="margin-left:auto">🔧 Organizza</button>
      </div>` : ''}
      <table id="docsTable">
        <thead><tr>
          <th>Nome File</th>
          ${isPro ? '<th>Categoria</th>' : ''}
          <th>Dimensione</th><th>Data</th><th></th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>`;
}

function chatSection(isPro, maxChat) {
  return `<section class="hidden" data-page="chat">
    <h1>💬 Chat AI</h1>
    ${!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : ''}
    <div class="card">
      <h3>📄 Chiedi ai tuoi documenti</h3>
      <div class="settings-row settings-row--compact">
        <label>Aderenza:</label>
        <select id="adherence" style="width:auto;padding:6px 10px">
          <option value="strict">Strettamente documenti</option>
          <option value="high">Alta aderenza</option>
          <option value="balanced" selected>Bilanciata</option>
          <option value="low">Bassa aderenza</option>
          <option value="free">Libera interpretazione</option>
        </select>
        <label style="margin-left:8px;display:flex;align-items:center;gap:6px">
          <input type="checkbox" id="showRefs" checked style="width:auto;margin:0"/>
          <span style="font-size:13px">Mostra riferimenti pagine</span>
        </label>
      </div>
      <div class="compose" style="margin-top:12px">
        <input id="qDocs" placeholder="Es: Quando scade l'IMU?" />
        <button class="btn icon-only" id="askDocsBtn" title="Invia">➤</button>
      </div>
      ${isPro ? '<div class="chat-docs-actions"><select id="categoryDocs"><option value="">-- Seleziona categoria --</option></select></div>' : '<div class="chat-docs-actions"><div class="pill">(Free: tutti)</div><select id="categoryDocs" class="hidden"><option value="">(Free: tutti)</option></select></div>'}
      <div style="margin-top:8px;font-size:12px;color:var(--muted)">Domande oggi: <b id="qCountChat">0</b>/${maxChat}</div>
    </div>
    ${!isPro ? '<div class="banner" id="upgradeBtn2">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
    <div class="card">
      <h3>🤖 Chat AI Generica (Google Gemini)</h3>
      <p style="color:var(--muted);font-size:13px;margin-bottom:12px">Chiedi qualsiasi cosa all'AI generica, anche senza documenti</p>
      <div id="contextBox" class="hidden"></div>
      <div class="compose">
        <input id="qAI" placeholder="Es: Spiegami come funziona la fotosintesi..." />
        <button class="btn success icon-only" id="askAIBtn" title="Invia">➤</button>
      </div>
    </div>
    <div class="card">
      <h3>💬 Conversazione</h3>
      <div id="chatLog" style="min-height:200px"></div>
    </div>
  </section>`;
}

function accountSection(isPro, maxDocs, maxChat, maxSize) {
  return `<section class="hidden" data-page="account">
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
        <div style="font-size:13px;margin:8px 0">Documenti: <b id="usageDocs">0</b> / ${maxDocs}</div>
        <div style="font-size:13px;margin:8px 0">Storage: <b id="usageStorage">0</b> MB / ${maxSize} MB</div>
        <div style="font-size:13px;margin:8px 0">Chat oggi: <b id="usageChat">0</b> / ${maxChat}</div>
        ${isPro ? '<div style="font-size:13px;margin:8px 0">Categorie: <b id="usageCategories">0</b></div>' : ''}
      </div>
    </div>
    ${!isPro ? `<div class="card">
      <h3>⚡ Upgrade a Pro</h3>
      <p style="color:var(--muted);margin-bottom:16px">Sblocca funzionalità avanzate e limiti aumentati</p>
      <div class="form-group">
        <label>Codice Promozionale</label>
        <input type="text" id="promoCodePage" placeholder="Inserisci codice"/>
      </div>
      <div id="upgradePageError" class="error hidden"></div>
      <div id="upgradePageSuccess" class="success hidden"></div>
      <button class="btn" id="activateProPage">Attiva Pro</button>
    </div>` : `<div class="card">
      <h3>⬇️ Downgrade a Free</h3>
      <p style="color:var(--muted);margin-bottom:16px">Torna al piano gratuito. <b>ATTENZIONE:</b> Devi avere massimo 5 documenti.</p>
      <div id="downgradeError" class="error hidden"></div>
      <button class="btn warn" id="downgradeBtn">Downgrade a Free</button>
    </div>`}
    <div class="card">
      <h3>⚙️ Impostazioni</h3>
      <button class="btn secondary" id="logoutBtn" style="width:100%;margin-bottom:12px">🚪 Logout</button>
      <button class="btn del" id="deleteAccountBtn" style="width:100%">🗑️ Elimina Account</button>
    </div>
  </section>`;
}

/**
 * RENDER APP
 */
export function renderApp() {
  const views = {
    login: loginView,
    register: registerView,
    forgot: forgotView,
    app: appView
  };

  const root = document.getElementById('app');
  if (!root) return console.error('Elemento root #app non trovato!');

  root.innerHTML = views[window.S.view]();
  bindEvents();
}

/**
 * BIND EVENTI
 */
function bindEvents() {
  if (window.S.view === 'login') {
    document.getElementById('loginBtn')?.addEventListener('click', doLogin);
    document.getElementById('goRegister')?.addEventListener('click', () => {
      window.S.view = 'register';
      renderApp();
    });
    document.getElementById('goForgot')?.addEventListener('click', () => {
      window.S.view = 'forgot';
      renderApp();
    });
    document.getElementById('password')?.addEventListener('keypress', e => {
      if (e.key === 'Enter') doLogin();
    });
  } else if (window.S.view === 'register') {
    document.getElementById('registerBtn')?.addEventListener('click', doRegister);
    document.getElementById('backToLogin')?.addEventListener('click', () => {
      window.S.view = 'login';
      renderApp();
    });
  } else if (window.S.view === 'forgot') {
    document.getElementById('forgotBtn')?.addEventListener('click', doForgot);
    document.getElementById('backToLogin2')?.addEventListener('click', () => {
      window.S.view = 'login';
      renderApp();
    });
  } else if (window.S.view === 'app') {
    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', doLogout);

    // Upgrade buttons
    ['upgradeBtn', 'upgradeBtn2'].forEach(id => {
      document.getElementById(id)?.addEventListener('click', showUpgradeModal);
    });

    // Account page
    document.getElementById('activateProPage')?.addEventListener('click', activateProFromPage);
    document.getElementById('downgradeBtn')?.addEventListener('click', doDowngrade);
    document.getElementById('deleteAccountBtn')?.addEventListener('click', doDeleteAccount);

    // Mobile nav
    setupMobileNav();

    // Dashboard
    setupUploadDropzone();
    document.getElementById('uploadBtn')?.addEventListener('click', uploadFile);
    document.getElementById('addCategoryBtn')?.addEventListener('click', createCategoryFromDashboard);
    
    // ✨ NUOVO: Bottone Organizza
    document.getElementById('organizeBtn')?.addEventListener('click', openOrganizeModal);
    
    // ✨ NUOVO: Filtri Area/Tipo
    document.getElementById('filterArea')?.addEventListener('change', (e) => {
      window.S.filterArea = e.target.value;
      renderDocsTable();
    });
    
    document.getElementById('filterTipo')?.addEventListener('change', (e) => {
      window.S.filterTipo = e.target.value;
      renderDocsTable();
    });
    
    document.getElementById('filterCategory')?.addEventListener('change', (e) => {
      window.S.filterCategory = e.target.value;
      renderDocsTable();
    });

    // Assistente AI
    document.getElementById('btnOpenAssistant')?.addEventListener('click', () => {
      openAssistantModal();
    });

    // Chat
    document.getElementById('askDocsBtn')?.addEventListener('click', askDocs);
    document.getElementById('askAIBtn')?.addEventListener('click', askAI);
  }
}

/**
 * SETUP MOBILE NAV
 */
function setupMobileNav() {
  const appEl = document.querySelector('.app');
  if (!appEl) return;

  const isPro = window.S.user && window.S.user.role === 'pro';

  // Aggiungi topbar se non esiste
  if (!document.querySelector('.topbar')) {
    appEl.insertAdjacentHTML('afterbegin', `
      <div class="topbar">
        <button id="menuToggle" class="menu-btn">☰</button>
        <div class="logo">✨ <b>gm_v3</b> ${isPro ? '<span class="badge-pro">PRO</span>' : ''}</div>
      </div>
      <div id="scrim" class="scrim"></div>
    `);
  }

  // Aggiungi mobile nav se non esiste
  if (!document.querySelector('.mobile-nav')) {
    appEl.insertAdjacentHTML('beforeend', `
      <nav class="mobile-nav">
        <a href="#/dashboard" data-route="dashboard" class="active">📊<br><span>Dashboard</span></a>
        <a href="#/chat" data-route="chat">💬<br><span>Chat</span></a>
        <a href="#/calendar" data-route="calendar">📅<br><span>Calendario</span></a>
        <a href="#/account" data-route="account">👤<br><span>Account</span></a>
      </nav>
    `);
  }

  // Bind menu toggle
  document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.body.classList.toggle('menu-open');
  });

  document.getElementById('scrim')?.addEventListener('click', () => {
    document.body.classList.remove('menu-open');
  });
}

/**
 * SHOW PAGE
 */
export function showPage(pageName) {
  // Aggiorna nav active
  document.querySelectorAll('.nav a, .mobile-nav a').forEach(a =>
    a.classList.toggle('active', a.dataset.route === pageName)
  );

  // Nascondi tutte le pagine
  document.querySelectorAll('[data-page]').forEach(p => p.classList.add('hidden'));

  // Mostra pagina richiesta
  const page = document.querySelector(`[data-page="${pageName}"]`);
  if (page) page.classList.remove('hidden');

  // Chiudi menu mobile
  document.body.classList.remove('menu-open');

  // Carica dati specifici pagina
  if (pageName === 'dashboard') {
    loadDocs();
    if (window.S.user && window.S.user.role === 'pro') {
      loadCategories();
      loadFiltersData(); // ✨ NUOVO: Carica aree/tipi per filtri
    }
    renderEventsWidget();
  } else if (pageName === 'calendar') {
    renderCalendar();
  } else if (pageName === 'chat') {
    updateChatCounter();
    if (window.S.user?.role === 'pro') loadCategories();
  } else if (pageName === 'account') {
    loadAccountInfo();
  }

  localStorage.setItem(LS_ROUTE_KEY, pageName);
}

/**
 * ✨ NUOVO: Carica dati per filtri Area/Tipo
 */
async function loadFiltersData() {
  try {
    const [settoriRes, tipiRes] = await Promise.all([
      fetch('api/settori.php?a=list'),
      fetch('api/tipi_attivita.php?a=list')
    ]);
    
    const settori = await settoriRes.json();
    const tipi = await tipiRes.json();
    
    if (settori.success) {
      const filterArea = document.getElementById('filterArea');
      if (filterArea) {
        filterArea.innerHTML = '<option value="">Tutte</option>' + 
          settori.data.map(s => `<option value="${s.id}">${s.icona} ${s.nome}</option>`).join('');
      }
    }
    
    if (tipi.success) {
      const filterTipo = document.getElementById('filterTipo');
      if (filterTipo) {
        filterTipo.innerHTML = '<option value="">Tutti</option>' + 
          tipi.data.map(t => `<option value="${t.id}">${t.icona} ${t.nome}</option>`).join('');
      }
    }
  } catch (error) {
    console.error('Errore caricamento filtri:', error);
  }
}

/**
 * ROUTING
 */
export function route() {
  const h = location.hash || (window.S.view === 'app' ? '#/dashboard' : '');
  if (!h.startsWith('#/')) return;

  const pageName = h.substring(2);
  if (window.S.view === 'app') {
    const validRoutes = ['dashboard', 'chat', 'calendar', 'account'];
    showPage(validRoutes.includes(pageName) ? pageName : 'dashboard');
  }
}

/**
 * BOOTSTRAP
 */
async function bootstrap() {
  window.S.view = getInitialView();
  renderApp();

  const isAuthenticated = await checkSession();
  
  if (isAuthenticated && window.S.view !== 'app') {
    window.S.view = 'app';
    renderApp();
  } else if (!isAuthenticated && window.S.view === 'app') {
    window.S.view = 'login';
    renderApp();
  }
}

/**
 * INIT
 */
window.addEventListener('hashchange', route);
window.addEventListener('load', () => {
  bootstrap().then(() => {
    route();
    
    // Service Worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('assets/service-worker.js')
        .catch(e => console.error('SW registration failed:', e));
    }
  });
});

// Esporta globalmente
window.renderApp = renderApp;
window.route = route;
