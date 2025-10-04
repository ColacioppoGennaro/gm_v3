
import React, { useState, useMemo } from 'react';
import { CalendarEvent } from '../types';
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from './icons/Icons';

interface CalendarViewProps {
  events: CalendarEvent[];
  onAddEvent: (event: Omit<CalendarEvent, 'id'>) => void;
}

const daysOfWeek = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

const CalendarView: React.FC<CalendarViewProps> = ({ events, onAddEvent }) => {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [newEventTitle, setNewEventTitle] = useState('');
  const [newEventDesc, setNewEventDesc] = useState('');

  const firstDayOfMonth = useMemo(() => new Date(currentDate.getFullYear(), currentDate.getMonth(), 1), [currentDate]);
  const lastDayOfMonth = useMemo(() => new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0), [currentDate]);

  const daysInMonth = useMemo(() => {
    const days: (Date | null)[] = [];
    const startingDay = (firstDayOfMonth.getDay() + 6) % 7; // 0 = Monday
    const totalDays = lastDayOfMonth.getDate();

    for (let i = 0; i < startingDay; i++) {
      days.push(null);
    }
    for (let i = 1; i <= totalDays; i++) {
      days.push(new Date(currentDate.getFullYear(), currentDate.getMonth(), i));
    }
    return days;
  }, [firstDayOfMonth, lastDayOfMonth, currentDate]);

  const changeMonth = (offset: number) => {
    setCurrentDate(prev => new Date(prev.getFullYear(), prev.getMonth() + offset, 1));
  };
  
  const handleAddEventClick = (date: Date) => {
    setSelectedDate(date);
    setIsModalOpen(true);
  };
  
  const handleSaveEvent = () => {
    if (newEventTitle && selectedDate) {
        onAddEvent({ title: newEventTitle, description: newEventDesc, date: selectedDate });
        setIsModalOpen(false);
        setNewEventTitle('');
        setNewEventDesc('');
    }
  };

  const EventModal = () => (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
        <div className="bg-slate-800 p-6 rounded-lg w-full max-w-md">
            <h3 className="text-xl font-bold mb-4">Aggiungi evento per il {selectedDate?.toLocaleDateString()}</h3>
            <div className="space-y-4">
                <input type="text" placeholder="Titolo evento" value={newEventTitle} onChange={e => setNewEventTitle(e.target.value)} className="w-full bg-slate-700 p-2 rounded-md" />
                <textarea placeholder="Descrizione" value={newEventDesc} onChange={e => setNewEventDesc(e.target.value)} className="w-full bg-slate-700 p-2 rounded-md" rows={3}></textarea>
            </div>
            <div className="flex justify-end gap-3 mt-6">
                <button onClick={() => setIsModalOpen(false)} className="px-4 py-2 bg-slate-600 rounded-md hover:bg-slate-500">Annulla</button>
                <button onClick={handleSaveEvent} className="px-4 py-2 bg-indigo-600 rounded-md hover:bg-indigo-500">Salva</button>
            </div>
        </div>
    </div>
  );

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-3xl font-bold">
          {currentDate.toLocaleString('it-IT', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase())}
        </h2>
        <div className="flex items-center gap-2">
          <button onClick={() => changeMonth(-1)} className="p-2 rounded-md bg-slate-700 hover:bg-slate-600"><ChevronLeftIcon /></button>
          <button onClick={() => setCurrentDate(new Date())} className="px-4 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded-md">Oggi</button>
          <button onClick={() => changeMonth(1)} className="p-2 rounded-md bg-slate-700 hover:bg-slate-600"><ChevronRightIcon /></button>
        </div>
      </div>
      
      <div className="grid grid-cols-7 gap-px bg-slate-700 border border-slate-700 rounded-lg overflow-hidden">
        {daysOfWeek.map(day => (
          <div key={day} className="text-center font-semibold py-3 bg-slate-800 text-sm text-slate-300">{day}</div>
        ))}
        {daysInMonth.map((day, index) => {
          const isToday = day && day.toDateString() === new Date().toDateString();
          const eventsOnDay = day ? events.filter(e => e.date.toDateString() === day.toDateString()) : [];
          return (
            <div key={index} className="relative bg-slate-800 min-h-[120px] p-2 group">
              {day && (
                <>
                  <time dateTime={day.toISOString()} className={`text-sm font-semibold ${isToday ? 'bg-indigo-600 text-white rounded-full h-7 w-7 flex items-center justify-center' : 'text-slate-300'}`}>
                    {day.getDate()}
                  </time>
                  <div className="mt-1 space-y-1">
                    {eventsOnDay.slice(0, 2).map(event => (
                        <div key={event.id} className="bg-indigo-900/70 text-indigo-200 text-xs p-1 rounded truncate cursor-pointer hover:bg-indigo-800">
                           {event.title}
                        </div>
                    ))}
                    {eventsOnDay.length > 2 && <div className="text-xs text-slate-400">+ {eventsOnDay.length - 2} altro</div>}
                  </div>
                  <button onClick={() => handleAddEventClick(day)} className="absolute bottom-2 right-2 p-1 bg-slate-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                    <PlusIcon className="h-4 w-4" />
                  </button>
                </>
              )}
            </div>
          );
        })}
      </div>
      {isModalOpen && <EventModal />}
    </div>
  );
};

export default CalendarView;
