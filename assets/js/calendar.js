/**
 * assets/js/calendar.js
 * Gestione completa calendario con eventi avanzati
 */

import { API } from './api.js';

let calendar = null;

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
    const checkResponse = await API.listGoogleEvents('primary', 
      new Date().toISOString(), 
      new Date(Date.now() + 86400000).toISOString()
    );
    isGoogleConnected = true;
  } catch (e) {
    console.log('Google Calendar non collegato');
  }
  
  pageContainer.innerHTML = `
    <h1>📅 Calendario</h1>
    ${!isPro ? '<div class="banner" id="upgradeBtn3">⚡ Stai usando il piano <b>Free</b>. Clicca qui per upgrade a Pro!</div>' : ''}
    ${!isGoogleConnected ? `
      <div class="card" style="background: #1f2937; border-left: 4px solid #f59e0b;">
        <h3 style="color: #f59e0b;">⚠️ Google Calendar non collegato</h3>
        <p style="color: var(--muted); margin: 12px 0;">Per usare Google Calendar devi collegare il tuo account Google.</p>
        <a href="google_connect.php" class="btn" style="text-decoration: none;">
          🔗 Collega Google Calendar
        </a>
      </div>
    ` : ''}
    <div class="card">
      <div class="toolbar" style="padding: 10px; display: flex; gap: 10px; justify-content: flex-end;">
        ${isGoogleConnected ? '<button id="btnNewEvent" class="btn">＋ Nuovo Evento</button>' : ''}
      </div>
      <div id="cal" style="height: calc(100vh - 250px); padding: 10px;"></div>
    </div>
  `;
  
  // Bind upgrade button
  document.getElementById('upgradeBtn3')?.addEventListener('click', () => {
    import('./account.js').then(m => m.showUpgradeModal());
  });
  
  if (!isGoogleConnected) return;
  
  // Inizializza FullCalendar
  initFullCalendar();
  
  // Bind nuovo evento
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
    events: async (info, successCallback, failureCallback) => {
      try {
        const start = encodeURIComponent(info.startStr);
        const end = encodeURIComponent(info.endStr);
        const events = await API.listGoogleEvents('primary', info.startStr, info.endStr);
        console.log('✅ Eventi ricevuti:', events);
        successCallback(events);
      } catch (e) {
        console.error('❌ Errore caricamento eventi:', e);
        if (e.message !== 'AUTH_EXPIRED') {
          alert('Errore nel caricamento degli eventi Google Calendar');
        }
        failureCallback(e);
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
        await API.updateGoogleEvent('primary', info.event.id, {
          start: info.event.start?.toISOString(),
          end: info.event.end?.toISOString()
        });
        console.log('✅ Evento spostato');
      } catch (e) {
        console.error('❌ Errore spostamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') {
          alert('Errore nello spostamento dell\'evento');
        }
      }
    },
    
    // Ridimensionamento evento
    eventResize: async (info) => {
      try {
        await API.updateGoogleEvent('primary', info.event.id, {
          end: info.event.end?.toISOString()
        });
        console.log('✅ Evento ridimensionato');
      } catch (e) {
        console.error('❌ Errore ridimensionamento evento:', e);
        info.revert();
        if (e.message !== 'AUTH_EXPIRED') {
          alert('Errore nel ridimensionamento dell\'evento');
        }
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
  
  // Formato date per input
  const formatDateTimeLocal = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };
  
  const formatDateLocal = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  };
  
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
  
  // Bind eventi
  document.getElementById('closeEventModal').onclick = () => {
    document.getElementById(modalId).remove();
  };
  
  document.getElementById('eventAllDay').onchange = (e) => {
    toggleAllDayInputs(e.target.checked);
  };
  
  document.getElementById('addReminderBtn').onclick = addReminderField;
  
  // Carica promemoria esistenti
  reminders.forEach(r => addReminderField(r.minutes, r.method));
  
  // Se nessun promemoria, aggiungi uno di default
  if (reminders.length === 0) {
    addReminderField(30, 'popup');
  }
  
  document.getElementById('saveEventBtn').onclick = () => {
    if (isEdit) {
      updateEvent(event);
    } else {
      createEvent();
    }
  };
  
  if (isEdit) {
    document.getElementById('deleteEventBtn').onclick = () => {
      deleteEvent(event);
    };
  }
}

