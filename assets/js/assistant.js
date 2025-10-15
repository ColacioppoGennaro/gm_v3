/**
 * assets/js/assistant.js
 * Frontend Assistente AI conversazionale
 * 
 * Features:
 * - Chat UI con conversazione multi-turno
 * - Supporto voce (Speech-to-Text e Text-to-Speech)
 * - State management locale
 * - Indicatori visivi (typing, intent, turn counter)
 */

import { API } from './api.js';

// State locale
const assistantState = {
  conversationActive: false,
  currentIntent: null,
  turnCount: 0,
  isListening: false,
  isSpeaking: false
};

/**
 * Apri modal assistente
 */
export function openAssistantModal() {
  // Rimuovi modal esistente se presente
  document.getElementById('assistantModal')?.remove();
  
  const modal = document.createElement('div');
  modal.id = 'assistantModal';
  modal.className = 'modal';
  
  modal.innerHTML = `
    <div class="modal-content assistant-modal-content">
      <div class="assistant-header">
        <div>
          <h2>🤖 Assistente AI</h2>
          <p class="assistant-subtitle">Crea eventi calendario con dialogo naturale</p>
        </div>
        <button class="btn-close" id="closeAssistantModal">✕</button>
      </div>
      
      <div class="assistant-status">
        <span class="status-badge" id="assistantStatus">Pronto</span>
        <span class="turn-counter" id="turnCounter" style="display:none">Turno: 0</span>
      </div>
      
      <div class="assistant-chat" id="assistantChat">
        <div class="chat-message bot">
          <div class="message-avatar">🤖</div>
          <div class="message-bubble">
            Ciao! Sono il tuo assistente AI. Posso aiutarti a:
            <ul style="margin:8px 0;padding-left:20px">
              <li>Creare eventi calendario</li>
              <li>Impostare promemoria</li>
              <li>Gestire scadenze</li>
            </ul>
            Come posso aiutarti?
          </div>
        </div>
      </div>
      
      <div class="assistant-typing" id="assistantTyping" style="display:none">
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span style="margin-left:8px;font-size:13px">Sto pensando...</span>
      </div>
      
      <div class="assistant-input-wrapper">
        <button class="btn-voice" id="btnVoiceInput" title="Input vocale">🎤</button>
        <input 
          type="text" 
          id="assistantInput" 
          placeholder="Scrivi o parla..."
          autocomplete="off"
        />
        <button class="btn-send" id="btnSendMessage">➤</button>
      </div>
      
      <div class="assistant-footer">
        <button class="btn secondary small" id="btnResetConversation">🔄 Nuova conversazione</button>
        <button class="btn secondary small" id="btnToggleTTS">🔊 Audio: ON</button>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Event listeners
  document.getElementById('closeAssistantModal').onclick = closeAssistantModal;
  document.getElementById('btnSendMessage').onclick = sendMessage;
  document.getElementById('btnVoiceInput').onclick = toggleVoiceInput;
  document.getElementById('btnResetConversation').onclick = resetConversation;
  document.getElementById('btnToggleTTS').onclick = toggleTTS;
  
  document.getElementById('assistantInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  
  // Focus input
  setTimeout(() => document.getElementById('assistantInput')?.focus(), 100);
}

/**
 * Chiudi modal
 */
function closeAssistantModal() {
  stopSpeaking();
  stopListening();
  document.getElementById('assistantModal')?.remove();
}

/**
 * Invia messaggio
 */
async function sendMessage() {
  const input = document.getElementById('assistantInput');
  const message = input.value.trim();
  
  if (!message) return;
  
  // Aggiungi messaggio utente alla chat
  addMessageToChat(message, 'user');
  input.value = '';
  
  // Mostra typing indicator
  showTyping();
  
  try {
    const response = await api('api/assistant.php', { message });
    
    hideTyping();
    
    if (response.success) {
      // Aggiorna state
      assistantState.conversationActive = response.status === 'incomplete';
      assistantState.currentIntent = response.intent || null;
      assistantState.turnCount = response.turn || 0;
      
      // Aggiorna UI status
      updateStatus(response.status, response.intent);
      
      // Aggiungi risposta bot
      addMessageToChat(response.message, 'bot');
      
      // TTS se abilitato
      if (window.S.assistantTTS) {
        speakMessage(response.message);
      }
      
      // Se conversazione completata e evento creato, mostra azioni
      if (response.status === 'complete' && response.data?.event_id) {
        showEventActions(response.data);
      }
      
    } else {
      hideTyping();
      addMessageToChat('❌ ' + (response.message || 'Errore sconosciuto'), 'error');
    }
    
  } catch (error) {
    hideTyping();
    console.error('Assistant error:', error);
    addMessageToChat('❌ Errore di connessione. Riprova.', 'error');
  }
}

/**
 * Aggiungi messaggio alla chat
 */
function addMessageToChat(text, sender) {
  const chatEl = document.getElementById('assistantChat');
  if (!chatEl) return;
  
  const messageDiv = document.createElement('div');
  messageDiv.className = `chat-message ${sender}`;
  
  const avatar = sender === 'user' ? '👤' : sender === 'bot' ? '🤖' : '⚠️';
  
  messageDiv.innerHTML = `
    <div class="message-avatar">${avatar}</div>
    <div class="message-bubble">${formatMessage(text)}</div>
  `;
  
  chatEl.appendChild(messageDiv);
  
  // Scroll to bottom
  chatEl.scrollTop = chatEl.scrollHeight;
}

/**
 * Formatta messaggio (supporto markdown base)
 */
function formatMessage(text) {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br>');
}

/**
 * Mostra typing indicator
 */
function showTyping() {
  const typing = document.getElementById('assistantTyping');
  if (typing) typing.style.display = 'flex';
}

/**
 * Nascondi typing indicator
 */
function hideTyping() {
  const typing = document.getElementById('assistantTyping');
  if (typing) typing.style.display = 'none';
}

/**
 * Aggiorna status badge
 */
function updateStatus(status, intent) {
  const statusEl = document.getElementById('assistantStatus');
  const turnEl = document.getElementById('turnCounter');
  
  if (!statusEl) return;
  
  const statusMap = {
    'complete': { text: '✅ Completato', color: 'var(--success, #22c55e)' },
    'incomplete': { text: '💬 In conversazione...', color: 'var(--accent, #7c3aed)' },
    'error': { text: '❌ Errore', color: 'var(--error, #ef4444)' }
  };
  
  const statusConfig = statusMap[status] || { text: 'Pronto', color: 'var(--muted)' };
  
  statusEl.textContent = statusConfig.text;
  statusEl.style.background = statusConfig.color;
  
  // Mostra turn counter se in conversazione
  if (turnEl) {
    if (status === 'incomplete' && assistantState.turnCount > 0) {
      turnEl.style.display = 'inline-block';
      turnEl.textContent = `Turno: ${assistantState.turnCount}`;
    } else {
      turnEl.style.display = 'none';
    }
  }
}

/**
 * Mostra azioni post-creazione evento
 */
function showEventActions(eventData) {
  const chatEl = document.getElementById('assistantChat');
  if (!chatEl) return;
  
  const actionsDiv = document.createElement('div');
  actionsDiv.className = 'event-actions';
  actionsDiv.innerHTML = `
    <div style="background:var(--bg-secondary);border-radius:8px;padding:12px;margin-top:12px">
      <div style="font-weight:600;margin-bottom:8px">Azioni rapide:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn small" onclick="window.viewCalendar()">📅 Vai al calendario</button>
        <button class="btn small secondary" onclick="window.location.reload()">🔄 Aggiorna dashboard</button>
      </div>
    </div>
  `;
  
  chatEl.appendChild(actionsDiv);
  chatEl.scrollTop = chatEl.scrollHeight;
}

/**
 * Reset conversazione
 */
async function resetConversation() {
  if (!confirm('Vuoi iniziare una nuova conversazione?')) return;
  
  try {
    await api('api/assistant.php', { reset: true });
    
    // Reset state locale
    assistantState.conversationActive = false;
    assistantState.currentIntent = null;
    assistantState.turnCount = 0;
    
    // Pulisci chat
    const chatEl = document.getElementById('assistantChat');
    if (chatEl) {
      chatEl.innerHTML = `
        <div class="chat-message bot">
          <div class="message-avatar">🤖</div>
          <div class="message-bubble">
            Conversazione resettata. Come posso aiutarti?
          </div>
        </div>
      `;
    }
    
    updateStatus('complete', null);
    
  } catch (error) {
    console.error('Reset error:', error);
    alert('Errore durante il reset');
  }
}

/**
 * Toggle Text-to-Speech
 */
function toggleTTS() {
  window.S.assistantTTS = !window.S.assistantTTS;
  const btn = document.getElementById('btnToggleTTS');
  if (btn) {
    btn.textContent = window.S.assistantTTS ? '🔊 Audio: ON' : '🔇 Audio: OFF';
  }
  
  if (!window.S.assistantTTS) {
    stopSpeaking();
  }
}

/**
 * Leggi messaggio (TTS)
 */
function speakMessage(text) {
  if (!window.S.assistantTTS) return;
  
  // Pulisci testo da markdown
  const cleanText = text
    .replace(/\*\*/g, '')
    .replace(/[✅📅🗓️🏷️🔔🔁]/g, '');
  
  const utterance = new SpeechSynthesisUtterance(cleanText);
  
  // Usa voce italiana se disponibile
  const voices = speechSynthesis.getVoices();
  const italianVoice = voices.find(v => v.lang.startsWith('it'));
  if (italianVoice) {
    utterance.voice = italianVoice;
  }
  
  utterance.rate = 1.0;
  utterance.pitch = 1.0;
  
  assistantState.isSpeaking = true;
  
  utterance.onend = () => {
    assistantState.isSpeaking = false;
  };
  
  speechSynthesis.speak(utterance);
}

/**
 * Ferma TTS
 */
function stopSpeaking() {
  if (assistantState.isSpeaking) {
    speechSynthesis.cancel();
    assistantState.isSpeaking = false;
  }
}

/**
 * Toggle input vocale (Speech-to-Text)
 */
function toggleVoiceInput() {
  if (assistantState.isListening) {
    stopListening();
  } else {
    startListening();
  }
}

/**
 * Avvia ascolto vocale
 */
function startListening() {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    alert('Il tuo browser non supporta il riconoscimento vocale');
    return;
  }
  
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const recognition = new SpeechRecognition();
  
  recognition.lang = 'it-IT';
  recognition.continuous = false;
  recognition.interimResults = false;
  
  recognition.onstart = () => {
    assistantState.isListening = true;
    const btn = document.getElementById('btnVoiceInput');
    if (btn) {
      btn.textContent = '🔴';
      btn.style.background = 'var(--error, #ef4444)';
    }
  };
  
  recognition.onresult = (event) => {
    const transcript = event.results[0][0].transcript;
    const input = document.getElementById('assistantInput');
    if (input) {
      input.value = transcript;
      // Invia automaticamente
      setTimeout(() => sendMessage(), 300);
    }
  };
  
  recognition.onerror = (event) => {
    console.error('Speech recognition error:', event.error);
    stopListening();
    
    if (event.error === 'no-speech') {
      alert('Nessun input vocale rilevato');
    } else {
      alert('Errore riconoscimento vocale: ' + event.error);
    }
  };
  
  recognition.onend = () => {
    stopListening();
  };
  
  window.S.recognition = recognition;
  recognition.start();
}

/**
 * Ferma ascolto vocale
 */
function stopListening() {
  assistantState.isListening = false;
  
  if (window.S.recognition) {
    window.S.recognition.stop();
    window.S.recognition = null;
  }
  
  const btn = document.getElementById('btnVoiceInput');
  if (btn) {
    btn.textContent = '🎤';
    btn.style.background = '';
  }
}

/**
 * Helper: vai al calendario
 */
window.viewCalendar = function() {
  closeAssistantModal();
  window.S.view = 'calendar';
  window.renderApp();
};

// Inizializza TTS settings
if (!('assistantTTS' in window.S)) {
  window.S.assistantTTS = true; // Default: audio ON
}

// Esporta funzione globalmente
window.openAssistantModal = openAssistantModal;
