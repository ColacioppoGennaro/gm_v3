/**
 * assets/js/dashboard-events.js — lista scorrevole con infinite scroll up/down
 * ✅ Filtri attivi immediatamente (senza bottone Applica)
 * ✅ Bottone "Fatto" con checkmark
 * ✅ Ricarica automatica quando si torna alla dashboard
 */
import { API } from './api.js';

let currentFilters = { type: null, category: null };
const _allCategories = new Set();

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

  // shell + filtri + area scrollabile
  container.innerHTML = `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap">
        <h3 style="margin:0">📅 Prossimi Eventi</h3>
        <button class="btn small" onclick="location.hash='#/calendar'">Vedi Calendario</button>
      </div>
      <div class="filter-bar" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <label>Tipo:</label>
        <select id="filterType">
          <option value="">Tutti</option>
          <option value="payment">💳 Pagamento</option>
          <option value="maintenance">🔧 Manutenzione</option>
          <option value="document">📄 Documento</option>
          <option value="personal">👤 Personale</option>
        </select>
        <label>Categoria:</label>
        <select id="filterCategory"><option value="">Tutte</option></select>
      </div>
      <div id="feed" style="max-height:420px;overflow-y:auto;border-radius:8px;background:#0f172a1a;padding:8px">
        <div id="feedTopSentinel" style="height:1px"></div>
        <div id="eventsList"><div style="padding:20px;text-align:center;color:var(--muted)">⏳ Caricamento...</div></div>
        <div id="feedBottomSentinel" style="height:1px"></div>
      </div>
    </div>`;

  // ✅ Aspetta che il DOM sia effettivamente renderizzato
  await new Promise(resolve => setTimeout(resolve, 50));

  // Verifica che gli elementi esistano ancora
  const filterType = document.getElementById('filterType');
  const filterCategory = document.getElementById('filterCategory');
  
  if (!filterType || !filterCategory) {
    console.warn('⚠️ Elementi filtro non trovati, riprovo...');
    setTimeout(() => renderEventsWidget(), 100);
    return;
  }

  // ✅ Eventi onChange immediati per filtri
  filterType.addEventListener('change', applyFiltersAndReload);
  filterCategory.addEventListener('change', applyFiltersAndReload);

  // ✅ Carica immediatamente
  await initializeAndLoad();

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
  const typeVal = document.getElementById('filterType')?.value || '';
  const catVal = document.getElementById('filterCategory')?.value || '';
  
  currentFilters.type = typeVal === '' ? null : typeVal;
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
  
  try {
    // ✅ Carica eventi FUTURI da oggi in avanti (dir=up)
    const data = await API.getDashboardFeed({
      anchor: now,
      dir: 'up',
      rangeDays: RANGE_DAYS,
      include_done: false,
      type: currentFilters.type,
      category: currentFilters.category,
      limit: 20
    });
    
    console.log('📊 Dati ricevuti:', data);
    
    // ✅ Aggiorna ancore
    anchorUp = data?.meta?.nextAnchorUp || anchorUp;
    anchorDown = now; // Per scrollare giù partiamo da oggi
    
    // aggiorna categorie
    (data.events||[]).map(e=>e.category).filter(Boolean).forEach(c=>_allCategories.add(c));
    renderCategories();
    
    // ✅ Pulisci e mostra eventi
    list.innerHTML = '';
    
    if (!data.events || data.events.length === 0) {
      list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">📭 Nessun evento futuro trovato. Scorri in basso per vedere eventi passati.</div>';
    } else {
      const html = data.events.map(eventRowHtml).join('');
      list.innerHTML = html;
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
  const dateStr = startDate.toLocaleDateString('it-IT',{ day:'numeric', month:'short', year: startDate.getFullYear()!==new Date().getFullYear()? 'numeric': undefined });
  const timeStr = event.allDay ? 'Tutto il giorno' : startDate.toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'});
  const isOverdue = startDate < new Date() && event.status !== 'done';
  const isDone = event.status === 'done';
  
  const categoryBadge = event.category ? `<span class="category-tag" style="font-size:11px;padding:2px 8px;border-radius:12px;background:#334155;color:#cbd5e1;border:1px solid #475569;margin-left:8px">${event.category}</span>` : '';
  
  const doneBadge = isDone ? '<span style="color:#10b981;font-size:11px;font-weight:600;margin-left:8px">✅ COMPLETATO</span>' : '';
  
  const overdueBadge = isOverdue ? '<span style="color:#ef4444;font-size:11px;font-weight:600">⚠️ SCADUTO</span>' : '';
  
  // ✅ Nascondi azioni se completato
  const actions = isDone ? '' : `
    <div class="event-actions" style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn success small" style="min-width:96px;white-space:nowrap;font-weight:600" onclick="window.markEventDone('${event.id}')" title="Segna come completato">✅ Fatto</button>
      <button class="btn secondary small" style="min-width:96px;white-space:nowrap" onclick="window.postponeEvent('${event.id}', '${(event.title||'').replace(/'/g, "\'")}')" title="Rimanda evento">⏸ Rimanda</button>
      <button class="btn secondary small icon-only" style="min-width:44px" onclick="window.viewEventDetails('${event.id}')" title="Vedi dettagli">👁</button>
    </div>`;
  
  return `
    <div class="event-row" style="display:flex;align-items:center;gap:12px;padding:12px;margin-bottom:8px;background:${isDone ? 'rgba(16,185,129,0.1)' : isOverdue?'rgba(239,68,68,0.1)':'#1f2937'};border-left:4px solid ${event.color};border-radius:8px;flex-wrap:wrap;opacity:${isDone ? '0.7' : '1'}">
      <div style="flex:1;min-width:240px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
          <span style="font-size:18px">${emoji}</span>
          <strong style="color:var(--fg);${isDone ? 'text-decoration:line-through' : ''}">${event.title}</strong>
          ${categoryBadge}
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
  const html = items.map(eventRowHtml).join('');
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
          include_done: false,
          type: currentFilters.type,
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

    // ✅ SCROLL GIÙ: carica eventi PASSATI (inclusi completati)
    if (nearBottom && !loadingDown && !noMoreDown) {
      loadingDown = true;
      showBottomSpinner();
      
      try {
        console.log('⬇️ Caricamento eventi passati...');
        const data = await API.getDashboardFeed({
          anchor: anchorDown,
          dir: 'down',
          rangeDays: RANGE_DAYS,
          include_done: true,
          type: currentFilters.type,
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
