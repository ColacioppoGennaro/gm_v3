-- Migrazione per integrazione Gemini
-- Aggiunge supporto per analisi AI e embedding

ALTER TABLE documents 
ADD COLUMN gemini_analysis TEXT DEFAULT NULL COMMENT 'Analisi completa di Gemini del documento',
ADD COLUMN gemini_summary VARCHAR(500) DEFAULT NULL COMMENT 'Riassunto breve per ricerche rapide',
ADD COLUMN gemini_embedding JSON DEFAULT NULL COMMENT 'Vettore embedding per ricerca semantica',
ADD COLUMN ai_extracted_data JSON DEFAULT NULL COMMENT 'Dati strutturati estratti (date, importi, scadenze)',
ADD COLUMN analysis_status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
ADD COLUMN analyzed_at TIMESTAMP NULL DEFAULT NULL;

-- Indice per ricerche rapide
ALTER TABLE documents ADD INDEX idx_analysis_status (analysis_status);