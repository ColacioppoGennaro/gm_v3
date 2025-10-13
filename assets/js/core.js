// ========================================
// assets/js/core.js  —  SOLO stato globale
// Compatibile con ES Modules + fallback su window.*
// ========================================

// Stato condiviso dell'app
export const S = {
  user: null,
  docs: [],
  events: [],
  categories: [],
  view: 'login',
  stats: { chatToday: 0, totalSize: 0 },
  filterCategory: '',
  chatContext: null,
  ttsState: {} // { [msgId]: { speaking: boolean, utterance?: SpeechSynthesisUtterance|null } }
};

// Helper leggeri di stato (opzionali ma comodi)
export function setState(patch = {}) {
  Object.assign(S, patch);
  return S;
}

export function resetTTSState() {
  S.ttsState = {};
  return S.ttsState;
}

// Fallback globale per codice legacy non-modulare
if (typeof window !== 'undefined') {
  window.S = S;
  window.setState = setState;
  window.resetTTSState = resetTTSState;
}
