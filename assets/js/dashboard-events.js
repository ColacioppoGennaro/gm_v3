/**
 * assets/js/dashboard-events.js — lista scorrevole con infinite scroll up/down
 * ✅ Filtri attivi immediatamente (senza bottone Applica)
 * ✅ Bottone "Fatto" con checkmark
 */
import { API } from './api.js';

let currentFilters = { type: null, category: null };
const _allCategories = new Set();

// ancore per lo scroll
let anchorUp = new Date().toISOString();     // per caricare FUTURI quando si scorre in alto
let anchorDown = new Date().toISOString();   // per caricare PASSATI quando si scorre in basso
let loadingUp = false, loadingDown = false;
const RANGE_DAYS = 30; // finestra logica per batch

export async function renderEventsWidget() {
  const container = document.getElementById('eventsWidget');
  if (!container) return;

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
        <div id="eventsList"></div>
        <div id="feedBottomSentinel" style="height:1px"></div>
      </div>
    </div>`;

  // ✅ Eventi onChange immediati per filtri
  document.getElementById('filterType').addEventListener('change', applyFiltersAndReload);
  document.getElementById('filterCategory').addEventListener('change', applyFiltersAndReload);

  await primeLoad();
  setupScrollHandlers();
}

// ✅ Funzione per applicare filtri e ricaricare
async function applyFiltersAndReload() {
  currentFilters.type = document.getElementById('filterType').value || null;
  currentFilters.category = document.getElementById('filterCategory').value || null;
  // reset ancore
  anchorUp = new Date().toISOString();
  anchorDown = new Date().toISOString();
  document.getElementById('eventsList').innerHTML = '';
  await primeLoad();
}

async function primeLoad() {
  // carica un primo blocco FUTURO (pending only)
  const data = await API.getDashboardFeed({
    anchor: anchorUp,
    dir: 'up',
    rangeDays: RANGE_DAYS,
    include_done: false,
    type: currentFilters.type,
    category: currentFilters.category,
    limit: 20
  });
  anchorUp = data?.meta?.nextAnchorUp || anchorUp;
  // aggiorna categorie e render
  (data.events||[]).map(e=>e.category).filter(Boolean).forEach(c=>_allCategories.add(c));
  renderCategories();
  prependOrAppend(data.events||[], 'append');
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
  const isOverdue = startDate < new Date();
  const categoryBadge = event.category ? `<span class="category-tag" style="font-size:11px;padding:2px 8px;border-radius:12px;background:#334155;color:#cbd5e1;border:1px solid #475569;margin-left:8px">${event.category}</span>` : '';
  return `
    <div class="event-row" style="display:flex;align-items:center;gap:12px;padding:12px;margin-bottom:8px;background:${isOverdue?'rgba(239,68,68,0.1)':'#1f2937'};border-left:4px solid ${event.color};border-radius:8px;flex-wrap:wrap">
      <div style="flex:1;min-width:240px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <span style="font-size:18px">${emoji}</span>
          <strong style="color:var(--fg)">${event.title}</strong>
          ${categoryBadge}
          ${isOverdue ? '<span style="color:#ef4444;font-size:11px;font-weight:600">⚠️ SCADUTO</span>' : ''}
        </div>
        <div style="font-size:12px;color:var(--muted)">📅 ${dateStr} ${!event.allDay ? `• 🕐 ${timeStr}` : ''}</div>
        ${event.description ? `<div style=\"font-size:12px;color:var(--muted);margin-top:4px\">${event.description.substring(0,80)}${event.description.length>80?'...':''}</div>` : ''}
      </div>
      <div class="event-actions" style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn success small" style="min-width:96px;white-space:nowrap" onclick="window.markEventDone('${event.id}')" title="Segna come completato">✓ Fatto</button>
        <button class="btn secondary small" style="min-width:96px;white-space:nowrap" onclick="window.postponeEvent('${event.id}', '${(event.title||'').replace(/'/g, "\'")}')" title="Rimanda evento">⏸ Rimanda</button>
        <button class="btn secondary small icon-only" style="min-width:44px" onclick="window.viewEventDetails('${event.id}')" title="Vedi dettagli">👁</button>
      </div>
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
  const TOP_THR = 60, BOT_THR = 60;

  feed.addEventListener('scroll', async () => {
    const nearTop = feed.scrollTop < TOP_THR;
    const nearBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < BOT_THR;

    if (nearTop && !loadingUp) {
      loadingUp = true;
      try {
        const data = await API.getDashboardFeed({
          anchor: anchorUp,
          dir: 'up',
          rangeDays: RANGE_DAYS,
          include_done: false,
          type: currentFilters.type,
          category: currentFilters.category,
          limit: 20
        });
        anchorUp = data?.meta?.nextAnchorUp || anchorUp;
        prependOrAppend(data.events||[], 'prepend');
      } finally { loadingUp = false; }
    }

    if (nearBottom && !loadingDown) {
      loadingDown = true;
      try {
        const data = await API.getDashboardFeed({
          anchor: anchorDown,
          dir: 'down',
          rangeDays: RANGE_DAYS,
          include_done: true, // 👈 giù = includi completati
          type: currentFilters.type,
          category: currentFilters.category,
          limit: 20
        });
        anchorDown = data?.meta?.nextAnchorDown || anchorDown;
        prependOrAppend(data.events||[], 'append');
      } finally { loadingDown = false; }
    }
  });
}

// Azioni rapide già definite altrove (riuso da versione precedente)
window.markEventDone = async function(eventId){
  if (!confirm('Segnare questo evento come completato?')) return;
  try {
    const fd = new FormData(); fd.append('_method','PATCH'); fd.append('status','done'); fd.append('show_in_dashboard','false');
    await API.updateGoogleEvent('primary', eventId, fd);
    // refresh solo parziale: reset down per farlo "sparire" e comparire in feed sotto al prossimo scroll
    anchorUp = new Date().toISOString(); anchorDown = new Date().toISOString();
    document.getElementById('eventsList').innerHTML = '';
    await primeLoad();
  } catch(e) { alert('Errore durante l\'aggiornamento'); }
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
