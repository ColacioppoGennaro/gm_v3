<?php
/**
 * Applica migrazione 002_gemini_integration.sql
 */
require_once '_core/helpers.php';

try {
    $db = db();
    
    // Verifica se la migrazione è già stata applicata
    $result = $db->query("SHOW COLUMNS FROM documents LIKE 'gemini_analysis'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Migrazione già applicata!\n";
        exit;
    }
    
    // Applica la migrazione campo per campo
    $migrations = [
        "ALTER TABLE documents ADD COLUMN gemini_analysis TEXT DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN gemini_summary VARCHAR(500) DEFAULT NULL", 
        "ALTER TABLE documents ADD COLUMN gemini_embedding JSON DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN ai_extracted_data JSON DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN analysis_status ENUM('pending','processing','completed','failed') DEFAULT 'pending'",
        "ALTER TABLE documents ADD COLUMN analyzed_at TIMESTAMP NULL DEFAULT NULL",
        "ALTER TABLE documents ADD INDEX idx_analysis_status (analysis_status)"
    ];
    
    foreach ($migrations as $sql) {
        echo "Executing: " . substr($sql, 0, 60) . "...\n";
        $result = $db->query($sql);
        if (!$result) {
            // Ignora errori se la colonna esiste già
            if (strpos($db->error, 'Duplicate column') === false) {
                throw new Exception("Query failed: " . $db->error);
            } else {
                echo "  (Campo già esistente, ignorato)\n";
            }
        } else {
            echo "  ✅ OK\n";
        }
    }
    
    echo "✅ Migrazione completata!\n";
    
} catch (Exception $e) {
    echo "❌ Errore migrazione: " . $e->getMessage() . "\n";
}
?>