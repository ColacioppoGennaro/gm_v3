/**
 * assets/js/calendar.js
 * Calendario (Google) con layout responsive e UI pulita
 * — logica API invariata —
 */

import { API } from './api.js';

let calendar = null;

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

  initFullCalendar();

  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    showEventModal();
  });
}

/** Inizializza FullCalendar (responsive, senza loop resize) */
function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  const mq = window.matchMedia('(max-width: 700px)');
  const isMobile = mq.matches;

  calendar = new FullCalendar.Calendar(calEl, {
    initialView: isMobile ? 'timeGridDay' : 'dayGridMonth',
    locale: 'it',

    // Header responsive
    headerToolbar: isMobile
      ? { left: 'prev,next today', center: 'title', right: 'timeGridDay,dayGridMonth' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    buttonText: { today: 'Oggi', month: 'Mese', week: 'Settimana', day: 'Giorno' },

    // Layout e dimensioni
    height: 'auto',
    contentHeight: 'auto',
    expandRows: true,
    handleWindowResize: true, // lascia a FC la gestione dimensioni interne
    stickyHeaderDates: true,
    aspectRatio: isMobile ? 0.95 : 1.4,

    // Densità e visibilità eventi
    dayMaxEventRows: true,
    eventMaxStack: isMobile ? 2 : 3,
    nowIndicator: true,

    // timeGrid ottimizzato
    slotMinTime: '07:00:00',
    slotMaxTime: '20:00:00',
    slotEventOverlap: false,
    slotLabelInterval: '01:00',
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },

    selectable: true,
    editable: true,

    // Caricamento eventi
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

    // Selezione range
    select: (info) => {
      showEventModal(null, info.start, info.end);
      calendar.unselect();
    },

    // Drag & drop
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

    // Resize evento
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

    // Click su evento
    eventClick: (info) => {
      showEventModal(info.event);
    }
  });

  calendar.render();

  // 🔒 Evita loop: cambia vista solo quando cambia davvero il breakpoint
  let lastIsMobile = mq.matches;
  function onMediaChange(e) {
    const isNowMobile = e.matches;
    if (isNowMobile === lastIsMobile) return;
    lastIsMobile = isNowMobile;
    const targetView = isNowMobile ? 'timeGridDay' : 'dayGridMonth';
    if (calendar.view?.type !== targetView) {
      calendar.changeView(targetView);
    }
  }
  if (mq.addEventListener) mq.addEventListener('change', onMediaChange);
  else mq.addListener(onMediaChange);
}

/** Modal evento (crea/modifica) — logica invariata salvo markup */
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
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
