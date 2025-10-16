/**
 * assets/js/settings.js
 * Gestione modal "Organizza" per aree, tipi, e collegamento documenti
 */

import { API } from './api.js';

/**
 * Apre modal Organizza
 */
export async function openOrganizeModal() {
    try {
        // Carica dati
        const [settoriRes, tipiRes] = await Promise.all([
            fetch('api/settori.php?a=list'),
            fetch('api/tipi_attivita.php?a=list')
        ]);
        
        const settori = await settoriRes.json();
        const tipi = await tipiRes.json();
        
        if (!settori.success || !tipi.success) {
            throw new Error('Errore caricamento dati');
        }
        
        // Organizza tipi per settore
        const tipiPerSettore = {};
        tipi.data.forEach(tipo => {
            if (!tipiPerSettore[tipo.settore_id]) {
                tipiPerSettore[tipo.settore_id] = [];
            }
            tipiPerSettore[tipo.settore_id].push(tipo);
        });
        
        // Crea modal
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'organizeModal';
        
        modal.innerHTML = `
            <div class="modal organize-modal" style="max-width:900px">
                <div class="modal-header">
                    <h2 style="margin:0">🔧 Organizza Aree e Tipi</h2>
                    <button class="close-btn" onclick="window.closeOrganizeModal()">Chiudi</button>
                </div>
                
                <div class="modal-body">
                    <!-- Crea nuova area -->
                    <div class="card" style="margin-bottom:16px;background:var(--card)">
                        <h4 style="margin:0 0 12px 0">➕ Crea Nuova Area</h4>
                        <div style="display:flex;gap:8px">
                            <input id="newAreaName" placeholder="Nome area (es: Ufficio)" style="flex:1"/>
                            <button class="btn" id="btnCreateArea">Crea Area</button>
                        </div>
                    </div>
                    
                    <!-- Lista aree -->
                    <div id="areasContainer">
                        ${renderAreas(settori.data, tipiPerSettore)}
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button class="btn secondary" onclick="window.closeOrganizeModal()">Chiudi</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Bind eventi
        document.getElementById('btnCreateArea').addEventListener('click', createArea);
        
        // Bind eventi per ogni area
        settori.data.forEach(settore => {
            bindAreaEvents(settore.id, tipiPerSettore[settore.id] || []);
        });
        
    } catch (error) {
        console.error('Errore apertura modal:', error);
        alert('Errore caricamento dati: ' + error.message);
    }
}

/**
 * Renderizza lista aree con tipi
 */
function renderAreas(settori, tipiPerSettore) {
    if (settori.length === 0) {
        return '<p style="text-align:center;color:var(--muted)">Nessuna area creata. Crea la prima area!</p>';
    }
    
    return settori.map(settore => `
        <div class="area-card" data-area-id="${settore.id}">
            <div class="area-header">
                <div style="display:flex;align-items:center;gap:8px;flex:1">
                    <span style="font-size:24px">${settore.icona}</span>
                    <h3 style="margin:0">${settore.nome}</h3>
                    <span class="badge">${settore.num_tipi} tipi</span>
                </div>
                <button class="btn small del" data-delete-area="${settore.id}">🗑️</button>
            </div>
            
            <!-- Crea nuovo tipo -->
            <div class="create-tipo-row">
                <input class="new-tipo-input" data-area="${settore.id}" placeholder="Nome nuovo tipo (es: Bollette)"/>
                <button class="btn small" data-create-tipo="${settore.id}">+ Tipo</button>
            </div>
            
            <!-- Lista tipi -->
            <div class="tipi-list">
                ${renderTipi(tipiPerSettore[settore.id] || [])}
            </div>
        </div>
    `).join('');
}

/**
 * Renderizza lista tipi di un'area
 */
function renderTipi(tipi) {
    if (tipi.length === 0) {
        return '<p style="font-size:12px;color:var(--muted);padding:8px">Nessun tipo creato</p>';
    }
    
    return tipi.map(tipo => `
        <div class="tipo-item" data-tipo-id="${tipo.id}">
            <div style="display:flex;align-items:center;gap:8px;flex:1">
                <span>${tipo.icona}</span>
                <span>${tipo.nome}</span>
            </div>
            <label class="checkbox-label">
                <input 
                    type="checkbox" 
                    data-tipo-checkbox="${tipo.id}"
                    ${tipo.puo_collegare_documento ? 'checked' : ''}
                />
                <span>Collega documenti</span>
            </label>
            <button class="btn small del" data-delete-tipo="${tipo.id}">✕</button>
        </div>
    `).join('');
}

/**
 * Bind eventi per area specifica
 */
function bindAreaEvents(settoreId, tipi) {
    // Delete area
    document.querySelector(`[data-delete-area="${settoreId}"]`)?.addEventListener('click', () => {
        deleteArea(settoreId);
    });
    
    // Create tipo
    document.querySelector(`[data-create-tipo="${settoreId}"]`)?.addEventListener('click', () => {
        const input = document.querySelector(`input[data-area="${settoreId}"]`);
        createTipo(settoreId, input.value);
    });
    
    // Bind eventi tipi
    tipi.forEach(tipo => {
        // Checkbox collega documenti
        document.querySelector(`[data-tipo-checkbox="${tipo.id}"]`)?.addEventListener('change', (e) => {
            toggleDocumentLink(tipo.id, e.target.checked);
        });
        
        // Delete tipo
        document.querySelector(`[data-delete-tipo="${tipo.id}"]`)?.addEventListener('click', () => {
            deleteTipo(tipo.id);
        });
    });
}

/**
 * Crea nuova area
 */
async function createArea() {
    const input = document.getElementById('newAreaName');
    const nome = input.value.trim();
    
    if (!nome) {
        alert('Inserisci un nome per la nuova area');
        return;
    }
    
    try {
        const response = await fetch('api/settori.php?a=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore creazione area');
        }
        
        input.value = '';
        
        // Notifica altri componenti e ricarica modal
        window.dispatchEvent(new CustomEvent('gm:taxonomyChanged', { detail: { scope: 'area', action: 'create' } }));
        // Ricarica modal
        closeOrganizeModal();
        setTimeout(() => openOrganizeModal(), 100);
        
    } catch (error) {
        console.error('Errore creazione area:', error);
        alert('Errore: ' + error.message);
    }
}

/**
 * Elimina area
 */
async function deleteArea(settoreId) {
    if (!confirm('Eliminare questa area? Verranno eliminati anche tutti i tipi associati.')) {
        return;
    }
    
    try {
        const response = await fetch('api/settori.php?a=delete', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: settoreId })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore eliminazione');
        }
        
        // Notifica altri componenti e ricarica modal
        window.dispatchEvent(new CustomEvent('gm:taxonomyChanged', { detail: { scope: 'area', action: 'delete' } }));
        // Ricarica modal
        closeOrganizeModal();
        setTimeout(() => openOrganizeModal(), 100);
        
    } catch (error) {
        console.error('Errore eliminazione area:', error);
        alert('Errore: ' + error.message);
    }
}

/**
 * Crea nuovo tipo
 */
async function createTipo(settoreId, nome) {
    nome = nome.trim();
    
    if (!nome) {
        alert('Inserisci un nome per il nuovo tipo');
        return;
    }
    
    try {
        const response = await fetch('api/tipi_attivita.php?a=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                settore_id: settoreId,
                nome: nome 
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore creazione tipo');
        }
        
        // Notifica altri componenti e ricarica modal
        window.dispatchEvent(new CustomEvent('gm:taxonomyChanged', { detail: { scope: 'tipo', action: 'create', settoreId } }));
        // Ricarica modal
        closeOrganizeModal();
        setTimeout(() => openOrganizeModal(), 100);
        
    } catch (error) {
        console.error('Errore creazione tipo:', error);
        alert('Errore: ' + error.message);
    }
}

/**
 * Elimina tipo
 */
async function deleteTipo(tipoId) {
    if (!confirm('Eliminare questo tipo?')) {
        return;
    }
    
    try {
        const response = await fetch('api/tipi_attivita.php?a=delete', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: tipoId })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore eliminazione');
        }
        
        // Notifica altri componenti e ricarica modal
        window.dispatchEvent(new CustomEvent('gm:taxonomyChanged', { detail: { scope: 'tipo', action: 'delete' } }));
        // Ricarica modal
        closeOrganizeModal();
        setTimeout(() => openOrganizeModal(), 100);
        
    } catch (error) {
        console.error('Errore eliminazione tipo:', error);
        alert('Errore: ' + error.message);
    }
}

/**
 * Toggle collegamento documenti per tipo
 */
async function toggleDocumentLink(tipoId, enabled) {
    try {
        const response = await fetch('api/tipi_attivita.php?a=update', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                id: tipoId,
                puo_collegare_documento: enabled ? 1 : 0
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore aggiornamento');
        }
        
        // Notifica e feedback visivo
        window.dispatchEvent(new CustomEvent('gm:taxonomyChanged', { detail: { scope: 'tipo', action: 'update' } }));
        // Feedback visivo
        const checkbox = document.querySelector(`[data-tipo-checkbox="${tipoId}"]`);
        if (checkbox) {
            checkbox.parentElement.style.background = enabled ? 'rgba(124, 58, 237, 0.1)' : '';
            setTimeout(() => {
                if (checkbox.parentElement) {
                    checkbox.parentElement.style.background = '';
                }
            }, 500);
        }
        
    } catch (error) {
        console.error('Errore toggle documento:', error);
        alert('Errore: ' + error.message);
        
        // Ripristina checkbox
        const checkbox = document.querySelector(`[data-tipo-checkbox="${tipoId}"]`);
        if (checkbox) {
            checkbox.checked = !enabled;
        }
    }
}

/**
 * Chiude modal
 */
export function closeOrganizeModal() {
    const modal = document.getElementById('organizeModal');
    if (modal) {
        modal.remove();
    }
}

// Esporta globalmente
window.openOrganizeModal = openOrganizeModal;
window.closeOrganizeModal = closeOrganizeModal;
