<?php
/**
 * FILE: _core/SettoriManager.php
 * Gestione CRUD settori utente
 * ✅ LIMITI: FREE 2 aree, PRO 20 aree
 */

require_once __DIR__ . '/helpers.php';

class SettoriManager {
    private $db;
    private $userId;
    
    const MAX_SETTORI_FREE = 2;
    const MAX_SETTORI_PRO = 50; // cap globale richiesto: max 50
    
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->db = db();
    }
    
    public function list() {
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                s.nome,
                s.icona,
                s.colore,
                s.ordine,
                COUNT(t.id) AS num_tipi,
                s.created_at
            FROM settori s
            LEFT JOIN tipi_attivita t ON s.id = t.settore_id
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.ordine ASC, s.id ASC
        ");
        
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function create($nome, $icona = '📁', $colore = '#7c3aed') {
        $role = $this->getUserRole();
        $maxSettori = ($role === 'pro') ? self::MAX_SETTORI_PRO : self::MAX_SETTORI_FREE;
        
        $count = $this->countUserSettori();
        
        if ($count >= $maxSettori) {
            throw new Exception("Limite aree raggiunto (" . ($role === 'pro' ? '20 per PRO' : '2 per FREE') . "). Passa a PRO per più aree.");
        }
        
        $nome = trim($nome);
        if (empty($nome)) {
            throw new Exception("Nome area obbligatorio");
        }
        
        if ($this->settoreExists($nome)) {
            throw new Exception("Area già esistente");
        }
        
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(ordine), 0) + 1 AS next_ordine FROM settori WHERE user_id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $ordine = $stmt->get_result()->fetch_assoc()['next_ordine'];
        
        $stmt = $this->db->prepare("INSERT INTO settori (user_id, nome, icona, colore, ordine) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $this->userId, $nome, $icona, $colore, $ordine);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore creazione area: " . $stmt->error);
        }
        
        return [
            'id' => $this->db->insert_id,
            'nome' => $nome,
            'icona' => $icona,
            'colore' => $colore,
            'ordine' => $ordine
        ];
    }
    
    public function update($settoreId, $data) {
        if (!$this->userOwnsSettore($settoreId)) {
            throw new Exception("Accesso negato");
        }
        
        $updates = [];
        $types = "";
        $values = [];
        
        if (isset($data['nome'])) {
            $nome = trim($data['nome']);
            if (empty($nome)) throw new Exception("Nome non può essere vuoto");
            
            $stmt = $this->db->prepare("SELECT id FROM settori WHERE user_id = ? AND nome = ? AND id != ?");
            $stmt->bind_param("isi", $this->userId, $nome, $settoreId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Nome area già esistente");
            }
            
            $updates[] = "nome = ?";
            $types .= "s";
            $values[] = $nome;
        }
        
        if (isset($data['icona'])) {
            $updates[] = "icona = ?";
            $types .= "s";
            $values[] = $data['icona'];
        }
        
        if (isset($data['colore'])) {
            $updates[] = "colore = ?";
            $types .= "s";
            $values[] = $data['colore'];
        }
        
        if (isset($data['ordine'])) {
            $updates[] = "ordine = ?";
            $types .= "i";
            $values[] = intval($data['ordine']);
        }
        
        if (empty($updates)) {
            throw new Exception("Nessun campo da aggiornare");
        }
        
        $sql = "UPDATE settori SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
        $types .= "ii";
        $values[] = $settoreId;
        $values[] = $this->userId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore aggiornamento: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    public function delete($settoreId) {
        if (!$this->userOwnsSettore($settoreId)) {
            throw new Exception("Accesso negato");
        }
        
        $stmt = $this->db->prepare("DELETE FROM settori WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $settoreId, $this->userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore eliminazione: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    private function countUserSettori() {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM settori WHERE user_id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        return intval($stmt->get_result()->fetch_assoc()['cnt']);
    }
    
    private function settoreExists($nome) {
        $stmt = $this->db->prepare("SELECT id FROM settori WHERE user_id = ? AND nome = ?");
        $stmt->bind_param("is", $this->userId, $nome);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function userOwnsSettore($settoreId) {
        $stmt = $this->db->prepare("SELECT id FROM settori WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $settoreId, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function getUserRole() {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['role'] ?? 'free';
    }
}
