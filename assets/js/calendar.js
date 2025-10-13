/**
 * assets/js/calendar.js
 * Calendario ibrido: Desktop = FullCalendar, Mobile = Mini Grid + Lista
 */

import { API } from './api.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];
let _reminders = []; // Stato per i promemoria del modal

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
        <div class="day-events-header" id="dayEventsHeader"><h3>Oggi</h3></div>
        <div class="day-events-content" id="dayEventsContent"><div class="loading">Caricamento...</div></div>
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
    allEvents = await API.listGoogleEvents('primary', start.toISOString(), end.toISOString());
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
  let startDay = firstDay.getDay();
  startDay = startDay === 0 ? 6 : startDay - 1;
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
  
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
  for (let i = 0; i < startDay; i++) html += '<div class="mini-day empty"></div>';
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    const dateStr = formatLocalYMD(date);
    const isToday = date.toDateString() === today.toDateString();
    const isSelected = date.toDateString() === currentDate.toDateString();
    const dayEvents = allEvents.filter(e => new Date(e.start).toDateString() === date.toDateString());
    const hasEvents = dayEvents.length > 0;
    const eventDots = hasEvents ? `<div class="event-dots">${'●'.repeat(Math.min(dayEvents.length, 3))}</div>` : '';
    html += `
      <div class="mini-day ${isToday?'today':''} ${isSelected?'selected':''} ${hasEvents?'has-events':''}" 
           data-date="${dateStr}" onclick="window.selectDate('${dateStr}')">
        <span class="day-number">${day}</span>${eventDots}
      </div>
    `;
  }
  html += '</div>';
  gridEl.innerHTML = html;

  document.getElementById('prevMonth')?.addEventListener('click', () => {
    currentDate = new Date(year, month - 1, 1);
    loadEventsForMonth().then(() => { renderMiniMonthGrid(); renderDayEvents(currentDate); });
  });
  document.getElementById('nextMonth')?.addEventListener('click', () => {
    currentDate = new Date(year, month + 1, 1);
    loadEventsForMonth().then(() => { renderMiniMonthGrid(); renderDayEvents(currentDate); });
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
  const dateStr = date.toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
  headerEl.innerHTML = `<h3>${isToday ? 'Oggi' : dateStr}</h3>`;
  const dayEvents = allEvents.filter(e => new Date(e.start).toDateString() === date.toDateString())
    .sort((a, b) => new Date(a.start) - new Date(b.start));

  if (dayEvents.length === 0) {
    contentEl.innerHTML = `<div class="no-events"><div style="font-size:48px;margin-bottom:12px">📭</div><div>Nessun evento</div></div>`;
    return;
  }
  contentEl.innerHTML = dayEvents.map(event => {
    const startTime = new Date(event.start).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    const endTime = event.end ? new Date(event.end).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : '';
    const timeStr = event.allDay ? 'Tutto il giorno' : `${startTime}${endTime ? ' - ' + endTime : ''}`;
    return `<div class="event-item" onclick="window.openEventDetail('${event.id}')"><div class="event-time">${timeStr}</div><div class="event-details"><div class="event-title">${event.title}</div>${event.extendedProps?.description ? `<div class="event-description">${event.extendedProps.description}</div>` : ''}</div></div>`;
  }).join('');
}

window.openEventDetail = async function(eventId) {
  const event = allEvents.find(e => e.id === eventId);
  if (!event) return;
  showEventModal({
    id: event.id, title: event.title, start: new Date(event.start),
    end: event.end ? new Date(event.end) : null, allDay: event.allDay || false,
    extendedProps: event.extendedProps || {}
  });
};

function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;
  calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth', locale: 'it',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    buttonText: { today: 'Oggi', month: 'Mese', week: 'Settimana', day: 'Giorno' },
    height: '80vh', nowIndicator: true, selectable: true, editable: true,
    events: async (info, success, failure) => {
      try { success(await API.listGoogleEvents('primary', info.startStr, info.endStr)); }
      catch (e) { console.error('❌ Errore caricamento eventi:', e); failure(e); }
    },
    select: (info) => { showEventModal(null, info.start, info.end); calendar.unselect(); },
    eventClick: (info) => showEventModal(info.event),
    eventDrop: async (info) => {
      try {
        const fd = new FormData();
        if (info.event.allDay) {
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
      } catch (e) { info.revert(); }
    }
  });
  calendar.render();
}

