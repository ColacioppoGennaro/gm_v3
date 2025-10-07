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
