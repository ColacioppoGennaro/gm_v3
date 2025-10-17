/**
 * assets/js/calendar.js
 * ✅ ENHANCED: Tipizzazione completa con entità e show_in_dashboard
 * ✅ ENHANCED: Integrazione con documento allegato e apertura da AI
 */

import { API } from './api.js';
import { openOrganizeModal } from './settings.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];
let _reminders = [];
let _attendees = [];

const TZ = 'Europe/Rome';

// Tipi evento (SENZA GENERIC)
const EVENT_TYPES = {
  payment: { label: '💳 Pagamento', color: '#dc2127', showEntity: false },
  maintenance: { label: '🔧 Manutenzione', color: '#ffb878', showEntity: true },
  document: { label: '📄 Documento', color: '#5484ed', showEntity: true },
  personal: { label: '👤 Personale', color: '#51b749', showEntity: false }
};

// Tipi entità (placeholder - da caricare da API in futuro)
const ENTITY_TYPES = [
  { id: 'machine_1', type: 'machine', name: 'Compressore A' },
  { id: 'machine_2', type: 'machine', name: 'Tornio B' },
  { id: 'client_1', type: 'client', name: 'Cliente Alfa Srl' },
  { id: 'regulation_1', type: 'regulation', name: 'ISO 9001' }
];

// Mappa colori Google Calendar
const GOOGLE_COLORS = [
  { id: '1', name: 'Lavanda', hex: '#a4bdfc' },
  { id: '2', name: 'Salvia', hex: '#7ae7bf' },
  { id: '3', name: 'Uva', hex: '#dbadff' },
  { id: '4', name: 'Fenicottero', hex: '#ff887c' },
  { id: '5', name: 'Banana', hex: '#fbd75b' },
  { id: '6', name: 'Mandarino', hex: '#ffb878' },
  { id: '7', name: 'Pavone', hex: '#46d6db' },
  { id: '8', name: 'Grafite', hex: '#e1e1e1' },
  { id: '9', name: 'Mirtillo', hex: '#5484ed' },
  { id: '10', name: 'Basilico', hex: '#51b749' },
  { id: '11', name: 'Pomodoro', hex: '#dc2127' }
];

function toLocalRFC3339(dtLocal) {
  if (!dtLocal) return null;
  if (/Z$/.test(dtLocal)) {
    const d = new Date(dtLocal);
    return toRFC3339WithOffset(d);
  }
  const [d, t = '00:00'] = dtLocal.split('T');
  const [Y, M, D] = d.split('-').map(Number);
  const [h, m] = t.split(':').map(Number);
  const local = new Date(Y, M - 1, D, h, m, 0, 0);
  return toRFC3339WithOffset(local);
}

