<?php
/**
 * FILE: _core/PersoneAnimaliManager.php
 * Gestione CRUD persone e animali (per settore Persone)
 */

require_once __DIR__ . '/helpers.php';

class PersoneAnimaliManager {
    private $db;
    private $userId;
    
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->db = db();
    }
    
    /**
     * Lista persone/animali (opzionale: filtro per tipo)
     */
    public function list($tipo = null) {
        if ($tipo && !in_array($tipo, ['persona', 'animale'])) {
            throw new Exception("Tipo non valido");
        }
        
        if ($tipo) {
            $stmt = $this->db->prepare("
                SELECT id, tipo, nome, note, created_at, updated_at
                FROM persone_animali
                WHERE user_id = ? AND tipo = ?
                ORDER BY nome ASC
            ");
            $stmt->bind_param("is", $this->userId, $tipo);
        } else {
            $stmt = $this->db->prepare("
                SELECT id, tipo, nome, note, created_at, updated_at
                FROM persone_animali
                WHERE user_id = ?
                ORDER BY tipo ASC, nome ASC
            ");
            $stmt->bind_param("i", $this->userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Crea nuova persona/animale
     */
    public function create($nome, $tipo = 'persona', $note = null) {
        // Validazione
        $nome = trim($nome);
        if (empty($nome)) {
            throw new Exception("Nome obbligatorio");
        }
        
        if (!in_array($tipo, ['persona', 'animale'])) {
            throw new Exception("Tipo non valido (persona o animale)");
        }
        
        // Check duplicati
        $stmt = $this->db->prepare("SELECT id FROM persone_animali WHERE user_id = ? AND nome = ?");
        $stmt->bind_param("is", $this->userId, $nome);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Nome già esistente");
        }
        
        // Insert
        $stmt = $this->db->prepare("
            INSERT INTO persone_animali (user_id, tipo, nome, note) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $this->userId, $tipo, $nome, $note);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore creazione: " . $stmt->error);
        }
        
        return [
            'id' => $this->db->insert_id,
            'tipo' => $tipo,
            'nome' => $nome,
            'note' => $note
        ];
    }
    
    /**
     * Aggiorna persona/animale
     */
    public function update($id, $data) {
        if (!$this->userOwns($id)) {
            throw new Exception("Accesso negato");
        }
        
        $updates = [];
        $types = "";
        $values = [];
        
        if (isset($data['nome'])) {
            $nome = trim($data['nome']);
            if (empty($nome)) throw new Exception("Nome non può essere vuoto");
            
            // Check duplicati
            $stmt = $this->db->prepare("SELECT id FROM persone_animali WHERE user_id = ? AND nome = ? AND id != ?");
            $stmt->bind_param("isi", $this->userId, $nome, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Nome già esistente");
            }
            
            $updates[] = "nome = ?";
            $types .= "s";
            $values[] = $nome;
        }
        
        if (isset($data['tipo'])) {
            if (!in_array($data['tipo'], ['persona', 'animale'])) {
                throw new Exception("Tipo non valido");
            }
            $updates[] = "tipo = ?";
            $types .= "s";
            $values[] = $data['tipo'];
        }
        
        if (isset($data['note'])) {
            $updates[] = "note = ?";
            $types .= "s";
            $values[] = $data['note'];
        }
        
        if (empty($updates)) {
            throw new Exception("Nessun campo da aggiornare");
        }
        
        $sql = "UPDATE persone_animali SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
        $types .= "ii";
        $values[] = $id;
        $values[] = $this->userId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore aggiornamento: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    /**
     * Elimina persona/animale
     */
    public function delete($id) {
        if (!$this->userOwns($id)) {
            throw new Exception("Accesso negato");
        }
        
        $stmt = $this->db->prepare("DELETE FROM persone_animali WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $this->userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore eliminazione: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    /**
     * Helper: verifica ownership
     */
    private function userOwns($id) {
        $stmt = $this->db->prepare("SELECT id FROM persone_animali WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}
