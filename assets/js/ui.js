// assets/js/ui.js - Versione Aggiornata con FullCalendar e Hash Routing

// === CHIAVI LOCALSTORAGE ===
const LS_USER_KEY = 'gmv3_user';
const LS_ROUTE_KEY = 'gmv3_route';

// === VIEW FUNCTIONS ===
function loginView() {
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

function registerView() {
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

function forgotView() {
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

// ===== NUOVA FUNZIONE appView() =====
function appView() {
    const isPro = S.user && S.user.role === 'pro';
    const maxDocs = isPro ? 200 : 5;
    const maxChat = isPro ? 200 : 20;
    const maxSize = isPro ? 150 : 50;

    let html = '<div class="app">' +
        '<aside>' +
        '<div class="logo">✨ <b>gm_v3</b> ' + (isPro ? '<span class="badge-pro">PRO</span>' : '') + '</div>' +
        '<div class="nav">' +
        // MODIFICATO: Link con HASH
        '<a href="#/dashboard" data-route="dashboard" class="active">📊 Dashboard</a>' +
        '<a href="#/chat" data-route="chat">💬 Chat AI</a>' +
        '<a href="#/calendar" data-route="calendar">📅 Calendario</a>' +
        '<a href="#/account" data-route="account">👤 Account</a>' +
        '</div></aside><main>' +

        // ===== DASHBOARD =====
        '<section data-page="dashboard">' +
        '<h1>Dashboard</h1>' +
        (!isPro ? '<div class="banner" id="upgradeBtn">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : '') +
        '<div class="cards stats">' +
        '<div class="card"><div class="stat-label">Documenti Archiviati</div><div class="stat-number"><span id="docCount">0</span> / ' + maxDocs + '</div></div>' +
        '<div class="card"><div class="stat-label">Domande AI Oggi</div><div class="stat-number"><span id="qCount">0</span> / ' + maxChat + '</div></div>' +
        '<div class="card"><div class="stat-label">Storage Usato</div><div class="stat-number"><span id="storageUsed">0</span> MB / ' + maxSize + ' MB</div></div>' +
        '</div>' +

        '<div class="card"><h3>📤 Carica Documento</h3>';

    if (isPro) {
        html += '<div style="background:#1f2937;padding:16px;border-radius:10px;margin-bottom:16px">' +
            '<h4 style="margin:0 0 12px 0;font-size:14px;color:var(--accent)">🏷️ Le tue categorie</h4>' +
            '<div id="categoriesList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;min-height:32px;align-items:center"></div>' +
            '<div style="display:flex;gap:8px">' +
            '<input id="newCategoryName" placeholder="Nome nuova categoria (es. Fatture)" style="flex:1"/>' +
            '<button class="btn" id="addCategoryBtn">+ Crea</button>' +
            '</div></div>' +
            '<div class="form-group">' +
            '<label>Categoria documento *</label>' +
            '<select id="uploadCategory" style="width:100%"><option value="">-- Seleziona una categoria --</option></select>' +
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

    if (isPro) {
        html += '<div class="filter-bar">' +
            '<label>Filtra per categoria:</label>' +
            '<select id="filterCategory"><option value="">Tutte le categorie</option></select>' +
            '<button class="btn secondary" id="organizeDocsBtn">🔧 Organizza Documenti</button>' +
            '</div>';
    }

    html += '<table id="docsTable"><thead><tr>' +
        '<th>Nome File</th>' + (isPro ? '<th>Categoria</th>' : '') +
        '<th>Dimensione</th><th>Data</th><th></th>' +
        '</tr></thead><tbody></tbody></table></div></section>' +

        // ===== CHAT =====
        '<section class="hidden" data-page="chat">' +
        '<h1>💬 Chat AI</h1>' +
        (!isPro ? '<div class="ads">[Slot Pubblicitario - Upgrade a Pro per rimuoverlo]</div>' : '') +
        '<div class="card"><h3>📄 Chiedi ai tuoi documenti</h3>' +
        '<div class="settings-row settings-row--compact">' +
        '<label>Aderenza:</label>' +
        '<select id="adherence" style="width:auto;padding:6px 10px">' +
        '<option value="strict">Strettamente documenti</option>' +
        '<option value="high">Alta aderenza</option>' +
        '<option value="balanced" selected>Bilanciata</option>' +
        '<option value="low">Bassa aderenza</option>' +
        '<option value="free">Libera interpretazione</option>' +
        '</select>' +
        '<label style="margin-left:8px;display:flex;align-items:center;gap:6px">' +
        '<input type="checkbox" id="showRefs" checked style="width:auto;margin:0"/>' +
        '<span style="font-size:13px">Mostra riferimenti pagine</span>' +
        '</label>' +
        '</div>' +
        '<div class="compose" style="margin-top:12px">' +
        '<input id="qDocs" placeholder="Es: Quando scade l\'IMU?" />' +
        '<button class="btn icon-only" id="askDocsBtn" title="Invia">➤</button>' +
        '</div>' +
        (isPro ?
            '<div class="chat-docs-actions"><select id="categoryDocs"><option value="">-- Seleziona categoria --</option></select></div>' :
            '<div class="chat-docs-actions">' +
            '<div class="pill">(Free: tutti)</div>' +
            '<select id="categoryDocs" class="hidden"><option value="">(Free: tutti)</option></select>' +
            '</div>'
        ) +
        '<div style="margin-top:8px;font-size:12px;color:var(--muted)">Domande oggi: <b id="qCountChat">0</b>/' + maxChat + '</div>' +
        '</div>' +
        (!isPro ? '<div class="banner" id="upgradeBtn2">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : '') +
        '<div class="card"><h3>🤖 Chat AI Generica (Google Gemini)</h3>' +
        '<p style="color:var(--muted);font-size:13px;margin-bottom:12px">Chiedi qualsiasi cosa all\'AI generica, anche senza documenti</p>' +
        '<div id="contextBox" class="hidden"></div>' +
        '<div class="compose">' +
        '<input id="qAI" placeholder="Es: Spiegami come funziona la fotosintesi..." />' +
        '<button class="btn success icon-only" id="askAIBtn" title="Invia">➤</button>' +
        '</div>' +
        '</div>' +
        '<div class="card"><h3>💬 Conversazione</h3><div id="chatLog" style="min-height:200px"></div></div>' +
        '</section>' +

        // ===== CALENDARIO (MODIFICATO) =====
        // Ora è un contenitore vuoto che verrà riempito da calendarView()
        '<section class="hidden" data-page="calendar"></section>' +

        // ===== ACCOUNT =====
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
        '</div></div>' +

        (!isPro ?
            '<div class="card"><h3>⚡ Upgrade a Pro</h3><p style="color:var(--muted);margin-bottom:16px">Sblocca funzionalità avanzate e limiti aumentati</p>' +
            '<div class="form-group"><label>Codice Promozionale</label><input type="text" id="promoCodePage" placeholder="Inserisci codice"/></div>' +
            '<div id="upgradePageError" class="error hidden"></div><div id="upgradePageSuccess" class="success hidden"></div>' +
            '<button class="btn" id="activateProPage">Attiva Pro</button></div>' :
            '<div class="card"><h3>⬇️ Downgrade a Free</h3><p style="color:var(--muted);margin-bottom:16px">Torna al piano gratuito. <b>ATTENZIONE:</b> Devi avere massimo 5 documenti.</p>' +
            '<div id="downgradeError" class="error hidden"></div><button class="btn warn" id="downgradeBtn">Downgrade a Free</button></div>'
        ) +

        '<div class="card"><h3>⚙️ Impostazioni</h3>' +
        '<button class="btn secondary" id="logoutBtn" style="width:100%;margin-bottom:12px">🚪 Logout</button>' +
        '<button class="btn del" id="deleteAccountBtn" style="width:100%">🗑️ Elimina Account</button>' +
        '</div></section></main></div>';

    return html;
}

function upgradeModal() {
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

function organizeDocsModal() {
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

// ===================================================
// FUNZIONE UNICA PER LE CHIAMATE API
// ===================================================
async function api(url, method = 'GET', body = null) {
    const opts = {
        method,
        credentials: 'same-origin', // << obbligatorio per mandare cookie
        headers: {}
    };

    if (body) {
        if (body instanceof FormData) {
            opts.body = body;
            // Per FormData, il browser imposta Content-Type automaticamente con il boundary corretto.
            // Non impostarlo manualmente.
        } else {
            // Altrimenti, si presume sia JSON
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
    }

    try {
        const res = await fetch(url, opts);

        if (res.status === 401) {
            console.warn('⚠️ Non sei loggato, redirect a login...');
            S.view = 'login';
            S.user = null;
            localStorage.removeItem(LS_USER_KEY);
            localStorage.removeItem(LS_ROUTE_KEY);
            render();
            // Non serve location.hash, render() gestisce la vista
            throw new Error('AUTH');
        }

        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            const data = await res.json();
            if (!res.ok) {
                // Se la risposta JSON contiene un messaggio di errore, mostralo
                alert(data.message || 'Errore API');
            }
            return data;
        } else {
            const textData = await res.text();
             if (!res.ok) {
                alert(textData || 'Errore API');
            }
            return textData;
        }

    } catch (e) {
        if (e.message !== 'AUTH') {
            console.error('Errore di rete o fetch fallita', e);
            alert('Errore di connessione. Controlla la tua rete.');
        }
        // Rilancia l'errore per interrompere l'esecuzione nella funzione chiamante
        throw e;
    }
}


// === RENDER/ROUTING/BIND ===
function render() {
    const views = {
        login: loginView,
        register: registerView,
        forgot: forgotView,
        app: appView
    };

    const root = document.getElementById('app') || document.body;
    if (!root) {
        console.error('Elemento root #app non trovato!');
        return;
    }

    root.innerHTML = views[S.view]();
    bind();
}


function showPage(pageName) {
    document.querySelectorAll('.nav a, .mobile-nav a').forEach(a =>
        a.classList.toggle('active', a.dataset.route === pageName)
    );

    document.querySelectorAll('[data-page]').forEach(p => p.classList.add('hidden'));
    const page = document.querySelector(`[data-page="${pageName}"]`);
    if (page) page.classList.remove('hidden');

    document.body.classList.remove('menu-open');

    if (pageName === 'dashboard') {
        loadDocs();
        if (S.user && S.user.role === 'pro') loadCategories();
    } else if (pageName === 'calendar') {
        if (window.FullCalendar) {
            calendarView();
        } else {
            console.warn('FullCalendar non caricato: torno alla dashboard.');
            alert('Calendario non disponibile. Riprova tra poco.');
            location.hash = '#/dashboard';
        }
    } else if (pageName === 'chat') {
        updateChatCounter();
        if (S.user && S.user.role === 'pro') loadCategories();
    } else if (pageName === 'account') {
        loadAccountInfo();
    }

    localStorage.setItem(LS_ROUTE_KEY, pageName);
}


function bind() {
    if (S.view === 'login') {
        document.getElementById('loginBtn')?.addEventListener('click', doLogin);
        document.getElementById('goRegister')?.addEventListener('click', () => { S.view = 'register'; render(); });
        document.getElementById('goForgot')?.addEventListener('click', () => { S.view = 'forgot'; render(); });
        document.getElementById('password')?.addEventListener('keypress', e => { if (e.key === 'Enter') doLogin(); });
    }

    if (S.view === 'register') {
        document.getElementById('registerBtn')?.addEventListener('click', doRegister);
        document.getElementById('backToLogin')?.addEventListener('click', () => { S.view = 'login'; render(); });
    }

    if (S.view === 'forgot') {
        document.getElementById('forgotBtn')?.addEventListener('click', doForgot);
        document.getElementById('backToLogin2')?.addEventListener('click', () => { S.view = 'login'; render(); });
    }

    if (S.view === 'app') {
        document.getElementById('logoutBtn')?.addEventListener('click', async () => {
            try {
                await api('api/auth.php?a=logout', 'POST');
            } catch (e) {
                console.error("Logout fallito ma procedo con la pulizia del client", e);
            } finally {
                S.user = null;
                S.view = 'login';
                localStorage.removeItem(LS_USER_KEY);
                localStorage.removeItem(LS_ROUTE_KEY);
                render();
            }
        });

        ['upgradeBtn', 'upgradeBtn2', 'upgradeBtn3'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', showUpgradeModal);
        });

        document.getElementById('activateProPage')?.addEventListener('click', activateProFromPage);
        document.getElementById('downgradeBtn')?.addEventListener('click', doDowngrade);
        document.getElementById('deleteAccountBtn')?.addEventListener('click', doDeleteAccount);

        const appEl = document.querySelector('.app');
        if (appEl && !document.querySelector('.topbar')) {
            const isPro = S.user && S.user.role === 'pro';
            appEl.insertAdjacentHTML('afterbegin',
                `<div class="topbar">
                    <button id="menuToggle" class="menu-btn">☰</button>
                    <div class="logo">✨ <b>gm_v3</b> ${isPro ? '<span class="badge-pro">PRO</span>' : ''}</div>
                </div>
                <div id="scrim" class="scrim"></div>`
            );
        }

        if (appEl && !document.querySelector('.mobile-nav')) {
            appEl.insertAdjacentHTML('beforeend',
                `<nav class="mobile-nav">
                    <a href="#/dashboard" data-route="dashboard" class="active">📊<br><span>Dashboard</span></a>
                    <a href="#/chat" data-route="chat">💬<br><span>Chat</span></a>
                    <a href="#/calendar" data-route="calendar">📅<br><span>Calendario</span></a>
                    <a href="#/account" data-route="account">👤<br><span>Account</span></a>
                </nav>`
            );
        }

        document.getElementById('menuToggle')?.addEventListener('click', () => document.body.classList.toggle('menu-open'));
        document.getElementById('scrim')?.addEventListener('click', () => document.body.classList.remove('menu-open'));
        
        const drop = document.getElementById('drop');
        const fileInput = document.getElementById('file');
        if (drop && fileInput) {
            drop.onclick = () => fileInput.click();
            drop.ondragover = e => { e.preventDefault(); drop.style.borderColor = 'var(--accent)'; };
            drop.ondragleave = () => drop.style.borderColor = '#374151';
            drop.ondrop = e => { e.preventDefault(); fileInput.files = e.dataTransfer.files; uploadFile(); };
        }

        document.getElementById('uploadBtn')?.addEventListener('click', uploadFile);
        document.getElementById('askDocsBtn')?.addEventListener('click', askDocs);
        document.getElementById('askAIBtn')?.addEventListener('click', askAI);
        document.getElementById('addCategoryBtn')?.addEventListener('click', createCategory);
        document.getElementById('organizeDocsBtn')?.addEventListener('click', showOrganizeModal);
        document.getElementById('filterCategory')?.addEventListener('change', (e) => { S.filterCategory = e.target.value; renderDocsTable(); });

        loadDocs();
        loadStats();
        if (S.user.role === 'pro') {
            loadCategories();
        }
    }
}


async function calendarView() {
    const pageContainer = document.querySelector('[data-page="calendar"]');
    if (!pageContainer || pageContainer.querySelector('#cal')) return;

    const isPro = S.user && S.user.role === 'pro';
    pageContainer.innerHTML = `
        <h1>📅 Calendario</h1>
        ${!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
        <div class="card">
            <div class="toolbar" style="padding: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                <button id="btnPush" class="btn">🔔 Abilita notifiche</button>
                <button id="btnNew" class="btn">＋ Nuovo Evento</button>
            </div>
            <div id="cal" style="height: calc(100vh - 250px); padding: 10px;"></div>
        </div>
    `;

    document.getElementById('upgradeBtn3')?.addEventListener('click', showUpgradeModal);

    const calEl = document.getElementById('cal');
    const calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        locale: 'it',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        buttonText: { today: 'Oggi', month: 'Mese', week: 'Settimana', day: 'Giorno' },
        selectable: true,
        editable: true,
        
        select: async (info) => {
            const title = prompt('Titolo evento:');
            if (!title) return;
            try {
                await api('api/calendar.php', 'POST', {
                    title,
                    start: info.startStr,
                    end: info.endStr,
                    allDay: info.allDay,
                    reminders: [2880]
                });
                calendar.refetchEvents();
            } catch (e) { console.error('Creazione evento fallita', e); }
        },
        
        eventDrop: async (info) => {
            try {
                await api(`api/calendar.php?id=${info.event.id}`, 'PATCH', {
                    start: info.event.start?.toISOString(),
                    end: info.event.end?.toISOString(),
                    allDay: info.event.allDay
                });
            } catch (e) { console.error('Spostamento evento fallito', e); info.revert(); }
        },
        
        eventResize: async (info) => {
             try {
                await api(`api/calendar.php?id=${info.event.id}`, 'PATCH', {
                    end: info.event.end?.toISOString()
                });
            } catch (e) { console.error('Ridimensionamento evento fallito', e); info.revert(); }
        },
        
        eventClick: async (info) => {
            if (confirm(`Vuoi eliminare l'evento "${info.event.title}"?`)) {
                 try {
                    await api(`api/calendar.php?id=${info.event.id}`, 'DELETE');
                    calendar.refetchEvents();
                } catch (e) { console.error('Eliminazione evento fallita', e); }
            }
        },
        events: { url: 'api/calendar.php', method: 'GET' }
    });
    calendar.render();

    document.getElementById('btnPush')?.addEventListener('click', () => window.gm_enablePush && window.gm_enablePush());
    document.getElementById('btnNew')?.addEventListener('click', async () => {
        const title = prompt('Titolo evento:');
        if (!title) return;
        const start = new Date();
        const end = new Date(start.getTime() + 60 * 60 * 1000);
        try {
            await api('api/calendar.php', 'POST', {
                title,
                start: start.toISOString(),
                end: end.toISOString(),
                reminders: []
            });
            calendar.refetchEvents();
        } catch(e) { console.error("Creazione nuovo evento fallita", e); }
    });
}
window.calendarView = calendarView;
window.renderFullCalendar = calendarView;


async function loadCategories() {
    try {
        const r = await api('api/categories.php?a=list');
        if (!r.success) return;
        S.categories = r.data;

        const updateSelect = (selectId, defaultOption) => {
            const select = document.getElementById(selectId);
            if (select) {
                let opts = defaultOption;
                S.categories.forEach(c => {
                    opts += `<option value="${c.name}">${c.name}</option>`;
                });
                select.innerHTML = opts;
            }
        };

        updateSelect('uploadCategory', '<option value="">-- Seleziona una categoria --</option>');
        updateSelect('categoryDocs', '<option value="">-- Seleziona categoria --</option>');
        updateSelect('filterCategory', '<option value="">Tutte le categorie</option>');

        const categoriesList = document.getElementById('categoriesList');
        if (categoriesList) {
            if (S.categories.length === 0) {
                categoriesList.innerHTML = '<p style="color:var(--muted);font-size:12px;padding:8px">Nessuna categoria. Creane una qui sotto!</p>';
            } else {
                categoriesList.innerHTML = S.categories.map(c =>
                    `<span class="category-tag">🏷️ ${c.name}
                        <button onclick="deleteCategory(${c.id})" title="Elimina categoria">✕</button>
                    </span>`
                ).join('');
            }
        }
    } catch(e) {
        console.error("Caricamento categorie fallito", e);
    }
}

async function createCategory(name, inputEl, btnEl) {
    const categoryName = name.trim();
    if (!categoryName) {
        alert('Inserisci un nome per la categoria');
        inputEl.focus();
        return;
    }

    btnEl.disabled = true;
    const originalText = btnEl.innerHTML;
    btnEl.innerHTML = '<span class="loader"></span>';

    const fd = new FormData();
    fd.append('name', categoryName);

    try {
        const r = await api('api/categories.php?a=create', fd);
        if (r.success) {
            inputEl.value = '';
            await loadCategories();
            return true;
        }
    } catch (e) {
        console.error('Creazione categoria fallita', e);
    } finally {
        btnEl.disabled = false;
        btnEl.innerHTML = originalText;
    }
    return false;
}

async function createCategoryFromDashboard() {
    const input = document.getElementById('newCategoryName');
    const btn = document.getElementById('addCategoryBtn');
    createCategory(input.value, input, btn);
}

async function createCategoryInModal() {
    const input = document.getElementById('modalNewCategoryName');
    const btn = document.getElementById('modalAddCategoryBtn');
    const success = await createCategory(input.value, input, btn);
    if (success) {
        document.getElementById('organizeModal')?.remove();
        showOrganizeModal();
    }
}

async function deleteCategory(id) {
    if (!confirm('Eliminare questa categoria?\n\nATTENZIONE: Non puoi eliminare categorie che contengono documenti.')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const r = await api('api/categories.php?a=delete', fd);
        if (r.success) {
            loadCategories();
            loadDocs();
        }
    } catch(e) { console.error("Eliminazione categoria fallita", e); }
}
window.deleteCategory = deleteCategory;

function showUpgradeModal() {
    if (document.getElementById('upgradeModal')) return;
    document.body.insertAdjacentHTML('beforeend', upgradeModal());
    document.getElementById('closeModal').onclick = () => document.getElementById('upgradeModal').remove();
    document.getElementById('activateBtn').onclick = activatePro;
}

function showOrganizeModal() {
    if (document.getElementById('organizeModal')) return;
    document.body.insertAdjacentHTML('beforeend', organizeDocsModal());
    document.getElementById('saveOrganizeBtn')?.addEventListener('click', saveOrganization);
    document.getElementById('modalAddCategoryBtn')?.addEventListener('click', createCategoryInModal);
}

async function saveOrganization() {
    const updates = Array.from(document.querySelectorAll('.organize-select'))
        .map(select => ({ docid: parseInt(select.dataset.docid), category: select.value }))
        .filter(u => u.category);

    if (updates.length === 0) return alert('Seleziona almeno una categoria.');

    const btn = document.getElementById('saveOrganizeBtn');
    btn.disabled = true;
    btn.innerHTML = 'Salvataggio... <span class="loader"></span>';

    const results = await Promise.all(updates.map(u => {
        const fd = new FormData();
        fd.append('id', u.docid);
        fd.append('category', u.category);
        return api('api/documents.php?a=change_category', fd).catch(() => ({ success: false }));
    }));

    const successCount = results.filter(r => r.success).length;
    const errorCount = updates.length - successCount;

    document.getElementById('organizeModal')?.remove();
    alert(errorCount === 0
        ? `✓ ${successCount} documento/i organizzato/i!`
        : `⚠ ${successCount} ok, ${errorCount} errori.`);

    loadDocs();
}

async function activatePro(code, errEl, successEl, btnEl) {
    const promoCode = code.trim();
    errEl.classList.add('hidden');
    successEl.classList.add('hidden');

    if (!promoCode) {
        errEl.textContent = 'Inserisci un codice';
        errEl.classList.remove('hidden');
        return;
    }
    
    if(btnEl) {
      btnEl.disabled = true;
      const originalText = btnEl.innerHTML;
      btnEl.innerHTML = 'Attivazione... <span class="loader"></span>';
    }

    const fd = new FormData();
    fd.append('code', promoCode);

    try {
        const r = await api('api/upgrade.php', fd);
        if (r.success) {
            successEl.textContent = '✓ Piano Pro attivato! Ricarico...';
            successEl.classList.remove('hidden');
            setTimeout(() => {
                S.user.role = 'pro';
                localStorage.setItem(LS_USER_KEY, JSON.stringify(S.user));
                document.getElementById('upgradeModal')?.remove();
                render();
                setTimeout(() => {
                    const masterDocs = S.docs.filter(d => d.category === 'master');
                    if (masterDocs.length > 0) showOrganizeModal();
                }, 500);
            }, 1500);
        } else {
             errEl.textContent = r.message || 'Codice non valido';
             errEl.classList.remove('hidden');
             if(btnEl) {
               btnEl.disabled = false;
               btnEl.innerHTML = originalText;
             }
        }
    } catch(e) {
        console.error("Attivazione Pro fallita", e);
        errEl.textContent = 'Errore di connessione.';
        errEl.classList.remove('hidden');
        if(btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = originalText;
        }
    }
}

function activateProFromModal(){
    const code = document.getElementById('promoCode').value;
    const err = document.getElementById('upgradeError');
    const success = document.getElementById('upgradeSuccess');
    const btn = document.getElementById('activateBtn');
    activatePro(code, err, success, btn);
}

function activateProFromPage() {
    const code = document.getElementById('promoCodePage').value;
    const err = document.getElementById('upgradePageError');
    const success = document.getElementById('upgradePageSuccess');
    const btn = document.getElementById('activateProPage');
    activatePro(code, err, success, btn);
}

async function loadStats() {
    try {
        const r = await api('api/stats.php');
        if (r.success) {
            S.stats = r.data;
            document.getElementById('qCount').textContent = S.stats.chatToday || 0;
            document.getElementById('qCountChat').textContent = S.stats.chatToday || 0;
            document.getElementById('storageUsed').textContent = (S.stats.totalSize / (1024 * 1024)).toFixed(1);
        }
    } catch(e){
        console.error("Caricamento statistiche fallito", e);
    }
}

function updateChatCounter() {
    document.getElementById('qCountChat').textContent = S.stats.chatToday || 0;
}

async function doLogin() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const err = document.getElementById('loginError');
    err.classList.add('hidden');

    if (!email || !password) {
        err.textContent = 'Inserisci email e password';
        err.classList.remove('hidden');
        return;
    }

    const fd = new FormData();
    fd.append('email', email);
    fd.append('password', password);

    try {
        const r = await api('api/auth.php?a=login', fd);
        if (r.success) {
            S.user = { email, role: r.role || 'free' };
            localStorage.setItem(LS_USER_KEY, JSON.stringify(S.user));
            S.view = 'app';
            render();
            // Dopo il render, il router gestirà la visualizzazione della pagina corretta
            setTimeout(() => route(), 0);
        } else {
            err.textContent = r.message || 'Errore durante il login';
            err.classList.remove('hidden');
        }
    } catch(e) {
         err.textContent = 'Errore di connessione durante il login.';
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

    if (!email || !pass || !passConfirm) return err.textContent = 'Compila tutti i campi', err.classList.remove('hidden');
    if (pass !== passConfirm) return err.textContent = 'Le password non coincidono', err.classList.remove('hidden');
    if (pass.length < 6) return err.textContent = 'La password deve essere di almeno 6 caratteri', err.classList.remove('hidden');

    const fd = new FormData();
    fd.append('email', email);
    fd.append('password', pass);

    try {
        const r = await api('api/auth.php?a=register', fd);
        if (r.success) {
            success.textContent = '✓ Registrazione completata! Ora puoi accedere.';
            success.classList.remove('hidden');
            setTimeout(() => { S.view = 'login'; render(); }, 2000);
        } else {
            err.textContent = r.message || 'Errore durante la registrazione';
            err.classList.remove('hidden');
        }
    } catch(e) {
        err.textContent = 'Errore di connessione.';
        err.classList.remove('hidden');
    }
}

async function doForgot() {
    // Funzione placeholder, non implementata lato server nel codice fornito
    const email = document.getElementById('forgotEmail').value;
    const err = document.getElementById('forgotError');
    const success = document.getElementById('forgotSuccess');
    err.classList.add('hidden');
    success.classList.add('hidden');

    if (!email) {
        err.textContent = 'Inserisci la tua email';
        err.classList.remove('hidden');
        return;
    }

    success.textContent = '✓ Se l\'email esiste, riceverai un link per reimpostare la password.';
    success.classList.remove('hidden');
}

async function loadDocs() {
    try {
        const r = await api('api/documents.php?a=list');
        if (!r.success) return;
        S.docs = r.data;
        document.getElementById('docCount').textContent = S.docs.length;
        renderDocsTable();
        loadStats();
    } catch(e) { console.error("Caricamento documenti fallito", e); }
}

function renderDocsTable() {
    const tb = document.querySelector('#docsTable tbody');
    if (!tb) return;

    const isPro = S.user && S.user.role === 'pro';
    const filteredDocs = S.filterCategory ? S.docs.filter(d => d.category === S.filterCategory) : S.docs;

    tb.innerHTML = filteredDocs.map(d => {
        let categorySelect = '';
        if (isPro) {
            const options = S.categories.map(c => `<option value="${c.name}" ${c.name === d.category ? 'selected' : ''}>${c.name}</option>`).join('');
            categorySelect = `<td class="category-select-cell">
                <select class="doc-category-select" data-docid="${d.id}" data-current="${d.category}">${options}</select>
            </td>`;
        }

        const ocrButton = d.ocr_recommended
            ? `<button class="btn small" data-id="${d.id}" data-action="ocr" style="background:#f59e0b;margin-right:8px" title="OCR Consigliato">🔍 OCR</button>`
            : `<button class="btn small secondary" data-id="${d.id}" data-action="ocr" style="margin-right:8px" title="OCR disponibile">🔍</button>`;

        return `<tr>
            <td>${d.file_name}</td>
            ${isPro ? categorySelect : ''}
            <td>${(d.size / (1024 * 1024)).toFixed(2)} MB</td>
            <td>${new Date(d.created_at).toLocaleString('it-IT')}</td>
            <td style="white-space:nowrap">
                <a href="api/documents.php?a=download&id=${d.id}" class="btn small" style="margin-right:8px;text-decoration:none;display:inline-block">📥</a>
                ${ocrButton}
                <button class="btn del" data-id="${d.id}" data-action="delete">🗑️</button>
            </td>
        </tr>`;
    }).join('');

    tb.querySelectorAll('button[data-action="delete"]').forEach(b => b.onclick = () => delDoc(b.dataset.id));
    tb.querySelectorAll('button[data-action="ocr"]').forEach(b => b.onclick = () => doOCR(b.dataset.id));

    if (isPro) {
        tb.querySelectorAll('.doc-category-select').forEach(select => {
            select.onchange = (e) => changeDocCategory(e.target);
        });
    }
}

async function changeDocCategory(select) {
    const docid = select.dataset.docid;
    const oldCategory = select.dataset.current;
    const newCategory = select.value;

    if (oldCategory === newCategory) return;
    if (!confirm(`Spostare il documento in "${newCategory}"?`)) {
        select.value = oldCategory;
        return;
    }

    select.disabled = true;
    const fd = new FormData();
    fd.append('id', docid);
    fd.append('category', newCategory);

    try {
        const r = await api('api/documents.php?a=change_category', fd);
        if (r.success) {
            await loadDocs(); // Ricarica tutto per coerenza
        } else {
            select.value = oldCategory; // Ripristina in caso di errore
        }
    } catch(e) {
        select.value = oldCategory;
    } finally {
        // Rirenderdocs table riabiliterà il select
    }
}

async function uploadFile() {
    const fileInput = document.getElementById('file');
    const uploadBtn = document.getElementById('uploadBtn');
    const drop = document.getElementById('drop');
    const uploadCategory = document.getElementById('uploadCategory');
    const f = fileInput.files[0];

    if (!f) return alert('Seleziona un file');
    if (S.user.role === 'pro' && uploadCategory && !uploadCategory.value) {
        return alert('Seleziona una categoria prima di caricare il file');
    }

    uploadBtn.disabled = true;
    const originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = 'Caricamento... <span class="loader"></span>';
    if(drop) drop.style.pointerEvents = 'none';

    const fd = new FormData();
    fd.append('file', f);
    if (uploadCategory && uploadCategory.value) {
        fd.append('category', uploadCategory.value);
    }

    try {
        const r = await api('api/documents.php?a=upload', fd);
        if (r.success) {
            loadDocs();
            fileInput.value = '';
            if (uploadCategory) uploadCategory.value = '';
        }
    } catch(e) {
        console.error("Upload fallito", e);
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
        if(drop) drop.style.pointerEvents = 'auto';
    }
}

async function delDoc(id) {
    if (!confirm('Eliminare questo documento?')) return;
    const btn = document.querySelector(`button[data-id="${id}"][data-action="delete"]`);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="loader"></span>';
    }

    const fd = new FormData();
    fd.append('id', id);
    try {
        await api('api/documents.php?a=delete', fd);
        loadDocs();
    } catch(e) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '🗑️';
        }
    }
}

async function doOCR(id) {
    const isPro = S.user && S.user.role === 'pro';
    let confirmMsg = 'Avviare OCR su questo documento?\n\n';
    if (!isPro) confirmMsg += '⚠️ SEI FREE: Hai diritto a 1 SOLO OCR.\n';
    confirmMsg += '💰 Costo: 1 credito DocAnalyzer per pagina del documento.';
    if (!confirm(confirmMsg)) return;

    const btn = document.querySelector(`button[data-id="${id}"][data-action="ocr"]`);
    if(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="loader"></span>';
    }

    const fd = new FormData();
    fd.append('id', id);
    try {
        const r = await api('api/documents.php?a=ocr', fd);
        if (r.success && btn) {
            btn.style.background = '#10b981';
            btn.innerHTML = '✓';
            btn.title = 'OCR Avviato';
        } else if (btn) {
             btn.disabled = false;
             btn.innerHTML = '🔍';
        }
    } catch(e) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '🔍';
        }
    }
}

