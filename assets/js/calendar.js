import { Calendar } from '@fullcalendar/core';
import dayGrid from '@fullcalendar/daygrid';
import timeGrid from '@fullcalendar/timegrid';
import interaction from '@fullcalendar/interaction';

const calendarEl = document.getElementById('calendar');
const calendarId = window.USER_CALENDAR_ID;

const calendar = new Calendar(calendarEl, {
  plugins: [dayGrid, timeGrid, interaction],
  initialView: 'timeGridWeek',
  selectable: true,
  events: (info, success, failure) => {
    fetch(`/api/google/events.php?calendarId=${encodeURIComponent(calendarId)}&start=${info.startStr}&end=${info.endStr}`, { credentials:'same-origin' })
      .then(r=>r.json()).then(success).catch(failure)
  },
  select: async (sel) => {
    const payload = {
      calendarId,
      title: prompt('Titolo evento?') || 'Nuovo evento',
      start: sel.startStr,
      end:   sel.endStr,
      reminders: [{method:'popup', minutes:30}]
    };
    await fetch(`/api/google/events.php`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      credentials:'same-origin', body: JSON.stringify({ ...payload })
    });
    calendar.refetchEvents();
  },
});
calendar.render();
