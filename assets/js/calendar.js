/**
 * assets/js/calendar.js
 * ✅ ENHANCED: Colori, inviti, promemoria + TIPIZZAZIONE EVENTI
 */

import { API } from './api.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];
let _reminders = [];
let _attendees = [];

const TZ = 'Europe/Rome';

// Tipi evento
const EVENT_TYPES = {
  payment: { label: '💳 Pagamento', color: '#dc2127' },
  maintenance: { label: '🔧 Manutenzione', color: '#ffb878' },
  document: { label: '📄 Documento', color: '#5484ed' },
  personal: { label: '👤 Personale', color: '#51b749' },
  generic: { label: '📌 Generico', color: '#e1e1e1' }
};

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
    
    // ✅ Tipo evento
    const eventType = event.extendedProps?.type || 'generic';
    const typeInfo = EVENT_TYPES[eventType] || EVENT_TYPES.generic;
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
  
  // ✅ Tipo e status
  const eventType = event?.extendedProps?.type || 'generic';
  const eventStatus = event?.extendedProps?.status || 'pending';

  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  // Opzioni colori
  const colorOptions = GOOGLE_COLORS.map(c => 
    `<option value="${c.id}" style="background:${c.hex}">${c.name}</option>`
  ).join('');
  
  // ✅ Opzioni tipo evento
  const typeOptions = Object.entries(EVENT_TYPES).map(([key, val]) =>
    `<option value="${key}" ${key === eventType ? 'selected' : ''}>${val.label}</option>`
  ).join('');

  const html = `<div class="modal" id="${modalId}">
    <div class="modal-content">
      <h2 style="margin-bottom:16px">${isEdit ? '✏️ Modifica Evento' : '➕ Nuovo Evento'}</h2>
      
      <div class="form-group">
        <label>Titolo *</label>
        <input type="text" id="eventTitle" value="${title}" placeholder="Titolo evento" required/>
      </div>
      
      <div class="form-group">
        <label>Descrizione</label>
        <textarea id="eventDescription" placeholder="Descrizione opzionale" rows="3">${description}</textarea>
      </div>
      
      <div class="form-group">
        <label>🏷️ Tipo evento *</label>
        <select id="eventType" required>
          ${typeOptions}
        </select>
        <small style="color:var(--muted);display:block;margin-top:4px">
          Il tipo è obbligatorio. Eventi creati da Google Calendar saranno "Generico".
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
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);
  
  // === GESTIONE INVITATI ===
  const attendeesListEl = document.getElementById('attendeesList');
  
  function renderAttendeeChips() {
    attendeesListEl.innerHTML = _attendees.map((email, i) =>
      `<span class="chip" data-idx="${i}" style="display:inline-flex;align-items:center;gap:6px;background:#1e40af;color:white;padding:5px 10px;border-radius:16px;font-size:13px;">
         👤 ${email}
         <button type="button" class="chip-x" style="border:none;background:transparent;color:#cbd5e1;cursor:pointer;font-weight:700;padding:0 0 0 4px;">✕</button>
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

  // === GESTIONE PROMEMORIA ===
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
      
      return `<span class="chip" data-idx="${i}" style="display:inline-flex;align-items:center;gap:6px;background:#334155;color:white;padding:5px 10px;border-radius:16px;font-size:14px;">
         ${r.method === 'email' ? '📧' : '🔔'} ${label}
         <button type="button" class="chip-x" style="border:none;background:transparent;color:#cbd5e1;cursor:pointer;font-weight:700;padding:0 0 0 4px;">✕</button>
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
    
    // Converti tutto in minuti
    let minutes = value;
    if (unit === 'hours') minutes = value * 60;
    else if (unit === 'days') minutes = value * 1440;
    else if (unit === 'weeks') minutes = value * 10080;
    else if (unit === 'months') minutes = value * 43200; // ~30 giorni
    
    if (_reminders.length < 5) {
      _reminders.push({ method, minutes });
      renderReminderChips();
    } else {
      alert('Massimo 5 promemoria per evento');
    }
  });

  // === CARICA DATI ESISTENTI ===
  if (isEdit && event.extendedProps) {
    // Colore
    const colorId = event.extendedProps.colorId;
    if (colorId) document.getElementById('eventColor').value = colorId;
    
    // Ricorrenza
    const recurSel = document.getElementById('eventRecurrence');
    const recurrence = event.extendedProps.recurrence;
    if (recurrence && recurrence.length > 0) {
      const rrule = recurrence[0];
      if (rrule.includes('FREQ=DAILY')) recurSel.value = 'DAILY';
      else if (rrule.includes('FREQ=WEEKLY')) recurSel.value = 'WEEKLY';
      else if (rrule.includes('FREQ=MONTHLY')) recurSel.value = 'MONTHLY';
      else if (rrule.includes('FREQ=YEARLY')) recurSel.value = 'YEARLY';
    }
    
    // Promemoria
    const rem = event.extendedProps.reminders;
    if (rem?.overrides && Array.isArray(rem.overrides)) {
      _reminders = rem.overrides.map(r => ({ 
        method: r.method || 'popup', 
        minutes: Number(r.minutes || 30) 
      }));
      renderReminderChips();
    }
    
    // Invitati
    const attendees = event.extendedProps.attendees;
    if (attendees && Array.isArray(attendees)) {
      _attendees = attendees.map(a => a.email);
      renderAttendeeChips();
    }
  }

  document.getElementById('closeEventModal').onclick = () => document.getElementById(modalId).remove();
  document.getElementById('saveEventBtn').onclick = () => isEdit ? updateEvent(event) : createEvent();
  if (isEdit) document.getElementById('deleteEventBtn').onclick = () => deleteEvent(event);
  
  // Toggle allDay
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
  const eventType = document.getElementById('eventType').value;

  if (!title || !start || !end || !eventType) {
    return alert('Compila tutti i campi obbligatori (titolo, date e tipo)');
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('description', description || '');
  fd.append('type', eventType);
  fd.append('status', 'pending');
  fd.append('trigger', 'manual');
  
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
  
  // Colore
  if (colorId) fd.append('colorId', colorId);
  
  // Ricorrenza
  const recur = document.getElementById('eventRecurrence')?.value;
  if (recur && recur !== 'none') {
    fd.append('recurrence', `FREQ=${recur}`);
  }
  
  // Promemoria
  if (_reminders.length > 0) {
    fd.append('reminders', JSON.stringify(_reminders));
  }
  
  // Invitati
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
  const eventType = document.getElementById('eventType').value;
  const eventStatus = document.getElementById('eventStatus')?.value || 'pending';

  if (!title || !eventType) {
    return alert('Inserisci un titolo e seleziona un tipo');
  }

  const fd = new FormData();
  fd.append('title', title);
  fd.append('description', description || '');
  fd.append('type', eventType);
  fd.append('status', eventStatus);
  
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
  
  // Colore
  fd.append('colorId', colorId || '');
  
  // Ricorrenza
  const recur = document.getElementById('eventRecurrence')?.value;
  if (recur && recur !== 'none') {
    fd.append('recurrence', `FREQ=${recur}`);
  }
  
  // Promemoria
  if (_reminders.length > 0) {
    fd.append('reminders', JSON.stringify(_reminders));
  }
  
  // Invitati
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

window.renderCalendar = renderCalendar;
