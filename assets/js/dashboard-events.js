/**
 * assets/js/dashboard-events.js
 * ✅ Widget eventi dashboard con azioni rapide
 */

import { API } from './api.js';

let currentFilters = {
  type: null,
  category: null
};

export async function renderEventsWidget() {
  const container = document.getElementById('eventsWidget');
  if (!container) return;

  try {
    const data = await API.getDashboardEvents(currentFilters);
    renderWidget(container, data);
  } catch (error) {
    console.error('Errore caricamento eventi:', error);
    container.innerHTML = `
      <div class="card">
        <h3>📅 Prossimi Eventi</h3>
        <p style="color:var(--muted);text-align:center;padding:24px">
          Impossibile caricare gli eventi. Verifica la connessione a Google Calendar.
        </p>
      </div>
    `;
  }
}

async function loadDashboardEvents() {
  const params = { limit: 10 };
  if (currentFilters.type) params.type = currentFilters.type;
  if (currentFilters.category) params.category = currentFilters.category;
  
  return API.getDashboardEvents(params);
}

function renderWidget(container, data) {
  const { events, count } = data;
  
  // Estrai categorie uniche per filtro
  const categories = [...new Set(events.map(e => e.category).filter(Boolean))];
  
  const categoryOptions = categories.length > 0
    ? categories.map(c => `<option value="${c}">${c}</option>`).join('')
    : '<option value="">Nessuna categoria</option>';
  
  container.innerHTML = `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <h3 style="margin:0">📅 Prossimi Eventi (${count})</h3>
        <button class="btn small" onclick="location.hash='#/calendar'">
          Vedi Calendario
        </button>
      </div>
      
      ${categories.length > 0 ? `
      <div class="filter-bar" style="margin-bottom:16px">
        <label>Tipo:</label>
        <select id="filterType" onchange="window.filterEvents()">
          <option value="">Tutti</option>
          <option value="payment">💳 Pagamento</option>
          <option value="maintenance">🔧 Manutenzione</option>
          <option value="document">📄 Documento</option>
          <option value="personal">👤 Personale</option>
        </select>
        
        <label>Categoria:</label>
        <select id="filterCategory" onchange="window.filterEvents()">
          <option value="">Tutte</option>
          ${categoryOptions}
        </select>
      </div>
      ` : ''}
      
      <div id="eventsList">
        ${events.length === 0 ? renderEmptyState() : events.map(renderEventRow).join('')}
      </div>
    </div>
  `;
  
  // Ripristina valori filtri
  if (currentFilters.type) {
    const typeSelect = document.getElementById('filterType');
    if (typeSelect) typeSelect.value = currentFilters.type;
  }
  if (currentFilters.category) {
    const catSelect = document.getElementById('filterCategory');
    if (catSelect) catSelect.value = currentFilters.category;
  }
}

function renderEmptyState() {
  return `
    <div class="no-events" style="padding:48px 24px;text-align:center">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <div style="color:var(--muted)">Nessun evento in programma!</div>
    </div>
  `;
}

function renderEventRow(event) {
  const typeEmoji = {
    payment: '💳',
    maintenance: '🔧',
    document: '📄',
    personal: '👤'
  };
  
  const emoji = typeEmoji[event.type] || '📌';
  const startDate = new Date(event.start);
  const dateStr = startDate.toLocaleDateString('it-IT', { 
    day: 'numeric', 
    month: 'short',
    year: startDate.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
  });
  
  const timeStr = event.allDay 
    ? 'Tutto il giorno'
    : startDate.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
  
  const isOverdue = startDate < new Date();
  const categoryBadge = event.category 
    ? `<span class="category-tag" style="font-size:11px;padding:2px 8px;border-radius:12px;background:#334155;color:#cbd5e1;border:1px solid #475569;margin-left:8px">${event.category}</span>`
    : '';
  
  return `
    <div class="event-row" style="
      display:flex;
      align-items:center;
      gap:12px;
      padding:12px;
      margin-bottom:8px;
      background:${isOverdue ? 'rgba(239,68,68,0.1)' : '#1f2937'};
      border-left:4px solid ${event.color};
      border-radius:8px;
      flex-wrap:wrap
    ">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <span style="font-size:18px">${emoji}</span>
          <strong style="color:var(--fg)">${event.title}</strong>
          ${categoryBadge}
          ${isOverdue ? '<span style="color:#ef4444;font-size:11px;font-weight:600">⚠️ SCADUTO</span>' : ''}
        </div>
        <div style="font-size:12px;color:var(--muted)">
          📅 ${dateStr} ${!event.allDay ? `• 🕐 ${timeStr}` : ''}
        </div>
        ${event.description ? `<div style="font-size:12px;color:var(--muted);margin-top:4px">${event.description.substring(0, 80)}${event.description.length > 80 ? '...' : ''}</div>` : ''}
      </div>
      
      <div class="event-actions" style="display:flex;gap:6px;flex-wrap:wrap">
        <button 
          class="btn success small" 
          onclick="window.markEventDone('${event.id}')"
          title="Segna come completato"
        >
          ✓ Fatto
        </button>
        <button 
          class="btn secondary small" 
          onclick="window.postponeEvent('${event.id}', '${event.title.replace(/'/g, "\\'")}')"
          title="Rimanda"
        >
          ⏸ Rimanda
        </button>
        <button 
          class="btn secondary small icon-only" 
          onclick="window.viewEventDetails('${event.id}')"
          title="Dettagli"
        >
          👁
        </button>
      </div>
    </div>
  `;
}

