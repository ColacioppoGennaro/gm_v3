/**
 * assets/js/calendar.js
 * Calendario ibrido: Desktop = FullCalendar, Mobile = Mini Grid + Lista
 */

import { API } from './api.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];

// === Time helpers (RFC3339 locale + end esclusivo per all-day)
const TZ = 'Europe/Rome';

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

/** Renderizza vista calendario */
export async function renderCalendar() {
  const page = document.querySelector('[data-page="calendar"]');
  if (!page || page.querySelector('#cal')) return;

  const isPro = window.S.user && window.S.user.role === 'pro';
  const isMobile = window.matchMedia('(max-width: 768px)').matches;

  // Verifica connessione Google
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

  // Scegli rendering in base a dispositivo
  if (isMobile) {
    await renderMobileCalendar();
  } else {
    initFullCalendar();
  }

  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    showEventModal();
  });
}

/**
 * MOBILE: Mini Grid + Lista Eventi
 */
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

/**
 * Carica eventi del mese corrente
 */
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

/**
 * Renderizza mini griglia mese (stile Google Calendar mobile)
 */
function renderMiniMonthGrid() {
  const gridEl = document.getElementById('miniMonthGrid');
  if (!gridEl) return;

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const today = new Date();
  
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const startDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // 0=Lun, 6=Dom
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

  // Celle vuote prima del 1° giorno
  for (let i = 0; i < startDay; i++) {
    html += '<div class="mini-day empty"></div>';
  }

  // Giorni del mese
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    const dateStr = date.toISOString().split('T')[0];
    
    const isToday = date.toDateString() === today.toDateString();
    const isSelected = date.toDateString() === currentDate.toDateString();
    
    // Conta eventi del giorno
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

  html += '</div></div>';
  gridEl.innerHTML = html;

  // Event listeners navigazione mese
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

/**
 * Seleziona data e mostra eventi
 */
window.selectDate = function(dateStr) {
  currentDate = new Date(dateStr + 'T12:00:00');
  renderMiniMonthGrid();
  renderDayEvents(currentDate);
};

/**
 * Renderizza lista eventi del giorno selezionato
 */
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

  // Filtra eventi del giorno
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

    return `
      <div class="event-item" onclick="window.openEventDetail('${event.id}')">
        <div class="event-time">${timeStr}</div>
        <div class="event-details">
          <div class="event-title">${event.title}</div>
          ${event.extendedProps?.description ? `<div class="event-description">${event.extendedProps.description}</div>` : ''}
        </div>
      </div>
    `;
  }).join('');
}

/**
 * Apri dettaglio evento
 */
window.openEventDetail = async function(eventId) {
  const event = allEvents.find(e => e.id === eventId);
  if (!event) return;
  
  // Converti in formato FullCalendar event object
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

/** Inizializza FullCalendar con configurazione performante */
function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  const isMobile = window.matchMedia('(max-width: 768px)').matches;

  calendar = new FullCalendar.Calendar(calEl, {
    initialView: isMobile ? 'timeGridDay' : 'dayGridMonth',
    locale: 'it',
    
    headerToolbar: isMobile
      ? { left: 'prev,next', center: 'title', right: 'today' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    
    footerToolbar: isMobile
      ? { center: 'dayGridMonth,timeGridWeek,timeGridDay' }
      : false,
    
    buttonText: { 
      today: 'Oggi', 
      month: isMobile ? 'M' : 'Mese', 
      week: isMobile ? 'S' : 'Settimana', 
      day: isMobile ? 'G' : 'Giorno' 
    },

    // --- ✅ CONFIGURAZIONE PERFORMANTE ---
    height: isMobile ? '75vh' : '80vh',
    handleWindowResize: true,
    
    stickyHeaderDates: !isMobile,
    dayMaxEventRows: isMobile ? 2 : true,
    eventMaxStack: isMobile ? 2 : 3,
    nowIndicator: true,

    // timeGrid ottimizzato
    slotMinTime: '07:00:00',
    slotMaxTime: '20:00:00',
    slotEventOverlap: false,
    slotLabelInterval: isMobile ? '02:00' : '01:00',
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },

    selectable: true,
    editable: true,
    
    // Gestione touch su mobile
    longPressDelay: isMobile ? 300 : 1000,
    eventLongPressDelay: isMobile ? 300 : 1000,
    selectLongPressDelay: isMobile ? 300 : 1000,

    events: async (info, success, failure) => {
      try {
        const events = await API.listGoogleEvents('primary', info.startStr, info.endStr);
        success(events);
      } catch (e) {
        console.error('❌ Errore caricamento eventi:', e);
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nel caricamento degli eventi Google Calendar');
        failure(e);
      }
    },

    select: (info) => {
      showEventModal(null, info.start, info.end);
      calendar.unselect();
    },

    eventDrop: async (info) => {
      try {
        const allDay = info.event.allDay === true;
        const fd = new FormData();
        fd.append('calendarId', 'primary');
        if (allDay) {
          const startDate = info.event.startStr.slice(0, 10);
          const endDate = info.event.endStr ? info.event.endStr.slice(0, 10) : startDate;
          fd.append('allDay', '1');
          fd.append('startDate', startDate);
          fd.append('endDate', endDate);
        } else {
          fd.append('allDay', '0');
          fd.append('startDateTime', toLocalRFC3339(info.event.start.toISOString()));
          fd.append('endDateTime', toLocalRFC3339((info.event.end || info.event.start).toISOString()));
          fd.append('timeZone', TZ);
        }
        await API.updateGoogleEvent('primary', info.event.id, fd);
      } catch (e) {
        console.error('❌ Errore spostamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nello spostamento dell\'evento');
      }
    },

    eventResize: async (info) => {
      try {
        const allDay = info.event.allDay === true;
        const fd = new FormData();
        fd.append('calendarId', 'primary');
        if (allDay) {
          const startDate = info.event.startStr.slice(0, 10);
          const endDate = info.event.endStr ? info.event.endStr.slice(0, 10) : startDate;
          fd.append('allDay', '1');
          fd.append('startDate', startDate);
          fd.append('endDate', endDate);
        } else {
          fd.append('allDay', '0');
          fd.append('startDateTime', toLocalRFC3339(info.event.start.toISOString()));
          fd.append('endDateTime', toLocalRFC3339(info.event.end.toISOString()));
          fd.append('timeZone', TZ);
        }
        await API.updateGoogleEvent('primary', info.event.id, fd);
      } catch (e) {
        console.error('❌ Errore ridimensionamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nel ridimensionamento dell\'evento');
      }
    },

    eventClick: (info) => {
      showEventModal(info.event);
    }
  });

  calendar.render();
  
  // Re-render su cambio orientamento mobile
  if (isMobile) {
    window.addEventListener('resize', () => {
      if (calendar) calendar.updateSize();
    });
  }
}