/**
 * Toggle input data tra datetime-local e date
 */
function toggleAllDayInputs(isAllDay) {
  const startInput = document.getElementById('eventStart');
  const endInput = document.getElementById('eventEnd');
  
  const currentStart = new Date(startInput.value);
  const currentEnd = new Date(endInput.value);
  
  const formatDateTimeLocal = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };
  
  const formatDateLocal = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  };
  
  if (isAllDay) {
    startInput.type = 'date';
    endInput.type = 'date';
    startInput.value = formatDateLocal(currentStart);
    endInput.value = formatDateLocal(currentEnd);
  } else {
    startInput.type = 'datetime-local';
    endInput.type = 'datetime-local';
    startInput.value = formatDateTimeLocal(currentStart);
    endInput.value = formatDateTimeLocal(currentEnd);
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

/**
 * Raccogli dati promemoria
 */
function collectReminders() {
  const reminders = [];
  document.querySelectorAll('[data-reminder]').forEach(el => {
    const method = el.querySelector('.reminder-method').value;
    const minutes = parseInt(el.querySelector('.reminder-minutes').value) || 0;
    reminders.push({ method, minutes });
  });
  return reminders;
}

/**
 * Crea nuovo evento
 */
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
  
  if (!title) {
    errEl.textContent = 'Inserisci un titolo';
    errEl.classList.remove('hidden');
    return;
  }
  
  if (!start || !end) {
    errEl.textContent = 'Seleziona data inizio e fine';
    errEl.classList.remove('hidden');
    return;
  }
  
  const btn = document.getElementById('saveEventBtn');
  btn.disabled = true;
  btn.innerHTML = 'Creazione... <span class="loader"></span>';
  
  try {
    const eventData = {
      calendarId: 'primary',
      title,
      description,
      start: allDay ? start : new Date(start).toISOString(),
      end: allDay ? end : new Date(end).toISOString(),
      allDay,
      reminders
    };
    
    if (recurrence) {
      eventData.rrule = recurrence;
    }
    
    await API.createGoogleEvent('primary', eventData);
    console.log('✅ Evento creato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore creazione evento:', e);
    errEl.textContent = 'Errore nella creazione dell\'evento';
    errEl.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = 'Crea';
  }
}

/**
 * Aggiorna evento esistente
 */
async function updateEvent(event) {
  const title = document.getElementById('eventTitle').value.trim();
  const description = document.getElementById('eventDescription').value.trim();
  const allDay = document.getElementById('eventAllDay').checked;
  const start = document.getElementById('eventStart').value;
  const end = document.getElementById('eventEnd').value;
  const reminders = collectReminders();
  const errEl = document.getElementById('eventError');
  
  errEl.classList.add('hidden');
  
  if (!title) {
    errEl.textContent = 'Inserisci un titolo';
    errEl.classList.remove('hidden');
    return;
  }
  
  const btn = document.getElementById('saveEventBtn');
  btn.disabled = true;
  btn.innerHTML = 'Salvataggio... <span class="loader"></span>';
  
  try {
    const eventData = {
      calendarId: 'primary',
      title,
      description,
      start: allDay ? start : new Date(start).toISOString(),
      end: allDay ? end : new Date(end).toISOString(),
      reminders
    };
    
    await API.updateGoogleEvent('primary', event.id, eventData);
    console.log('✅ Evento aggiornato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore aggiornamento evento:', e);
    errEl.textContent = 'Errore nell\'aggiornamento dell\'evento';
    errEl.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = 'Salva';
  }
}

/**
 * Elimina evento
 */
async function deleteEvent(event) {
  if (!confirm(`Vuoi eliminare l'evento "${event.title}"?`)) return;
  
  const btn = document.getElementById('deleteEventBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';
  
  try {
    await API.deleteGoogleEvent('primary', event.id);
    console.log('✅ Evento eliminato');
    calendar.refetchEvents();
    document.getElementById('eventModal').remove();
  } catch (e) {
    console.error('❌ Errore eliminazione evento:', e);
    alert('Errore nell\'eliminazione dell\'evento');
    btn.disabled = false;
    btn.innerHTML = '🗑️ Elimina';
  }
}

// Esporta globalmente
window.renderCalendar = renderCalendar;
