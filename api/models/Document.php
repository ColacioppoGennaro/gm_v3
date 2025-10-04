<?php
/**
 * GM_V3 - Document Model
 * 
 * Gestisce operazioni database per i documenti
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

class Document {
    
    /**
     * Ottieni tutti i documenti di un utente
     */
    public static function getAllByUser($userId) {
        $sql = "SELECT 
                    d.id,
                    d.name,
                    d.original_name,
                    d.size,
                    d.type,
                    d.upload_date,
                    d.category_id,
                    c.name as category
                FROM documents d
                LEFT JOIN categories c ON d.category_id = c.id
                WHERE d.user_id = ?
                ORDER BY d.upload_date DESC";
        
        $result = executeQuery($sql, "s", [$userId]);
        
        if (!$result) {
            return [];
        }
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'size' => (int)$row['size'],
                'type' => $row['type'],
                'uploadDate' => $row['upload_date'],
                'category' => $row['category']
            ];
        }
        
        return $documents;
    }
    
    /**
     * Ottieni documenti filtrati per categoria
     */
    public static function getByCategory($userId, $categoryId) {
        $sql = "SELECT 
                    d.id,
                    d.name,
                    d.original_name,
                    d.size,
                    d.type,
                    d.upload_date
                FROM documents d
                WHERE d.user_id = ? AND d.category_id = ?
                ORDER BY d.upload_date DESC";
        
        $result = executeQuery($sql, "si", [$userId, $categoryId]);
        
        if (!$result) {
            return [];
        }
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'size' => (int)$row['size'],
                'type' => $row['type'],
                'uploadDate' => $row['upload_date']
            ];
        }
        
        return $documents;
    }
    
    /**
     * Ottieni singolo documento
     */
    public static function getById($documentId, $userId) {
        $sql = "SELECT * FROM documents WHERE id = ? AND user_id = ?";
        $result = executeQuery($sql, "ss", [$documentId, $userId]);
        
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Conta documenti utente
     */
    public static function countByUser($userId) {
        $sql = "SELECT COUNT(*) as total FROM documents WHERE user_id = ?";
        $result = executeQuery($sql, "s", [$userId]);
        
        if (!$result) {
            return 0;
        }
        
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    
    /**
     * Inserisci nuovo documento
     */
    public static function create($data) {
        $sql = "INSERT INTO documents 
                (id, user_id, name, original_name, file_path, size, type, category_id, docanalyzer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $categoryId = $data['category_id'] ?? null;
        $docanalyzerId = $data['docanalyzer_id'] ?? null;
        
        $result = executeQuery($sql, "sssssisis", [
            $data['id'],
            $data['user_id'],
            $data['name'],
            $data['original_name'],
            $data['file_path'],
            $data['size'],
            $data['type'],
            $categoryId,
            $docanalyzerId
        ]);
        
        return $result !== false;
    }
    
    /**
     * Elimina documento
     */
    public static function delete($documentId, $userId) {
        // Prima recupera il path del file
        $doc = self::getById($documentId, $userId);
        
        if (!$doc) {
            return false;
        }
        
        // Elimina dal database
        $sql = "DELETE FROM documents WHERE id = ? AND user_id = ?";
        $result = executeQuery($sql, "ss", [$documentId, $userId]);
        
        if ($result === false) {
            return false;
        }
        
        // Elimina file fisico
        if (file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        
        return true;
    }
    
    /**
     * Aggiorna docanalyzer_id dopo vettorializzazione
     */
    public static function updateDocAnalyzerId($documentId, $docanalyzerId) {
        $sql = "UPDATE documents SET docanalyzer_id = ? WHERE id = ?";
        return executeQuery($sql, "ss", [$docanalyzerId, $documentId]) !== false;
    }
    
    /**
     * Verifica se utente ha raggiunto limite documenti
     */
    public static function hasReachedLimit($userId, $userTier) {
        $count = self::countByUser($userId);
        $limits = getLimitsForTier($userTier);
        
        return $count >= $limits['maxDocs'];
    }
    
    /**
     * Genera ID univoco per documento
     */
    public static function generateId() {
        return 'doc-' . uniqid() . '-' . bin2hex(random_bytes(8));
    }
}
