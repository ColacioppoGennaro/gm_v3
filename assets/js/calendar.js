/**
 * assets/js/calendar.js
 * Calendario completo (Google) con modal, all-day, ricorrenze e promemoria
 */

import { API } from './api.js';

let calendar = null;

// === Time helpers (RFC3339 locale + end esclusivo per all-day)
const TZ = 'Europe/Rome';

function toLocalRFC3339(dtLocal /* "YYYY-MM-DDTHH:MM" da input, o ISO con Z */) {
  if (!dtLocal) return null;
  if (/Z$/.test(dtLocal)) {
    // già ISO UTC -> converti a RFC3339 con offset locale
    const d = new Date(dtLocal);
    return toRFC3339WithOffset(d);
  }
  // "YYYY-MM-DDTHH:MM" da <input type="datetime-local">
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

/**
 * Renderizza vista calendario
 */
export async function renderCalendar() {
  const pageContainer = document.querySelector('[data-page="calendar"]');
  if (!pageContainer || pageContainer.querySelector('#cal')) return;

  const isPro = window.S.user && window.S.user.role === 'pro';

  // Verifica se Google Calendar è collegato
  let isGoogleConnected = false;
  try {
    await API.listGoogleEvents(
      'primary',
      new Date().toISOString(),
      new Date(Date.now() + 86400000).toISOString()
    );
    isGoogleConnected = true;
  } catch {
    console.log('Google Calendar non collegato');
  }

  pageContainer.innerHTML = `
    <h1>📅 Calendario</h1>
    ${!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
    ${!isGoogleConnected ? `
      <div class="card" style="background: #1f2937; border-left: 4px solid #f59e0b;">
        <h3 style="color: #f59e0b;">⚠️ Google Calendar non collegato</h3>
        <p style="color: var(--muted); margin: 12px 0;">Per usare Google Calendar devi collegare il tuo account Google.</p>
        <a href="google_connect.php" class="btn" style="text-decoration: none;">🔗 Collega Google Calendar</a>
      </div>
    ` : ''}
    <div class="card">
      <div class="toolbar" style="padding: 10px; display: flex; gap: 10px; justify-content: flex-end;">
        ${isGoogleConnected ? '<button id="btnNewEvent" class="btn">＋ Nuovo Evento</button>' : ''}
      </div>
      <div id="cal" style="height: calc(100vh - 250px); padding: 10px;"></div>
    </div>
  `;

  // Upgrade
  document.getElementById('upgradeBtn3')?.addEventListener('click', () => {
    import('./account.js').then(m => m.showUpgradeModal && m.showUpgradeModal());
  });

  if (!isGoogleConnected) return;

  // Inizializza FullCalendar
  initFullCalendar();

  // Nuovo evento
  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    showEventModal();
  });
}

/**
 * Inizializza FullCalendar
 */
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
    buttonText: {
      today: 'Oggi',
      month: 'Mese',
      week: 'Settimana',
      day: 'Giorno'
    },
    selectable: true,
    editable: true,

    // Carica eventi da Google Calendar
    events: async (info, success, failure) => {
      try {
        const events = await API.listGoogleEvents('primary', info.startStr, info.endStr);
        console.log('✅ Eventi ricevuti:', events);
        success(events);
      } catch (e) {
        console.error('❌ Errore caricamento eventi:', e);
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nel caricamento degli eventi Google Calendar');
        failure(e);
      }
    },

    // Selezione nuova data (click e drag)
    select: (info) => {
      showEventModal(null, info.start, info.end);
      calendar.unselect();
    },

    // Spostamento evento (drag&drop)
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
        console.log('✅ Evento spostato');
      } catch (e) {
        console.error('❌ Errore spostamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nello spostamento dell\'evento');
      }
    },

    // Ridimensionamento evento
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
        console.log('✅ Evento ridimensionato');
      } catch (e) {
        console.error('❌ Errore ridimensionamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') alert('Errore nel ridimensionamento dell\'evento');
      }
    },

    // Click su evento (apre modal modifica)
    eventClick: (info) => {
      showEventModal(info.event);
    }
  });

  calendar.render();
  console.log('✅ Calendario renderizzato');
}

/**
 * Mostra modal evento (crea o modifica)
 */