function toRFC3339WithOffset(date) {
  const d = new Date(date);
  const offMin = -d.getTimezoneOffset();
  const sign = offMin >= 0 ? '+' : '-';
  const abs = Math.abs(offMin);
  const oh = String(Math.floor(abs / 60)).padStart(2, '0');
  const om = String(abs % 60).padStart(2, '0');
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const HH = String(d.getHours()).padStart(2, '0');
  const MM = String(d.getMinutes()).padStart(2, '0');
  const SS = String(d.getSeconds()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${HH}:${MM}:${SS}${sign}${oh}:${om}`;
}

// Gestisce upload documenti dal calendario con analisi AI
async function handleCalendarFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  // Verifica dimensione file
  const maxSizeMB = 5; // TODO: prendere da limiti utente
  if (file.size > maxSizeMB * 1024 * 1024) {
    alert(`File troppo grande. Massimo ${maxSizeMB}MB consentiti.`);
    return;
  }
  
  // Mostra loading
  const uploadBtn = document.getElementById('btnUploadNewDoc');
  const photoBtn = document.getElementById('btnTakePhoto');
  const originalUploadText = uploadBtn?.textContent || '';
  const originalPhotoText = photoBtn?.textContent || '';
  
  if (uploadBtn) uploadBtn.innerHTML = '⏳ Caricamento...';
  if (photoBtn) photoBtn.innerHTML = '⏳ Analisi...';
  
  try {
    // Prepara FormData per upload
    const formData = new FormData();
    formData.append('file', file);
    formData.append('analyze_with_ai', 'true'); // Flag per analisi AI
    
    // Upload documento
    const response = await fetch('api/documents.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Documento caricato con successo
      const docId = result.document_id;
      const aiData = result.ai_analysis;
      
      // Aggiorna dropdown documenti
      await loadDocuments();
      
      // Seleziona il documento appena caricato
      const docSelect = document.getElementById('eventDocumentSelect');
      if (docSelect) {
        docSelect.value = docId;
        document.getElementById('eventDocumentId').value = docId;
      }
      
      // Se l'AI ha estratto dati, pre-compila il form
      if (aiData) {
        await fillEventFormFromAI(aiData);
      }
      
      alert('✅ Documento caricato e analizzato con successo!');
      
    } else {
      throw new Error(result.message || 'Errore durante il caricamento');
    }
    
  } catch (error) {
    console.error('Errore upload:', error);
    alert('❌ Errore durante il caricamento: ' + error.message);
  } finally {
    // Ripristina pulsanti
    if (uploadBtn) uploadBtn.innerHTML = originalUploadText;
    if (photoBtn) photoBtn.innerHTML = originalPhotoText;
    
    // Reset input
    event.target.value = '';
  }
}

// Pre-compila il form evento con dati estratti dall'AI
async function fillEventFormFromAI(aiData) {
  try {
    // Titolo evento
    if (aiData.title && !document.getElementById('eventTitle').value) {
      document.getElementById('eventTitle').value = aiData.title;
    }
    
    // Descrizione
    if (aiData.description) {
      const descField = document.getElementById('eventDescription');
      if (descField && !descField.value) {
        descField.value = aiData.description;
      }
    }
    
    // Date (scadenza, promemoria)
    if (aiData.due_date) {
      const startField = document.getElementById('eventStart');
      if (startField && !startField.value) {
        startField.value = aiData.due_date;
      }
    }
    
    if (aiData.reminder_date) {
      const endField = document.getElementById('eventEnd');
      if (endField && !endField.value) {
        endField.value = aiData.reminder_date;
      }
    }
    
    // Categoria se estratta
    if (aiData.category) {
      const categoryField = document.getElementById('eventCategory');
      if (categoryField && !categoryField.value) {
        categoryField.value = aiData.category;
      }
    }
    
    // TODO: Auto-select area/tipo in base ai dati estratti
    
  } catch (error) {
    console.warn('Errore pre-compilazione form:', error);
  }
}

function nextDate(yyyy_mm_dd) {
  const [Y, M, D] = yyyy_mm_dd.split('-').map(Number);
  const d = new Date(Y, M - 1, D);
  d.setDate(d.getDate() + 1);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function formatLocalYMD(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/**
 * Apre modal evento con dati precompilati (da Assistente AI)
 */
export function openEventModal(eventData = null) {
  // Se ci sono dati, precompila il form
  if (eventData) {
    // Apri modal
    showEventModal();

    // Compila campi
    document.getElementById('eventTitle').value = eventData.title || '';
    
    // Per ora non gestisco date/time e settore, perché il formato è diverso
    // document.getElementById('eventDate').value = eventData.date || '';
    // document.getElementById('eventTime').value = eventData.time || '';
    // document.getElementById('eventSettore').value = eventData.settore_id || '';

    // Salva document_id in un campo hidden
    if (eventData.document_id) {
      document.getElementById('eventDocumentId').value = eventData.document_id;
    }
  } else {
    // Modal vuoto per nuovo evento
    showEventModal();
  }
}
window.openEventModal = openEventModal;


export async function renderCalendar() {
  const page = document.querySelector('[data-page="calendar"]');
  if (!page) return;

  const isPro = window.S.user && window.S.user.role === 'pro';
  const isMobile = window.matchMedia('(max-width: 768px)').matches;

  let isGoogleConnected = false;
  try {
    await API.listGoogleEvents('primary', new Date().toISOString(), new Date(Date.now() + 86400000).toISOString());
    isGoogleConnected = true;
  } catch {
    console.log('Google Calendar non collegato');
  }

  page.innerHTML = `
    <h1>📅 Calendario</h1>
    ${!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Piano <b>Free</b>. Clicca per upgrade a Pro</div>' : ''}

    ${!isGoogleConnected ? `
      <div class="card calendar-card-warning">
        <h3>⚠️ Google Calendar non collegato</h3>
        <p style="color:var(--muted);margin:12px 0">Collega il tuo account Google per usare il calendario.</p>
        <a href="google_connect.php" class="btn" style="text-decoration:none">🔗 Collega Google Calendar</a>
      </div>
    ` : ''}

    <div class="card calendar-card">
      <div class="calendar-toolbar">
        ${isGoogleConnected ? '<button id="btnNewEvent" class="btn">＋ Nuovo Evento</button>' : ''}
      </div>
      <div id="cal" class="calendar-root"></div>
    </div>
  `;

  document.getElementById('upgradeBtn3')?.addEventListener('click', () => {
    import('./account.js').then(m => m.showUpgradeModal && m.showUpgradeModal());
  });

  if (!isGoogleConnected) return;

  if (isMobile) {
    await renderMobileCalendar();
  } else {
    initFullCalendar();
  }

  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    const start = new Date(currentDate);
    start.setHours(9, 0, 0, 0);
    const end = new Date(start.getTime() + 60 * 60 * 1000);
    showEventModal(null, start, end);
  });
}

async function renderMobileCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  calEl.innerHTML = `
    <div class="mobile-calendar">
      <div class="mini-month-grid" id="miniMonthGrid"></div>
      
      <div class="day-events-list" id="dayEventsList">
        <div class="day-events-header" id="dayEventsHeader">
          <h3>Oggi</h3>
        </div>
        <div class="day-events-content" id="dayEventsContent">
          <div class="loading">Caricamento eventi...</div>
        </div>
      </div>
    </div>
  `;

  await loadEventsForMonth();
  renderMiniMonthGrid();
  renderDayEvents(currentDate);
}

async function loadEventsForMonth() {
  const start = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
  const end = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
  
  try {
    allEvents = await API.listGoogleEvents(
      'primary',
      start.toISOString(),
      end.toISOString()
    );
  } catch (e) {
    console.error('Errore caricamento eventi:', e);
    allEvents = [];
  }
}

function renderMiniMonthGrid() {
  const gridEl = document.getElementById('miniMonthGrid');
  if (!gridEl) return;

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const today = new Date();
  
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  
  let startDay = firstDay.getDay();
  startDay = startDay === 0 ? 6 : startDay - 1;
  
  const daysInMonth = lastDay.getDate();

  const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                      'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
  
  let html = `
    <div class="mini-month-header">
      <button class="month-nav-btn" id="prevMonth">‹</button>
      <h3>${monthNames[month]} ${year}</h3>
      <button class="month-nav-btn" id="nextMonth">›</button>
    </div>
    <div class="mini-month-days-header">
      <div>L</div><div>M</div><div>M</div><div>G</div><div>V</div><div>S</div><div>D</div>
    </div>
    <div class="mini-month-days">
  `;

  for (let i = 0; i < startDay; i++) {
    html += '<div class="mini-day empty"></div>';
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    const dateStr = formatLocalYMD(date);
    
    const isToday = date.toDateString() === today.toDateString();
    const isSelected = date.toDateString() === currentDate.toDateString();
    
    const dayEvents = allEvents.filter(e => {
      const eventDate = new Date(e.start);
      return eventDate.toDateString() === date.toDateString();
    });
    
    const hasEvents = dayEvents.length > 0;
    const eventDots = hasEvents ? '<div class="event-dots">' + '●'.repeat(Math.min(dayEvents.length, 3)) + '</div>' : '';
    
    html += `
      <div class="mini-day ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''} ${hasEvents ? 'has-events' : ''}" 
           data-date="${dateStr}" 
           onclick="window.selectDate('${dateStr}')">
        <span class="day-number">${day}</span>
        ${eventDots}
      </div>
    `;
  }

  html += '</div>';
  gridEl.innerHTML = html;

  document.getElementById('prevMonth')?.addEventListener('click', () => {
    currentDate = new Date(year, month - 1, 1);
    loadEventsForMonth().then(() => {
      renderMiniMonthGrid();
      renderDayEvents(currentDate);
    });
  });

  document.getElementById('nextMonth')?.addEventListener('click', () => {
    currentDate = new Date(year, month + 1, 1);
    loadEventsForMonth().then(() => {
      renderMiniMonthGrid();
      renderDayEvents(currentDate);
    });
  });
}

window.selectDate = function(dateStr) {
  const [year, month, day] = dateStr.split('-').map(Number);
  currentDate = new Date(year, month - 1, day);
  renderMiniMonthGrid();
  renderDayEvents(currentDate);
};

function renderDayEvents(date) {
  const headerEl = document.getElementById('dayEventsHeader');
  const contentEl = document.getElementById('dayEventsContent');
  if (!headerEl || !contentEl) return;

  const today = new Date();
  const isToday = date.toDateString() === today.toDateString();
  
  const dateStr = date.toLocaleDateString('it-IT', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long' 
  });

  headerEl.innerHTML = `<h3>${isToday ? 'Oggi' : dateStr}</h3>`;

  const dayEvents = allEvents.filter(e => {
    const eventDate = new Date(e.start);
    return eventDate.toDateString() === date.toDateString();
  }).sort((a, b) => new Date(a.start) - new Date(b.start));

  if (dayEvents.length === 0) {
    contentEl.innerHTML = `
      <div class="no-events">
        <div style="font-size:48px;margin-bottom:12px">📭</div>
        <div>Nessun evento</div>
      </div>
    `;
    return;
  }

  contentEl.innerHTML = dayEvents.map(event => {
    const startTime = new Date(event.start).toLocaleTimeString('it-IT', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
    
    const endTime = event.end ? new Date(event.end).toLocaleTimeString('it-IT', { 
      hour: '2-digit', 
      minute: '2-digit' 
    }) : '';

    const timeStr = event.allDay ? 'Tutto il giorno' : `${startTime}${endTime ? ' - ' + endTime : ''}`;
    const bgColor = event.backgroundColor || 'var(--accent)';
    
    const eventType = event.extendedProps?.type || 'personal';
    const typeInfo = EVENT_TYPES[eventType] || EVENT_TYPES.personal;
    const typeChip = `<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;background:${typeInfo.color};color:white;margin-left:8px">${typeInfo.label}</span>`;

    return `
      <div class="event-item" onclick="window.openEventDetail('${event.id}')" style="border-left-color:${bgColor}">
        <div class="event-time">${timeStr}</div>
        <div class="event-details">
          <div class="event-title">${event.title} ${typeChip}</div>
          ${event.extendedProps?.description ? `<div class="event-description">${event.extendedProps.description}</div>` : ''}
        </div>
      </div>
    `;
  }).join('');
}

