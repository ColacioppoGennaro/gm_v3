
import React, { useState, useRef, useEffect } from 'react';
import { User, ChatMessage, UserTier, Document } from '../types';
import { PaperAirplaneIcon, DocumentTextIcon, CpuChipIcon } from './icons/Icons';

interface ChatProps {
  user: User;
  messages: ChatMessage[];
  onSendMessage: (text: string, category?: string) => void;
  documents: Document[];
}

const Chat: React.FC<ChatProps> = ({ user, messages, onSendMessage, documents }) => {
  const [input, setInput] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const documentCategories = [...new Set(documents.map(doc => doc.category).filter(Boolean))] as string[];

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(scrollToBottom, [messages]);

  const handleSend = async () => {
    if (input.trim() === '' || isLoading) return;
    setIsLoading(true);
    const categoryToSend = user.tier === UserTier.Pro && selectedCategory !== 'all' ? selectedCategory : undefined;
    await onSendMessage(input, categoryToSend);
    setInput('');
    setIsLoading(false);
  };

  const MessageBubble: React.FC<{ message: ChatMessage }> = ({ message }) => {
    const isUser = message.sender === 'user';
    const sourceIcon = message.source === 'documents' 
        ? <DocumentTextIcon className="h-4 w-4 mr-1 text-indigo-300" /> 
        : message.source === 'generic' ? <CpuChipIcon className="h-4 w-4 mr-1 text-green-300" />
        : null;
    const sourceText = message.source === 'documents' ? "Dai tuoi documenti" 
        : message.source === 'generic' ? "Da AI generica" 
        : null;

    return (
      <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
        <div className={`max-w-xl lg:max-w-2xl px-4 py-3 rounded-2xl ${isUser ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-slate-700 text-slate-200 rounded-bl-none'}`}>
          <p className="whitespace-pre-wrap">{message.text}</p>
          {message.source && (
              <div className="flex items-center mt-2 text-xs text-slate-400">
                {sourceIcon}
                <span>{sourceText}</span>
              </div>
          )}
        </div>
      </div>
    );
  };

  return (
    <div className="flex flex-col h-full max-h-full">
      <h2 className="text-3xl font-bold mb-4">Chat AI</h2>
      <div className="flex-1 overflow-y-auto pr-2 space-y-4 pb-4">
        {messages.map((msg) => (
          <MessageBubble key={msg.id} message={msg} />
        ))}
        {isLoading && (
            <div className="flex justify-start">
                <div className="bg-slate-700 text-slate-200 px-4 py-3 rounded-2xl rounded-bl-none">
                    <div className="flex items-center">
                        <div className="w-2 h-2 bg-indigo-400 rounded-full animate-pulse mr-1.5"></div>
                        <div className="w-2 h-2 bg-indigo-400 rounded-full animate-pulse delay-75 mr-1.5"></div>
                        <div className="w-2 h-2 bg-indigo-400 rounded-full animate-pulse delay-150"></div>
                    </div>
                </div>
            </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      <div className="mt-auto pt-4 border-t border-slate-700">
        {user.tier === UserTier.Pro && documentCategories.length > 0 && (
          <div className="mb-2">
            <label htmlFor="category-select" className="text-sm text-slate-400 mr-2">Interroga categoria:</label>
            <select
              id="category-select"
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="bg-slate-700 border-slate-600 rounded-md py-1 px-2 text-sm"
            >
              <option value="all">Tutti i documenti</option>
              {documentCategories.map(cat => <option key={cat} value={cat}>{cat}</option>)}
            </select>
          </div>
        )}
        <div className="relative">
          <textarea
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
              }
            }}
            placeholder="Scrivi la tua domanda..."
            rows={1}
            className="w-full bg-slate-800 border border-slate-600 rounded-lg py-3 pl-4 pr-12 resize-none focus:ring-indigo-500 focus:border-indigo-500"
            disabled={isLoading}
          />
          <button
            onClick={handleSend}
            disabled={input.trim() === '' || isLoading}
            className="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-full text-white bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-500 disabled:cursor-not-allowed transition-colors"
          >
            <PaperAirplaneIcon />
          </button>
        </div>
      </div>
    </div>
  );
};

export default Chat;
