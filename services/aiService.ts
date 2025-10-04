
import { GoogleGenAI, Type } from "@google/genai";
import { AISuggestedEvent } from '../types';

// This is a MOCK implementation for the fictional docanalyzer.ai API
// In a real application, this would be an HTTP client call.
export const mockDocAnalyzer = async (
  prompt: string,
  userLabel: string,
  categoryLabel?: string
): Promise<{ answer: string } | null> => {
  console.log(
    `Querying docAnalyzer.ai for user "${userLabel}" with category "${categoryLabel || 'all'}"...`
  );
  await new Promise(resolve => setTimeout(resolve, 1500)); // Simulate network delay

  const lowerCasePrompt = prompt.toLowerCase();

  if (lowerCasePrompt.includes("imu")) {
    return {
      answer: "Secondo i documenti analizzati, la scadenza per il pagamento dell'IMU è il 16 Giugno. L'importo dovuto è di €345,50. Documenti di riferimento: F24_2023.pdf, Visura_Catastale_Immobile.pdf.",
    };
  }
  
  if (lowerCasePrompt.includes("fattura") && lowerCasePrompt.includes("telecom")) {
      return {
        answer: "L'ultima fattura Telecom presente nei tuoi documenti è di €45,90 e scade il 28 del mese corrente. Riferimento: fattura_telecom_maggio.pdf"
      }
  }

  // Simulate no answer found
  return null;
};

// Gemini API setup
const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });

export const queryGenericAI = async (prompt: string, context?: string): Promise<string> => {
  try {
    const fullPrompt = context
      ? `Contesto dai documenti dell'utente: "${context}".\n\nDomanda dell'utente: "${prompt}".\n\nRispondi alla domanda dell'utente basandoti sul contesto fornito.`
      : prompt;

    const response = await ai.models.generateContent({
      model: 'gemini-2.5-flash',
      contents: fullPrompt,
      config: {
        systemInstruction: "Sei un assistente personale italiano. Sii conciso, amichevole e vai dritto al punto. Se non sai la risposta, ammettilo chiaramente."
      }
    });
    
    return response.text;
  } catch (error) {
    console.error("Error querying Generic AI:", error);
    return "Mi dispiace, si è verificato un errore durante la comunicazione con l'intelligenza artificiale. Riprova più tardi.";
  }
};

export const analyzeForCalendarEvents = async (text: string): Promise<AISuggestedEvent | null> => {
  try {
    const response = await ai.models.generateContent({
      model: "gemini-2.5-flash",
      contents: `Analizza il seguente testo e, se trovi una data o una scadenza specifica, estrai i dettagli per creare un evento di calendario. Testo: "${text}"`,
      config: {
        responseMimeType: "application/json",
        responseSchema: {
          type: Type.OBJECT,
          properties: {
            title: {
              type: Type.STRING,
              description: "Un titolo breve e descrittivo per l'evento (es. 'Scadenza pagamento IMU').",
            },
            date: {
              type: Type.STRING,
              description: "La data esatta dell'evento in formato ISO 8601 (YYYY-MM-DD).",
            },
            description: {
              type: Type.STRING,
              description: "Una breve descrizione dell'evento, che includa dettagli importanti come importi o riferimenti.",
            },
          },
        },
      },
    });
    
    // The response text is a JSON string, we need to parse it.
    const jsonString = response.text.trim();
    if (jsonString) {
        // Simple check to see if it returned an empty object
        const parsed = JSON.parse(jsonString);
        if (parsed.title && parsed.date) {
             return parsed as AISuggestedEvent;
        }
    }
    return null;
  } catch (error) {
    console.error("Error analyzing text for calendar events:", error);
    return null;
  }
};
