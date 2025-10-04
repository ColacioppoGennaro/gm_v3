import React, { useState } from 'react';
import { User, Document, UserTier } from '../types';
import { DocumentPlusIcon, TrashIcon, DocumentIcon } from './icons/Icons';

interface DashboardProps {
  user: User;
  documents: Document[];
  limits: { maxDocs: number; maxFileSize: number; maxQueries: number };
  onFileUpload: (file: File, category?: string) => Promise<void>; // Make it return a promise
  onDeleteDocument: (docId: string) => void;
  isLoading: boolean; // New prop
}

const StatCard: React.FC<{ title: string; value: string; total: string; }> = ({ title, value, total }) => (
    <div className="bg-slate-800 p-4 rounded-lg">
        <h3 className="text-sm font-medium text-slate-400">{title}</h3>
        <p className="text-2xl font-bold text-white mt-1">{value} / <span className="text-lg text-slate-300">{total}</span></p>
    </div>
);

const AdBanner: React.FC = () => (
    <div className="bg-yellow-500/10 border border-yellow-500 text-yellow-300 p-4 rounded-lg text-center mt-6">
        <p className="font-semibold">Stai usando il piano Free.</p>
        <p className="text-sm">Pubblicità di esempio. Passa a Pro per rimuoverla!</p>
    </div>
);

const DocumentList: React.FC<{ documents: Document[]; onDelete: (id: string) => void; isLoading: boolean }> = ({ documents, onDelete, isLoading }) => (
    <div className="mt-6 bg-slate-800/50 rounded-lg p-4">
        <h3 className="text-lg font-semibold mb-4">I tuoi documenti</h3>
        {isLoading ? (
             <p className="text-slate-400 text-center py-4">Caricamento documenti in corso...</p>
        ) : documents.length === 0 ? (
            <p className="text-slate-400 text-center py-4">Nessun documento caricato.</p>
        ) : (
            <ul className="space-y-3">
                {documents.map(doc => (
                    <li key={doc.id} className="flex items-center justify-between bg-slate-700 p-3 rounded-md hover:bg-slate-600 transition-colors">
                        <div className="flex items-center">
                            <DocumentIcon className="h-6 w-6 text-indigo-400" />
                            <div className="ml-3">
                                <p className="font-medium text-white">{doc.name}</p>
                                <p className="text-sm text-slate-400">
                                    {(doc.size / 1024).toFixed(2)} KB - Caricato il: {doc.uploadDate.toLocaleDateString()}
                                    {doc.category && <span className="ml-2 px-2 py-0.5 text-xs bg-indigo-500 rounded-full">{doc.category}</span>}
                                </p>
                            </div>
                        </div>
                        <button onClick={() => onDelete(doc.id)} className="text-slate-400 hover:text-red-500 p-2 rounded-full transition-colors">
                            <TrashIcon />
                        </button>
                    </li>
                ))}
            </ul>
        )}
    </div>
);

const Dashboard: React.FC<DashboardProps> = ({ user, documents, limits, onFileUpload, onDeleteDocument, isLoading }) => {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [category, setCategory] = useState('');
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false); // New state for upload progress

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setSelectedFile(e.target.files[0]);
        }
    };
    
    const handleDragEvents = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setIsDragging(true);
        } else if (e.type === 'dragleave') {
            setIsDragging(false);
        }
    };
    
    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            setSelectedFile(e.dataTransfer.files[0]);
        }
    };

    const handleUploadClick = async () => {
        if (selectedFile) {
            setIsUploading(true);
            await onFileUpload(selectedFile, category);
            setSelectedFile(null);
            setCategory('');
            setIsUploading(false);
        }
    };

    return (
        <div>
            <h2 className="text-3xl font-bold mb-6">Dashboard</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <StatCard title="Documenti Archiviati" value={documents.length.toString()} total={limits.maxDocs.toString()} />
                <StatCard title="Domande AI oggi" value="0" total={limits.maxQueries.toString()} />
                <StatCard title="Dimensione max. File" value={`${limits.maxFileSize / 1024 / 1024} MB`} total="" />
            </div>

            <div className="mt-8 bg-slate-800 p-6 rounded-lg">
                <h3 className="text-xl font-semibold mb-4">Carica un nuovo documento</h3>
                <div 
                    className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${isDragging ? 'border-indigo-500 bg-indigo-900/50' : 'border-slate-600'}`}
                    onDragEnter={handleDragEvents}
                    onDragOver={handleDragEvents}
                    onDragLeave={handleDragEvents}
                    onDrop={handleDrop}
                >
                    <DocumentPlusIcon className="mx-auto h-12 w-12 text-slate-500"/>
                    <p className="mt-2 text-slate-400">Trascina un file qui o</p>
                    <label htmlFor="file-upload" className="cursor-pointer font-medium text-indigo-400 hover:text-indigo-300">
                        selezionalo dal tuo computer
                        <input id="file-upload" name="file-upload" type="file" className="sr-only" onChange={handleFileChange} disabled={isUploading} />
                    </label>
                    {selectedFile && <p className="mt-2 text-white font-medium">Selezionato: {selectedFile.name}</p>}
                </div>
                
                <div className="flex flex-col sm:flex-row items-center mt-4 gap-4">
                    {user.tier === UserTier.Pro && (
                        <input
                            type="text"
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                            placeholder="Assegna una categoria (es. Fatture)"
                            className="w-full sm:w-auto flex-grow bg-slate-700 border border-slate-600 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                            disabled={isUploading}
                        />
                    )}
                    <button
                        onClick={handleUploadClick}
                        disabled={!selectedFile || isUploading}
                        className="w-full sm:w-auto bg-indigo-600 text-white font-semibold px-6 py-2 rounded-md hover:bg-indigo-500 disabled:bg-slate-500 disabled:cursor-not-allowed transition-colors"
                    >
                        {isUploading ? 'Caricamento...' : 'Carica File'}
                    </button>
                </div>
            </div>

            {user.tier === UserTier.Free && <AdBanner />}
            <DocumentList documents={documents} onDelete={onDeleteDocument} isLoading={isLoading} />
        </div>
    );
};

export default Dashboard;
