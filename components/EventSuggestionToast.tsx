
import React from 'react';
import { AISuggestedEvent } from '../types';
import { CalendarPlusIcon, XMarkIcon, CheckIcon } from './icons/Icons';

interface EventSuggestionToastProps {
  suggestion: AISuggestedEvent;
  onConfirm: () => void;
  onDismiss: () => void;
}

const EventSuggestionToast: React.FC<EventSuggestionToastProps> = ({ suggestion, onConfirm, onDismiss }) => {
  return (
    <div className="fixed bottom-5 right-5 w-full max-w-sm bg-slate-800 border border-indigo-500/50 rounded-lg shadow-2xl p-4 animate-fade-in-up z-50">
      <div className="flex items-start">
        <div className="flex-shrink-0 pt-0.5">
          <CalendarPlusIcon className="h-6 w-6 text-indigo-400" />
        </div>
        <div className="ml-3 w-0 flex-1">
          <p className="text-sm font-bold text-white">Suggerimento Calendario</p>
          <p className="mt-1 text-sm text-slate-300">
            Vuoi creare un evento per "{suggestion.title}" il {new Date(suggestion.date).toLocaleDateString()}?
          </p>
          <div className="mt-3 flex gap-3">
            <button
              onClick={onConfirm}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
            >
              <CheckIcon className="h-4 w-4 mr-1"/>
              Sì, aggiungi
            </button>
            <button
              onClick={onDismiss}
              className="inline-flex items-center px-3 py-1.5 border border-slate-600 text-xs font-medium rounded-md text-slate-300 bg-slate-700 hover:bg-slate-600"
            >
              No, grazie
            </button>
          </div>
        </div>
        <div className="ml-4 flex-shrink-0 flex">
          <button onClick={onDismiss} className="inline-flex text-slate-400 hover:text-white">
            <span className="sr-only">Close</span>
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>
      </div>
      <style>{`
        @keyframes fade-in-up {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
          animation: fade-in-up 0.3s ease-out forwards;
        }
      `}</style>
    </div>
  );
};

export default EventSuggestionToast;
