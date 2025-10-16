/**
 * assets/js/dashboard-events.js — lista scorrevole con infinite scroll up/down
 * ✅ Filtri attivi immediatamente (senza bottone Applica)
 * ✅ Bottone "Fatto" con checkmark
 * ✅ Ricarica automatica quando si torna alla dashboard
 */
import { API } from './api.js';
import { openOrganizeModal } from './settings.js';

let currentFilters = { areaId: null, tipoId: null, category: null };
const _allCategories = new Set();
let _settori = [];
let _tipi = [];
let _tipoById = new Map();

// ancore per lo scroll
let anchorUp = new Date().toISOString();
let anchorDown = new Date().toISOString();
let loadingUp = false, loadingDown = false;
const RANGE_DAYS = 30;

// ✅ Flag per evitare caricamenti multipli
let isInitialized = false;
let observer = null;

export async function renderEventsWidget() {
  const container = document.getElementById('eventsWidget');
  if (!container) return;

  console.log('🎯 renderEventsWidget chiamato');

  // ✅ Reset stato
  anchorUp = new Date().toISOString();
  anchorDown = new Date().toISOString();
  loadingUp = false;
  loadingDown = false;
  isInitialized = false;

  // Disconnetti observer precedente se esiste
  if (observer) {
    observer.disconnect();
    observer = null;
  }

  // shell + filtri + checkbox + area scrollabile
  container.innerHTML = `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap">
        <h3 style="margin:0">📅 Prossimi Eventi</h3>
        <button class="btn small" onclick="location.hash='#/calendar'">Vedi Calendario</button>
      </div>
      <div class="filter-bar" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <div style="display:flex;gap:8px;align-items:center">
          <label>Tipo:</label>
          <select id="filterType">
            <option value="">Tutti</option>
            <option value="payment">💳 Pagamento</option>
            <option value="maintenance">🔧 Manutenzione</option>
            <option value="document">📄 Documento</option>
            <option value="personal">👤 Personale</option>
          </select>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <label>Categoria:</label>
          <select id="filterCategory"><option value="">Tutte</option></select>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-left:auto">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none">
            <input type="checkbox" id="showCompleted" style="cursor:pointer">
            <span>Mostra completati</span>
          </label>
        </div>
      </div>
      <div id="feed" style="max-height:420px;overflow-y:auto;border-radius:8px;background:#0f172a1a;padding:8px">
        <div id="feedTopSentinel" style="height:1px"></div>
        <div id="eventsList"><div style="padding:20px;text-align:center;color:var(--muted)">⏳ Caricamento...</div></div>
        <div id="feedBottomSentinel" style="height:1px"></div>
      </div>
    </div>`;

  // Ricostruzione filter bar: Area, Tipo, Categoria, Mostra completati, Organizza
  const _fb = container.querySelector('.filter-bar');
  if (_fb) {
    _fb.innerHTML = [
      '<div style="display:flex;gap:8px;align-items:center">',
      '  <label>Area:</label>',
      '  <select id="filterArea"><option value="">Tutte</option></select>',
      '</div>',
      '<div style="display:flex;gap:8px;align-items:center">',
      '  <label>Tipo:</label>',
      '  <select id="filterTipo"><option value="">Tutti</option></select>',
      '</div>',
      '<div style="display:flex;gap:8px;align-items:center">',
      '  <label>Categoria:</label>',
      '  <select id="filterCategory"><option value="">Tutte</option></select>',
      '</div>',
      '<div style="display:flex;gap:8px;align-items:center;margin-left:auto">',
      '  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none">',
      '    <input type="checkbox" id="showCompleted" style="cursor:pointer">',
      '    <span>Mostra completati</span>',
      '  </label>',
      '  <button class="btn secondary" id="btnOrganize" title="Gestisci Aree e Tipi">Organizza</button>',
      // placeholder nascosto per compat con codice esistente
      '  <select id="filterType" style="display:none"><option value=""></option></select>',
      '</div>'
    ].join('');
  }

  // ✅ Aspetta che il DOM sia effettivamente renderizzato
  await new Promise(resolve => setTimeout(resolve, 50));

  // Verifica che gli elementi esistano ancora
  const filterType = document.getElementById('filterType');
  const filterCategory = document.getElementById('filterCategory');
  const showCompleted = document.getElementById('showCompleted');
  
  if (!filterType || !filterCategory || !showCompleted) {
    console.warn('⚠️ Elementi filtro non trovati, riprovo...');
    setTimeout(() => renderEventsWidget(), 100);
    return;
  }

  // ✅ Eventi onChange immediati per filtri
  filterType.addEventListener('change', applyFiltersAndReload);
  filterCategory.addEventListener('change', applyFiltersAndReload);
  showCompleted.addEventListener('change', applyFiltersAndReload);

  // Nuovi controlli: Area/Tipo + Organizza
  const filterArea = document.getElementById('filterArea');
  const filterTipo = document.getElementById('filterTipo');
  const btnOrganize = document.getElementById('btnOrganize');
  try {
    await loadAreaTipoOptions();
  } catch (_) { /* ignore */ }
  filterArea?.addEventListener('change', () => { populateTipoOptions(); applyFiltersAndReload(); });
  filterTipo?.addEventListener('change', applyFiltersAndReload);
  btnOrganize?.addEventListener('click', () => openOrganizeModal());

  // ✅ Carica immediatamente
  await initializeAndLoad();

  // Se l'utente modifica Aree/Tipi da "Organizza", aggiorna i select
  window.addEventListener('gm:taxonomyChanged', async () => {
    try { await loadAreaTipoOptions(); applyFiltersAndReload(); } catch(_){}
  });

  // ✅ Observer per ricaricare se il widget diventa visibile dopo essere stato nascosto
  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !isInitialized) {
        console.log('👁️ Widget tornato visibile, ricarico...');
        initializeAndLoad();
      }
    });
  }, { threshold: 0.1 });

  const feed = document.getElementById('feed');
  if (feed) {
    observer.observe(feed);
  }
}

