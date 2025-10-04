
export enum UserTier {
  Free = 'Free',
  Pro = 'Pro',
}

export interface User {
  id: string;
  name: string;
  tier: UserTier;
  userLabel: string; // Etichetta "master" privata per docAnalyzer.ai
}

export interface Document {
  id: string;
  name: string;
  size: number; // in bytes
  type: string;
  uploadDate: Date;
  category?: string; // Solo per utenti Pro
}

export interface ChatMessage {
  id: string;
  sender: 'user' | 'ai';
  text: string;
  source?: 'documents' | 'generic' | 'system';
  timestamp: Date;
}

export interface CalendarEvent {
  id: string;
  title: string;
  description: string;
  date: Date;
}

export interface AISuggestedEvent {
    title: string;
    date: string; // ISO string format
    description: string;
}