/** Modal evento (crea/modifica) */
function showEventModal(event = null, startDate = null, endDate = null) {
  const isEdit = !!event;
  const modalId = 'eventModal';
  document.getElementById(modalId)?.remove();

  const title = event?.title || '';
  const description = event?.extendedProps?.description || '';
  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;
  const rrule = event?.extendedProps?.rrule || '';
  const reminders = event?.extendedProps?.reminders || [];

  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

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
      <div class="settings-row settings-row--compact">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="eventAllDay" ${allDay ? 'checked' : ''} style="width:auto;margin:0"/>
          <span>Tutto il giorno</span>
        </label>
      </div>
      <div class="grid-2">
        <div class="form-group" id="eventStartGroup">
          <label>Inizio *</label>
          <input type="${allDay ? 'date' : 'datetime-local'}" id="eventStart"
                 value="${allDay ? formatDateLocal(start) : formatDateTimeLocal(start)}" required/>
        </div>
        <div class="form-group" id="eventEndGroup">
          <label>Fine *</label>
          <input type="${allDay ? 'date' : 'datetime-local'}" id="eventEnd"
                 value="${allDay ? formatDateLocal(end) : formatDateTimeLocal(end)}" required/>
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label>Ricorrenza</label>
          <select id="eventRecurrence">
            <option value="">Non ripetere</option>
            <option value="FREQ=DAILY">Ogni giorno</option>
            <option value="FREQ=WEEKLY">Ogni settimana</option>
            <option value="FREQ=MONTHLY">Ogni mese</option>
            <option value="FREQ=YEARLY">Ogni anno</option>
          </select>
        </div>
        <div class="form-group">
          <label>Promemoria</label>
          <div id="remindersList" style="margin-bottom:8px"></div>
          <button type="button" class="btn small" id="addReminderBtn">+ Aggiungi Promemoria</button>
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

  document.getElementById('closeEventModal').onclick = () => document.getElementById(modalId).remove();
  document.getElementById('eventAllDay').onchange = (e) => toggleAllDayInputs(e.target.checked);
  document.getElementById('addReminderBtn').onclick = addReminderField;
  reminders.forEach(r => addReminderField(r.minutes, r.method));
  if (reminders.length === 0) addReminderField(30, 'popup');

  document.getElementById('saveEventBtn').onclick = () => isEdit ? updateEvent(event) : createEvent();
  if (isEdit) document.getElementById('deleteEventBtn').onclick = () => deleteEvent(event);

  document.getElementById('eventRecurrence').value = rrule || '';
}

