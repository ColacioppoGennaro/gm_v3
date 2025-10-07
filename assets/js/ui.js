// === VIEW FUNCTIONS (copiate identiche) ===
function loginView(){
  return '<div class="auth-container">' +
    '<div class="auth-box">' +
    '<h1>✨ Bentornato</h1>' +
    '<p>Accedi al tuo assistente AI personale</p>' +
    '<div class="form-group">' +
    '<label>Email</label>' +
    '<input type="email" id="email" placeholder="tua@email.com" autocomplete="email"/>' +
    '</div>' +
    '<div class="form-group">' +
    '<label>Password</label>' +
    '<input type="password" id="password" placeholder="••••••••" autocomplete="current-password"/>' +
    '</div>' +
    '<div id="loginError" class="error hidden"></div>' +
    '<div class="btn-group">' +
    '<button class="btn" id="loginBtn">Accedi</button>' +
    '</div>' +
    '<button class="link-btn" id="goRegister">Non hai un account? Registrati</button>' +
    '<button class="link-btn" id="goForgot" style="display:block;margin-top:8px">Password dimenticata?</button>' +
    '</div></div>';
}

function registerView(){
  return '<div class="auth-container">' +
    '<div class="auth-box">' +
    '<h1>🚀 Crea Account</h1>' +
    '<p>Inizia subito a usare il tuo assistente AI</p>' +
    '<div class="form-group">' +
    '<label>Email</label>' +
    '<input type="email" id="regEmail" placeholder="tua@email.com" autocomplete="email"/>' +
    '</div>' +
    '<div class="form-group">' +
    '<label>Password</label>' +
    '<input type="password" id="regPass" placeholder="Minimo 6 caratteri" autocomplete="new-password"/>' +
    '</div>' +
    '<div class="form-group">' +
    '<label>Conferma Password</label>' +
    '<input type="password" id="regPassConfirm" placeholder="Ripeti la password" autocomplete="new-password"/>' +
    '</div>' +
    '<div id="regError" class="error hidden"></div>' +
    '<div id="regSuccess" class="success hidden"></div>' +
    '<div class="btn-group">' +
    '<button class="btn secondary" id="backToLogin">Annulla</button>' +
    '<button class="btn" id="registerBtn">Registrati</button>' +
    '</div></div></div>';
}

function forgotView(){
  return '<div class="auth-container">' +
    '<div class="auth-box">' +
    '<h1>🔑 Password Dimenticata</h1>' +
    '<p>Inserisci la tua email, ti invieremo un link per reimpostarla</p>' +
    '<div class="form-group">' +
    '<label>Email</label>' +
    '<input type="email" id="forgotEmail" placeholder="tua@email.com"/>' +
    '</div>' +
    '<div id="forgotError" class="error hidden"></div>' +
    '<div id="forgotSuccess" class="success hidden"></div>' +
    '<div class="btn-group">' +
    '<button class="btn secondary" id="backToLogin2">Annulla</button>' +
    '<button class="btn" id="forgotBtn">Invia Link</button>' +
    '</div></div></div>';
}

