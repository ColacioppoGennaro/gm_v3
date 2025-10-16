/**
 * assets/js/chat.js
 * Gestione chat documenti, AI generica e TTS
 */

import { API } from './api.js';

/**
 * Chiedi ai documenti
 */
export async function askDocs() {
  const q = document.getElementById('qDocs');
  const category = document.getElementById('categoryDocs');
  
  if (!q.value.trim()) {
    return alert('Inserisci una domanda');
  }
  
  if (window.S.user.role === 'pro' && !category.value) {
    return alert('Seleziona una categoria');
  }

  const btn = document.getElementById('askDocsBtn');
  const adherence = document.getElementById('adherence').value;
  const showRefs = document.getElementById('showRefs').checked;
  
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';
  
  try {
    const r = await API.askDocs(q.value, category.value || '', adherence, showRefs);
    
    if (r.success && r.source !== 'none') {
      addMessageToLog(r.answer, 'docs', q.value);
      q.value = '';
      window.S.stats.chatToday++;
      updateChatCounter();
      document.getElementById('qCount').textContent = window.S.stats.chatToday;
    } else if (r.can_ask_ai) {
      alert('Non ho trovato informazioni nei tuoi documenti. Prova a chiedere a Gemini qui sotto!');
    }
  } catch (e) {
    console.error("Chiamata chat fallita", e);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '➤';
  }
}

/**
 * Chiedi all'AI generica (Gemini)
 */
