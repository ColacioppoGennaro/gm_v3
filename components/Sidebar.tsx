
import React from 'react';
import { User } from '../types';
import { HomeIcon, ChatBubbleLeftRightIcon, CalendarDaysIcon, ArrowUpCircleIcon, UserCircleIcon, SparklesIcon } from './icons/Icons';

interface SidebarProps {
  user: User;
  activeView: 'dashboard' | 'chat' | 'calendar';
  setActiveView: (view: 'dashboard' | 'chat' | 'calendar') => void;
  onUpgradeClick: () => void;
}

const Sidebar: React.FC<SidebarProps> = ({ user, activeView, setActiveView, onUpgradeClick }) => {
  // FIX: Replaced `JSX.Element` with `React.ReactElement` to resolve potential JSX namespace issues.
  const NavItem = ({ icon, label, view, isActive }: { icon: React.ReactElement, label: string, view: 'dashboard' | 'chat' | 'calendar', isActive: boolean }) => (
    <button
      onClick={() => setActiveView(view)}
      className={`flex items-center w-full px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 ${
        isActive ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white'
      }`}
    >
      {icon}
      <span className="ml-3">{label}</span>
    </button>
  );

  return (
    <aside className="w-64 bg-slate-800 flex flex-col p-4 border-r border-slate-700">
      <div className="flex items-center mb-8">
        <SparklesIcon className="h-8 w-8 text-indigo-400" />
        <h1 className="text-xl font-bold ml-2 text-white">gm_v3</h1>
      </div>
      
      <nav className="flex-1 space-y-2">
        <NavItem icon={<HomeIcon />} label="Dashboard" view="dashboard" isActive={activeView === 'dashboard'} />
        <NavItem icon={<ChatBubbleLeftRightIcon />} label="Chat AI" view="chat" isActive={activeView === 'chat'} />
        <NavItem icon={<CalendarDaysIcon />} label="Calendario" view="calendar" isActive={activeView === 'calendar'} />
      </nav>

      <div className="mt-auto">
        {user.tier === 'Free' && (
          <div className="p-4 mb-4 bg-indigo-900/50 rounded-lg text-center">
            <p className="text-sm text-indigo-200 mb-3">Passa a Pro per più funzioni e limiti aumentati.</p>
            <button 
              onClick={onUpgradeClick}
              className="flex items-center justify-center w-full px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-500 transition-colors"
            >
              <ArrowUpCircleIcon />
              <span className="ml-2">Upgrade a Pro</span>
            </button>
          </div>
        )}
        <div className="flex items-center p-3 bg-slate-900 rounded-lg">
          <UserCircleIcon className="h-10 w-10 text-slate-400"/>
          <div className="ml-3">
            <p className="font-semibold text-white">{user.name}</p>
            <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${
              user.tier === 'Pro' ? 'bg-yellow-400 text-yellow-900' : 'bg-slate-600 text-slate-200'
            }`}>
              {user.tier}
            </span>
          </div>
        </div>
      </div>
    </aside>
  );
};

export default Sidebar;