function showEventModal(event = null, startDate = null, endDate = null) {
  const isEdit = !!event;
  const modalId = 'eventModal';
  document.getElementById(modalId)?.remove();
  _reminders = [];

  const title = event?.title || '';
  const description = event?.extendedProps?.description || '';
  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;

  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  // ✅ HTML AGGIORNATO CON SELETTORE UNITÀ (minuti, ore, giorni)
  const html = `<div class="modal" id="${modalId}">
    <div class="modal-content">
      <h2 style="margin-bottom:16px">${isEdit ? '✏️ Modifica Evento' : '➕ Nuovo Evento'}</h2>
      <div class="form-group"><label>Titolo *</label><input type="text" id="eventTitle" value="${title}" required/></div>
      <div class="form-group"><label>Descrizione</label><textarea id="eventDescription" rows="3">${description}</textarea></div>
      <div class="settings-row"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="eventAllDay" ${allDay ? 'checked' : ''}/><span>Tutto il giorno</span></label></div>
      <div class="grid-2">
        <div class="form-group"><label>Inizio *</label><input type="${allDay?'date':'datetime-local'}" id="eventStart" value="${allDay?formatDateLocal(start):formatDateTimeLocal(start)}" required/></div>
        <div class="form-group"><label>Fine *</label><input type="${allDay?'date':'datetime-local'}" id="eventEnd" value="${allDay?formatDateLocal(end):formatDateTimeLocal(end)}" required/></div>
      </div>
      <div class="form-group"><label>Ricorrenza</label><select id="eventRecurrence"><option value="none">Non ripetere</option><option value="DAILY">Ogni giorno</option><option value="WEEKLY">Ogni settimana</option><option value="MONTHLY">Ogni mese</option><option value="YEARLY">Ogni anno</option></select></div>
      <div class="form-group">
        <label>Promemoria</label>
        <div id="reminderList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:8px;align-items:center;">
          <select id="reminderMethod" style="flex-grow:1;"><option value="popup">Notifica</option><option value="email">Email</option></select>
          <input type="number" id="reminderValue" min="1" step="1" value="30" style="width:70px;" />
          <select id="reminderUnit"><option value="minutes">minuti</option><option value="hours">ore</option><option value="days">giorni</option></select>
          <button class="btn secondary" id="addReminderBtn" type="button" style="padding: 8px 12px;">+</button>
        </div>
      </div>
      <div id="eventError" class="error hidden"></div>
      <div class="btn-group" style="margin-top:20px">
        <button class="btn secondary" id="closeEventModal">Annulla</button>
        ${isEdit ? `<button class="btn del" id="deleteEventBtn">🗑️ Elimina</button>` : ''}
        <button class="btn" id="saveEventBtn">${isEdit ? 'Salva' : 'Crea'}</button>
      </div>
    </div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);

  const reminderListEl = document.getElementById('reminderList');
  
  // ✅ FUNZIONE AGGIORNATA PER MOSTRARE L'UNITÀ CORRETTA
  function formatReminderMinutes(minutes) {
      if (minutes === 0) return 'Al momento dell\'evento';
      if (minutes % 1440 === 0) {
          const days = minutes / 1440;
          return `${days} ${days > 1 ? 'giorni' : 'giorno'} prima`;
      }
      if (minutes % 60 === 0) {
          const hours = minutes / 60;
          return `${hours} ${hours > 1 ? 'ore' : 'ora'} prima`;
      }
      return `${minutes} minuti prima`;
  }

  function renderReminderChips() {
    reminderListEl.innerHTML = _reminders.map((r, i) =>
      `<span class="chip" data-idx="${i}" style="display:inline-flex;align-items:center;gap:6px;background:#334155;color:white;padding:5px 10px;border-radius:16px;font-size:14px;">
         ${r.method === 'email' ? '📧' : '🔔'} ${formatReminderMinutes(r.minutes)}
         <button type="button" class="chip-x" style="border:none;background:transparent;color:#cbd5e1;cursor:pointer;font-weight:700;padding:0 0 0 4px;">✕</button>
       </span>`
    ).join('');
  }
  
  reminderListEl.addEventListener('click', (e) => {
    if (e.target.classList.contains('chip-x')) {
      _reminders.splice(e.target.closest('.chip').dataset.idx, 1);
      renderReminderChips();
    }
  });

  // ✅ LOGICA AGGIORNATA PER CALCOLARE I MINUTI TOTALI
  document.getElementById('addReminderBtn').addEventListener('click', () => {
    const method = document.getElementById('reminderMethod').value;
    const value = Math.max(0, Number(document.getElementById('reminderValue').value || 0));
    const unit = document.getElementById('reminderUnit').value;
    let minutes = 0;
    if (unit === 'hours') minutes = value * 60;
    else if (unit === 'days') minutes = value * 1440;
    else minutes = value;

    if (_reminders.length < 5) {
        _reminders.push({ method, minutes });
        renderReminderChips();
    } else {
        alert('Puoi aggiungere un massimo di 5 promemoria.');
    }
  });

  if (isEdit && event.extendedProps) {
    const recurSel = document.getElementById('eventRecurrence');
    const rrule = event.extendedProps.recurrence?.[0] || '';
    if (rrule.includes('FREQ=DAILY')) recurSel.value = 'DAILY';
    if (rrule.includes('FREQ=WEEKLY')) recurSel.value = 'WEEKLY';
    if (rrule.includes('FREQ=MONTHLY')) recurSel.value = 'MONTHLY';
    if (rrule.includes('FREQ=YEARLY')) recurSel.value = 'YEARLY';
    
    const rem = event.extendedProps.reminders;
    if (rem?.overrides && Array.isArray(rem.overrides)) {
      _reminders = rem.overrides.map(r => ({ method: r.method || 'popup', minutes: Number(r.minutes || 30) }));
      renderReminderChips();
    }
  }

  document.getElementById('closeEventModal').onclick = () => document.getElementById(modalId).remove();
  document.getElementById('saveEventBtn').onclick = () => isEdit ? updateEvent(event) : createEvent();
  if (isEdit) document.getElementById('deleteEventBtn').onclick = () => deleteEvent(event);
}

function getFormDataFromModal() {
    const title = document.getElementById('eventTitle').value.trim();
    if (!title) { alert('Il titolo è obbligatorio.'); return null; }
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('eventDescription').value.trim() || '');
    const allDay = document.getElementById('eventAllDay').checked;
    const start = document.getElementById('eventStart').value;
    const end = document.getElementById('eventEnd').value;
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
    const recur = document.getElementById('eventRecurrence')?.value;
    if (recur && recur !== 'none') fd.append('recurrence', `RRULE:FREQ=${recur}`);
    if (_reminders.length > 0) fd.append('reminders', JSON.stringify(_reminders));
    return fd;
}

async function createEvent() {
  const fd = getFormDataFromModal();
  if (!fd) return;
  try {
    await API.createGoogleEvent('primary', fd);
    document.getElementById('eventModal').remove();
    _refreshCalendar();
  } catch (e) { alert("Errore nella creazione dell'evento"); }
}

async function updateEvent(event) {
  const fd = getFormDataFromModal();
  if (!fd) return;
  try {
    await API.updateGoogleEvent('primary', event.id, fd);
    document.getElementById('eventModal').remove();
    _refreshCalendar();
  } catch (e) { alert("Errore nell'aggiornamento dell'evento"); }
}

async function deleteEvent(event) {
  if (!confirm(`Vuoi eliminare l'evento "${event.title}"?`)) return;
  try {
    await API.deleteGoogleEvent('primary', event.id);
    document.getElementById('eventModal').remove();
    _refreshCalendar();
  } catch (e) { alert("Errore nell'eliminazione dell'evento"); }
}

async function _refreshCalendar() {
  if (calendar) {
    calendar.refetchEvents();
  } else {
    await loadEventsForMonth();
    renderMiniMonthGrid();
    renderDayEvents(currentDate);
  }
}

window.renderCalendar = renderCalendar;