async function ask(endpoint, formData, btn, input) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span>';
    try {
        const r = await api(endpoint, formData);
        if (r.success && r.source !== 'none') {
            addMessageToLog(r.answer, formData.get('mode'), formData.get('q'));
            if(input) input.value = '';
            S.stats.chatToday++;
            updateChatCounter();
            document.getElementById('qCount').textContent = S.stats.chatToday;
        } else if (r.can_ask_ai) {
            alert('Non ho trovato informazioni nei tuoi documenti. Prova a chiedere a Gemini qui sotto!');
        }
    } catch(e) {
        console.error("Chiamata chat fallita", e);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '➤';
    }
}

function askDocs() {
    const q = document.getElementById('qDocs');
    const category = document.getElementById('categoryDocs');
    if (!q.value.trim()) return alert('Inserisci una domanda');
    if (S.user.role === 'pro' && !category.value) return alert('Seleziona una categoria');

    const fd = new FormData();
    fd.append('q', q.value);
    fd.append('category', category.value || '');
    fd.append('mode', 'docs');
    fd.append('adherence', document.getElementById('adherence').value);
    fd.append('show_refs', document.getElementById('showRefs').checked ? '1' : '0');
    ask('api/chat.php', fd, document.getElementById('askDocsBtn'), q);
}

