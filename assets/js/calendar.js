/** Inizializza FullCalendar con configurazione performante */
function initFullCalendar() {
  const calEl = document.getElementById('cal');
  if (!calEl) return;

  const isMobile = window.matchMedia('(max-width: 768px)').matches;
  const isVerySmall = window.matchMedia('(max-width: 480px)').matches;

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