// ✅ Funzione di inizializzazione centralizzata
async function initializeAndLoad() {
  if (isInitialized) {
    console.log('⏭️ Già inizializzato, skip');
    return;
  }
  
  console.log('🚀 Inizializzazione widget eventi...');
  isInitialized = true;
  
  await primeLoad();
  setupScrollHandlers();
}

// ✅ Funzione per applicare filtri e ricaricare
async function applyFiltersAndReload() {
  const areaVal = document.getElementById('filterArea')?.value || '';
  const tipoVal = document.getElementById('filterTipo')?.value || '';
  const catVal = document.getElementById('filterCategory')?.value || '';
  
  currentFilters.areaId = areaVal === '' ? null : parseInt(areaVal, 10);
  currentFilters.tipoId = tipoVal === '' ? null : parseInt(tipoVal, 10);
  currentFilters.category = catVal === '' ? null : catVal;
  
  console.log('🔍 Filtri applicati:', currentFilters);
  
  // reset ancore e flag
  anchorUp = new Date().toISOString();
  anchorDown = new Date().toISOString();
  isInitialized = false;
  
  // ✅ Rimuovi messaggi "fine eventi" se presenti
  const endMsg = document.getElementById('endMessage');
  if (endMsg) endMsg.remove();
  
  document.getElementById('eventsList').innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">⏳ Caricamento...</div>';
  
  await initializeAndLoad();
}

