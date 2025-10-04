import { Document } from '../types';

// NOTA: Questo file è ora un client HTTP.
// Presuppone che tu abbia un backend in esecuzione che risponda a queste rotte.
// Esempio di rotte:
// GET /api/documents -> Restituisce l'elenco dei documenti
// POST /api/documents -> Carica un nuovo documento
// DELETE /api/documents/{id} -> Elimina un documento

const API_BASE_URL = '/api'; // Prefisso per tutte le chiamate API

/**
 * Effettua il fetch dei documenti dell'utente dal backend reale.
 */
export const getDocuments = async (): Promise<Document[]> => {
    console.log(`[API Client] GET ${API_BASE_URL}/documents`);
    const response = await fetch(`${API_BASE_URL}/documents`);
    
    if (!response.ok) {
        throw new Error('Failed to fetch documents from the server.');
    }
    
    const data = await response.json();
    // Il backend dovrebbe restituire le date come stringhe ISO 8601, le convertiamo in oggetti Date
    return data.map((doc: any) => ({
        ...doc,
        uploadDate: new Date(doc.uploadDate),
    }));
};

/**
 * Carica un file sul backend reale. Il backend gestirà il salvataggio
 * e la comunicazione con docanalyzer.ai.
 */
export const uploadDocument = async (
    file: File, 
    category?: string
): Promise<Document> => {
    console.log(`[API Client] POST ${API_BASE_URL}/documents`);

    const formData = new FormData();
    formData.append('file', file);
    if (category) {
        formData.append('category', category);
    }
    // Il backend potrà estrarre userId e userLabel dalla sessione dell'utente
    
    const response = await fetch(`${API_BASE_URL}/documents`, {
        method: 'POST',
        body: formData,
        // Non impostare 'Content-Type', il browser lo farà automaticamente per FormData
    });

    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Failed to upload document: ${errorText}`);
    }

    const newDocData = await response.json();
    return {
        ...newDocData,
        uploadDate: new Date(newDocData.uploadDate),
    };
};

/**
 * Invia una richiesta di eliminazione di un documento al backend.
 */
export const deleteDocument = async (docId: string): Promise<boolean> => {
    console.log(`[API Client] DELETE ${API_BASE_URL}/documents/${docId}`);
    
    const response = await fetch(`${API_BASE_URL}/documents/${docId}`, {
        method: 'DELETE',
    });

    if (!response.ok) {
        throw new Error('Failed to delete document on the server.');
    }

    // Ci aspettiamo una risposta con status 200 o 204 (No Content) per la riuscita
    return response.ok;
};