/**
 * assets/js/documents.js
 * Gestione documenti, upload, OCR, categorie
 */

import { API } from './api.js';

/**
 * Carica lista documenti
 */
export async function loadDocs() {
  try {
    const r = await API.listDocs();
    if (r.success) {
      window.S.docs = r.data;
      document.getElementById('docCount').textContent = window.S.docs.length;
      renderDocsTable();
      loadStats();
    }
  } catch (e) {
    console.error("Caricamento documenti fallito", e);
  }
}

/**
 * Renderizza tabella documenti
 */
export function renderDocsTable() {
  const tb = document.querySelector('#docsTable tbody');
  if (!tb) return;
  
  const isPro = window.S.user?.role === 'pro';
  const filteredDocs = window.S.filterCategory 
    ? window.S.docs.filter(d => d.category === window.S.filterCategory) 
    : window.S.docs;

  tb.innerHTML = filteredDocs.map(d => `<tr>
    <td>${d.file_name}</td>
    ${isPro ? `<td class="category-select-cell">
      <select class="doc-category-select" data-docid="${d.id}" data-current="${d.category}">
        ${window.S.categories.map(c => 
          `<option value="${c.name}" ${c.name === d.category ? 'selected' : ''}>${c.name}</option>`
        ).join('')}
      </select>
    </td>` : ''}
    <td>${(d.size / (1024 * 1024)).toFixed(2)} MB</td>
    <td>${new Date(d.created_at).toLocaleString('it-IT')}</td>
    <td style="white-space:nowrap">
      <a href="api/documents.php?a=download&id=${d.id}" class="btn small" style="margin-right:8px;text-decoration:none;display:inline-block">📥</a>
      <button class="btn small ${d.ocr_recommended ? '' : 'secondary'}" data-id="${d.id}" data-action="ocr" style="${d.ocr_recommended ? 'background:#f59e0b;' : ''}margin-right:8px" title="${d.ocr_recommended ? 'OCR Consigliato' : 'OCR disponibile'}">🔍 ${d.ocr_recommended ? 'OCR' : ''}</button>
      <button class="btn del" data-id="${d.id}" data-action="delete">🗑️</button>
    </td>
  </tr>`).join('');

  tb.querySelectorAll('button[data-action="delete"]').forEach(b => 
    b.onclick = () => deleteDoc(b.dataset.id)
  );
  tb.querySelectorAll('button[data-action="ocr"]').forEach(b => 
    b.onclick = () => doOCR(b.dataset.id)
  );
  if (isPro) {
    tb.querySelectorAll('.doc-category-select').forEach(sel => 
      sel.onchange = () => changeDocCategory(sel)
    );
  }
}

/**
 * Upload file
 */
export async function uploadFile() {
  const fileInput = document.getElementById('file');
  const f = fileInput.files[0];
  if (!f) return alert('Seleziona un file');
  
  const uploadCategory = document.getElementById('uploadCategory');
  if (window.S.user?.role === 'pro' && !uploadCategory?.value) {
    return alert('Seleziona una categoria');
  }

  const uploadBtn = document.getElementById('uploadBtn');
  uploadBtn.disabled = true;
  const originalText = uploadBtn.innerHTML;
  uploadBtn.innerHTML = 'Caricamento... <span class="loader"></span>';
  document.getElementById('drop').style.pointerEvents = 'none';

  try {
    const r = await API.uploadDoc(f, uploadCategory?.value);
    if (r.success) {
      loadDocs();
      clearFileSelection(); // Ripristina dropzone
      if (uploadCategory) uploadCategory.value = '';
    }
  } catch (e) {
    console.error("Upload fallito", e);
  } finally {
    uploadBtn.disabled = false;
    uploadBtn.innerHTML = originalText;
    document.getElementById('drop').style.pointerEvents = 'auto';
  }
}

/**
 * Elimina documento
 */
