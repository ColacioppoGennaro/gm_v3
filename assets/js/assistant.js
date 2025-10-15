/**
 * assets/js/assistant.js
 * 
 * Gestione Assistente AI v3.0
 * - Conversazione intelligente
 * - Upload e analisi immagini
 * - Apertura modal calendario precompilato
 */

import { API } from './api.js';

let assistantState = null;

/**
 * Apre modal assistente
 */
export function openAssistantModal() {
    // Resetta stato
    assistantState = null;
    
    // Crea modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'assistantModal';
    
    modal.innerHTML = `
        <div class="modal assistant-modal">
            <div class="modal-header">
                <h2>🤖 Assistente AI</h2>
                <button class="close-btn" onclick="window.closeAssistantModal()">✕</button>
            </div>
            
            <div class="modal-body">
                <div class="assistant-chat" id="assistantChat">
                    <div class="assistant-message ai">
                        <div class="message-bubble">
                            Ciao! Sono il tuo assistente AI. 
                            Dimmi di cosa hai bisogno oppure carica una foto di una bolletta/documento! 📸
                        </div>
                    </div>
                </div>
                
                <div class="assistant-input-container">
                    <input type="file" id="assistantImageInput" accept="image/*" style="display:none">
                    <button class="btn icon-only secondary" id="btnAssistantPhoto" title="Carica foto">
                        📷
                    </button>
                    <input 
                        type="text" 
                        id="assistantMessageInput" 
                        placeholder="Scrivi qui o carica una foto..."
                        style="flex: 1"
                    />
                    <button class="btn icon-only" id="btnAssistantSend" title="Invia">
                        ➤
                    </button>
                </div>
                
                <div id="assistantImagePreview" class="image-preview hidden">
                    <img id="assistantPreviewImg" src="" alt="Preview">
                    <button class="btn small del" id="btnRemoveImage">✕ Rimuovi</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Bind eventi
    document.getElementById('btnAssistantPhoto').addEventListener('click', () => {
        document.getElementById('assistantImageInput').click();
    });
    
    document.getElementById('assistantImageInput').addEventListener('change', handleImageSelect);
    document.getElementById('btnRemoveImage').addEventListener('click', removeImagePreview);
    
    document.getElementById('btnAssistantSend').addEventListener('click', sendAssistantMessage);
    
    document.getElementById('assistantMessageInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendAssistantMessage();
        }
    });
    
    // Focus input
    setTimeout(() => {
        document.getElementById('assistantMessageInput').focus();
    }, 100);
}

/**
 * Chiude modal assistente
 */
export function closeAssistantModal() {
    const modal = document.getElementById('assistantModal');
    if (modal) {
        modal.remove();
    }
    assistantState = null;
}

/**
 * Gestisce selezione immagine
 */
function handleImageSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validazione
    if (!file.type.startsWith('image/')) {
        alert('Seleziona un file immagine valido');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        alert('Immagine troppo grande. Max 10MB.');
        return;
    }
    
    // Mostra preview
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('assistantPreviewImg').src = e.target.result;
        document.getElementById('assistantImagePreview').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

/**
 * Rimuove preview immagine
 */
function removeImagePreview() {
    document.getElementById('assistantImageInput').value = '';
    document.getElementById('assistantImagePreview').classList.add('hidden');
}

/**
 * Invia messaggio all'assistente
 */
async function sendAssistantMessage() {
    const input = document.getElementById('assistantMessageInput');
    const fileInput = document.getElementById('assistantImageInput');
    const sendBtn = document.getElementById('btnAssistantSend');
    
    const message = input.value.trim();
    const hasImage = fileInput.files.length > 0;
    
    if (!message && !hasImage) {
        alert('Scrivi un messaggio o carica una foto');
        return;
    }
    
    // Aggiungi messaggio utente nella chat
    if (message) {
        addMessageToChat(message, 'user');
    }
    
    if (hasImage) {
        addMessageToChat('📷 [Foto caricata]', 'user');
    }
    
    // Disabilita input
    input.disabled = true;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="loader"></span>';
    
    // Pulisci input
    input.value = '';
    
    try {
        // Prepara FormData
        const formData = new FormData();
        formData.append('message', message || 'Ho caricato una foto');
        
        if (hasImage) {
            formData.append('image', fileInput.files[0]);
        }
        
        // Invia richiesta
        const response = await fetch('api/assistant.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Errore sconosciuto');
        }
        
        // Gestisci risposta in base allo status
        if (data.status === 'incomplete') {
            // Conversazione in corso
            addMessageToChat(data.message, 'ai');
            assistantState = data;
            
        } else if (data.status === 'ready_for_modal') {
            // Pronto per aprire modal calendario
            addMessageToChat(data.message, 'ai');
            
            // Attendi un attimo per far leggere il messaggio
            setTimeout(() => {
                openCalendarModalWithData(data.data);
                closeAssistantModal();
            }, 1500);
            
        } else if (data.status === 'complete') {
            // Conversazione terminata
            addMessageToChat(data.message, 'ai');
            assistantState = null;
            
        } else if (data.status === 'error') {
            throw new Error(data.message);
        }
        
        // Rimuovi preview immagine dopo invio
        removeImagePreview();
        
    } catch (error) {
        console.error('Assistant error:', error);
        addMessageToChat('❌ Errore: ' + error.message, 'ai');
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        sendBtn.innerHTML = '➤';
        input.focus();
    }
}

/**
 * Aggiungi messaggio alla chat
 */
function addMessageToChat(text, type) {
    const chatContainer = document.getElementById('assistantChat');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `assistant-message ${type}`;
    
    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'message-bubble';
    bubbleDiv.innerHTML = text.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    messageDiv.appendChild(bubbleDiv);
    chatContainer.appendChild(messageDiv);
    
    // Scroll to bottom
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

/**
 * Apre modal calendario con dati precompilati
 */
function openCalendarModalWithData(eventData) {
    // Importa funzione da calendar.js
    if (typeof window.openEventModal === 'function') {
        window.openEventModal(eventData);
    } else {
        console.error('openEventModal non trovata. Carico calendar.js...');
        // Fallback: crea evento direttamente
        alert('Apertura calendario... (implementare fallback)');
    }
}

// Esporta funzioni globalmente
window.openAssistantModal = openAssistantModal;
window.closeAssistantModal = closeAssistantModal;
