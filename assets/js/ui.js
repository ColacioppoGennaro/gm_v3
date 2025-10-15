/**
 * assets/js/ui.js - Dashboard UI Manager
 * ✅ VERSIONE CORRETTA:
 * - Widget Eventi: filtri Area/Tipo/Categoria + bottone Organizza
 * - Documenti: SOLO filtro Categoria (visibile solo per PRO)
 */

import { openOrganizeModal } from './settings.js';

const state = {
    currentView: 'dashboard',
    user: null,
    isPro: false
};

export async function init() {
    await loadUserStatus();
    setupNavigation();
    await renderDashboard();
}

async function loadUserStatus() {
    try {
        const res = await fetch('/api/auth.php?a=status');
        const data = await res.json();
        
        if (data.success && data.account) {
            state.user = data.account;
            state.isPro = data.account.role === 'pro';
            
            updateUserBadge();
        }
    } catch (e) {
        console.error('Errore caricamento stato utente:', e);
    }
}

function updateUserBadge() {
    const badge = document.getElementById('userBadge');
    if (!badge) return;
    
    if (state.isPro) {
        badge.textContent = '⚡ PRO';
        badge.className = 'badge badge-pro';
    } else {
        badge.textContent = '🆓 Free';
        badge.className = 'badge badge-free';
    }
}

function setupNavigation() {
    document.querySelectorAll('[data-view]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const view = btn.getAttribute('data-view');
            navigateTo(view);
        });
    });
}

export async function navigateTo(view) {
    state.currentView = view;
    
    // Aggiorna menu attivo
    document.querySelectorAll('[data-view]').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-view') === view);
    });
    
    // Render view
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    switch (view) {
        case 'dashboard':
            await renderDashboard();
            break;
        case 'chat':
            await renderChat();
            break;
        case 'calendar':
            await renderCalendar();
            break;
        case 'documents':
            await renderDocuments();
            break;
        case 'account':
            await renderAccount();
            break;
        default:
            container.innerHTML = '<p>Vista non trovata</p>';
    }
}

// ============================================
// 📅 DASHBOARD CON WIDGET EVENTI
// ============================================
async function renderDashboard() {
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    container.innerHTML = `
        <div class="dashboard-container">
            <h1>📊 Dashboard</h1>
            
            <!-- Widget Prossimi Eventi con filtri Area/Tipo/Categoria -->
            <div class="widget widget-events">
                <div class="widget-header">
                    <h2>📅 Prossimi Eventi</h2>
                    <button class="btn btn-primary" onclick="window.uiModule.openCalendar()">
                        Vedi Calendario
                    </button>
                </div>
                
                <!-- ✅ FILTRI EVENTI: Area/Tipo/Categoria + Organizza -->
                <div class="events-filters">
                    <label>Area:</label>
                    <select id="filterEventArea">
                        <option value="">Tutte</option>
                    </select>
                    
                    <label>Tipo:</label>
                    <select id="filterEventType">
                        <option value="">Tutti</option>
                    </select>
                    
                    <label>Categoria:</label>
                    <select id="filterEventCategory">
                        <option value="">Tutte</option>
                    </select>
                    
                    <button id="organizeBtn" class="btn btn-secondary">
                        🔧 Organizza
                    </button>
                </div>
                
                <div id="eventsWidget" class="events-list">
                    <p class="loading">Caricamento eventi...</p>
                </div>
            </div>
            
            <!-- Widget Statistiche -->
            <div class="widget widget-stats">
                <h2>📈 Statistiche</h2>
                <div id="statsWidget">
                    <p class="loading">Caricamento statistiche...</p>
                </div>
            </div>
        </div>
    `;
    
    // Carica dati
    await loadEventFiltersData();
    await loadUpcomingEvents();
    await loadStats();
    
    // Setup event listeners
    setupEventFilters();
    
    // Bottone Organizza
    document.getElementById('organizeBtn')?.addEventListener('click', () => {
        openOrganizeModal();
    });
}