function askAI() {
    const q = document.getElementById('qAI');
    if (!q.value.trim() && !S.chatContext) return alert('Inserisci una domanda');

    let finalQuestion = q.value;
    if (S.chatContext) {
        finalQuestion = `Contesto: ${S.chatContext}\n\nDomanda: ${q.value || 'Continua'}`;
    }

    const fd = new FormData();
    fd.append('q', finalQuestion);
    fd.append('mode', 'ai');
    ask('api/chat.php', fd, document.getElementById('askAIBtn'), q);
    removeContext();
}

function addMessageToLog(answer, type, question) {
    const msgId = 'msg_' + Date.now();
    const log = document.getElementById('chatLog');
    if(!log) return;
    
    const voices = getItalianVoices();
    const voiceOptions = voices.map((v, i) => `<option value="${i}">${v.name} (${v.lang})</option>`).join('');

    const item = document.createElement('div');
    item.className = `chat-message ${type}`;
    item.dataset.msgid = msgId;

    const title = type === 'docs' ? '📄 Risposta dai documenti' : '🤖 Risposta AI Generica (Google Gemini)';
    const useContextBtn = type === 'docs' ? `<button class="btn small" onclick="useAsContext('${msgId}')">📋 Usa come contesto</button>` : '';

    item.innerHTML = `<div style="font-weight:600;margin-bottom:8px">${title}</div>
        <div class="message-text" style="white-space:pre-wrap">${answer}</div>
        <div class="chat-controls">
            ${useContextBtn}
            <button class="btn small icon copy-btn" onclick="copyToClipboard('${msgId}')" title="Copia">📋</button>
            <select class="voice-select" title="Voce">${voiceOptions}</select>
            <select class="speed-select" title="Velocità">
                <option value="0.75">0.75x</option><option value="1" selected>1x</option><option value="1.25">1.25x</option>
            </select>
            <button class="btn small icon play-btn" onclick="speakText('${msgId}')" title="Leggi">▶️</button>
            <button class="btn small icon stop-btn hidden" onclick="stopSpeaking('${msgId}')" title="Stop">⏸️</button>
        </div>`;
    log.insertBefore(item, log.firstChild);
}