function toggleAllDayInputs(isAllDay) {
  const startInput = document.getElementById('eventStart');
  const endInput = document.getElementById('eventEnd');

  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
  const formatDateLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  const start = new Date(startInput.value);
  const end = new Date(endInput.value);

  if (isAllDay) {
    startInput.type = 'date';
    endInput.type = 'date';
    startInput.value = formatDateLocal(start);
    endInput.value = formatDateLocal(end);
  } else {
    startInput.type = 'datetime-local';
    endInput.type = 'datetime-local';
    startInput.value = formatDateTimeLocal(start);
    endInput.value = formatDateTimeLocal(end);
  }
}

function addReminderField(minutes = 30, method = 'popup') {
  const list = document.getElementById('remindersList');
  const id = 'reminder_' + Date.now();

  const html = `<div class="settings-row settings-row--compact" data-reminder="${id}">
    <select class="reminder-method">
      <option value="popup" ${method === 'popup' ? 'selected' : ''}>Notifica</option>
      <option value="email" ${method === 'email' ? 'selected' : ''}>Email</option>
    </select>
    <input type="number" class="reminder-minutes" value="${minutes}" min="0" max="10080" style="width:80px" placeholder="Min"/>
    <span style="font-size:12px;color:var(--muted)">min prima</span>
    <button type="button" class="btn del small" onclick="this.parentElement.remove()">✕</button>
  </div>`;

  list.insertAdjacentHTML('beforeend', html);
}

function collectReminders() {
  const reminders = [];
  document.querySelectorAll('[data-reminder]').forEach(el => {
    const method = el.querySelector('.reminder-method').value;
    const minutes = parseInt(el.querySelector('.reminder-minutes').value) || 0;
    reminders.push({ method, minutes });
  });
  return reminders;
}

async function createEvent() {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value;
  const end = document.getElementById('eventEnd').value;
  const recurrence = document.getElementById('eventRecurrence').value;
  const reminders = collectReminders();
  const errEl = document.getElementById('eventError');

  errEl.classList.add('hidden');
  if (!title) { errEl.textContent = 'Inserisci un titolo'; errEl.classList.remove('hidden'); return; }
  if (!start || !end) { errEl.textContent = 'Seleziona data inizio e fine'; errEl.classList.remove('hidden'); return; }

  const btn = document.getElementById('saveEventBtn');
  btn.disabled = true; btn.innerHTML = 'Creazione... <span class="loader"></span>';

  try {
    const fd = new FormData();
    fd.append('calendarId', 'primary');
    fd.append('title', title);
    fd.append('description', description || '');
    if (allDay) {
      fd.append('allDay', '1');
      fd.append('startDate', start);
      fd.append('endDate', nextDate(end)); // end esclusivo
    } else {
      fd.append('allDay', '0');
      fd.append('startDateTime', toLocalRFC3339(start));
      fd.append('endDateTime', toLocalRFC3339(end));
      fd.append('timeZone', TZ);
    }
    if (recurrence) fd.append('rrule', recurrence);
    if (reminders.length) fd.append('reminders', JSON.stringify(reminders));

    await API.createGoogleEvent('primary', fd);
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore creazione evento:', e);
    const errEl2 = document.getElementById('eventError');
    errEl2.textContent = 'Errore nella creazione dell\'evento';
    errEl2.classList.remove('hidden');
    btn.disabled = false; btn.innerHTML = 'Crea';
  }
}

async function updateEvent(event) {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value;
  const end = document.getElementById('eventEnd').value;
  const reminders = collectReminders();
  const errEl = document.getElementById('eventError');

  errEl.classList.add('hidden');
  if (!title) { errEl.textContent = 'Inserisci un titolo'; errEl.classList.remove('hidden'); return; }

  const btn = document.getElementById('saveEventBtn');
  btn.disabled = true; btn.innerHTML = 'Salvataggio... <span class="loader"></span>';

  try {
    const fd = new FormData();
    fd.append('calendarId', 'primary');
    fd.append('title', title);
    fd.append('description', description || '');
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
    if (reminders.length) fd.append('reminders', JSON.stringify(reminders));

    await API.updateGoogleEvent('primary', event.id, fd);
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore aggiornamento evento:', e);
    const errEl2 = document.getElementById('eventError');
    errEl2.textContent = 'Errore nell\'aggiornamento dell\'evento';
    errEl2.classList.remove('hidden');
    btn.disabled = false; btn.innerHTML = 'Salva';
  }
}

async function deleteEvent(event) {
  if (!confirm(`Vuoi eliminare l'evento "${event.title}"?`)) return;

  const btn = document.getElementById('deleteEventBtn');
  btn.disabled = true; btn.innerHTML = '<span class="loader"></span>';

  try {
    await API.deleteGoogleEvent('primary', event.id);
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore eliminazione evento:', e);
    alert('Errore nell\'eliminazione dell\'evento');
    btn.disabled = false; btn.innerHTML = '🗑️ Elimina';
  }
}

// Esporta globalmente (se serve)
window.renderCalendar = renderCalendar;
