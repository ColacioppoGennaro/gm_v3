/*  assets/js/calendar.js
 *  Calendario ibrido: Desktop = FullCalendar, Mobile = Mini Grid + Lista
 */

import { API } from './api.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];
const TZ = 'Europe/Rome';

// === Utility base ===
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

// === Utility locale ===
function formatLocalYMD(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

// === Render principale ===
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
    </div>`;

  document.getElementById('upgradeBtn3')?.addEventListener('click', () => {
    import('./account.js').then(m => m.showUpgradeModal && m.showUpgradeModal());
  });

  if (!isGoogleConnected) return;
  if (isMobile) await renderMobileCalendar(); else initFullCalendar();

  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    const start = new Date(currentDate);
    start.setHours(9, 0, 0, 0);
    const end = new Date(start.getTime() + 3600000);
    showEventModal(null, start, end);
  });
}

// === Mobile ===
async function renderMobileCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  calEl.innerHTML = `
    <div class="mobile-calendar">
      <div class="mini-month-grid" id="miniMonthGrid"></div>
      <div class="day-events-list" id="dayEventsList">
        <div class="day-events-header" id="dayEventsHeader"><h3>Oggi</h3></div>
        <div class="day-events-content" id="dayEventsContent"><div class="loading">Caricamento eventi...</div></div>
      </div>
    </div>`;

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
  const lastDay = new Date(year, month + 1, 0);

  let startDay = firstDay.getDay();
  startDay = startDay === 0 ? 6 : startDay - 1;
  const daysInMonth = lastDay.getDate();

  const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

  let html = `<div class="mini-month-header">
      <button class="month-nav-btn" id="prevMonth">‹</button>
      <h3>${monthNames[month]} ${year}</h3>
      <button class="month-nav-btn" id="nextMonth">›</button>
    </div>
    <div class="mini-month-days-header"><div>L</div><div>M</div><div>M</div><div>G</div><div>V</div><div>S</div><div>D</div></div>
    <div class="mini-month-days">`;

  for (let i = 0; i < startDay; i++) html += '<div class="mini-day empty"></div>';

  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    const dateStr = formatLocalYMD(date);
    const isToday = date.toDateString() === today.toDateString();
    const isSelected = date.toDateString() === currentDate.toDateString();
    const dayEvents = allEvents.filter(e => new Date(e.start).toDateString() === date.toDateString());
    const hasEvents = dayEvents.length > 0;
    const eventDots = hasEvents ? `<div class="event-dots">${'●'.repeat(Math.min(dayEvents.length, 3))}</div>` : '';

    html += `<div class="mini-day ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''} ${hasEvents ? 'has-events' : ''}" data-date="${dateStr}" onclick="window.selectDate('${dateStr}')">
      <span class="day-number">${day}</span>${eventDots}</div>`;
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
  const dateStr = date.toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
  headerEl.innerHTML = `<h3>${isToday ? 'Oggi' : dateStr}</h3>`;

  const dayEvents = allEvents.filter(e => new Date(e.start).toDateString() === date.toDateString()).sort((a, b) => new Date(a.start) - new Date(b.start));

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
  const fcEvent = { id: event.id, title: event.title, start: new Date(event.start), end: event.end ? new Date(event.end) : null, allDay: event.allDay || false, extendedProps: event.extendedProps || {} };
  showEventModal(fcEvent);
};

// === Desktop FullCalendar ===
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
      catch (e) { console.error('❌ Errore eventi:', e); failure(e); }
    },
    select: (info) => { showEventModal(null, info.start, info.end); calendar.unselect(); },
    eventClick: (info) => showEventModal(info.event),
    eventDrop: async (info) => {
      try {
        const allDay = info.event.allDay;
        const fd = new FormData();
        if (allDay) {
          fd.append('allDay', '1');
          fd.append('startDate', info.event.startStr.slice(0,10));
          fd.append('endDate', info.event.endStr ? info.event.endStr.slice(0,10) : info.event.startStr.slice(0,10));
        } else {
          fd.append('allDay', '0');
          fd.append('startDateTime', toLocalRFC3339(info.event.start.toISOString()));
          fd.append('endDateTime', toLocalRFC3339((info.event.end || info.event.start).toISOString()));
          fd.append('timeZone', TZ);
        }
        await API.updateGoogleEvent('primary', info.event.id, fd);
      } catch { info.revert(); }
    }
  });
  calendar.render();
}

// === Modal Evento (semplificato, ma coerente) ===
function showEventModal(event=null, startDate=null, endDate=null) {
  const isEdit = !!event;
  const modalId = 'eventModal';
  document.getElementById(modalId)?.remove();
  const title = event?.title || '';
  const description = event?.extendedProps?.description || '';
  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;
  const pad = (n) => String(n).padStart(2, '0');
  const formatDateTimeLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDateLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

  const html = `<div class="modal" id="${modalId}"><div class="modal-content">
    <h2>${isEdit ? '✏️ Modifica Evento' : '➕ Nuovo Evento'}</h2>
    <div class="form-group"><label>Titolo *</label><input type="text" id="eventTitle" value="${title}" required></div>
    <div class="form-group"><label>Descrizione</label><textarea id="eventDescription" rows="3">${description}</textarea></div>
    <div class="settings-row"><label><input type="checkbox" id="eventAllDay" ${allDay ? 'checked' : ''}/> Tutto il giorno</label></div>
    <div class="grid-2"><div class="form-group"><label>Inizio *</label><input type="${allDay ? 'date':'datetime-local'}" id="eventStart" value="${allDay ? formatDateLocal(start):formatDateTimeLocal(start)}"></div>
    <div class="form-group"><label>Fine *</label><input type="${allDay ? 'date':'datetime-local'}" id="eventEnd" value="${allDay ? formatDateLocal(end):formatDateTimeLocal(end)}"></div></div>

    <div class="form-group"><label>Ricorrenza</label><select id="eventRecurrence"><option value="none">Non ripetere</option><option value="DAILY">Ogni giorno</option><option value="WEEKLY">Ogni settimana</option><option value="MONTHLY">Ogni mese</option><option value="YEARLY">Ogni anno</
