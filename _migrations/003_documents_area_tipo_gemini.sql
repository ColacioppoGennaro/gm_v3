-- Migration 003: Aggiungi area_id, tipo_id, gemini_embedding, summary a documents
-- Esegui con: php migrate.php

-- 1. Aggiungi colonne per Area e Tipo
ALTER TABLE documents 
  ADD COLUMN area_id INT NULL COMMENT 'FK a settori - per tutti i documenti',
  ADD COLUMN tipo_id INT NULL COMMENT 'FK a tipi_attivita - solo per documenti leggeri (non DocAnalyzer)';

-- 2. Aggiungi colonne per Gemini embedding (documenti leggeri)
ALTER TABLE documents 
  ADD COLUMN gemini_embedding MEDIUMTEXT NULL COMMENT 'JSON array di float - vector embedding per similarity search',
  ADD COLUMN summary TEXT NULL COMMENT 'Riassunto generato da Gemini per documenti leggeri',
  ADD COLUMN ocr_text MEDIUMTEXT NULL COMMENT 'Testo estratto con OCR/Vision (opzionale)';

-- 3. Aggiungi foreign keys
ALTER TABLE documents 
  ADD CONSTRAINT fk_documents_area 
    FOREIGN KEY (area_id) REFERENCES settori(id) 
    ON DELETE SET NULL,
  ADD CONSTRAINT fk_documents_tipo 
    FOREIGN KEY (tipo_id) REFERENCES tipi_attivita(id) 
    ON DELETE SET NULL;

-- 4. Aggiungi indici per performance
CREATE INDEX idx_documents_area ON documents(area_id);
CREATE INDEX idx_documents_tipo ON documents(tipo_id);
CREATE INDEX idx_documents_area_tipo ON documents(area_id, tipo_id);

-- 5. Commento sulla logica:
-- - Documenti PESANTI (>5MB): area_id NOT NULL, tipo_id NULL, docanalyzer_doc_id NOT NULL
-- - Documenti LEGGERI (<5MB): area_id NOT NULL, tipo_id NOT NULL, gemini_embedding NOT NULL