async function loadAccountInfo() {
    try {
        const r = await api('api/account.php?a=info');
        if (!r.success) return;

        document.getElementById('accountEmail').textContent = r.account.email;
        document.getElementById('accountSince').textContent = new Date(r.account.created_at).toLocaleDateString('it-IT');
        document.getElementById('usageDocs').textContent = r.usage.documents;
        document.getElementById('usageStorage').textContent = (r.usage.storage_bytes / (1024 * 1024)).toFixed(1);
        document.getElementById('usageChat').textContent = r.usage.chat_today;
        if (document.getElementById('usageCategories')) {
            document.getElementById('usageCategories').textContent = r.usage.categories;
        }
    } catch(e) { console.error("Caricamento info account fallito", e); }
}

async function doDowngrade() {
    if (!confirm('Sei sicuro di voler passare al piano Free?\n\nDevi avere massimo 5 documenti.')) return;

    const btn = document.getElementById('downgradeBtn');
    const err = document.getElementById('downgradeError');
    err.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = 'Downgrade... <span class="loader"></span>';

    try {
        const r = await api('api/account.php?a=downgrade', 'POST');
        if (r.success) {
            alert('✓ ' + r.message);
            S.user.role = 'free';
            localStorage.setItem(LS_USER_KEY, JSON.stringify(S.user));
            render();
        } else {
            err.textContent = r.message || 'Errore';
            err.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = 'Downgrade a Free';
        }
    } catch(e) {
         err.textContent = 'Errore di connessione.';
         err.classList.remove('hidden');
         btn.disabled = false;
         btn.innerHTML = 'Downgrade a Free';
    }
}