// Carica dati per i filtri eventi (Area/Tipo)
async function loadEventFiltersData() {
    try {
        // Carica Aree
        const resAree = await fetch('/api/settori.php?a=list');
        const dataAree = await resAree.json();
        
        const selectArea = document.getElementById('filterEventArea');
        if (selectArea && dataAree.success) {
            dataAree.data.forEach(area => {
                const opt = document.createElement('option');
                opt.value = area.id;
                opt.textContent = `${area.icona} ${area.nome}`;
                selectArea.appendChild(opt);
            });
        }
        
        // Carica Tipi
        const resTipi = await fetch('/api/tipi_attivita.php?a=list');
        const dataTipi = await resTipi.json();
        
        const selectTipo = document.getElementById('filterEventType');
        if (selectTipo && dataTipi.success) {
            dataTipi.data.forEach(tipo => {
                const opt = document.createElement('option');
                opt.value = tipo.id;
                opt.textContent = `${tipo.icona} ${tipo.nome}`;
                selectTipo.appendChild(opt);
            });
        }
        
    } catch (e) {
        console.error('Errore caricamento filtri eventi:', e);
    }
}

// Setup filtri eventi
function setupEventFilters() {
    const filterArea = document.getElementById('filterEventArea');
    const filterType = document.getElementById('filterEventType');
    const filterCategory = document.getElementById('filterEventCategory');
    
    [filterArea, filterType, filterCategory].forEach(select => {
        select?.addEventListener('change', loadUpcomingEvents);
    });
}