export async function askAI() {
  const q = document.getElementById('qAI');
  
  if (!q.value.trim() && !window.S.chatContext) {
    return alert('Inserisci una domanda');
  }

  const btn = document.getElementById('askAIBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';
  
  try {
    const r = await API.askAI(q.value, window.S.chatContext);
    
    if (r.success) {
      addMessageToLog(r.answer, 'ai', q.value);
      q.value = '';
      removeContext();
      window.S.stats.chatToday++;
      updateChatCounter();
      document.getElementById('qCount').textContent = window.S.stats.chatToday;
    }
  } catch (e) {
    console.error("Chiamata chat fallita", e);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '➤';
  }
}

/**
 * Aggiungi messaggio al log chat
 */
function addMessageToLog(answer, type, question) {
  const msgId = 'msg_' + Date.now();
  const log = document.getElementById('chatLog');
  if (!log) return;
  
  const voices = getItalianVoices();
  const voiceOptions = voices.map((v, i) => 
    `<option value="${i}">${v.name} (${v.lang})</option>`
  ).join('');

  const item = document.createElement('div');
  item.className = `chat-message ${type}`;
  item.dataset.msgid = msgId;

  const title = type === 'docs' 
    ? '📄 Risposta dai documenti' 
    : '🤖 Risposta AI Generica (Google Gemini)';
  
  const useContextBtn = type === 'docs' 
    ? `<button class="btn small" onclick="window.useAsContext('${msgId}')">📋 Usa come contesto</button>` 
    : '';

  item.innerHTML = `
    <div style="font-weight:600;margin-bottom:8px">${title}</div>
    <div class="message-text" style="white-space:pre-wrap">${answer}</div>
    <div class="chat-controls">
      ${useContextBtn}
      <button class="btn small icon copy-btn" onclick="window.copyToClipboard('${msgId}')" title="Copia">📋</button>
      <select class="voice-select" title="Voce">${voiceOptions}</select>
      <select class="speed-select" title="Velocità">
        <option value="0.75">0.75x</option>
        <option value="1" selected>1x</option>
        <option value="1.25">1.25x</option>
      </select>
      <button class="btn small icon play-btn" onclick="window.speakText('${msgId}')" title="Leggi">▶️</button>
      <button class="btn small icon stop-btn hidden" onclick="window.stopSpeaking('${msgId}')" title="Stop">⏸️</button>
    </div>
  `;
  
  log.insertBefore(item, log.firstChild);
}

/**
 * Ottieni voci italiane disponibili
 */
function getItalianVoices() {
  const voices = speechSynthesis.getVoices();
  const italian = voices.filter(v => v.lang.startsWith('it'));
  return italian.length > 0 ? italian : voices;
}

/**
 * Leggi testo (TTS)
 */
export function speakText(msgId) {
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (!msg) return;
  
  const text = msg.querySelector('.message-text').textContent;
  const voiceSelect = msg.querySelector('.voice-select');
  const speedSelect = msg.querySelector('.speed-select');
  const playBtn = msg.querySelector('.play-btn');
  const stopBtn = msg.querySelector('.stop-btn');
  
  if (window.S.ttsState[msgId] && window.S.ttsState[msgId].speaking) {
    speechSynthesis.cancel();
    window.S.ttsState[msgId].speaking = false;
    playBtn.classList.remove('hidden');
    stopBtn.classList.add('hidden');
    return;
  }
  
  const utterance = new SpeechSynthesisUtterance(text);
  const voices = speechSynthesis.getVoices();
  utterance.voice = voices[voiceSelect.value] || voices[0];
  utterance.rate = parseFloat(speedSelect.value);
  
  utterance.onend = () => {
    window.S.ttsState[msgId].speaking = false;
    playBtn.classList.remove('hidden');
    stopBtn.classList.add('hidden');
  };
  
  window.S.ttsState[msgId] = { speaking: true };
  playBtn.classList.add('hidden');
  stopBtn.classList.remove('hidden');
  
  speechSynthesis.speak(utterance);
}

/**
 * Ferma TTS
 */
export function stopSpeaking(msgId) {
  speechSynthesis.cancel();
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (msg) {
    msg.querySelector('.play-btn').classList.remove('hidden');
    msg.querySelector('.stop-btn').classList.add('hidden');
  }
  if (window.S.ttsState[msgId]) {
    window.S.ttsState[msgId].speaking = false;
  }
}

/**
 * Copia negli appunti
 */
export function copyToClipboard(msgId) {
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

/**
 * Usa risposta come contesto
 */
export function useAsContext(msgId) {
  const msg = document.querySelector('[data-msgid="' + msgId + '"]');
  if (!msg) return;
  
  const text = msg.querySelector('.message-text').textContent;
  window.S.chatContext = text;
  
  const contextBox = document.getElementById('contextBox');
  if (!contextBox) return;
  
  contextBox.className = 'context-box';
  contextBox.innerHTML = `
    <button class="remove-context" onclick="window.removeContext()">✕ Rimuovi contesto</button>
    <label style="display:block;margin-bottom:8px;font-size:13px;color:var(--accent);font-weight:600">
      📋 Contesto dalla risposta documenti:
    </label>
    <textarea id="contextText" readonly>${text}</textarea>
    <div style="margin-top:8px;font-size:12px;color:var(--muted)">
      💡 Aggiungi una domanda di follow-up nell'input qui sotto e chiedi a Gemini
    </div>
  `;
  
  document.querySelector('#qAI').scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => document.querySelector('#qAI').focus(), 500);
}

/**
 * Rimuovi contesto
 */
export function removeContext() {
  window.S.chatContext = null;
  const contextBox = document.getElementById('contextBox');
  if (contextBox) {
    contextBox.className = 'hidden';
    contextBox.innerHTML = '';
  }
}

/**
 * Aggiorna contatore chat
 */
export function updateChatCounter() {
  const qCountChat = document.getElementById('qCountChat');
  if (qCountChat && window.S.stats) {
    qCountChat.textContent = window.S.stats.chatToday || 0;
  }
}

// Esporta funzioni globalmente per onclick HTML
window.speakText = speakText;
window.stopSpeaking = stopSpeaking;
window.copyToClipboard = copyToClipboard;
window.useAsContext = useAsContext;
window.removeContext = removeContext;

// Setup voices
if (speechSynthesis.onvoiceschanged !== undefined) {
  speechSynthesis.onvoiceschanged = () => {
    getItalianVoices();
  };
}
