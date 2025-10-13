/**
 * assets/js/calendar.js
 * Calendario ibrido: Desktop = FullCalendar, Mobile = Mini Grid + Lista
 * VERSIONE DEFINITIVA: Logica C.R.U.D. unificata e robusta.
 */

import { API } from './api.js';

let calendar = null;
let currentDate = new Date();
let allEvents = [];
let _reminders = [];

const TZ = 'Europe/Rome';

function toLocalRFC3339(dtLocal) {
  if (!dtLocal) return null;
  if (/Z$/.test(dtLocal)) return toRFC3339WithOffset(new Date(dtLocal));
  const [d, t = '00:00'] = dtLocal.split('T');
  const [Y, M, D] = d.split('-').map(Number);
  const [h, m] = t.split(':').map(Number);
  return toRFC3339WithOffset(new Date(Y, M - 1, D, h, m));
}

function toRFC3339WithOffset(date) {
  const d = new Date(date);
  const offMin = -d.getTimezoneOffset();
  const sign = offMin >= 0 ? '+' : '-';
  const abs = Math.abs(offMin);
  const oh = String(Math.floor(abs / 60)).padStart(2, '0');
  const om = String(abs % 60).padStart(2, '0');
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}T${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}${sign}${oh}:${om}`;
}

function nextDate(yyyy_mm_dd) {
  const [Y, M, D] = yyyy_mm_dd.split('-').map(Number);
  const d = new Date(Y, M - 1, D);
  d.setDate(d.getDate() + 1);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function formatLocalYMD(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

export async function renderCalendar() {
  const page = document.querySelector('[data-page="calendar"]');
  if (!page) return;
  
  const isPro = window.S.user && window.S.user.role === 'pro';
  const isMobile = window.matchMedia('(max-width: 768px)').matches;

  let isGoogleConnected = false;
  try {
    await API.listGoogleEvents('primary');
    isGoogleConnected = true;
  } catch {
    console.log('Google Calendar non collegato');
  }

  page.innerHTML = `<h1>📅 Calendario</h1>
    ${!isPro?'<div class="banner" id="upgradeBtn3">⚡ Piano <b>Free</b>. Clicca per upgrade a Pro</div>':''}
    ${!isGoogleConnected?`<div class="card calendar-card-warning"><h3>⚠️ Google Calendar non collegato</h3><p style="color:var(--muted);margin:12px 0">Collega il tuo account Google per usare il calendario.</p><a href="google_connect.php" class="btn" style="text-decoration:none">🔗 Collega Google Calendar</a></div>`:''}
    <div class="card calendar-card">
      <div class="calendar-toolbar">${isGoogleConnected?'<button id="btnNewEvent" class="btn">＋ Nuovo Evento</button>':''}</div>
      <div id="cal" class="calendar-root"></div>
    </div>`;

  document.getElementById('upgradeBtn3')?.addEventListener('click', () => import('./account.js').then(m => m.showUpgradeModal?.()));
  if (!isGoogleConnected) return;

  if (isMobile) await renderMobileCalendar();
  else initFullCalendar();

  document.getElementById('btnNewEvent')?.addEventListener('click', () => {
    const start = new Date(currentDate);
    start.setHours(9,0,0,0);
    showEventModal(null, start, new Date(start.getTime() + 3600000));
  });
}

async function renderMobileCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;
  calEl.innerHTML = `<div class="mobile-calendar">
      <div class="mini-month-grid" id="miniMonthGrid"></div>
      <div class="day-events-list" id="dayEventsList">
        <div class="day-events-header" id="dayEventsHeader"><h3>Oggi</h3></div>
        <div class="day-events-content" id="dayEventsContent"><div class="loading">Caricamento...</div></div>
      </div></div>`;
  await _refreshCalendar();
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
  const year = currentDate.getFullYear(), month = currentDate.getMonth();
  const firstDay = new Date(year, month, 1);
  let startDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const monthNames = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
  let html = `<div class="mini-month-header">
      <button class="month-nav-btn" id="prevMonth">‹</button>
      <h3>${monthNames[month]} ${year}</h3>
      <button class="month-nav-btn" id="nextMonth">›</button>
    </div><div class="mini-month-days-header">${['L','M','M','G','V','S','D'].map(d=>`<div>${d}</div>`).join('')}</div>
    <div class="mini-month-days">`;
  for (let i=0; i<startDay; i++) html+='<div class="mini-day empty"></div>';
  for (let day=1; day<=daysInMonth; day++) {
    const date = new Date(year, month, day);
    const dayEvents = allEvents.filter(e => new Date(e.start).toDateString() === date.toDateString());
    html += `<div class="mini-day ${date.toDateString()===new Date().toDateString()?'today':''} ${date.toDateString()===currentDate.toDateString()?'selected':''} ${dayEvents.length>0?'has-events':''}" 
           data-date="${formatLocalYMD(date)}" onclick="window.selectDate('${formatLocalYMD(date)}')">
        <span class="day-number">${day}</span>
        ${dayEvents.length>0?`<div class="event-dots">${'●'.repeat(Math.min(dayEvents.length,3))}</div>`:''}
      </div>`;
  }
  gridEl.innerHTML = html + '</div>';
  document.getElementById('prevMonth').onclick = () => { currentDate.setMonth(month-1,1); _refreshCalendar(); };
  document.getElementById('nextMonth').onclick = () => { currentDate.setMonth(month+1,1); _refreshCalendar(); };
}

window.selectDate = function(dateStr) {
  const [y,m,d] = dateStr.split('-').map(Number);
  currentDate = new Date(y,m-1,d);
  renderMiniMonthGrid();
  renderDayEvents(currentDate);
};

function renderDayEvents(date) {
  const contentEl = document.getElementById('dayEventsContent');
  if (!contentEl) return;
  document.getElementById('dayEventsHeader').innerHTML = `<h3>${date.toDateString()===new Date().toDateString()?'Oggi':date.toLocaleDateString('it-IT',{weekday:'long',day:'numeric',month:'long'})}</h3>`;
  const dayEvents = allEvents.filter(e=>new Date(e.start).toDateString()===date.toDateString()).sort((a,b)=>new Date(a.start)-new Date(b.start));
  if(dayEvents.length===0){ contentEl.innerHTML=`<div class="no-events"><div style="font-size:48px;margin-bottom:12px">📭</div><div>Nessun evento</div></div>`; return; }
  contentEl.innerHTML=dayEvents.map(event=>{
    const timeStr=event.allDay?'Tutto il giorno':`${new Date(event.start).toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'})}${event.end?` - ${new Date(event.end).toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'})}`:''}`;
    return `<div class="event-item" onclick="window.openEventDetail('${event.id}')"><div class="event-time">${timeStr}</div><div class="event-details"><div class="event-title">${event.title}</div>${event.extendedProps?.description?`<div class="event-description">${event.extendedProps.description}</div>`:''}</div></div>`}).join('');
}