function showEventModal(event = null, startDate = null, endDate = null) {
  const isEdit = !!event;
  const modalId = 'eventModal';

  // Rimuovi modal esistente
  document.getElementById(modalId)?.remove();

  // Dati evento
  const title = event?.title || '';
  const description = event?.extendedProps?.description || '';
  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;
  const rrule = event?.extendedProps?.rrule || '';
  const reminders = event?.extendedProps?.reminders || [];

  // Formati per input
  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  const html = `<div class="modal" id="${modalId}">
    <div class="modal-content" style="max-width: 600px;">
      <h2 style="margin-bottom:20px">${isEdit ? '✏️ Modifica Evento' : '➕ Nuovo Evento'}</h2>

      <div class="form-group">
        <label>Titolo *</label>
        <input type="text" id="eventTitle" value="${title}" placeholder="Titolo evento" required/>
      </div>

      <div class="form-group">
        <label>Descrizione</label>
        <textarea id="eventDescription" placeholder="Descrizione opzionale" rows="3">${description}</textarea>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="eventAllDay" ${allDay ? 'checked' : ''} style="width:auto;margin:0"/>
          <span>Tutto il giorno</span>
        </label>
      </div>

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

      <div id="eventError" class="error hidden"></div>

      <div class="btn-group" style="margin-top:24px">
        <button class="btn secondary" id="closeEventModal">Annulla</button>
        ${isEdit ? '<button class="btn del" id="deleteEventBtn">🗑️ Elimina</button>' : ''}
        <button class="btn" id="saveEventBtn">${isEdit ? 'Salva' : 'Crea'}</button>
      </div>
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);

  // Bind eventi modal
  document.getElementById('closeEventModal').onclick = () => {
    document.getElementById(modalId).remove();
  };

  document.getElementById('eventAllDay').onchange = (e) => {
    toggleAllDayInputs(e.target.checked);
  };

  document.getElementById('addReminderBtn').onclick = addReminderField;

  // Carica promemoria esistenti
  reminders.forEach(r => addReminderField(r.minutes, r.method));
  if (reminders.length === 0) addReminderField(30, 'popup');

  document.getElementById('saveEventBtn').onclick = () => {
    if (isEdit) updateEvent(event);
    else createEvent();
  };

  if (isEdit) {
    document.getElementById('deleteEventBtn').onclick = () => {
      deleteEvent(event);
    };
  }

  // Seleziona valore ricorrenza se presente
  document.getElementById('eventRecurrence').value = rrule || '';
}

/**
 * Toggle input data tra datetime-local e date
 */
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

/**
 * Aggiungi campo promemoria
 */
function addReminderField(minutes = 30, method = 'popup') {
  const list = document.getElementById('remindersList');
  const id = 'reminder_' + Date.now();

  const html = `<div class="settings-row settings-row--compact" data-reminder="${id}">
    <select class="reminder-method">
      <option value="popup" ${method === 'popup' ? 'selected' : ''}>Notifica</option>
      <option value="email" ${method === 'email' ? 'selected' : ''}>Email</option>
    </select>
    <input type="number" class="reminder-minutes" value="${minutes}" min="0" max="10080" style="width:80px" placeholder="Min"/>
    <span style="font-size:12px;color:var(--muted)">minuti prima</span>
    <button type="button" class="btn del small" onclick="this.parentElement.remove()">✕</button>
  </div>`;

  list.insertAdjacentHTML('beforeend', html);
}

/** Raccogli dati promemoria */
function collectReminders() {
  const reminders = [];
  document.querySelectorAll('[data-reminder]').forEach(el => {
    const method = el.querySelector('.reminder-method').value;
    const minutes = parseInt(el.querySelector('.reminder-minutes').value) || 0;
    reminders.push({ method, minutes });
  });
  return reminders;
}

/** Crea nuovo evento */
async function createEvent() {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value; // date o datetime-local
  const end = document.getElementById('eventEnd').value;     // date o datetime-local
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
      fd.append('startDate', start);        // "YYYY-MM-DD"
      fd.append('endDate', nextDate(end));  // end esclusivo
    } else {
      fd.append('allDay', '0');
      fd.append('startDateTime', toLocalRFC3339(start)); // from datetime-local
      fd.append('endDateTime', toLocalRFC3339(end));
      fd.append('timeZone', TZ);
    }
    if (recurrence) fd.append('rrule', recurrence);
    if (reminders.length) fd.append('reminders', JSON.stringify(reminders));

    await API.createGoogleEvent('primary', fd);
    console.log('✅ Evento creato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore creazione evento:', e);
    errEl.textContent = 'Errore nella creazione dell\'evento';
    errEl.classList.remove('hidden');
    btn.disabled = false; btn.innerHTML = 'Crea';
  }
}

/** Aggiorna evento esistente */
async function updateEvent(event) {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value; // date o datetime-local
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
      fd.append('endDate', nextDate(end));  // end esclusivo
    } else {
      fd.append('allDay', '0');
      fd.append('startDateTime', toLocalRFC3339(start));
      fd.append('endDateTime', toLocalRFC3339(end));
      fd.append('timeZone', TZ);
    }
    if (reminders.length) fd.append('reminders', JSON.stringify(reminders));

    await API.updateGoogleEvent('primary', event.id, fd);
    console.log('✅ Evento aggiornato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore aggiornamento evento:', e);
    errEl.textContent = 'Errore nell\'aggiornamento dell\'evento';
    errEl.classList.remove('hidden');
    btn.disabled = false; btn.innerHTML = 'Salva';
  }
}

/** Elimina evento */
async function deleteEvent(event) {
  if (!confirm(`Vuoi eliminare l'evento "${event.title}"?`)) return;

  const btn = document.getElementById('deleteEventBtn');
  btn.disabled = true; btn.innerHTML = '<span class="loader"></span>';

  try {
    await API.deleteGoogleEvent('primary', event.id);
    console.log('✅ Evento eliminato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore eliminazione evento:', e);
    alert('Errore nell\'eliminazione dell\'evento');
    btn.disabled = false; btn.innerHTML = '🗑️ Elimina';
  }
}

// Esporta globalmente (per chiamarlo da ui/router se serve)
window.renderCalendar = renderCalendar;