export async function deleteDoc(id) {
  if (!confirm('Eliminare questo documento?')) return;
  
  const btn = document.querySelector(`button[data-id="${id}"][data-action="delete"]`);
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span>';
  }
  
  try {
    await API.deleteDoc(id);
    loadDocs();
  } catch (e) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🗑️';
    }
  }
}

/**
 * Avvia OCR
 */
export async function doOCR(id) {
  let confirmMsg = 'Avviare OCR?\n\n';
  if (window.S.user?.role !== 'pro') {
    confirmMsg += '⚠️ SEI FREE: Hai 1 SOLO OCR.\n';
  }
  confirmMsg += '💰 Costo: 1 credito DocAnalyzer per pagina.';
  
  if (!confirm(confirmMsg)) return;

  const btn = document.querySelector(`button[data-id="${id}"][data-action="ocr"]`);
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span>';
  }
  
  try {
    const r = await API.ocrDoc(id);
    if (r.success && btn) {
      btn.style.background = '#10b981';
      btn.innerHTML = '✓';
      btn.title = 'OCR Avviato';
    } else if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🔍';
    }
  } catch (e) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🔍';
    }
  }
}

/**
 * Cambia categoria documento
 */
export async function changeDocCategory(select) {
  const { docid, current } = select.dataset;
  const newCategory = select.value;
  
  if (current === newCategory) return;
  if (!confirm(`Spostare il documento in "${newCategory}"?`)) {
    select.value = current;
    return;
  }
  
  select.disabled = true;
  
  try {
    const r = await API.changeDocCategory(docid, newCategory);
    if (!r.success) select.value = current;
    await loadDocs();
  } catch (e) {
    select.value = current;
  }
}

/**
 * Carica categorie
 */
export async function loadCategories() {
  try {
    const r = await API.listCategories();
    if (!r.success) return;
    
    window.S.categories = r.data;
    
    // Aggiorna select
    const updateSelect = (selectId, defaultOption) => {
      const select = document.getElementById(selectId);
      if (select) {
        select.innerHTML = defaultOption + window.S.categories.map(c => 
          `<option value="${c.name}">${c.name}</option>`
        ).join('');
      }
    };
    
    updateSelect('uploadCategory', '<option value="">-- Seleziona una categoria --</option>');
    updateSelect('categoryDocs', '<option value="">-- Seleziona categoria --</option>');
    updateSelect('filterCategory', '<option value="">Tutte le categorie</option>');

    // Aggiorna lista categorie dashboard
    const categoriesList = document.getElementById('categoriesList');
    if (categoriesList) {
      categoriesList.innerHTML = window.S.categories.length === 0
        ? '<p style="color:var(--muted);font-size:12px;padding:8px">Nessuna categoria. Creane una qui sotto!</p>'
        : window.S.categories.map(c => 
            `<span class="category-tag">🏷️ ${c.name}<button onclick="window.deleteCategory(${c.id})" title="Elimina categoria">✕</button></span>`
          ).join('');
    }
  } catch (e) {
    console.error("Caricamento categorie fallito", e);
  }
}

/**
 * Crea categoria
 */