async function doDeleteAccount() {
    if (!confirm('⚠️ ATTENZIONE ⚠️\n\nVuoi eliminare il tuo account?\nQuesta azione è IRREVERSIBILE.')) return;
    if (!confirm('Confermi l\'eliminazione dell\'account?')) return;

    const btn = document.getElementById('deleteAccountBtn');
    btn.disabled = true;
    btn.innerHTML = 'Eliminazione... <span class="loader"></span>';

    try {
        await api('api/account.php?a=delete', 'POST');
        alert('Account eliminato. Arrivederci.');
        S.user = null; S.view = 'login';
        localStorage.clear();
        render();
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '🗑️ Elimina Account';
    }
}

// === BOOTSTRAP FUNCTION ===
async function bootstrap() {
    const cachedUser = localStorage.getItem(LS_USER_KEY);
    if (cachedUser) {
        try {
            S.user = JSON.parse(cachedUser);
            S.view = 'app';
        } catch (_) { S.user = null; S.view = 'login'; }
    } else {
        S.view = 'login';
    }
    
    render();

    // L'evento 'load' del router gestirà la visualizzazione della pagina iniziale
    try {
        const r = await api('api/auth.php?a=status');
        if (r && r.success && r.account) {
            S.user = { email: r.account.email, role: r.account.role };
            localStorage.setItem(LS_USER_KEY, JSON.stringify(S.user));
            if (S.view !== 'app') {
                S.view = 'app';
                render();
            }
        } else {
            throw new Error('Sessione non valida');
        }
    } catch (e) {
        // Se lo status fallisce (401 o rete), e non siamo già sul login, pulisci e vai al login
        if (S.view !== 'login') {
            S.user = null;
            S.view = 'login';
            localStorage.removeItem(LS_USER_KEY);
            localStorage.removeItem(LS_ROUTE_KEY);
            render();
        }
    }
}

function route() {
    const h = location.hash || (S.view === 'app' ? '#/dashboard' : '');
    if (!h.startsWith('#/')) return;

    const pageName = h.substring(2);
    if (S.view === 'app') {
        const validRoutes = ['dashboard', 'chat', 'calendar', 'account'];
        showPage(validRoutes.includes(pageName) ? pageName : 'dashboard');
    }
}

window.addEventListener('hashchange', route);
window.addEventListener('load', () => {
    bootstrap().then(() => {
        route();
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('assets/service-worker.js').catch(e => console.error('SW registration failed:', e));
        }
    });
});