function appView(){
  const isPro = S.user && S.user.role === 'pro';
  const maxDocs = isPro ? 200 : 5;
  const maxChat = isPro ? 200 : 20;
  const maxSize = isPro ? 150 : 50;
  
  let html = '<div class="app">' +
    '<aside>' +
    '<div class="logo">✨ <b>gm_v3</b> ' + (isPro ? '<span class="badge-pro">PRO</span>' : '') + '</div>' +
    '<div class="nav">' +
    '<a href="#" data-route="dashboard" class="active">📊 Dashboard</a>' +
    '<a href="#" data-route="chat">💬 Chat AI</a>' +
    '<a href="#" data-route="calendar">📅 Calendario</a>' +
    '<a href="#" data-route="account">👤 Account</a>' +
    '</div></aside><main>' +
    
    '<section data-page="dashboard">' +
    '<h1>Dashboard</h1>' +
    (!isPro ? '<div class="banner" id="upgradeBtn">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : '') +
    '<div class="cards stats">' +
      '<div class="card">' +
        '<div class="stat-label">Documenti Archiviati</div>' +
        '<div class="stat-number"><span id="docCount">0</span> / ' + maxDocs + '</div>' +
      '</div>' +
      '<div class="card">' +
        '<div class="stat-label">Domande AI Oggi</div>' +
        '<div class="stat-number"><span id="qCount">0</span> / ' + maxChat + '</div>' +
      '</div>' +
      '<div class="card">' +
        '<div class="stat-label">Storage Usato</div>' +
        '<div class="stat-number"><span id="storageUsed">0</span> MB / ' + maxSize + ' MB</div>' +
      '</div>' +
    '</div>' +
    
    '<div class="card"><h3>📤 Carica Documento</h3>';
    
  if(isPro) {
    html += '<div style="background:#1f2937;padding:16px;border-radius:10px;margin-bottom:16px">' +
      '<h4 style="margin:0 0 12px 0;font-size:14px;color:var(--accent)">🏷️ Le tue categorie</h4>' +
      '<div id="categoriesList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;min-height:32px;align-items:center"></div>' +
      '<div style="display:flex;gap:8px">' +
      '<input id="newCategoryName" placeholder="Nome nuova categoria (es. Fatture)" style="flex:1"/>' +
      '<button class="btn" id="addCategoryBtn">+ Crea</button>' +
      '</div></div>' +
      '<div class="form-group">' +
      '<label>Categoria documento *</label>' +
      '<select id="uploadCategory" style="width:100%">' +
      '<option value="">-- Seleziona una categoria --</option>' +
      '</select>' +
      '<div style="font-size:12px;color:var(--muted);margin-top:4px">Devi scegliere una categoria prima di caricare</div>' +
      '</div>';
  }
  
  html += '<div class="drop" id="drop">' +
    '<div style="text-align:center">' +
    '<div style="font-size:48px;margin-bottom:8px">📁</div>' +
    '<div>Trascina qui un file o clicca per selezionare</div>' +
    '<div style="font-size:12px;color:#64748b;margin-top:4px">PDF, DOC, DOCX, TXT, CSV, XLSX, JPG, PNG (Max ' + maxSize + 'MB)</div>' +
    '</div></div>' +
    '<input type="file" id="file" class="hidden"/>' +
    '<button class="btn" id="uploadBtn" style="width:100%">Carica File</button>' +
    '</div>' +
    
    (!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : '') +
    
    '<div class="card"><h3>📚 I Tuoi Documenti</h3>';
    
  if(isPro) {
    html += '<div class="filter-bar">' +
      '<label>Filtra per categoria:</label>' +
      '<select id="filterCategory">' +
      '<option value="">Tutte le categorie</option>' +
      '</select>' +
      '<button class="btn secondary" id="organizeDocsBtn">🔧 Organizza Documenti</button>' +
      '</div>';
  }
  
  html += '<table id="docsTable"><thead><tr>' +
    '<th>Nome File</th>' +
    (isPro ? '<th>Categoria</th>' : '') +
    '<th>Dimensione</th><th>Data</th><th></th>' +
    '</tr></thead><tbody></tbody></table></div></section>' +
    
    '<section class="hidden" data-page="chat">' +
    '<h1>💬 Chat AI</h1>' +
    (!isPro ? '<div class="banner" id="upgradeBtn2">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : '') +
    
    '<div class="card"><h3>📄 Chiedi ai tuoi documenti</h3>' +
    '<div class="settings-row">' +
    '<label>Aderenza:</label>' +
    '<select id="adherence" style="width:auto;padding:6px 10px">' +
    '<option value="strict">Strettamente documenti</option>' +
    '<option value="high">Alta aderenza</option>' +
    '<option value="balanced" selected>Bilanciata</option>' +
    '<option value="low">Bassa aderenza</option>' +
    '<option value="free">Libera interpretazione</option>' +
    '</select>' +
    '<label style="margin-left:16px;display:flex;align-items:center;gap:4px">' +
    '<input type="checkbox" id="showRefs" checked style="width:auto;margin:0"/>' +
    '<span style="font-size:13px">Mostra riferimenti pagine</span>' +
    '</label></div>' +
    '<div style="display:flex;gap:12px;margin-top:16px">' +
    '<input id="qDocs" placeholder="Es: Quando scade l\'IMU?" style="flex:1"/>' +
    (isPro ? '<select id="categoryDocs" style="width:200px"><option value="">-- Seleziona categoria --</option></select>' : 
             '<select id="categoryDocs" style="width:180px"><option value="">(Free: tutti)</option></select>') +
    '<button class="btn" id="askDocsBtn">🔍 Chiedi ai documenti</button>' +
    '</div>' +
    '<div style="margin-top:8px;font-size:12px;color:var(--muted)">Domande oggi: <b id="qCountChat">0</b>/' + maxChat + '</div>' +
    '</div>' +
    
    (!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : '') +
    
    '<div class="card"><h3>🤖 Chat AI Generica (Google Gemini)</h3>' +
    '<p style="color:var(--muted);font-size:13px;margin-bottom:16px">Chiedi qualsiasi cosa all\'AI generica, anche senza documenti</p>' +
    '<div id="contextBox" class="hidden"></div>' +
    '<div style="display:flex;gap:12px">' +
    '<input id="qAI" placeholder="Es: Spiegami come funziona la fotosintesi..." style="flex:1"/>' +
    '<button class="btn success" id="askAIBtn">🤖 Chiedi a Gemini</button>' +
    '</div></div>' +
    
    '<div class="card"><h3>💬 Conversazione</h3>' +
    '<div id="chatLog" style="min-height:200px"></div>' +
    '</div></section>' +
    
    '<section class="hidden" data-page="calendar">' +
    '<h1>📅 Calendario</h1>' +
    (!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : '') +
    (!isPro ? '<div class="abs">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : '') +
    '<div class="card"><h3>Aggiungi Evento</h3>' +
    '<div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;margin:16px 0">' +
    '<input id="evTitle" placeholder="Titolo evento"/>' +
    '<input id="evStart" type="datetime-local"/>' +
    '<input id="evEnd" type="datetime-local"/>' +
    '<button class="btn" id="addEv">Aggiungi</button>' +
    '</div><table id="evTable">' +
    '<thead><tr><th>Data e Ora</th><th>Titolo</th><th></th></tr></thead>' +
    '<tbody></tbody></table></div></section>' +
    
    '<section class="hidden" data-page="account">' +
    '<h1>👤 Account</h1>' +
    '<div class="cards">' +
    '<div class="card"><h3>Piano Attuale</h3>' +
    '<div style="font-size:32px;font-weight:700;margin:16px 0;color:var(--accent)">' + (isPro ? 'PRO' : 'FREE') + '</div>' +
    '<div style="color:var(--muted);font-size:13px">Email: <b id="accountEmail">...</b></div>' +
    '<div style="color:var(--muted);font-size:13px;margin-top:4px">Membro da: <b id="accountSince">...</b></div>' +
    '</div>' +
    '<div class="card"><h3>Utilizzo</h3>' +
    '<div style="font-size:13px;margin:8px 0">Documenti: <b id="usageDocs">0</b> / ' + (isPro ? '200' : '5') + '</div>' +
    '<div style="font-size:13px;margin:8px 0">Storage: <b id="usageStorage">0</b> MB / ' + (isPro ? '150' : '50') + ' MB</div>' +
    '<div style="font-size:13px;margin:8px 0">Chat oggi: <b id="usageChat">0</b> / ' + (isPro ? '200' : '20') + '</div>' +
    (isPro ? '<div style="font-size:13px;margin:8px 0">Categorie: <b id="usageCategories">0</b></div>' : '') +
    '</div></div>';
    
  if(!isPro) {
    html += '<div class="card"><h3>⚡ Upgrade a Pro</h3>' +
      '<p style="color:var(--muted);margin-bottom:16px">Sblocca funzionalità avanzate e limiti aumentati</p>' +
      '<div class="form-group"><label>Codice Promozionale</label>' +
      '<input type="text" id="promoCodePage" placeholder="Inserisci codice"/>' +
      '</div>' +
      '<div id="upgradePageError" class="error hidden"></div>' +
      '<div id="upgradePageSuccess" class="success hidden"></div>' +
      '<button class="btn" id="activateProPage">Attiva Pro</button>' +
      '</div>';
  } else {
    html += '<div class="card"><h3>⬇️ Downgrade a Free</h3>' +
      '<p style="color:var(--muted);margin-bottom:16px">Torna al piano gratuito. <b>ATTENZIONE:</b> Devi avere massimo 5 documenti.</p>' +
      '<div id="downgradeError" class="error hidden"></div>' +
      '<button class="btn warn" id="downgradeBtn">Downgrade a Free</button>' +
      '</div>';
  }
  
  html += '<div class="card"><h3>⚙️ Impostazioni</h3>' +
    '<button class="btn secondary" id="logoutBtn" style="width:100%;margin-bottom:12px">🚪 Logout</button>' +
    '<button class="btn del" id="deleteAccountBtn" style="width:100%">🗑️ Elimina Account</button>' +
    '</div></section></main></div>';
  
  return html;
}

function upgradeModal(){
  return '<div class="modal" id="upgradeModal">' +
    '<div class="modal-content">' +
    '<h2 style="margin-bottom:16px">🚀 Upgrade a Pro</h2>' +
    '<p style="margin-bottom:24px;color:var(--muted)">Inserisci il codice promozionale per attivare il piano Pro</p>' +
    '<div class="form-group"><label>Codice Promozionale</label>' +
    '<input type="text" id="promoCode" placeholder="Inserisci codice"/></div>' +
    '<div id="upgradeError" class="error hidden"></div>' +
    '<div id="upgradeSuccess" class="success hidden"></div>' +
    '<div class="btn-group">' +
    '<button class="btn secondary" id="closeModal">Annulla</button>' +
    '<button class="btn" id="activateBtn">Attiva Pro</button>' +
    '</div></div></div>';
}

function organizeDocsModal(){
  const masterDocs = S.docs.filter(d => d.category === 'master');
  
  if (masterDocs.length === 0) {
    return '<div class="modal" id="organizeModal">' +
      '<div class="modal-content">' +
      '<h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>' +
      '<p style="color:var(--ok);margin-bottom:16px">✓ Tutti i documenti sono già organizzati in categorie!</p>' +
      '<button class="btn" onclick="document.getElementById(\'organizeModal\').remove()">Chiudi</button>' +
      '</div></div>';
  }
  
  let html = '<div class="modal" id="organizeModal">' +
    '<div class="modal-content">' +
    '<h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>' +
    '<p style="color:var(--muted);margin-bottom:16px">Hai ' + masterDocs.length + ' documento/i senza categoria specifica. Assegna una categoria a ciascuno:</p>' +
    '<div class="new-category-box">' +
    '<h4>➕ Crea Nuova Categoria</h4>' +
    '<div style="display:flex;gap:8px">' +
    '<input id="modalNewCategoryName" placeholder="Nome categoria (es. Lavoro, Fatture...)" style="flex:1"/>' +
    '<button class="btn small" id="modalAddCategoryBtn">Crea</button>' +
    '</div>' +
    '<div style="margin-top:8px;font-size:12px;color:var(--muted)">Categorie esistenti: ' + 
    (S.categories.length > 0 ? S.categories.map(c => c.name).join(', ') : 'nessuna') + '</div>' +
    '</div><div id="organizeList">';
    
  masterDocs.forEach(d => {
    html += '<div class="organize-item" data-docid="' + d.id + '">' +
      '<div class="filename">📄 ' + d.file_name + '</div>' +
      '<select class="organize-select" data-docid="' + d.id + '">' +
      '<option value="">-- Scegli categoria --</option>';
    S.categories.forEach(c => {
      html += '<option value="' + c.name + '">' + c.name + '</option>';
    });
    html += '</select></div>';
  });
  
  html += '</div><div class="btn-group" style="margin-top:24px">' +
    '<button class="btn secondary" onclick="document.getElementById(\'organizeModal\').remove()">Chiudi</button>' +
    '<button class="btn" id="saveOrganizeBtn">Salva Organizzazione</button>' +
    '</div></div></div>';
  
  return html;
}

// === RENDER/ROUTING/BIND ===
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

// ======= ROUTER (versione con topbar + bottom bar + chiusura drawer) =======
function route(r){
  // attiva link in sidebar
  document.querySelectorAll('.nav a').forEach(a =>
    a.classList.toggle('active', a.dataset.route === r)
  );
  // attiva link in bottom bar (mobile)
  document.querySelectorAll('.mobile-nav a').forEach(a =>
    a.classList.toggle('active', a.dataset.route === r)
  );

  // mostra pagina
  document.querySelectorAll('[data-page]').forEach(p=>p.classList.add('hidden'));
  const page = document.querySelector('[data-page="' + r + '"]');
  if(page) page.classList.remove('hidden');

  // chiudi eventuale drawer aperto
  document.body.classList.remove('menu-open');

  // caricamenti per pagina
  if(r==='dashboard') {
    loadDocs();
    if(S.user && S.user.role === 'pro') loadCategories();
  }
  if(r==='calendar') loadEvents();
  if(r==='chat') {
    updateChatCounter();
    if(S.user && S.user.role === 'pro') loadCategories();
  }
  if(r==='account') loadAccountInfo();
}

// ======= BIND (versione con iniezione topbar + bottom bar) =======
function bind(){
  if(S.view === 'login'){
    const loginBtn = document.getElementById('loginBtn');
    const goRegister = document.getElementById('goRegister');
    const goForgot = document.getElementById('goForgot');
    if(loginBtn) loginBtn.onclick = doLogin;
    if(goRegister) goRegister.onclick = ()=>{S.view='register'; render();};
    if(goForgot) goForgot.onclick = ()=>{S.view='forgot'; render();};
    const pwd = document.getElementById('password');
    if(pwd) pwd.addEventListener('keypress', e=>{ if(e.key==='Enter') doLogin(); });
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

    ['upgradeBtn', 'upgradeBtn2', 'upgradeBtn3'].forEach(id => {
      const btn = document.getElementById(id);
      if(btn) btn.onclick = showUpgradeModal;
    });

    const activateProPage = document.getElementById('activateProPage');
    const downgradeBtn = document.getElementById('downgradeBtn');
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    if(activateProPage) activateProPage.onclick = activateProFromPage;
    if(downgradeBtn) downgradeBtn.onclick = doDowngrade;
    if(deleteAccountBtn) deleteAccountBtn.onclick = doDeleteAccount;

    // Router: sidebar
    document.querySelectorAll('.nav a').forEach(a=>{
      a.onclick=(e)=>{ e.preventDefault(); route(a.dataset.route); };
    });

    // === Inject Topbar + Bottom Bar SOLO se non esistono ===
    const appEl = document.querySelector('.app');
    const isPro = S.user && S.user.role === 'pro';

    if(appEl && !document.querySelector('.topbar')){
      appEl.insertAdjacentHTML('afterbegin',
        '<div class="topbar">' +
          '<button id="menuToggle" class="menu-btn">☰</button>' +
          '<div class="logo">✨ <b>gm_v3</b> ' + (isPro ? '<span class="badge-pro">PRO</span>' : '') + '</div>' +
        '</div>' +
        '<div id="scrim" class="scrim"></div>'
      );
    }

    if(appEl && !document.querySelector('.mobile-nav')){
      appEl.insertAdjacentHTML('beforeend',
        '<nav class="mobile-nav">' +
          '<a href="#" data-route="dashboard" class="active">📊<br><span>Dashboard</span></a>' +
          '<a href="#" data-route="chat">💬<br><span>Chat</span></a>' +
          '<a href="#" data-route="calendar">📅<br><span>Calendario</span></a>' +
          '<a href="#" data-route="account">👤<br><span>Account</span></a>' +
        '</nav>'
      );
    }

    // Handler bottom bar
    document.querySelectorAll('.mobile-nav a').forEach(a=>{
      a.onclick = (e)=>{ e.preventDefault(); route(a.dataset.route); };
    });

    // Toggle drawer + scrim
    const menuToggle = document.getElementById('menuToggle');
    const scrim = document.getElementById('scrim');
    if(menuToggle) menuToggle.onclick = ()=> document.body.classList.toggle('menu-open');
    if(scrim) scrim.onclick = ()=> document.body.classList.remove('menu-open');

    // Upload/azioni varie
    const drop = document.getElementById('drop');
    const file = document.getElementById('file');
    if(drop && file){
      drop.onclick=()=>file.click();
      drop.ondragover=e=>{e.preventDefault(); drop.style.borderColor='var(--accent)';};
      drop.ondragleave=()=>drop.style.borderColor='#374151';
      drop.ondrop=e=>{e.preventDefault(); file.files=e.dataTransfer.files; uploadFile();};
    }

    const uploadBtn = document.getElementById('uploadBtn');
    const askDocsBtn = document.getElementById('askDocsBtn');
    const askAIBtn = document.getElementById('askAIBtn');
    const addEv = document.getElementById('addEv');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const organizeDocsBtn = document.getElementById('organizeDocsBtn');
    const filterCategory = document.getElementById('filterCategory');

    if(uploadBtn) uploadBtn.onclick=uploadFile;
    if(askDocsBtn) askDocsBtn.onclick=askDocs;
    if(askAIBtn) askAIBtn.onclick=askAI;
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

// === FUNZIONI OPERATIVE (copiate identiche) ===
async function loadCategories(){
  const r = await api('api/categories.php?a=list');
  if(!r.success) return;
  
  S.categories = r.data;
  
  const uploadCategory = document.getElementById('uploadCategory');
  if(uploadCategory) {
    let opts = '<option value="">-- Seleziona una categoria --</option>';
    S.categories.forEach(c => {
      opts += '<option value="' + c.name + '">' + c.name + '</option>';
    });
    uploadCategory.innerHTML = opts;
  }
  
  const categoryDocs = document.getElementById('categoryDocs');
  if(categoryDocs) {
    let opts = '<option value="">-- Seleziona categoria --</option>';
    S.categories.forEach(c => {
      opts += '<option value="' + c.name + '">' + c.name + '</option>';
    });
    categoryDocs.innerHTML = opts;
  }
  
  const filterCategory = document.getElementById('filterCategory');
  if(filterCategory) {
    let opts = '<option value="">Tutte le categorie</option>';
    S.categories.forEach(c => {
      opts += '<option value="' + c.name + '">' + c.name + '</option>';
    });
    filterCategory.innerHTML = opts;
  }
  
  const categoriesList = document.getElementById('categoriesList');
  if(categoriesList) {
    if(S.categories.length === 0) {
      categoriesList.innerHTML = '<p style="color:var(--muted);font-size:12px;padding:8px">Nessuna categoria. Creane una qui sotto!</p>';
    } else {
      let html = '';
      S.categories.forEach(c => {
        html += '<span class="category-tag">🏷️ ' + c.name +
          '<button onclick="deleteCategory(' + c.id + ')" title="Elimina categoria">✕</button></span>';
      });
      categoriesList.innerHTML = html;
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
    alert('✓ ' + success + ' documento/i organizzato/i correttamente!');
  } else {
    alert('⚠ ' + success + ' documento/i organizzato/i, ' + errors + ' errore/i.');
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
  
  let filteredDocs = S.docs;
  if(S.filterCategory) {
    filteredDocs = S.docs.filter(d => d.category === S.filterCategory);
  }
  
  let html = '';
  filteredDocs.forEach(d => {
    html += '<tr><td>' + d.file_name + '</td>';
    
    if(isPro) {
      html += '<td class="category-select-cell">' +
        '<select class="doc-category-select" data-docid="' + d.id + '" data-current="' + d.category + '">';
      S.categories.forEach(c => {
        html += '<option value="' + c.name + '"' + (c.name === d.category ? ' selected' : '') + '>' + c.name + '</option>';
      });
      html += '</select></td>';
    }
    
    html += '<td>' + (d.size/(1024*1024)).toFixed(2) + ' MB</td>' +
      '<td>' + new Date(d.created_at).toLocaleString('it-IT') + '</td>' +
      '<td style="white-space:nowrap">' +
      '<a href="api/documents.php?a=download&id=' + d.id + '" class="btn small" style="margin-right:8px;text-decoration:none;display:inline-block">📥</a>';
    
    if (d.ocr_recommended) {
      html += '<button class="btn small" data-id="' + d.id + '" data-action="ocr" style="background:#f59e0b;margin-right:8px" title="OCR Consigliato">🔍 OCR</button>';
    } else {
      html += '<button class="btn small secondary" data-id="' + d.id + '" data-action="ocr" style="margin-right:8px" title="OCR disponibile">🔍</button>';
    }
    
    html += '<button class="btn del" data-id="' + d.id + '" data-action="delete">🗑️</button>' +
      '</td></tr>';
  });
  
  tb.innerHTML = html;
  
  tb.querySelectorAll('button[data-action="delete"]').forEach(b=>b.onclick=()=>delDoc(b.dataset.id));
  tb.querySelectorAll('button[data-action="ocr"]').forEach(b=>b.onclick=()=>doOCR(b.dataset.id));
  
  if(isPro) {
    tb.querySelectorAll('.doc-category-select').forEach(select => {
      select.onchange = async (e) => {
        const docid = e.target.dataset.docid;
        const oldCategory = e.target.dataset.current;
        const newCategory = e.target.value;
        
        if(oldCategory === newCategory) return;
        
        if(!confirm('Spostare il documento nella categoria "' + newCategory + '"?\n\nIl documento verrà spostato anche su DocAnalyzer.')) {
          e.target.value = oldCategory;
          return;
        }
        
        e.target.disabled = true;
        const originalHTML = e.target.innerHTML;
        e.target.innerHTML = '<option>Spostamento...</option>';
        
        const fd = new FormData();
        fd.append('id', docid);
        fd.append('category', newCategory);
        
        try {
          const r = await api('api/documents.php?a=change_category', fd);
          
          if(r.success) {
            e.target.dataset.current = newCategory;
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
  
  if(S.user.role === 'pro' && uploadCategory && !uploadCategory.value){
    alert('Seleziona una categoria prima di caricare il file');
    uploadCategory.focus();
    return;
  }
  
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
  
  const btn = document.querySelector('button[data-id="' + id + '"][data-action="delete"]');
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
      btn.innerHTML = '🗑️';
    }
  }
}

async function doOCR(id){
  const isPro = S.user && S.user.role === 'pro';
  
  let confirmMsg = 'Avviare OCR su questo documento?\n\n';
  
  if (!isPro) {
    confirmMsg += '⚠️ SEI FREE: Hai diritto a 1 SOLO OCR.\nDopo questo non potrai più usare OCR su altri documenti.\n\nPassa a Pro per OCR illimitato.\n\n';
  }
  
  confirmMsg += '💰 Costo: 1 credito DocAnalyzer per pagina del documento.';
  
  if(!confirm(confirmMsg)) return;
  
  const btn = document.querySelector('button[data-id="' + id + '"][data-action="ocr"]');
  if(btn){
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span>';
  }
  
  const fd = new FormData();
  fd.append('id', id);
  
  try {
    const r = await api('api/documents.php?a=ocr', fd);
    
    if(r.success) {
      alert('✓ ' + r.message);
      if(btn) {
        btn.style.background = '#10b981';
        btn.innerHTML = '✓';
        btn.title = 'OCR Avviato';
        setTimeout(() => btn.disabled = true, 500);
      }
    } else {
      alert('❌ ' + r.message);
      if(btn) {
        btn.disabled = false;
        btn.innerHTML = '🔍';
      }
    }
  } catch(e) {
    alert('Errore connessione: ' + e);
    if(btn) {
      btn.disabled = false;
      btn.innerHTML = '🔍';
    }
  }
}

async function askDocs(){
  const q = document.getElementById('qDocs');
  const category = document.getElementById('categoryDocs');
  const askBtn = document.getElementById('askDocsBtn');
  const adherence = document.getElementById('adherence');
  const showRefs = document.getElementById('showRefs');
  
  if(!q.value.trim()){
    alert('Inserisci una domanda');
    q.focus();
    return;
  }
  
  if(S.user.role === 'pro' && (!category.value || category.value === '')){
    alert('Seleziona una categoria prima di fare la domanda');
    category.focus();
    return;
  }
  
  askBtn.disabled = true;
  askBtn.innerHTML = 'Cerco nei documenti... <span class="loader"></span>';
  
  const fd = new FormData();
  fd.append('q', q.value);
  fd.append('category', category.value || '');
  fd.append('mode', 'docs');
  fd.append('adherence', adherence.value);
  fd.append('show_refs', showRefs.checked ? '1' : '0');
  
  try {
    const r = await api('api/chat.php', fd);
    
    if(r.success && r.source !== 'none'){
      addMessageToLog(r.answer, 'docs', q.value);
      
      q.value = '';
      S.stats.chatToday++;
      updateChatCounter();
      const qCount = document.getElementById('qCount');
      if(qCount) qCount.textContent = S.stats.chatToday;
    } else if(r.can_ask_ai) {
      alert('Non ho trovato informazioni nei tuoi documenti. Prova a chiedere a Gemini nella sezione qui sotto!');
    } else {
      alert(r.message || 'Errore');
    }
  } finally {
    askBtn.disabled = false;
    askBtn.innerHTML = '🔍 Chiedi ai documenti';
  }
}

async function askAI(){
  const q = document.getElementById('qAI');
  const askBtn = document.getElementById('askAIBtn');
  
  if(!q.value.trim() && !S.chatContext){
    alert('Inserisci una domanda');
    q.focus();
    return;
  }
  
  askBtn.disabled = true;
  askBtn.innerHTML = 'Gemini sta pensando... <span class="loader"></span>';
  
  const fd = new FormData();
  
  let finalQuestion = q.value;
  if(S.chatContext) {
    finalQuestion = 'Contesto: ' + S.chatContext + '\n\nDomanda: ' + (q.value || 'Continua con questo contesto');
  }
  
  fd.append('q', finalQuestion);
  fd.append('mode', 'ai');
  
  try {
    const r = await api('api/chat.php', fd);
    
    if(r.success){
      addMessageToLog(r.answer, 'ai', q.value || 'Continua contesto');
      
      q.value = '';
      removeContext();
      S.stats.chatToday++;
      updateChatCounter();
      const qCount = document.getElementById('qCount');
      if(qCount) qCount.textContent = S.stats.chatToday;
    } else {
      alert(r.message || 'Errore AI');
    }
  } finally {
    askBtn.disabled = false;
    askBtn.innerHTML = '🤖 Chiedi a Gemini';
  }
}

function addMessageToLog(answer, type, question) {
  const msgId = 'msg_' + Date.now();
  const voices = getItalianVoices();
  let voiceOptions = '';
  voices.forEach((v, i) => {
    voiceOptions += '<option value="' + i + '">' + v.name + ' (' + v.lang + ')</option>';
  });
  
  const log = document.getElementById('chatLog');
  const item = document.createElement('div');
  item.className = 'chat-message ' + type;
  item.dataset.msgid = msgId;
  
  const title = type === 'docs' ? '📄 Risposta dai documenti' : '🤖 Risposta AI Generica (Google Gemini)';
  const useContextBtn = type === 'docs' ? '<button class="btn small" onclick="useAsContext(\'' + msgId + '\')">📋 Usa come contesto</button>' : '';
  
  item.innerHTML = '<div style="font-weight:600;margin-bottom:8px">' + title + '</div>' +
    '<div class="message-text" style="white-space:pre-wrap">' + answer + '</div>' +
    '<div class="chat-controls">' +
    useContextBtn +
    '<button class="btn small icon copy-btn" onclick="copyToClipboard(\'' + msgId + '\')" title="Copia">📋</button>' +
    '<select class="voice-select" title="Voce">' + voiceOptions + '</select>' +
    '<select class="speed-select" title="Velocità">' +
    '<option value="0.75">0.75x</option>' +
    '<option value="1" selected>1x</option>' +
    '<option value="1.25">1.25x</option>' +
    '<option value="1.5">1.5x</option>' +
    '<option value="2">2x</option>' +
    '</select>' +
    '<button class="btn small icon play-btn" onclick="speakText(\'' + msgId + '\')" title="Leggi">▶️</button>' +
    '<button class="btn small icon stop-btn hidden" onclick="stopSpeaking(\'' + msgId + '\')" title="Stop">⏸️</button>' +
    '</div>';
  
  log.insertBefore(item, log.firstChild);
}

async function loadEvents(){
  const r = await api('api/calendar.php?a=list');
  if(!r.success) return;
  
  const tb = document.querySelector('#evTable tbody');
  if(tb){
    let html = '';
    r.data.forEach(e => {
      html += '<tr>' +
        '<td>' + new Date(e.start).toLocaleString('it-IT') + '</td>' +
        '<td>' + e.title + '</td>' +
        '<td><button class="btn del" data-id="' + e.id + '">Elimina</button></td>' +
        '</tr>';
    });
    tb.innerHTML = html;
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
  if(!confirm('⚠️ ATTENZIONE ⚠️\n\nVuoi eliminare il tuo account?\n\nQuesta azione eliminerà:\n- Tutti i tuoi documenti\n- Tutte le chat\n- Tutti gli eventi\n- Il tuo account\n\nQuesta azione è IRREVERSIBILE.')) return;
  
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

// Bootstrap identico
render();
if('serviceWorker' in navigator) navigator.serviceWorker.register('assets/service-worker.js');