export async function createCategory(name, inputEl, btnEl) {
  const categoryName = name.trim();
  if (!categoryName) return alert('Inserisci un nome per la categoria');
  
  btnEl.disabled = true;
  const originalText = btnEl.innerHTML;
  btnEl.innerHTML = '<span class="loader"></span>';
  
  try {
    const r = await API.createCategory(categoryName);
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

/**
 * Crea categoria dalla dashboard
 */
export function createCategoryFromDashboard() {
  createCategory(
    document.getElementById('newCategoryName').value,
    document.getElementById('newCategoryName'),
    document.getElementById('addCategoryBtn')
  );
}

/**
 * Elimina categoria
 */
export async function deleteCategory(id) {
  if (!confirm('Eliminare questa categoria?\n\nATTENZIONE: Non puoi eliminare categorie che contengono documenti.')) {
    return;
  }
  
  try {
    const r = await API.deleteCategory(id);
    if (r.success) {
      loadCategories();
      loadDocs();
    }
  } catch (e) {
    console.error("Eliminazione categoria fallita", e);
  }
}

// Esporta globalmente per onclick HTML
window.deleteCategory = deleteCategory;

/**
 * Setup drag&drop upload
 */
export function setupUploadDropzone() {
  const drop = document.getElementById('drop');
  const fileInput = document.getElementById('file');
  
  if (!drop || !fileInput) return;
  
  drop.onclick = () => fileInput.click();
  
  // Mostra preview quando file selezionato
  fileInput.onchange = () => {
    if (fileInput.files.length > 0) {
      showFilePreview(fileInput.files[0]);
    }
  };
  
  drop.ondragover = (e) => {
    e.preventDefault();
    drop.style.borderColor = 'var(--accent)';
  };
  
  drop.ondragleave = () => {
    drop.style.borderColor = '#374151';
  };
  
  drop.ondrop = (e) => {
    e.preventDefault();
    drop.style.borderColor = '#374151';
    fileInput.files = e.dataTransfer.files;
    if (fileInput.files.length > 0) {
      showFilePreview(fileInput.files[0]);
    }
  };
}

/**
 * Mostra preview del file selezionato
 */
function showFilePreview(file) {
  const drop = document.getElementById('drop');
  if (!drop) return;
  
  const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
  const fileIcon = getFileIcon(file.type);
  
  drop.innerHTML = `
    <div style="text-align:center">
      <div style="font-size:48px;margin-bottom:8px">${fileIcon}</div>
      <div style="font-weight:600;color:var(--accent);margin-bottom:4px">${file.name}</div>
      <div style="font-size:13px;color:var(--muted)">${sizeInMB} MB</div>
      <div style="font-size:12px;color:var(--ok);margin-top:8px">✓ File selezionato - Clicca "Carica File" per procedere</div>
      <button type="button" id="clearFile" class="btn secondary" style="margin-top:12px;padding:6px 16px;font-size:13px">✕ Rimuovi</button>
    </div>
  `;
  
  drop.style.borderColor = 'var(--ok)';
  drop.style.background = 'rgba(16, 185, 129, 0.05)';
  
  // Bind rimuovi file
  document.getElementById('clearFile')?.addEventListener('click', (e) => {
    e.stopPropagation();
    clearFileSelection();
  });
}

/**
 * Ottieni icona in base al tipo di file
 */
function getFileIcon(mimeType) {
  if (mimeType.includes('pdf')) return '📕';
  if (mimeType.includes('word') || mimeType.includes('document')) return '📘';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📗';
  if (mimeType.includes('image')) return '🖼️';
  if (mimeType.includes('text')) return '📄';
  return '📎';
}

/**
 * Pulisci selezione file
 */
function clearFileSelection() {
  const fileInput = document.getElementById('file');
  const drop = document.getElementById('drop');
  
  if (fileInput) fileInput.value = '';
  if (drop) {
    const isPro = window.S.user?.role === 'pro';
    const maxSize = isPro ? 150 : 50;
    
    drop.innerHTML = `
      <div style="text-align:center">
        <div style="font-size:48px;margin-bottom:8px">📁</div>
        <div>Trascina qui un file o clicca per selezionare</div>
        <div style="font-size:12px;color:#64748b;margin-top:4px">PDF, DOC, DOCX, TXT, CSV, XLSX, JPG, PNG (Max ${maxSize}MB)</div>
      </div>
    `;
    drop.style.borderColor = '#374151';
    drop.style.background = 'transparent';
  }
}

/**
 * Carica statistiche
 */
async function loadStats() {
  try {
    const r = await API.loadStats();
    if (r.success) {
      window.S.stats = r.data;
      document.getElementById('qCount').textContent = window.S.stats.chatToday || 0;
      document.getElementById('qCountChat').textContent = window.S.stats.chatToday || 0;
      document.getElementById('storageUsed').textContent = (window.S.stats.totalSize / (1024 * 1024)).toFixed(1);
    }
  } catch (e) {
    console.error("Caricamento statistiche fallito", e);
  }
}

/**
 * Mostra modal organizza documenti
 */
export function showOrganizeModal() {
  if (document.getElementById('organizeModal')) return;
  
  const masterDocs = window.S.docs.filter(d => d.category === 'master');
  
  if (masterDocs.length === 0) {
    const html = `<div class="modal" id="organizeModal">
      <div class="modal-content">
        <h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>
        <p style="color:var(--ok);margin-bottom:16px">✓ Tutti i documenti sono già organizzati in categorie!</p>
        <button class="btn" onclick="document.getElementById('organizeModal').remove()">Chiudi</button>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    return;
  }

  let html = `<div class="modal" id="organizeModal">
    <div class="modal-content">
      <h2 style="margin-bottom:16px">🔧 Organizza Documenti</h2>
      <p style="color:var(--muted);margin-bottom:16px">Hai ${masterDocs.length} documento/i senza categoria specifica. Assegna una categoria a ciascuno:</p>
      <div class="new-category-box">
        <h4>➕ Crea Nuova Categoria</h4>
        <div style="display:flex;gap:8px">
          <input id="modalNewCategoryName" placeholder="Nome categoria (es. Lavoro, Fatture...)" style="flex:1"/>
          <button class="btn small" id="modalAddCategoryBtn">Crea</button>
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--muted)">Categorie esistenti: ${window.S.categories.length > 0 ? window.S.categories.map(c => c.name).join(', ') : 'nessuna'}</div>
      </div>
      <div id="organizeList">`;

  masterDocs.forEach(d => {
    html += `<div class="organize-item" data-docid="${d.id}">
      <div class="filename">📄 ${d.file_name}</div>
      <select class="organize-select" data-docid="${d.id}">
        <option value="">-- Scegli categoria --</option>
        ${window.S.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('')}
      </select>
    </div>`;
  });

  html += `</div>
    <div class="btn-group" style="margin-top:24px">
      <button class="btn secondary" onclick="document.getElementById('organizeModal').remove()">Chiudi</button>
      <button class="btn" id="saveOrganizeBtn">Salva Organizzazione</button>
    </div>
    </div>
  </div>`;
  
  document.body.insertAdjacentHTML('beforeend', html);
  
  document.getElementById('saveOrganizeBtn')?.addEventListener('click', saveOrganization);
  document.getElementById('modalAddCategoryBtn')?.addEventListener('click', createCategoryInModal);
}

/**
 * Crea categoria dal modal organizza
 */
async function createCategoryInModal() {
  const input = document.getElementById('modalNewCategoryName');
  const btn = document.getElementById('modalAddCategoryBtn');
  
  if (await createCategory(input.value, input, btn)) {
    document.getElementById('organizeModal')?.remove();
    showOrganizeModal();
  }
}

/**
 * Salva organizzazione documenti
 */
async function saveOrganization() {
  const updates = Array.from(document.querySelectorAll('.organize-select'))
    .map(select => ({
      docid: parseInt(select.dataset.docid),
      category: select.value
    }))
    .filter(u => u.category);

  if (updates.length === 0) {
    return alert('Seleziona almeno una categoria.');
  }

  const btn = document.getElementById('saveOrganizeBtn');
  btn.disabled = true;
  btn.innerHTML = 'Salvataggio... <span class="loader"></span>';

  const results = await Promise.all(
    updates.map(u => API.changeDocCategory(u.docid, u.category).catch(() => ({ success: false })))
  );

  const successCount = results.filter(r => r.success).length;
  
  alert(
    updates.length === successCount
      ? `✓ ${successCount} documento/i organizzato/i!`
      : `⚠ ${successCount} ok, ${updates.length - successCount} errori.`
  );

  document.getElementById('organizeModal')?.remove();
  loadDocs();
}