// ===== AZIONI RAPIDE =====

window.filterEvents = async function() {
  currentFilters.type = document.getElementById('filterType')?.value || null;
  currentFilters.category = document.getElementById('filterCategory')?.value || null;
  await renderEventsWidget();
};

window.markEventDone = async function(eventId) {
  if (!confirm('Segnare questo evento come completato?')) return;
  
  try {
    const fd = new FormData();
    fd.append('_method', 'PATCH');
    fd.append('status', 'done');
    fd.append('show_in_dashboard', 'false');
    
    await API.updateGoogleEvent('primary', eventId, fd);
    await renderEventsWidget();
    showNotification('✅ Evento segnato come completato!', 'success');
  } catch (error) {
    console.error('Errore aggiornamento evento:', error);
    alert('Errore durante l\'aggiornamento. Riprova.');
  }
};

window.postponeEvent = async function(eventId, eventTitle) {
  showPostponeModal(eventId, eventTitle);
};

window.viewEventDetails = async function(eventId) {
  try {
    // Carica tutti gli eventi per trovare quello richiesto
    const now = new Date();
    const future = new Date(now.getTime() + 90 * 24 * 60 * 60 * 1000);
    const allEvents = await API.listGoogleEvents('primary', now.toISOString(), future.toISOString());
    
    const event = allEvents.find(e => e.id === eventId);
    if (!event) {
      alert('Evento non trovato');
      return;
    }
    
    // Converti in formato per modal calendario
    const fcEvent = {
      id: event.id,
      title: event.title,
      start: new Date(event.start),
      end: event.end ? new Date(event.end) : null,
      allDay: event.allDay || false,
      extendedProps: event.extendedProps || {}
    };
    
    // Importa dinamicamente la funzione dal calendario
    const calendarModule = await import('./calendar.js');
    if (calendarModule.showEventModal) {
      calendarModule.showEventModal(fcEvent);
    } else {
      // Fallback: apri calendario
      location.hash = '#/calendar';
    }
  } catch (error) {
    console.error('Errore caricamento evento:', error);
    alert('Impossibile caricare i dettagli dell\'evento');
  }
};

function showPostponeModal(eventId, eventTitle) {
  const modalId = 'postponeModal';
  document.getElementById(modalId)?.remove();
  
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowStr = tomorrow.toISOString().split('T')[0];
  
  const html = `
    <div class="modal" id="${modalId}">
      <div class="modal-content" style="max-width:500px">
        <h2 style="margin-bottom:16px">⏸ Rimanda Evento</h2>
        <p style="color:var(--muted);margin-bottom:20px">
          Seleziona la nuova data per: <strong>${eventTitle}</strong>
        </p>
        
        <div class="form-group">
          <label>Nuova data *</label>
          <input type="date" id="postponeDate" value="${tomorrowStr}" required/>
        </div>
        
        <div class="form-group">
          <label>Suggerimenti rapidi:</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn secondary small" onclick="window.setPostponeDate(1)">
              Domani
            </button>
            <button class="btn secondary small" onclick="window.setPostponeDate(7)">
              +1 settimana
            </button>
            <button class="btn secondary small" onclick="window.setPostponeDate(30)">
              +1 mese
            </button>
          </div>
        </div>
        
        <div class="btn-group" style="margin-top:24px">
          <button class="btn secondary" id="cancelPostpone">Annulla</button>
          <button class="btn" id="confirmPostpone">Conferma</button>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', html);
  
  document.getElementById('cancelPostpone').onclick = () => {
    document.getElementById(modalId).remove();
  };
  
  document.getElementById('confirmPostpone').onclick = async () => {
    const newDate = document.getElementById('postponeDate').value;
    if (!newDate) {
      alert('Seleziona una data');
      return;
    }
    
    try {
      const fd = new FormData();
      fd.append('_method', 'PATCH');
      fd.append('allDay', '1');
      fd.append('startDate', newDate);
      
      // Fine = giorno dopo
      const endDate = new Date(newDate);
      endDate.setDate(endDate.getDate() + 1);
      fd.append('endDate', endDate.toISOString().split('T')[0]);
      
      await API.updateGoogleEvent('primary', eventId, fd);
      document.getElementById(modalId).remove();
      await renderEventsWidget();
      showNotification(`✅ Evento rimandato al ${new Date(newDate).toLocaleDateString('it-IT')}`, 'success');
    } catch (error) {
      console.error('Errore rimando evento:', error);
      alert('Errore durante il rimando. Riprova.');
    }
  };
}

window.setPostponeDate = function(days) {
  const date = new Date();
  date.setDate(date.getDate() + days);
  document.getElementById('postponeDate').value = date.toISOString().split('T')[0];
};

function showNotification(message, type = 'success') {
  const notif = document.createElement('div');
  notif.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${type === 'success' ? 'var(--ok)' : 'var(--danger)'};
    color: white;
    padding: 16px 24px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 10000;
    animation: slideIn 0.3s ease;
  `;
  notif.textContent = message;
  document.body.appendChild(notif);
  
  setTimeout(() => {
    notif.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => notif.remove(), 300);
  }, 3000);
}

// Esporta per riutilizzo
window.renderEventsWidget = renderEventsWidget;