window.openEventDetail = async function(eventId) {
  const event = allEvents.find(e => e.id === eventId);
  if (!event) return;
  
  const fcEvent = {
    id: event.id,
    title: event.title,
    start: new Date(event.start),
    end: event.end ? new Date(event.end) : null,
    allDay: event.allDay || false,
    extendedProps: event.extendedProps || {}
  };
  
  showEventModal(fcEvent);
};

function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    locale: 'it',
    headerToolbar: { 
      left: 'prev,next today', 
      center: 'title', 
      right: 'dayGridMonth,timeGridWeek,timeGridDay' 
    },
    buttonText: { today: 'Oggi', month: 'Mese', week: 'Settimana', day: 'Giorno' },
    height: '80vh',
    nowIndicator: true,
    selectable: true,
    editable: true,
    events: async (info, success, failure) => {
      try {
        const events = await API.listGoogleEvents('primary', info.startStr, info.endStr);
        success(events);
      } catch (e) {
        console.error('❌ Errore caricamento eventi:', e);
        failure(e);
      }
    },
    select: (info) => {
      showEventModal(null, info.start, info.end);
      calendar.unselect();
    },
    eventClick: (info) => {
      showEventModal(info.event);
    },
    eventDrop: async (info) => {
      try {
        const allDay = info.event.allDay === true;
        const fd = new FormData();
        if (allDay) {
          fd.append('allDay', '1');
          fd.append('startDate', info.event.startStr.slice(0, 10));
          fd.append('endDate', info.event.endStr ? info.event.endStr.slice(0, 10) : info.event.startStr.slice(0, 10));
        } else {
          fd.append('allDay', '0');
          fd.append('startDateTime', toLocalRFC3339(info.event.start.toISOString()));
          fd.append('endDateTime', toLocalRFC3339((info.event.end || info.event.start).toISOString()));
          fd.append('timeZone', TZ);
        }
        await API.updateGoogleEvent('primary', info.event.id, fd);
      } catch (e) {
        info.revert();
      }
    }
  });

  calendar.render();
}

