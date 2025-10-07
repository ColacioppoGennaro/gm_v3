// Stato globale (identico a prima)
window.S = { 
  user: null, 
  docs: [], 
  events: [],
  categories: [],
  view: 'login',
  stats: {chatToday: 0, totalSize: 0},
  filterCategory: '',
  chatContext: null,
  ttsState: {}
};

// Helper API (identico)
window.api = (p, fd=null) => fetch(p, {method: fd?'POST':'GET', body: fd}).then(r=>r.json());

// === TTS FUNCTIONS (copiate identiche) ===
function getItalianVoices() {
  const voices = speechSynthesis.getVoices();
  const italian = voices.filter(v => v.lang.startsWith('it'));
  return italian.length > 0 ? italian : voices;
}

function speakText(msgId) {
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (!msg) return;
  
  const text = msg.querySelector('.message-text').textContent;
  const voiceSelect = msg.querySelector('.voice-select');
  const speedSelect = msg.querySelector('.speed-select');
  const playBtn = msg.querySelector('.play-btn');
  const stopBtn = msg.querySelector('.stop-btn');
  
  if (S.ttsState[msgId] && S.ttsState[msgId].speaking) {
    speechSynthesis.cancel();
    S.ttsState[msgId].speaking = false;
    playBtn.classList.remove('hidden');
    stopBtn.classList.add('hidden');
    return;
  }
  
  const utterance = new SpeechSynthesisUtterance(text);
  const voices = speechSynthesis.getVoices();
  utterance.voice = voices[voiceSelect.value] || voices[0];
  utterance.rate = parseFloat(speedSelect.value);
  
  utterance.onend = () => {
    S.ttsState[msgId].speaking = false;
    playBtn.classList.remove('hidden');
    stopBtn.classList.add('hidden');
  };
  
  S.ttsState[msgId] = { speaking: true };
  playBtn.classList.add('hidden');
  stopBtn.classList.remove('hidden');
  
  speechSynthesis.speak(utterance);
}

function stopSpeaking(msgId) {
  speechSynthesis.cancel();
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (msg) {
    msg.querySelector('.play-btn').classList.remove('hidden');
    msg.querySelector('.stop-btn').classList.add('hidden');
  }
  if (S.ttsState[msgId]) {
    S.ttsState[msgId].speaking = false;
  }
}

function copyToClipboard(msgId) {
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (!msg) return;
  
  const text = msg.querySelector('.message-text').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = msg.querySelector('.copy-btn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '✓';
    setTimeout(() => btn.innerHTML = originalText, 1500);
  });
}

function useAsContext(msgId) {
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (!msg) return;
  
  const text = msg.querySelector('.message-text').textContent;
  S.chatContext = text;
  
  const contextBox = document.getElementById('contextBox');
  if (!contextBox) return;
  
  contextBox.className = 'context-box';
  contextBox.innerHTML = '<button class="remove-context" onclick="removeContext()">✕ Rimuovi contesto</button>' +
    '<label style="display:block;margin-bottom:8px;font-size:13px;color:var(--accent);font-weight:600">📋 Contesto dalla risposta documenti:</label>' +
    '<textarea id="contextText" readonly>' + text + '</textarea>' +
    '<div style="margin-top:8px;font-size:12px;color:var(--muted)">' +
    '💡 Aggiungi una domanda di follow-up nell\'input qui sotto e chiedi a Gemini' +
    '</div>';
  
  document.querySelector('#qAI').scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => document.querySelector('#qAI').focus(), 500);
}

function removeContext() {
  S.chatContext = null;
  const contextBox = document.getElementById('contextBox');
  if (contextBox) {
    contextBox.className = 'hidden';
    contextBox.innerHTML = '';
  }
}

// Esporta globali come prima
window.speakText = speakText;
window.stopSpeaking = stopSpeaking;
window.copyToClipboard = copyToClipboard;
window.useAsContext = useAsContext;
window.removeContext = removeContext;
window.getItalianVoices = getItalianVoices;

if (speechSynthesis.onvoiceschanged !== undefined) {
  speechSynthesis.onvoiceschanged = () => {
    getItalianVoices();
  };
}
