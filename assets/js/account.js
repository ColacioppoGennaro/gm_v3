/**
 * assets/js/account.js
 * Gestione account: upgrade, downgrade, delete
 */

import { API } from './api.js';

/**
 * Carica informazioni account
 */
export async function loadAccountInfo() {
  try {
    const r = await API.loadAccountInfo();
    if (!r.success) return;
    
    document.getElementById('accountEmail').textContent = r.account.email;
    document.getElementById('accountSince').textContent = new Date(r.account.created_at).toLocaleDateString('it-IT');
    document.getElementById('usageDocs').textContent = r.usage.documents;
    document.getElementById('usageStorage').textContent = (r.usage.storage_bytes / (1024 * 1024)).toFixed(1);
    document.getElementById('usageChat').textContent = r.usage.chat_today;
    
    const usageCategories = document.getElementById('usageCategories');
    if (usageCategories) {
      usageCategories.textContent = r.usage.categories;
    }
  } catch (e) {
    console.error("Caricamento info account fallito", e);
  }
}

/**
 * Mostra modal upgrade
 */
export function showUpgradeModal() {
  if (document.getElementById('upgradeModal')) return;
  
  const html = `<div class="modal" id="upgradeModal">
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
  
  document.body.insertAdjacentHTML('beforeend', html);
  
  document.getElementById('closeModal').onclick = () => {
    document.getElementById('upgradeModal').remove();
  };
  
  document.getElementById('activateBtn').onclick = activateProFromModal;
}

/**
 * Attiva Pro (generico)
 */
async function activatePro(code, errEl, successEl, btnEl) {
  errEl?.classList.add('hidden');
  successEl?.classList.add('hidden');
  
  if (!code.trim()) {
    errEl.textContent = 'Inserisci un codice';
    errEl?.classList.remove('hidden');
    return;
  }
  
  if (btnEl) {
    btnEl.disabled = true;
    const originalText = btnEl.innerHTML;
    btnEl.innerHTML = 'Attivazione... <span class="loader"></span>';
  }

  try {
    const r = await API.upgradePro(code.trim());
    
    if (r.success) {
      successEl.textContent = '✓ Piano Pro attivato! Ricarico...';
      successEl?.classList.remove('hidden');
      
      setTimeout(() => {
        window.S.user.role = 'pro';
        localStorage.setItem('gmv3_user', JSON.stringify(window.S.user));
        document.getElementById('upgradeModal')?.remove();
        window.renderApp();
        
        setTimeout(() => {
          if (window.S.docs.filter(d => d.category === 'master').length > 0) {
            import('./documents.js').then(m => m.showOrganizeModal());
          }
        }, 500);
      }, 1500);
    } else {
      errEl.textContent = r.message || 'Codice non valido';
      errEl?.classList.remove('hidden');
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = originalText;
      }
    }
  } catch (e) {
    console.error("Attivazione Pro fallita", e);
    errEl.textContent = 'Errore di connessione.';
    errEl?.classList.remove('hidden');
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerHTML = originalText;
    }
  }
}

/**
 * Attiva Pro dal modal
 */
function activateProFromModal() {
  activatePro(
    document.getElementById('promoCode').value,
    document.getElementById('upgradeError'),
    document.getElementById('upgradeSuccess'),
    document.getElementById('activateBtn')
  );
}

/**
 * Attiva Pro dalla pagina account
 */
export function activateProFromPage() {
  activatePro(
    document.getElementById('promoCodePage').value,
    document.getElementById('upgradePageError'),
    document.getElementById('upgradePageSuccess'),
    document.getElementById('activateProPage')
  );
}

/**
 * Downgrade a Free
 */
export async function doDowngrade() {
  if (!confirm('Sei sicuro di voler passare al piano Free?\n\nDevi avere massimo 5 documenti.')) {
    return;
  }
  
  const btn = document.getElementById('downgradeBtn');
  const err = document.getElementById('downgradeError');
  
  err?.classList.add('hidden');
  btn.disabled = true;
  btn.innerHTML = 'Downgrade... <span class="loader"></span>';
  
  try {
    const r = await API.downgrade();
    
    if (r.success) {
      alert('✓ ' + r.message);
      window.S.user.role = 'free';
      localStorage.setItem('gmv3_user', JSON.stringify(window.S.user));
      window.renderApp();
    } else {
      err.textContent = r.message || 'Errore';
      err?.classList.remove('hidden');
      btn.disabled = false;
      btn.innerHTML = 'Downgrade a Free';
    }
  } catch (e) {
    err.textContent = 'Errore di connessione.';
    err?.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = 'Downgrade a Free';
  }
}

/**
 * Elimina account
 */
export async function doDeleteAccount() {
  if (!confirm('⚠️ ATTENZIONE ⚠️\n\nVuoi eliminare il tuo account?\nQuesta azione è IRREVERSIBILE.')) {
    return;
  }
  
  if (!confirm('Confermi l\'eliminazione dell\'account?')) {
    return;
  }

  const btn = document.getElementById('deleteAccountBtn');
  btn.disabled = true;
  btn.innerHTML = 'Eliminazione... <span class="loader"></span>';
  
  try {
    await API.deleteAccount();
    alert('Account eliminato. Arrivederci.');
    window.S.user = null;
    window.S.view = 'login';
    localStorage.clear();
    window.renderApp();
  } catch (e) {
    btn.disabled = false;
    btn.innerHTML = '🗑️ Elimina Account';
  }
}

// Esporta globalmente
window.showUpgradeModal = showUpgradeModal;
