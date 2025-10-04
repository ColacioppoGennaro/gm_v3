
import React, { useState } from 'react';
import { XMarkIcon, SparklesIcon } from './icons/Icons';

interface UpgradeModalProps {
  onClose: () => void;
  onUpgrade: (promoCode: string) => void;
}

const UpgradeModal: React.FC<UpgradeModalProps> = ({ onClose, onUpgrade }) => {
  const [promoCode, setPromoCode] = useState('');

  return (
    <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50" onClick={onClose}>
      <div className="bg-slate-800 border border-slate-700 rounded-lg w-full max-w-md p-8 relative" onClick={(e) => e.stopPropagation()}>
        <button onClick={onClose} className="absolute top-4 right-4 text-slate-400 hover:text-white">
          <XMarkIcon />
        </button>

        <div className="text-center">
            <SparklesIcon className="h-12 w-12 text-yellow-400 mx-auto" />
            <h2 className="text-2xl font-bold mt-4">Upgrade a Pro</h2>
            <p className="text-slate-400 mt-2">Sblocca tutte le funzionalità e aumenta i tuoi limiti per un'esperienza senza interruzioni.</p>
        </div>

        <div className="mt-6">
            <label htmlFor="promo-code" className="block text-sm font-medium text-slate-300">Codice Promozionale</label>
            <input
                type="text"
                id="promo-code"
                value={promoCode}
                onChange={(e) => setPromoCode(e.target.value)}
                placeholder="Inserisci il codice di test"
                className="mt-1 block w-full bg-slate-900 border border-slate-600 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
        </div>

        <div className="mt-8">
            <button
                onClick={() => onUpgrade(promoCode)}
                className="w-full bg-indigo-600 text-white font-bold py-3 rounded-md hover:bg-indigo-500 transition-colors"
            >
                Attiva Piano Pro
            </button>
            <p className="text-xs text-slate-500 text-center mt-3">Per questo test, usa il codice: PRO_TRIAL_2024</p>
        </div>
      </div>
    </div>
  );
};

export default UpgradeModal;
