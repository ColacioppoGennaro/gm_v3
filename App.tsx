import React, { useState, useCallback, useMemo, useEffect } from 'react';
import { User, Document, ChatMessage, CalendarEvent, UserTier, AISuggestedEvent } from './types';
import { mockDocAnalyzer, queryGenericAI, analyzeForCalendarEvents } from './services/aiService';
import * as apiService from './services/apiService'; // Import the new service
import Sidebar from './components/Sidebar';
import Dashboard from './components/Dashboard';
import Chat from './components/Chat';
import CalendarView from './components/CalendarView';
import UpgradeModal from './components/UpgradeModal';
import EventSuggestionToast from './components/EventSuggestionToast';

// Mock Data
const initialUser: User = {
  id: 'user-001',
  name: 'Mario Rossi',
  tier: UserTier.Free,
  userLabel: 'mario_rossi_xyz123',
};

const App: React.FC = () => {
  const [user, setUser] = useState<User>(initialUser);
  const [documents, setDocuments] = useState<Document[]>([]); // Start with empty array
  const [isLoadingDocs, setIsLoadingDocs] = useState<boolean>(true); // Add loading state
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      id: 'msg-0',
      sender: 'ai',
      text: "Ciao! Sono il tuo assistente personale. Chiedimi qualcosa riguardo i tuoi documenti o un'informazione generica.",
      source: 'system',
      timestamp: new Date(),
    },
  ]);
  const [calendarEvents, setCalendarEvents] = useState<CalendarEvent[]>([
      { id: 'evt-1', title: 'Appuntamento dal dentista', description: 'Controllo annuale', date: new Date(new Date().setDate(new Date().getDate() + 5)) },
  ]);

  const [activeView, setActiveView] = useState<'dashboard' | 'chat' | 'calendar'>('dashboard');
  const [isUpgradeModalOpen, setUpgradeModalOpen] = useState(false);
  const [suggestedEvent, setSuggestedEvent] = useState<AISuggestedEvent | null>(null);
  
  // Fetch initial documents from the REAL API
  useEffect(() => {
    const fetchDocs = async () => {
      try {
        setIsLoadingDocs(true);
        const fetchedDocs = await apiService.getDocuments();
        setDocuments(fetchedDocs);
      } catch (error) {
        console.error("Failed to fetch documents:", error);
        // Potresti voler mostrare un messaggio di errore all'utente
      } finally {
        setIsLoadingDocs(false);
      }
    };
    fetchDocs();
  }, []);

  const userLimits = useMemo(() => {
    if (user.tier === UserTier.Pro) {
      return { maxDocs: 200, maxFileSize: 150 * 1024 * 1024, maxQueries: 200 };
    }
    return { maxDocs: 5, maxFileSize: 10 * 1024 * 1024, maxQueries: 20 };
  }, [user.tier]);

  const handleSendMessage = useCallback(async (text: string, category?: string) => {
    const userMessage: ChatMessage = {
      id: `msg-${Date.now()}`,
      sender: 'user',
      text,
      timestamp: new Date(),
    };
    setMessages(prev => [...prev, userMessage]);

    // Step 1: Query docAnalyzer.ai (this part remains mocked as it's a separate AI call)
    const docAnswer = await mockDocAnalyzer(text, user.userLabel, category);

    let aiResponseText: string;
    let responseSource: 'documents' | 'generic';

    if (docAnswer) {
      // Answer found in documents
      aiResponseText = docAnswer.answer;
      responseSource = 'documents';
    } else {
      // Step 2: Fallback to Generic AI
      aiResponseText = await queryGenericAI(text);
      responseSource = 'generic';
    }

    const aiMessage: ChatMessage = {
      id: `msg-${Date.now() + 1}`,
      sender: 'ai',
      text: aiResponseText,
      source: responseSource,
      timestamp: new Date(),
    };
    setMessages(prev => [...prev, aiMessage]);

    // Step 3 (Pro Only): Analyze for calendar events
    if (user.tier === UserTier.Pro) {
      const eventSuggestion = await analyzeForCalendarEvents(aiResponseText);
      if (eventSuggestion) {
        setSuggestedEvent(eventSuggestion);
      }
    }
  }, [user]);
  
  const handleFileUpload = async (file: File, category?: string) => {
    if (documents.length >= userLimits.maxDocs) {
        alert("Limite di archiviazione raggiunto.");
        return;
    }
    if (file.size > userLimits.maxFileSize) {
        alert(`Il file supera il limite di dimensione di ${userLimits.maxFileSize / 1024 / 1024} MB.`);
        return;
    }
    
    try {
        // Use the apiService to upload the document to the REAL backend
        const newDoc = await apiService.uploadDocument(file, category);
        setDocuments(prev => [...prev, newDoc].sort((a, b) => b.uploadDate.getTime() - a.uploadDate.getTime()));
    } catch (error) {
        console.error("Upload failed:", error);
        alert(`Si è verificato un errore durante il caricamento del file: ${error.message}`);
    }
  };

  const handleDeleteDocument = async (docId: string) => {
      try {
          await apiService.deleteDocument(docId);
          setDocuments(prev => prev.filter(doc => doc.id !== docId));
      } catch (error) {
          console.error("Deletion failed:", error);
          alert(`Si è verificato un errore durante l'eliminazione del file: ${error.message}`);
      }
  };

  const handleAddEvent = (event: Omit<CalendarEvent, 'id'>) => {
      const newEvent: CalendarEvent = { ...event, id: `evt-${Date.now()}` };
      setCalendarEvents(prev => [...prev, newEvent]);
      setSuggestedEvent(null); // Clear suggestion after adding
  };
  
  const handleUpgrade = (promoCode: string) => {
    // In a real app, you would validate this code against a backend.
    if (promoCode.toUpperCase() === 'PRO_TRIAL_2024') {
        setUser(prev => ({ ...prev, tier: UserTier.Pro }));
        setUpgradeModalOpen(false);
        alert('Congratulazioni! Hai effettuato l-upgrade a Pro.');
    } else {
        alert('Codice promozionale non valido.');
    }
  };

  const renderContent = () => {
    switch (activeView) {
      case 'dashboard':
        return <Dashboard user={user} documents={documents} limits={userLimits} onFileUpload={handleFileUpload} onDeleteDocument={handleDeleteDocument} isLoading={isLoadingDocs} />; // Pass isLoading
      case 'chat':
        return <Chat user={user} messages={messages} onSendMessage={handleSendMessage} documents={documents} />;
      case 'calendar':
        return <CalendarView events={calendarEvents} onAddEvent={handleAddEvent} />;
      default:
        return <Dashboard user={user} documents={documents} limits={userLimits} onFileUpload={handleFileUpload} onDeleteDocument={handleDeleteDocument} isLoading={isLoadingDocs} />; // Pass isLoading
    }
  };

  return (
    <div className="flex h-screen bg-slate-900 font-sans">
      <Sidebar user={user} activeView={activeView} setActiveView={setActiveView} onUpgradeClick={() => setUpgradeModalOpen(true)} />
      <main className="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
        {renderContent()}
      </main>
      {isUpgradeModalOpen && <UpgradeModal onClose={() => setUpgradeModalOpen(false)} onUpgrade={handleUpgrade} />}
      {suggestedEvent && (
        <EventSuggestionToast 
          suggestion={suggestedEvent} 
          onConfirm={() => {
              handleAddEvent({
                  title: suggestedEvent.title,
                  date: new Date(suggestedEvent.date),
                  description: suggestedEvent.description
              });
              setSuggestedEvent(null);
          }}
          onDismiss={() => setSuggestedEvent(null)}
        />
      )}
    </div>
  );
};

export default App;