function showEventModal(event = null, startDate = null, endDate = null) {
  console.log('🚀 showEventModal chiamata', { event: !!event, startDate, endDate });
  const isEdit = !!event;
  const modalId = 'eventModal';
  document.getElementById(modalId)?.remove();
  _reminders = [];
  _attendees = [];

  const title = event?.title || '';
  const description = event?.extendedProps?.description || '';
  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;
  
  const eventType = event?.extendedProps?.type || 'personal';
  const eventStatus = event?.extendedProps?.status || 'pending';
  const entityId = event?.extendedProps?.entity_id || '';
  const eventCategory = event?.extendedProps?.category || '';
  const showInDashboard = event?.extendedProps?.show_in_dashboard !== false;
  const documentId = event?.extendedProps?.document_id || '';


  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  const colorOptions = GOOGLE_COLORS.map(c => 
    `<option value="${c.id}" style="background:${c.hex}">${c.name}</option>`
  ).join('');
  
  const typeOptions = Object.entries(EVENT_TYPES).map(([key, val]) =>
    `<option value="${key}" ${key === eventType ? 'selected' : ''}>${val.label}</option>`
  ).join('');
  
  const entityOptions = ENTITY_TYPES.map(e =>
    `<option value="${e.id}" ${e.id === entityId ? 'selected' : ''}>${e.name} (${e.type})</option>`
  ).join('');

  const html = `<div class="modal" id="${modalId}">
    <div class="modal-content" style="max-height:90vh;overflow-y:auto">
      <h2 style="margin-bottom:16px">${isEdit ? '✏️ Modifica Evento' : '➕ Nuovo Evento'}</h2>
      
      <div class="form-group">
        <label>Titolo *</label>
        <input type="text" id="eventTitle" value="${title}" placeholder="Titolo evento" required/>
      </div>
      
      <div class="form-group">
        <label>Descrizione</label>
        <textarea id="eventDescription" placeholder="Descrizione opzionale" rows="3">${description}</textarea>
      </div>

      <div id="eventDocumentSection" class="form-group ${isEdit ? (documentId ? '' : 'hidden') : ''}">
          <label>📎 Documento Allegato ${isEdit ? '' : '(opzionale)'}</label>
          ${isEdit ? `
          <div style="display:flex;gap:8px;align-items:center">
              <span id="eventDocumentName" style="flex:1;font-size:13px"></span>
              <button type="button" class="btn small secondary" id="btnViewDocument">
                  👁️ Visualizza
              </button>
              <button type="button" class="btn small secondary" id="btnDownloadDocument">
                  ⬇️ Scarica
              </button>
          </div>
          ` : `
          <select id="eventDocumentSelect" style="width:100%">
              <option value="">Nessun documento</option>
          </select>
          <div style="display:flex;gap:8px;margin-top:8px">
              <button type="button" class="btn small secondary" id="btnUploadNewDoc">
                  📎 Carica nuovo
              </button>
              <button type="button" class="btn small secondary" id="btnTakePhoto">
                  📷 Foto
              </button>
          </div>
          <small style="color:var(--muted);display:block;margin-top:4px">
              Collega un documento esistente o carica nuovo con analisi AI automatica
          </small>
          `}
      </div>
      
      <div class="form-group">
        <label>🏷️ Tipo evento *</label>
        <select id="eventType" required>
          ${typeOptions}
        </select>
        <small style="color:var(--muted);display:block;margin-top:4px">
          Seleziona il tipo di evento (campo obbligatorio)
        </small>
      </div>
      
      <div class="form-group" id="entityGroup" style="display:none">
        <label>🔗 Collega a entità</label>
        <select id="eventEntity">
          <option value="">Nessuno</option>
          ${entityOptions}
        </select>
        <small style="color:var(--muted);display:block;margin-top:4px">
          Opzionale: collega questo evento a una macchina, cliente o regolamento
        </small>
      </div>
      
      <div class="form-group">
        <label>🏷️ Categoria (opzionale)</label>
        <input type="text" id="eventCategory" value="${eventCategory}"
               placeholder="es: bolletta, multa, tagliando, assicurazione"
               list="categoryDatalist"/>
        <datalist id="categoryDatalist">
          <option value="bolletta">
          <option value="multa">
          <option value="tagliando">
          <option value="assicurazione">
          <option value="scadenza">
          <option value="rinnovo">
        </datalist>
        <small style="color:var(--muted);display:block;margin-top:4px">
          Etichetta libera per filtrare eventi simili
        </small>
      </div>
      
      ${isEdit ? `
      <div class="form-group">
        <label>Stato</label>
        <select id="eventStatus">
          <option value="pending" ${eventStatus === 'pending' ? 'selected' : ''}>⏳ Da fare</option>
          <option value="done" ${eventStatus === 'done' ? 'selected' : ''}>✅ Completato</option>
        </select>
      </div>
      ` : ''}
      
      <div class="settings-row settings-row--compact">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="showInDashboard" ${showInDashboard ? 'checked' : ''} style="width:auto;margin:0"/>
          <span>📊 Mostra nella Dashboard</span>
        </label>
      </div>
      
      <div class="settings-row settings-row--compact">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="eventAllDay" ${allDay ? 'checked' : ''} style="width:auto;margin:0"/>
          <span>Tutto il giorno</span>
        </label>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Inizio *</label>
          <input type="${allDay ? 'date' : 'datetime-local'}" id="eventStart"
                 value="${allDay ? formatDateLocal(start) : formatDateTimeLocal(start)}" required/>
        </div>
        <div class="form-group">
          <label>Fine *</label>
          <input type="${allDay ? 'date' : 'datetime-local'}" id="eventEnd"
                 value="${allDay ? formatDateLocal(end) : formatDateTimeLocal(end)}" required/>
        </div>
      </div>
      
      <div class="form-group">
        <label>🎨 Colore evento</label>
        <select id="eventColor">
          <option value="">Predefinito</option>
          ${colorOptions}
        </select>
      </div>
      
      <div class="form-group">
        <label>Ricorrenza</label>
        <select id="eventRecurrence">
          <option value="none">Non ripetere</option>
          <option value="DAILY">Ogni giorno</option>
          <option value="WEEKLY">Ogni settimana</option>
          <option value="MONTHLY">Ogni mese</option>
          <option value="YEARLY">Ogni anno</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>👥 Invita persone</label>
        <div id="attendeesList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="email" id="attendeeEmail" placeholder="email@esempio.com" style="flex:1"/>
          <button class="btn secondary" id="addAttendeeBtn" type="button" style="padding:8px 12px">+ Aggiungi</button>
        </div>
      </div>
      
      <div class="form-group">
        <label>🔔 Promemoria</label>
        <div id="reminderList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="number" id="reminderValue" min="1" value="30" style="width:70px;" placeholder="30"/>
          <select id="reminderUnit" style="flex:1;min-width:120px;">
            <option value="minutes">Minuti prima</option>
            <option value="hours">Ore prima</option>
            <option value="days">Giorni prima</option>
            <option value="weeks">Settimane prima</option>
            <option value="months">Mesi prima</option>
          </select>
          <select id="reminderMethod" style="flex:1;min-width:100px;">
            <option value="popup">Notifica</option>
            <option value="email">Email</option>
          </select>
          <button class="btn secondary" id="addReminderBtn" type="button" style="padding:8px 12px">+ Aggiungi</button>
        </div>
      </div>
      
      <div id="eventError" class="error hidden"></div>
      <div class="btn-group" style="margin-top:20px">
        <button class="btn secondary" id="closeEventModal">Annulla</button>
        ${isEdit ? '<button class="btn del" id="deleteEventBtn">🗑️ Elimina</button>' : ''}
        <button class="btn" id="saveEventBtn">${isEdit ? 'Salva' : 'Crea'}</button>
      </div>
      <input type="hidden" id="eventDocumentId" value="${documentId}"/>
      
      <!-- Input nascosti per upload documenti -->
      <input type="file" id="calendarFileInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none">
      <input type="file" id="calendarCameraInput" accept="image/*" capture="camera" style="display:none">
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);

  // --- Nuovi campi Area/Tipo/Categoria ---
  console.log('🔧 Inizializzando sezione Area/Tipo...');
  try {
    const typeSelect = document.getElementById('eventType');
    const entityGroup = document.getElementById('entityGroup');
    console.log('🔧 Elementi trovati:', { typeSelect: !!typeSelect, entityGroup: !!entityGroup });
    // Nascondi i vecchi campi (manteniamo value per compatibilità)
    if (typeSelect) {
      typeSelect.value = 'personal';
      const fg = typeSelect.closest('.form-group');
      if (fg) fg.style.display = 'none';
    }
    if (entityGroup) entityGroup.style.display = 'none';

    // Inserisci Area/Tipo sopra la categoria
    const catInput = document.getElementById('eventCategory');
    const catGroup = catInput?.closest('.form-group');
    if (catGroup) {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = `
        <div class="form-group">
          <label>Area *</label>
          <div style="display:flex;gap:8px;align-items:center">
            <select id="eventArea"><option value="">Seleziona...</option></select>
            <button type="button" class="btn small secondary" id="btnOrganizeAreaTipo">Organizza</button>
          </div>
        </div>
        <div class="form-group" style="display:block !important;">
          <label>Tipo *</label>
          <select id="eventTipoSelect" style="display:block !important; width:100%;padding:8px;border:1px solid #374151;border-radius:6px;background:var(--card);color:var(--text);"><option value="">Seleziona...</option></select>
          <small style="color:var(--muted);display:block;margin-top:4px">I tipi dipendono dall'area selezionata</small>
        </div>`;
      catGroup.parentNode.insertBefore(wrapper, catGroup);
    }

    // Carica Aree/Tipi (DOPO aver inserito l'HTML!)
    const areaSel = document.getElementById('eventArea');
    const tipoSel = document.getElementById('eventTipoSelect');
    const organizeBtn = document.getElementById('btnOrganizeAreaTipo');
    
    console.log('🎯 Elementi DOM trovati:', { 
      areaSel: !!areaSel, 
      tipoSel: !!tipoSel, 
      organizeBtn: !!organizeBtn,
      tipoSelVisible: tipoSel ? tipoSel.offsetParent !== null : false
    });

    let _settori = [];
    let _tipi = [];
    let _cats = [];

    async function loadAreaTipo() {
      try {
        console.log('📡 Chiamando API settori e tipi...');
        const [settoriRes, tipiRes] = await Promise.all([
          fetch('api/settori.php?a=list'),
          fetch('api/tipi_attivita.php?a=list')
        ]);
        console.log('📡 Risposte ricevute:', { settoriStatus: settoriRes.status, tipiStatus: tipiRes.status });
        
        const settori = await settoriRes.json().catch((e)=>{
          console.error('Errore parsing settori:', e);
          return {success:false};
        });
        const tipi = await tipiRes.json().catch((e)=>{
          console.error('Errore parsing tipi:', e);
          return {success:false};
        });
        
        console.log('📋 Dati ricevuti:', { settori: settori, tipi: tipi });
        
        if (!settori?.success || !tipi?.success) {
          console.error('❌ Errore caricamento dati:', { 
            settori: settori?.success, 
            tipi: tipi?.success,
            settoriMsg: settori?.message,
            tipiMsg: tipi?.message
          });
          return;
        }
        
        _settori = settori.data || [];
        _tipi = tipi.data || [];
        
        console.log('Dati caricati:', { settori: _settori.length, tipi: _tipi.length });
        console.log('🏢 Settori disponibili:', _settori.map(s => ({id: s.id, nome: s.nome})));
        
        // Popola area
        if (areaSel) {
          const current = areaSel.value || '';
          areaSel.innerHTML = '<option value="">Seleziona...</option>' + _settori.map(s=>`<option value="${s.id}">${s.nome}</option>`).join('');
          if (current) areaSel.value = current;
        }
        
        // Popola tipi (questo è il punto cruciale)
        populateTipi();

        // Se l'evento ha già entity_id, seleziona area e tipo coerenti
        if (tipoSel && entityId) {
          const t = _tipi.find(x => String(x.id) === String(entityId));
          if (t) {
            if (areaSel) areaSel.value = String(t.settore_id);
            populateTipi();
            tipoSel.value = String(t.id);
          }
        }

        // carica categorie per tipo corrente
        if (tipoSel && tipoSel.value) {
          await loadCategoriesForTipo(tipoSel.value);
        }
      } catch (e) { 
        console.error('Errore loadAreaTipo:', e); 
      }
    }

    function populateTipi() {
      if (!tipoSel) {
        console.warn('tipoSel non trovato');
        return;
      }
      
      console.log('🔍 Popolamento tipi su elemento:', tipoSel.id, 'Visible:', tipoSel.offsetParent !== null);
      
      const selArea = areaSel?.value ? Number(areaSel.value) : null;
      let list = _tipi || [];
      
      console.log('populateTipi - Area selezionata:', selArea, 'Tipi totali:', list.length);
      console.log('Tutti i tipi disponibili:', list.map(t => ({id: t.id, nome: t.nome, settore_id: t.settore_id})));
      
      if (selArea) {
        list = list.filter(t => Number(t.settore_id) === selArea);
        console.log('Tipi filtrati per area', selArea, ':', list.length, list.map(t => t.nome));
      } else {
        console.log('Nessuna area selezionata, mostrando tutti i tipi');
        // Mostra tutti i tipi se nessuna area è selezionata
      }
      
      const cur = tipoSel.value || '';
      
      // Se nessun tipo per quest'area: crea i 4 default e ricarica
      if (list.length === 0 && selArea) {
        console.log('Nessun tipo trovato per area', selArea, '- creando tipi default');
        seedDefaultTypesForArea(selArea).then(() => loadAreaTipo());
        return;
      }
      
      // Popola select con i tipi disponibili
      const optionsHtml = '<option value="">Seleziona...</option>' + list.map(t=>`<option value="${t.id}">${t.nome}</option>`).join('');
      console.log('🎯 Prima di popolare - innerHTML attuale:', tipoSel.innerHTML);
      tipoSel.innerHTML = optionsHtml;
      console.log('🎯 Dopo popolamento - innerHTML nuovo:', tipoSel.innerHTML);
      console.log('🎯 Numero opzioni nel DOM:', tipoSel.options.length);
      
      if (cur && Array.from(tipoSel.options).some(o=>o.value===cur)) {
        tipoSel.value = cur;
      } else if (!cur && list.length > 0) {
        // seleziona il primo tipo disponibile per evitare lista vuota
        tipoSel.value = String(list[0].id);
      }
      
      console.log('Valore finale tipoSel:', tipoSel.value);
    }

    areaSel?.addEventListener('change', async () => { 
      console.log('🏢 Area cambiata:', areaSel.value, 'Nome:', areaSel.options[areaSel.selectedIndex]?.text);
      populateTipi(); 
      await loadCategoriesForTipo(''); 
    });
    
    tipoSel?.addEventListener('change', async () => { 
      console.log('Tipo cambiato:', tipoSel.value);
      await loadCategoriesForTipo(tipoSel.value); 
    });
    
    organizeBtn?.addEventListener('click', () => openOrganizeModal());
    
    // Carica i dati iniziali
    console.log('🚀 Iniziando caricamento Area/Tipo...');
    loadAreaTipo().catch(e => console.error('❌ Errore caricamento iniziale:', e));
    
    // Carica documenti se è un nuovo evento e il select esiste
    if (!isEdit && document.getElementById('eventDocumentSelect')) {
      loadDocuments();
    }

    // Aggiorna select se cambia la tassonomia da Organizza
    window.addEventListener('gm:taxonomyChanged', async () => {
      await loadAreaTipo();
    });

    async function seedDefaultTypesForArea(settoreId){
      try{
        const defaults = [
          { nome:'documenti', ico:'📄', doc:1 },
          { nome:'macchina',  ico:'🛠️', doc:1 },
          { nome:'pagamenti', ico:'💳', doc:0 },
          { nome:'personale', ico:'👤', doc:0 }
        ];
        for (const d of defaults){
          await fetch('api/tipi_attivita.php?a=create', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ settore_id: Number(settoreId), nome: d.nome, icona: d.ico, puo_collegare_documento: d.doc?1:0 })
          }).then(r=>r.json()).catch(()=>null);
        }
      }catch(e){ console.warn('seedDefaultTypesForArea failed', e); }
    }

    async function loadCategoriesForTipo(tipoId) {
      try {
        _cats = [];
        const dl = document.getElementById('categoryDatalist');
        if (dl) dl.innerHTML = '';
        tipoId = parseInt(tipoId||'0',10);
        if (!tipoId) return;
        const res = await fetch(`api/event_categories.php?a=list&tipo_id=${encodeURIComponent(tipoId)}`);
        const js = await res.json();
        if (js?.success) {
          _cats = js.data || [];
          if (dl) dl.innerHTML = _cats.map(c => `<option value="${c.nome}">`).join('');
        }
      } catch(e){ console.warn('loadCategoriesForTipo failed', e); }
    }

    async function loadDocuments() {
      try {
        const response = await fetch('api/documents.php?a=list');
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
          const select = document.getElementById('eventDocumentSelect');
          if (select) {
            const options = data.data.map(doc => 
              `<option value="${doc.id}">${doc.file_name} (${doc.category || 'Senza categoria'})</option>`
            ).join('');
            
            select.innerHTML = '<option value="">Nessun documento</option>' + options;
            
            // Gestisci la selezione del documento
            select.addEventListener('change', (e) => {
              document.getElementById('eventDocumentId').value = e.target.value;
            });
          }
        } else {
          console.warn('Nessun documento disponibile o errore API:', data.message);
        }
      } catch (e) {
        console.warn('Errore caricamento documenti:', e.message);
        // Non bloccare il resto dell'applicazione se i documenti non si caricano
      }
    }

    window.__gmv3_getCurrentEventCats = () => (_cats || []).map(c=>c.nome);
    window.__gmv3_loadCatsForTipo = loadCategoriesForTipo;
  } catch(e) { 
    console.error('❌ Setup Area/Tipo fallito:', e);
    console.error('❌ Stack trace:', e.stack);
  }
  
  const attendeesListEl = document.getElementById('attendeesList');
  
  function renderAttendeeChips() {
    attendeesListEl.innerHTML = _attendees.map((email, i) =>
      `<span class="chip" data-idx="${i}">
         👤 ${email}
         <button type="button" class="chip-x">✕</button>
       </span>`
    ).join('');
  }
  
  attendeesListEl.addEventListener('click', (e) => {
    if (e.target.classList.contains('chip-x')) {
      const idx = e.target.closest('.chip').dataset.idx;
      _attendees.splice(idx, 1);
      renderAttendeeChips();
    }
  });

  document.getElementById('addAttendeeBtn').addEventListener('click', () => {
    const emailInput = document.getElementById('attendeeEmail');
    const email = emailInput.value.trim();
    if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      if (!_attendees.includes(email)) {
        _attendees.push(email);
        renderAttendeeChips();
        emailInput.value = '';
      }
    } else {
      alert('Inserisci un\'email valida');
    }
  });

  const reminderListEl = document.getElementById('reminderList');
  
  function renderReminderChips() {
    reminderListEl.innerHTML = _reminders.map((r, i) => {
      const minutes = r.minutes;
      let label = '';
      if (minutes < 60) label = `${minutes} min`;
      else if (minutes < 1440) label = `${minutes/60} ore`;
      else if (minutes < 10080) label = `${minutes/1440} giorni`;
      else if (minutes < 43200) label = `${minutes/10080} settimane`;
      else label = `${Math.round(minutes/43200)} mesi`;
      
      return `<span class="chip" data-idx="${i}">
         ${r.method === 'email' ? '📧' : '🔔'} ${label}
         <button type="button" class="chip-x">✕</button>
       </span>`;
    }).join('');
  }
  
  reminderListEl.addEventListener('click', (e) => {
    if (e.target.classList.contains('chip-x')) {
      const idx = e.target.closest('.chip').dataset.idx;
      _reminders.splice(idx, 1);
      renderReminderChips();
    }
  });

  document.getElementById('addReminderBtn').addEventListener('click', () => {
    const value = Math.max(1, Number(document.getElementById('reminderValue').value || 1));
    const unit = document.getElementById('reminderUnit').value;
    const method = document.getElementById('reminderMethod').value;
    
    let minutes = value;
    if (unit === 'hours') minutes = value * 60;
    else if (unit === 'days') minutes = value * 1440;
    else if (unit === 'weeks') minutes = value * 10080;
    else if (unit === 'months') minutes = value * 43200;
    
    if (_reminders.length < 5) {
      _reminders.push({ method, minutes });
      renderReminderChips();
    } else {
      alert('Massimo 5 promemoria per evento');
    }
  });

  if (isEdit && event.extendedProps) {
    const colorId = event.extendedProps.colorId;
    if (colorId) document.getElementById('eventColor').value = colorId;
    
    const recurSel = document.getElementById('eventRecurrence');
    const recurrence = event.extendedProps.recurrence;
    if (recurrence && recurrence.length > 0) {
      const rrule = recurrence[0];
      if (rrule.includes('FREQ=DAILY')) recurSel.value = 'DAILY';
      else if (rrule.includes('FREQ=WEEKLY')) recurSel.value = 'WEEKLY';
      else if (rrule.includes('FREQ=MONTHLY')) recurSel.value = 'MONTHLY';
      else if (rrule.includes('FREQ=YEARLY')) recurSel.value = 'YEARLY';
    }
    
    const rem = event.extendedProps.reminders;
    if (rem?.overrides && Array.isArray(rem.overrides)) {
      _reminders = rem.overrides.map(r => ({ 
        method: r.method || 'popup', 
        minutes: Number(r.minutes || 30) 
      }));
      renderReminderChips();
    }
    
    const attendees = event.extendedProps.attendees;
    if (attendees && Array.isArray(attendees)) {
      _attendees = attendees.map(a => a.email);
      renderAttendeeChips();
    }

    // Aggiungi logica per mostrare documento quando evento viene caricato
    const docId = event.extendedProps?.document_id;
    if (docId) {
      document.getElementById('eventDocumentSection').classList.remove('hidden');
      document.getElementById('eventDocumentId').value = docId;

      fetch(`api/documents.php?action=get&id=${docId}`)
          .then(r => r.json())
          .then(data => {
              if (data.success && data.document) {
                  document.getElementById('eventDocumentName').textContent = data.document.original_name;
                  document.getElementById('btnViewDocument').onclick = () => {
                      window.open(`uploads/${data.document.filename}`, '_blank');
                  };
                  document.getElementById('btnDownloadDocument').onclick = () => {
                      const a = document.createElement('a');
                      a.href = `uploads/${data.document.filename}`;
                      a.download = data.document.original_name;
                      a.click();
                  };
              }
          })
          .catch(err => console.error('Errore caricamento documento:', err));
    } else {
        document.getElementById('eventDocumentSection').classList.add('hidden');
    }
  }

  document.getElementById('closeEventModal').onclick = () => document.getElementById(modalId).remove();
  document.getElementById('saveEventBtn').onclick = () => isEdit ? updateEvent(event) : createEvent();
  if (isEdit) document.getElementById('deleteEventBtn').onclick = () => deleteEvent(event);
  
  // Event listeners per upload documenti
  document.getElementById('btnUploadNewDoc')?.addEventListener('click', () => {
    document.getElementById('calendarFileInput').click();
  });
  
  document.getElementById('btnTakePhoto')?.addEventListener('click', () => {
    document.getElementById('calendarCameraInput').click();
  });
  
  document.getElementById('calendarFileInput')?.addEventListener('change', handleCalendarFileUpload);
  document.getElementById('calendarCameraInput')?.addEventListener('change', handleCalendarFileUpload);
  
  document.getElementById('eventAllDay').addEventListener('change', (e) => {
    const isAllDay = e.target.checked;
    const startInput = document.getElementById('eventStart');
    const endInput = document.getElementById('eventEnd');
    
    if (isAllDay) {
      const startVal = startInput.value.split('T')[0];
      const endVal = endInput.value.split('T')[0];
      startInput.type = 'date';
      endInput.type = 'date';
      startInput.value = startVal;
      endInput.value = endVal;
    } else {
      startInput.type = 'datetime-local';
      endInput.type = 'datetime-local';
      const now = new Date();
      startInput.value = formatDateTimeLocal(now);
      endInput.value = formatDateTimeLocal(new Date(now.getTime() + 3600000));
    }
  });
}

async function createEvent() {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value;
  const end = document.getElementById('eventEnd').value;
  const colorId = document.getElementById('eventColor').value;
  const eventType = document.getElementById('eventType')?.value || 'personal';
  const tipoAttivitaId = document.getElementById('eventTipoSelect')?.value || '';
  const entityId = document.getElementById('eventEntity')?.value || '';
  const eventCategory = document.getElementById('eventCategory')?.value.trim() || '';
  const showInDashboard = document.getElementById('showInDashboard')?.checked !== false;
  const documentId = document.getElementById('eventDocumentId')?.value || '';


  if (!title || !start || !end) {
    return alert('Compila tutti i campi obbligatori (titolo e date)');
  }
  if (!tipoAttivitaId) {
    return alert('Seleziona Area e Tipo');
  }

  // Se categoria è testo libero: prova a crearla se non esiste e c'è spazio (<50)
  const areaSelVal = document.getElementById('eventArea')?.value || '';
  const tipoSelVal = document.getElementById('eventTipoSelect')?.value || '';
  if (eventCategory && tipoSelVal) {
    await ensureEventCategoryExists(tipoSelVal, eventCategory);
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('description', description || '');
  fd.append('type', eventType);
  fd.append('status', 'pending');
  fd.append('trigger', 'manual');
  if (tipoAttivitaId) fd.append('tipo_attivita_id', tipoAttivitaId);
  if (entityId) fd.append('entity_id', entityId);
  if (eventCategory) fd.append('category', eventCategory);
  if (documentId) fd.append('document_id', documentId);
  fd.append('show_in_dashboard', showInDashboard ? 'true' : 'false');
  
  if (allDay) {
    fd.append('allDay', '1');
    fd.append('startDate', start);
    fd.append('endDate', nextDate(end));
  } else {
    fd.append('allDay', '0');
    fd.append('startDateTime', toLocalRFC3339(start));
    fd.append('endDateTime', toLocalRFC3339(end));
    fd.append('timeZone', TZ);
  }
  
  if (colorId) fd.append('colorId', colorId);
  
  const recur = document.getElementById('eventRecurrence')?.value;
  if (recur && recur !== 'none') {
    fd.append('recurrence', `FREQ=${recur}`);
  }
  
  if (_reminders.length > 0) {
    fd.append('reminders', JSON.stringify(_reminders));
  }
  
  if (_attendees.length > 0) {
    fd.append('attendees', _attendees.join(','));
  }

  try {
    await API.createGoogleEvent('primary', fd);
    if (calendar) calendar.refetchEvents();
    await loadEventsForMonth();
    renderMiniMonthGrid();
    renderDayEvents(currentDate);
    document.getElementById('eventModal').remove();
  } catch (e) {
    alert('Errore nella creazione dell\'evento');
  }
}

async function updateEvent(event) {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value;
  const end = document.getElementById('eventEnd').value;
  const colorId = document.getElementById('eventColor').value;
  const eventType = document.getElementById('eventType')?.value || 'personal';
  const eventStatus = document.getElementById('eventStatus')?.value || 'pending';
  const tipoAttivitaId = document.getElementById('eventTipoSelect')?.value || '';
  const entityId = document.getElementById('eventEntity')?.value || '';
  const eventCategory = document.getElementById('eventCategory')?.value.trim() || '';
  const showInDashboard = document.getElementById('showInDashboard')?.checked !== false;
  const documentId = document.getElementById('eventDocumentId')?.value || '';


  if (!title) {
    return alert('Inserisci un titolo');
  }
  if (!tipoAttivitaId) {
    return alert('Seleziona Area e Tipo');
  }

  const areaSelVal2 = document.getElementById('eventArea')?.value || '';
  const tipoSelVal2 = document.getElementById('eventTipoSelect')?.value || '';
  if (eventCategory && tipoSelVal2) {
    await ensureEventCategoryExists(tipoSelVal2, eventCategory);
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('description', description || '');
  fd.append('type', eventType);
  fd.append('status', eventStatus);
  if (tipoAttivitaId) fd.append('tipo_attivita_id', tipoAttivitaId);
  if (entityId) fd.append('entity_id', entityId);
  if (eventCategory) fd.append('category', eventCategory);
  if (documentId) fd.append('document_id', documentId);
  fd.append('show_in_dashboard', showInDashboard ? 'true' : 'false');
  
  if (allDay) {
    fd.append('allDay', '1');
    fd.append('startDate', start);
    fd.append('endDate', nextDate(end));
  } else {
    fd.append('allDay', '0');
    fd.append('startDateTime', toLocalRFC3339(start));
    fd.append('endDateTime', toLocalRFC3339(end));
    fd.append('timeZone', TZ);
  }
  
  fd.append('colorId', colorId || '');
  
  const recur = document.getElementById('eventRecurrence')?.value;
  if (recur && recur !== 'none') {
    fd.append('recurrence', `FREQ=${recur}`);
  }
  
  if (_reminders.length > 0) {
    fd.append('reminders', JSON.stringify(_reminders));
  }
  
  if (_attendees.length > 0) {
    fd.append('attendees', _attendees.join(','));
  }

  try {
    await API.updateGoogleEvent('primary', event.id, fd);
    if (calendar) calendar.refetchEvents();
    await loadEventsForMonth();
    renderMiniMonthGrid();
    renderDayEvents(currentDate);
    document.getElementById('eventModal').remove();
  } catch (e) {
    alert('Errore nell\'aggiornamento dell\'evento');
  }
}

async function deleteEvent(event) {
  if (!confirm(`Vuoi eliminare l'evento "${event.title}"?`)) return;

  try {
    await API.deleteGoogleEvent('primary', event.id);
    if (calendar) calendar.refetchEvents();
    await loadEventsForMonth();
    renderMiniMonthGrid();
    renderDayEvents(currentDate);
    document.getElementById('eventModal').remove();
  } catch (e) {
    alert('Errore nell\'eliminazione dell\'evento');
  }
}

// Esporta showEventModal per riutilizzo in dashboard-events
export { showEventModal };
window.renderCalendar = renderCalendar;