// Carica prossimi eventi (con filtri)
async function loadUpcomingEvents() {
    const container = document.getElementById('eventsWidget');
    if (!container) return;
    
    try {
        const filterArea = document.getElementById('filterEventArea')?.value || '';
        const filterType = document.getElementById('filterEventType')?.value || '';
        const filterCategory = document.getElementById('filterEventCategory')?.value || '';
        
        const params = new URLSearchParams({
            limit: '10',
            dir: 'up'
        });
        
        if (filterArea) params.append('area', filterArea);
        if (filterType) params.append('type', filterType);
        if (filterCategory) params.append('category', filterCategory);
        
        const res = await fetch(`/api/dashboard/events.php?${params}`);
        const data = await res.json();
        
        if (data.events && data.events.length > 0) {
            container.innerHTML = data.events.map(evt => `
                <div class="event-card" style="border-left: 4px solid ${evt.color || '#7c3aed'}">
                    <div class="event-title">${evt.title}</div>
                    <div class="event-meta">
                        <span>📅 ${formatDate(evt.start)}</span>
                        ${evt.type ? `<span class="badge">${evt.type}</span>` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="empty">Nessun evento in arrivo</p>';
        }
        
    } catch (e) {
        console.error('Errore caricamento eventi:', e);
        container.innerHTML = '<p class="error">Errore caricamento eventi</p>';
    }
}

// Carica statistiche
async function loadStats() {
    const container = document.getElementById('statsWidget');
    if (!container) return;
    
    try {
        const res = await fetch('/api/stats.php');
        const data = await res.json();
        
        if (data.success) {
            container.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Chat oggi</div>
                        <div class="stat-value">${data.data.chatToday} / ${state.isPro ? 200 : 20}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Spazio usato</div>
                        <div class="stat-value">${formatBytes(data.data.totalSize)}</div>
                    </div>
                </div>
            `;
        }
    } catch (e) {
        console.error('Errore caricamento stats:', e);
        container.innerHTML = '<p class="error">Errore caricamento statistiche</p>';
    }
}

// ============================================
// 📄 DOCUMENTI - SOLO CATEGORIA (PRO)
// ============================================
async function renderDocuments() {
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    container.innerHTML = `
        <div class="documents-container">
            <h1>📄 I Tuoi Documenti</h1>
            
            ${state.isPro ? `
                <!-- ✅ SOLO CATEGORIA per documenti (visibile solo PRO) -->
                <div class="documents-filters">
                    <label>Categoria documento:</label>
                    <select id="filterDocCategory">
                        <option value="">Tutte</option>
                    </select>
                </div>
            ` : `
                <div class="upgrade-banner">
                    ⚡ Passa a PRO per organizzare i documenti in categorie!
                </div>
            `}
            
            <div id="documentsList" class="documents-list">
                <p class="loading">Caricamento documenti...</p>
            </div>
            
            <div class="upload-section">
                <h3>📤 Carica Documento</h3>
                ${state.isPro ? `
                    <select id="uploadCategory">
                        <option value="">-- Seleziona categoria --</option>
                    </select>
                ` : '<p class="info">I documenti FREE vanno in categoria unica</p>'}
                <input type="file" id="fileInput" />
                <button id="uploadBtn" class="btn btn-primary">Carica File</button>
            </div>
        </div>
    `;
    
    // Carica categorie documenti (solo PRO)
    if (state.isPro) {
        await loadDocumentCategories();
    }
    
    await loadDocumentsList();
    
    // Setup upload
    document.getElementById('uploadBtn')?.addEventListener('click', uploadDocument);
}

// Carica categorie documenti (label DocAnalyzer)
async function loadDocumentCategories() {
    try {
        const res = await fetch('/api/categories.php?a=list');
        const data = await res.json();
        
        if (data.success) {
            const filterSelect = document.getElementById('filterDocCategory');
            const uploadSelect = document.getElementById('uploadCategory');
            
            data.data.forEach(cat => {
                const opt1 = document.createElement('option');
                opt1.value = cat.name;
                opt1.textContent = cat.name;
                
                const opt2 = opt1.cloneNode(true);
                
                filterSelect?.appendChild(opt1);
                uploadSelect?.appendChild(opt2);
            });
        }
    } catch (e) {
        console.error('Errore caricamento categorie documenti:', e);
    }
}

// Carica lista documenti
async function loadDocumentsList() {
    const container = document.getElementById('documentsList');
    if (!container) return;
    
    try {
        const res = await fetch('/api/documents.php?a=list');
        const data = await res.json();
        
        if (data.success && data.data.length > 0) {
            container.innerHTML = `
                <table class="documents-table">
                    <thead>
                        <tr>
                            <th>Nome File</th>
                            ${state.isPro ? '<th>Categoria</th>' : ''}
                            <th>Dimensione</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.data.map(doc => `
                            <tr>
                                <td>${doc.file_name}</td>
                                ${state.isPro ? `<td>${doc.category || 'master'}</td>` : ''}
                                <td>${formatBytes(doc.size)}</td>
                                <td>${formatDate(doc.created_at)}</td>
                                <td>
                                    <button onclick="window.uiModule.downloadDoc(${doc.id})">⬇️</button>
                                    <button onclick="window.uiModule.deleteDoc(${doc.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } else {
            container.innerHTML = '<p class="empty">Nessun documento caricato</p>';
        }
    } catch (e) {
        console.error('Errore caricamento documenti:', e);
        container.innerHTML = '<p class="error">Errore caricamento documenti</p>';
    }
}

// Upload documento
async function uploadDocument() {
    const fileInput = document.getElementById('fileInput');
    const categorySelect = document.getElementById('uploadCategory');
    
    if (!fileInput || !fileInput.files[0]) {
        alert('Seleziona un file');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    
    if (state.isPro && categorySelect) {
        const category = categorySelect.value;
        if (!category) {
            alert('Seleziona una categoria');
            return;
        }
        formData.append('category', category);
    }
    
    try {
        const res = await fetch('/api/documents.php?a=upload', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('✅ Documento caricato!');
            await loadDocumentsList();
            fileInput.value = '';
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (e) {
        console.error('Errore upload:', e);
        alert('❌ Errore upload');
    }
}

// ============================================
// 💬 CHAT AI
// ============================================
async function renderChat() {
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    container.innerHTML = `
        <div class="chat-container">
            <h1>💬 Chat AI</h1>
            <div id="chatMessages" class="chat-messages"></div>
            <div class="chat-input">
                <textarea id="chatInput" placeholder="Scrivi qui..."></textarea>
                <button id="sendBtn" class="btn btn-primary">Invia</button>
            </div>
        </div>
    `;
    
    document.getElementById('sendBtn')?.addEventListener('click', sendMessage);
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input?.value.trim();
    
    if (!message) return;
    
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;
    
    // Mostra messaggio utente
    messagesContainer.innerHTML += `
        <div class="message user-message">${message}</div>
    `;
    
    input.value = '';
    
    try {
        const res = await fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `q=${encodeURIComponent(message)}&mode=ai`
        });
        
        const data = await res.json();
        
        if (data.success) {
            messagesContainer.innerHTML += `
                <div class="message ai-message">${data.answer}</div>
            `;
        } else {
            messagesContainer.innerHTML += `
                <div class="message error-message">Errore: ${data.message}</div>
            `;
        }
        
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
    } catch (e) {
        console.error('Errore invio messaggio:', e);
        messagesContainer.innerHTML += `
            <div class="message error-message">Errore di connessione</div>
        `;
    }
}

// ============================================
// 📅 CALENDARIO
// ============================================
async function renderCalendar() {
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    container.innerHTML = `
        <div class="calendar-container">
            <h1>📅 Calendario</h1>
            <div id="calendar"></div>
        </div>
    `;
    
    // Qui andrebbe l'integrazione con FullCalendar o simili
    // Per ora placeholder
}

// ============================================
// 👤 ACCOUNT
// ============================================
async function renderAccount() {
    const container = document.getElementById('mainContent');
    if (!container) return;
    
    container.innerHTML = `
        <div class="account-container">
            <h1>👤 Il Mio Account</h1>
            
            <div class="account-info">
                <p><strong>Email:</strong> ${state.user?.email || 'N/A'}</p>
                <p><strong>Piano:</strong> ${state.isPro ? '⚡ PRO' : '🆓 Free'}</p>
            </div>
            
            ${!state.isPro ? `
                <div class="upgrade-section">
                    <h3>⚡ Passa a PRO</h3>
                    <p>Sblocca funzionalità avanzate:</p>
                    <ul>
                        <li>✅ Aree illimitate (20 vs 2)</li>
                        <li>✅ Tipi illimitati (50 per area vs 4)</li>
                        <li>✅ Categorie documenti</li>
                        <li>✅ 200 chat/giorno vs 20</li>
                    </ul>
                    <button class="btn btn-primary" onclick="window.uiModule.showUpgradeModal()">
                        Attiva PRO
                    </button>
                </div>
            ` : ''}
            
            <div class="danger-zone">
                <h3>⚠️ Zona Pericolosa</h3>
                <button class="btn btn-danger" onclick="window.uiModule.logout()">
                    🚪 Logout
                </button>
            </div>
        </div>
    `;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ============================================
// PUBLIC API
// ============================================
export function openCalendar() {
    navigateTo('calendar');
}

export async function downloadDoc(id) {
    window.location.href = `/api/documents.php?a=download&id=${id}`;
}

export async function deleteDoc(id) {
    if (!confirm('Eliminare questo documento?')) return;
    
    try {
        const res = await fetch('/api/documents.php?a=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('✅ Documento eliminato');
            await loadDocumentsList();
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (e) {
        console.error('Errore eliminazione:', e);
        alert('❌ Errore eliminazione');
    }
}

export function showUpgradeModal() {
    alert('Funzionalità upgrade in arrivo!');
}

export async function logout() {
    try {
        await fetch('/api/auth.php?a=logout', { method: 'POST' });
        window.location.href = '/';
    } catch (e) {
        console.error('Errore logout:', e);
    }
}

// Esporta modulo globale per onclick
window.uiModule = {
    openCalendar,
    downloadDoc,
    deleteDoc,
    showUpgradeModal,
    logout
};