window.openEventDetail = function(eventId) {
  const event = allEvents.find(e => e.id === eventId);
  if (!event) return;
  showEventModal({...event, start:new Date(event.start), end:event.end?new Date(event.end):null});
};

function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;
  calendar = new FullCalendar.Calendar(calEl, {
    initialView:'dayGridMonth', locale:'it', height:'80vh',
    headerToolbar: {left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,timeGridDay'},
    buttonText: {today:'Oggi',month:'Mese',week:'Settimana',day:'Giorno'},
    nowIndicator:true, selectable:true, editable:true,
    events: (info, success, failure) => API.listGoogleEvents('primary', info.startStr, info.endStr).then(success).catch(failure),
    select: (info) => { showEventModal(null, info.start, info.end); calendar.unselect(); },
    eventClick: (info) => showEventModal(info.event),
    eventDrop: async (info) => {
      try {
        const fd = new FormData();
        fd.append('allDay', info.event.allDay?'1':'0');
        if(info.event.allDay){
          fd.append('startDate', info.event.startStr);
          fd.append('endDate', info.event.endStr || nextDate(info.event.startStr));
        } else {
          fd.append('startDateTime', toLocalRFC3339(info.event.start.toISOString()));
          fd.append('endDateTime', toLocalRFC3339((info.event.end||info.event.start).toISOString()));
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

  const start = event?.start || startDate || new Date();
  const end = event?.end || endDate || new Date(start.getTime() + 3600000);
  const allDay = event?.allDay || false;
  const pad = (n)=>String(n).padStart(2,'0');
  const formatDateTime = (d)=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const formatDate = (d)=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

  document.body.insertAdjacentHTML('beforeend', `<div class="modal" id="${modalId}">
    <div class="modal-content">
      <input type="hidden" id="eventId" value="${event?.id || ''}" />
      <h2>${isEdit?'✏️ Modifica':'➕ Nuovo'} Evento</h2>
      <div class="form-group"><label>Titolo *</label><input type="text" id="eventTitle" value="${event?.title||''}" required/></div>
      <div class="form-group"><label>Descrizione</label><textarea id="eventDescription" rows="3">${event?.extendedProps?.description||''}</textarea></div>
      <div class="settings-row"><label><input type="checkbox" id="eventAllDay" ${allDay?'checked':''}/> Tutto il giorno</label></div>
      <div class="grid-2">
        <div class="form-group"><label>Inizio *</label><input type="${allDay?'date':'datetime-local'}" id="eventStart" value="${allDay?formatDate(start):formatDateTime(start)}" required/></div>
        <div class="form-group"><label>Fine *</label><input type="${allDay?'date':'datetime-local'}" id="eventEnd" value="${allDay?formatDate(end):formatDateTime(end)}" required/></div>
      </div>
      <div class="form-group"><label>Ricorrenza</label><select id="eventRecurrence"><option value="none">Non ripetere</option><option value="DAILY">Ogni giorno</option><option value="WEEKLY">Ogni settimana</option><option value="MONTHLY">Ogni mese</option><option value="YEARLY">Ogni anno</option></select></div>
      <div class="form-group"><label>Promemoria</label>
        <div id="reminderList" class="chips-container"></div>
        <div class="reminder-input-group">
          <select id="reminderMethod"><option value="popup">Notifica</option><option value="email">Email</option></select>
          <input type="number" id="reminderValue" min="1" value="30" />
          <select id="reminderUnit"><option value="minutes">minuti</option><option value="hours">ore</option><option value="days">giorni</option></select>
          <button class="btn secondary" id="addReminderBtn" type="button">+</button>
        </div>
      </div>
      <div class="btn-group">
        <button class="btn secondary" id="closeEventModal">Annulla</button>
        ${isEdit?`<button class="btn del" id="deleteEventBtn">Elimina</button>`:''}
        <button class="btn" id="saveEventBtn">${isEdit?'Salva Modifiche':'Crea Evento'}</button>
      </div>
    </div></div>`);

  const reminderListEl = document.getElementById('reminderList');
  const formatRemMins = (m)=>{if(m%1440===0&&m)return`${m/1440} ${m/1440>1?'giorni':'giorno'}`;if(m%60===0&&m)return`${m/60} ${m/60>1?'ore':'ora'}`;return`${m} minuti`};
  const renderRemChips=()=>{reminderListEl.innerHTML=_reminders.map((r,i)=>`<span class="chip" data-idx="${i}">${r.method==='email'?'📧':'🔔'} ${formatRemMins(r.minutes)} prima <button class="chip-x">✕</button></span>`).join('')};
  reminderListEl.onclick=(e)=>{if(e.target.classList.contains('chip-x')){_reminders.splice(parseInt(e.target.closest('.chip').dataset.idx,10),1);renderRemChips()}};
  document.getElementById('addReminderBtn').onclick=()=>{
    const unit=document.getElementById('reminderUnit').value,val=Math.max(1,Number(document.getElementById('reminderValue').value||1));
    const mins=unit==='days'?val*1440:unit==='hours'?val*60:val;
    if(_reminders.length<5){_reminders.push({method:document.getElementById('reminderMethod').value,minutes:mins});renderRemChips()}else{alert('Max 5 promemoria.')}
  };

  if (isEdit && event.extendedProps) {
    const rrule=event.extendedProps.recurrence?.[0]||'';
    const recurSel=document.getElementById('eventRecurrence');
    if(rrule.includes('DAILY'))recurSel.value='DAILY';else if(rrule.includes('WEEKLY'))recurSel.value='WEEKLY';else if(rrule.includes('MONTHLY'))recurSel.value='MONTHLY';else if(rrule.includes('YEARLY'))recurSel.value='YEARLY';
    
    let remindersData=event.extendedProps.reminders;
    if(typeof remindersData==='string'){try{remindersData=JSON.parse(remindersData)}catch(e){remindersData=[]}}
    if(Array.isArray(remindersData)){_reminders=remindersData}
    renderRemChips();
  }

  document.getElementById('closeEventModal').onclick = () => document.getElementById(modalId).remove();
  document.getElementById('saveEventBtn').onclick = saveEvent;
  if(isEdit) document.getElementById('deleteEventBtn').onclick = () => deleteEvent(event.id);
}

function getFormDataFromModal() {
    const title = document.getElementById('eventTitle').value.trim();
    if (!title) { alert('Il titolo è obbligatorio.'); return null; }
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('eventDescription').value.trim());
    const allDay = document.getElementById('eventAllDay').checked;
    fd.append('allDay', allDay ? '1' : '0');
    if (allDay) {
        fd.append('startDate', document.getElementById('eventStart').value);
        fd.append('endDate', nextDate(document.getElementById('eventEnd').value));
    } else {
        fd.append('startDateTime', toLocalRFC3339(document.getElementById('eventStart').value));
        fd.append('endDateTime', toLocalRFC3339(document.getElementById('eventEnd').value));
    }
    const recur = document.getElementById('eventRecurrence').value;
    fd.append('recurrence', (recur && recur !== 'none') ? `RRULE:FREQ=${recur}` : '');
    fd.append('reminders', JSON.stringify(_reminders));
    return fd;
}

async function saveEvent() {
    const eventId = document.getElementById('eventId').value;
    const fd = getFormDataFromModal();
    if (!fd) return;
    try {
        await API[eventId ? 'updateGoogleEvent' : 'createGoogleEvent']('primary', eventId || fd, eventId ? fd : null);
        document.getElementById('eventModal').remove();
        await _refreshCalendar();
    } catch (e) {
        console.error("Errore salvataggio:", e);
        alert("Errore durante il salvataggio dell'evento.");
    }
}

async function deleteEvent(eventId) {
  if (!confirm(`Sei sicuro di voler eliminare questo evento?`)) return;
  try {
    await API.deleteGoogleEvent('primary', eventId);
    document.getElementById('eventModal').remove();
    await _refreshCalendar();
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