async function primeLoad() {
  const list = document.getElementById('eventsList');
  if (!list) return;
  
  // ✅ Mostra loading
  list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">⏳ Caricamento...</div>';
  
  console.log('🔄 Caricamento iniziale eventi...', { currentFilters });
  
  // ✅ Reset ancore a ADESSO
  const now = new Date().toISOString();
  anchorUp = now;
  anchorDown = now;
  
  // ✅ Leggi checkbox completati
  const showCompleted = document.getElementById('showCompleted')?.checked || false;
  
  try {
    // ✅ Carica FUTURI (da oggi in avanti)
    const futureData = await API.getDashboardFeed({
      anchor: now,
      dir: 'up',
      rangeDays: RANGE_DAYS,
      include_done: showCompleted,
      category: currentFilters.category,
      limit: 10
    });
    
    // ✅ Carica PASSATI/OGGI (ultimi 7 giorni)
    const pastData = await API.getDashboardFeed({
      anchor: now,
      dir: 'down',
      rangeDays: 7, // Solo ultimi 7 giorni
      include_done: showCompleted,
      category: currentFilters.category,
      limit: 10
    });
    
    console.log('📊 Eventi futuri:', futureData?.events?.length || 0);
    console.log('📊 Eventi passati:', pastData?.events?.length || 0);
    
    // ✅ Aggiorna ancore
    anchorUp = futureData?.meta?.nextAnchorUp || anchorUp;
    anchorDown = pastData?.meta?.nextAnchorDown || anchorDown;
    
    // aggiorna categorie (sulla base dei risultati filtrati per Area/Tipo)
    let allEvents = [...(pastData.events || []), ...(futureData.events || [])];
    allEvents = filterByAreaTipo(allEvents);
    allEvents.map(e=>e.category).filter(Boolean).forEach(c=>_allCategories.add(c));
    renderCategories();
    
    // ✅ Pulisci e mostra eventi
    list.innerHTML = '';
    
    // Ordina: passati in ordine cronologico inverso (più recente prima)
    const pastSorted = filterByAreaTipo((pastData.events || [])).sort((a, b) => 
      new Date(b.start).getTime() - new Date(a.start).getTime()
    );
    
    // Futuri già in ordine corretto
    const futureEvents = filterByAreaTipo(futureData.events || []);
    
    if (pastSorted.length === 0 && futureEvents.length === 0) {
      list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">📭 Nessun evento trovato</div>';
    } else {
      let html = '';
      
      // ✅ Eventi passati
      if (pastSorted.length > 0) {
        html += pastSorted.map(eventRowHtml).join('');
      }
      
      // ✅ SEPARATORE tra passato e futuro/oggi
      if (pastSorted.length > 0 && futureEvents.length > 0) {
        html += `
          <div id="todaySeparator" style="display:flex;align-items:center;gap:12px;margin:16px 0;padding:0 8px">
            <div style="flex:1;height:2px;background:linear-gradient(to right, transparent, #fff, transparent);opacity:0.3"></div>
            <span style="color:#fff;font-size:12px;font-weight:600;white-space:nowrap;opacity:0.6">ORA</span>
            <div style="flex:1;height:2px;background:linear-gradient(to left, transparent, #fff, transparent);opacity:0.3"></div>
          </div>`;
      }
      
      // ✅ Eventi futuri/oggi
      if (futureEvents.length > 0) {
        html += futureEvents.map(eventRowHtml).join('');
      }
      
      list.innerHTML = html;
      
      // ✅ Scroll al separatore "ORA" se presente, altrimenti al primo evento futuro
      setTimeout(() => {
        const separator = document.getElementById('todaySeparator');
        if (separator) {
          separator.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          const firstFuture = list.querySelector('[data-future="true"]');
          if (firstFuture) {
            firstFuture.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      }, 100);
    }
  } catch (error) {
    console.error('❌ Errore caricamento eventi:', error);
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444">❌ Errore nel caricamento</div>';
  }
}

function renderCategories() {
  const sel = document.getElementById('filterCategory');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Tutte</option>' + Array.from(_allCategories).map(c=>`<option value="${c}">${c}</option>`).join('');
  if (cur) sel.value = cur;
}

function eventRowHtml(event) {
  const typeEmoji = { payment:'💳', maintenance:'🔧', document:'📄', personal:'👤' };
  const emoji = typeEmoji[event.type] || '📌';
  const startDate = new Date(event.start);
  const now = new Date();
  const dateStr = startDate.toLocaleDateString('it-IT',{ day:'numeric', month:'short', year: startDate.getFullYear()!==now.getFullYear()? 'numeric': undefined });
  const timeStr = event.allDay ? 'Tutto il giorno' : startDate.toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'});
  const isOverdue = startDate < now && event.status !== 'done';
  const isDone = event.status === 'done';
  const isFuture = startDate >= now;
  
  const categoryBadge = event.category ? `<span class="category-tag" style="font-size:11px;padding:2px 8px;border-radius:12px;background:#334155;color:#cbd5e1;border:1px solid #475569;margin-left:8px">${event.category}</span>` : '';
  
  const doneBadge = isDone ? '<span style="color:#10b981;font-size:11px;font-weight:600;margin-left:8px">✅ COMPLETATO</span>' : '';
  
  const overdueBadge = isOverdue ? '<span style="color:#ef4444;font-size:11px;font-weight:600">⚠️ SCADUTO</span>' : '';
  
  // ✅ Badge OGGI
  const isToday = startDate.toDateString() === now.toDateString();
  const todayBadge = isToday && !isDone ? '<span style="color:#3b82f6;font-size:11px;font-weight:600;margin-left:8px">📍 OGGI</span>' : '';
  
  // ✅ Nascondi azioni se completato
  const actions = isDone ? '' : `
    <div class="event-actions" style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn success small" style="min-width:96px;white-space:nowrap;font-weight:600" onclick="window.markEventDone('${event.id}')" title="Segna come completato">✅ Fatto</button>
      <button class="btn secondary small" style="min-width:96px;white-space:nowrap" onclick="window.postponeEvent('${event.id}', '${(event.title||'').replace(/'/g, "\'")}')" title="Rimanda evento">⏸ Rimanda</button>
      <button class="btn secondary small icon-only" style="min-width:44px" onclick="window.viewEventDetails('${event.id}')" title="Vedi dettagli">👁</button>
    </div>`;
  
  return `
    <div class="event-row" data-future="${isFuture}" style="display:flex;align-items:center;gap:12px;padding:12px;margin-bottom:8px;background:${isDone ? 'rgba(16,185,129,0.1)' : isToday ? 'rgba(59,130,246,0.1)' : isOverdue?'rgba(239,68,68,0.1)':'#1f2937'};border-left:4px solid ${event.color};border-radius:8px;flex-wrap:wrap;opacity:${isDone ? '0.7' : '1'}">
      <div style="flex:1;min-width:240px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
          <span style="font-size:18px">${emoji}</span>
          <strong style="color:var(--fg);${isDone ? 'text-decoration:line-through' : ''}">${event.title}</strong>
          ${categoryBadge}
          ${todayBadge}
          ${doneBadge}
          ${overdueBadge}
        </div>
        <div style="font-size:12px;color:var(--muted)">📅 ${dateStr} ${!event.allDay ? `• 🕐 ${timeStr}` : ''}</div>
        ${event.description ? `<div style=\"font-size:12px;color:var(--muted);margin-top:4px\">${event.description.substring(0,80)}${event.description.length>80?'...':''}</div>` : ''}
      </div>
      ${actions}
    </div>`;
}

function prependOrAppend(items, mode) {
  const list = document.getElementById('eventsList');
  if (!list) return;
  if (!items || items.length===0) return;
  const filtered = filterByAreaTipo(items);
  if (!filtered || filtered.length === 0) return;
  const html = filtered.map(eventRowHtml).join('');
  if (mode === 'prepend') list.insertAdjacentHTML('afterbegin', html); else list.insertAdjacentHTML('beforeend', html);
}

function setupScrollHandlers() {
  const feed = document.getElementById('feed');
  const list = document.getElementById('eventsList');
  if (!feed || !list) return;
  
  const TOP_THR = 150;
  const BOT_THR = 150;
  
  // ✅ Flag per evitare caricamenti quando non ci sono più eventi
  let noMoreUp = false;
  let noMoreDown = false;
  
  // ✅ Debounce per evitare troppi trigger
  let scrollTimeout = null;

  // ✅ Spinner helper
  const showTopSpinner = () => {
    if (document.getElementById('topSpinner')) return;
    const spinner = document.createElement('div');
    spinner.id = 'topSpinner';
    spinner.style.cssText = 'padding:12px;text-align:center;color:var(--muted);font-size:20px';
    spinner.innerHTML = '🔄';
    list.insertBefore(spinner, list.firstChild);
  };
  
  const hideTopSpinner = () => {
    const spinner = document.getElementById('topSpinner');
    if (spinner) spinner.remove();
  };
  
  const showBottomSpinner = () => {
    if (document.getElementById('bottomSpinner')) return;
    const spinner = document.createElement('div');
    spinner.id = 'bottomSpinner';
    spinner.style.cssText = 'padding:12px;text-align:center;color:var(--muted);font-size:20px';
    spinner.innerHTML = '🔄';
    list.appendChild(spinner);
  };
  
  const hideBottomSpinner = () => {
    const spinner = document.getElementById('bottomSpinner');
    if (spinner) spinner.remove();
  };

  const handleScroll = async () => {
    const nearTop = feed.scrollTop < TOP_THR;
    const nearBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < BOT_THR;
    
    // ✅ Leggi checkbox
    const showCompleted = document.getElementById('showCompleted')?.checked || false;

    // ✅ SCROLL SU: carica eventi FUTURI
    if (nearTop && !loadingUp && !noMoreUp) {
      loadingUp = true;
      showTopSpinner();
      
      try {
        console.log('⬆️ Caricamento eventi futuri...');
        const data = await API.getDashboardFeed({
          anchor: anchorUp,
          dir: 'up',
          rangeDays: RANGE_DAYS,
          include_done: showCompleted,
          category: currentFilters.category,
          limit: 20
        });
        
        console.log('📊 Eventi futuri ricevuti:', data?.events?.length || 0);
        
        if (!data.events || data.events.length === 0) {
          noMoreUp = true;
          console.log('⚠️ Nessun altro evento futuro');
          hideTopSpinner();
          return;
        }
        
        if (data?.meta?.nextAnchorUp) {
          anchorUp = data.meta.nextAnchorUp;
        }
        
        // Salva posizione scroll prima di aggiungere
        const oldScrollHeight = feed.scrollHeight;
        const oldScrollTop = feed.scrollTop;
        
        hideTopSpinner();
        prependOrAppend(data.events, 'prepend');
        
        // ✅ Mantieni posizione scroll (evita scatti)
        requestAnimationFrame(() => {
          const newScrollHeight = feed.scrollHeight;
          feed.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
        });
        
      } catch (error) {
        console.error('❌ Errore caricamento scroll up:', error);
      } finally {
        hideTopSpinner();
        loadingUp = false;
      }
    }

    // ✅ SCROLL GIÙ: carica eventi PASSATI
    if (nearBottom && !loadingDown && !noMoreDown) {
      loadingDown = true;
      showBottomSpinner();
      
      try {
        console.log('⬇️ Caricamento eventi passati...');
        const data = await API.getDashboardFeed({
          anchor: anchorDown,
          dir: 'down',
          rangeDays: RANGE_DAYS,
          include_done: showCompleted,
          category: currentFilters.category,
          limit: 20
        });
        
        console.log('📊 Eventi passati ricevuti:', data?.events?.length || 0);
        
        if (!data.events || data.events.length === 0) {
          noMoreDown = true;
          console.log('⚠️ Nessun altro evento passato');
          hideBottomSpinner();
          // ✅ Mostra messaggio fine lista
          if (!document.getElementById('endMessage')) {
            const endMsg = document.createElement('div');
            endMsg.id = 'endMessage';
            endMsg.style.cssText = 'padding:20px;text-align:center;color:var(--muted);font-size:12px';
            endMsg.innerHTML = '📭 Fine eventi';
            list.appendChild(endMsg);
          }
          return;
        }
        
        if (data?.meta?.nextAnchorDown) {
          anchorDown = data.meta.nextAnchorDown;
        }
        
        hideBottomSpinner();
        prependOrAppend(data.events, 'append');
        
      } catch (error) {
        console.error('❌ Errore caricamento scroll down:', error);
      } finally {
        hideBottomSpinner();
        loadingDown = false;
      }
    }
  };

  // ✅ Scroll con debounce (evita troppi trigger)
  feed.addEventListener('scroll', () => {
    if (scrollTimeout) clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(handleScroll, 150);
  });
  
  console.log('✅ Scroll handlers configurati');
}

// Azioni rapide già definite altrove (riuso da versione precedente)
window.markEventDone = async function(eventId){
  if (!confirm('Segnare questo evento come completato?')) return;
  try {
    const fd = new FormData(); fd.append('_method','PATCH'); fd.append('status','done'); fd.append('show_in_dashboard','false');
    await API.updateGoogleEvent('primary', eventId, fd);
    
    // ✅ Reset e ricarica usando la funzione centralizzata
    anchorUp = new Date().toISOString(); 
    anchorDown = new Date().toISOString();
    isInitialized = false;
    document.getElementById('eventsList').innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">⏳ Caricamento...</div>';
    await initializeAndLoad();
  } catch(e) { 
    alert('Errore durante l\'aggiornamento'); 
    console.error(e);
  }
};

window.postponeEvent = function(eventId, title){
  // riuso della tua modale già implementata in precedente versione (puoi tenere invariato)
  alert('Rimando rapido invariato — usa la modale esistente');
};

window.viewEventDetails = async function(eventId){
  const now = new Date(); const future = new Date(now.getTime()+90*24*60*60*1000);
  const all = await API.listGoogleEvents('primary', now.toISOString(), future.toISOString());
  const e = all.find(x=>x.id===eventId); if(!e) return alert('Evento non trovato');
  const fcEvent = { id:e.id, title:e.title, start:new Date(e.start), end:e.end?new Date(e.end):null, allDay:!!e.allDay, extendedProps:e.extendedProps||{} };
  const cal = await import('./calendar.js'); cal.showEventModal? cal.showEventModal(fcEvent) : location.hash='#/calendar';
};

// -------------------------
// Helpers Area/Tipo
// -------------------------
async function loadAreaTipoOptions() {
  try {
    const [settoriRes, tipiRes] = await Promise.all([
      fetch('api/settori.php?a=list'),
      fetch('api/tipi_attivita.php?a=list')
    ]);
    const settori = await settoriRes.json().catch(()=>({success:false}));
    const tipi = await tipiRes.json().catch(()=>({success:false}));
    _settori = settori?.success ? (settori.data||[]) : [];
    _tipi = tipi?.success ? (tipi.data||[]) : [];
    _tipoById = new Map(_tipi.map(t => [Number(t.id), t]));
    populateAreaOptions();
    populateTipoOptions();
  } catch (e) {
    console.warn('Errore caricamento Aree/Tipi', e);
  }
}

function populateAreaOptions() {
  const sel = document.getElementById('filterArea');
  if (!sel) return;
  const cur = String(currentFilters.areaId ?? '');
  sel.innerHTML = '<option value="">Tutte</option>' + _settori.map(s => `<option value="${s.id}" ${String(s.id)===cur?'selected':''}>${s.nome}</option>`).join('');
}

function populateTipoOptions() {
  const sel = document.getElementById('filterTipo');
  if (!sel) return;
  const areaVal = document.getElementById('filterArea')?.value || '';
  const selectedAreaId = areaVal ? parseInt(areaVal,10) : null;
  const cur = String(currentFilters.tipoId ?? '');
  let list = _tipi;
  if (selectedAreaId) list = list.filter(t => Number(t.settore_id) === selectedAreaId);
  sel.innerHTML = '<option value="">Tutti</option>' + list.map(t => `<option value="${t.id}" ${String(t.id)===cur?'selected':''}>${t.nome}</option>`).join('');
}

function filterByAreaTipo(items) {
  if ((!currentFilters.areaId && !currentFilters.tipoId)) return items || [];
  return (items || []).filter(e => {
    const entId = e.entity_id ? Number(e.entity_id) : null;
    if (!entId) return false; // se filtro attivo ma evento non ha entity
    const tipo = _tipoById.get(entId);
    if (!tipo) return false;
    if (currentFilters.tipoId && entId !== currentFilters.tipoId) return false;
    if (currentFilters.areaId && Number(tipo.settore_id) !== currentFilters.areaId) return false;
    return true;
  });
}
