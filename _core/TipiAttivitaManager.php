</document>

---

### 📄 FILE 2: `_core/TipiAttivitaManager.php`

<document path="_core/TipiAttivitaManager.php">
```php
<?php
/**
 * _core/TipiAttivitaManager.php
 * Gestione CRUD tipi attività
 */

require_once __DIR__ . '/helpers.php';

class TipiAttivitaManager {
    private $db;
    private $userId;
    
    // Limiti
    const MAX_TIPI_PER_SETTORE_FREE = 5;
    const MAX_TIPI_PER_SETTORE_PRO = 999;
    
    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception("User ID non valido");
        }
        
        $this->userId = intval($userId);
        $this->db = db();
    }
    
    /**
     * Lista tipi attività (opzionale: filtro per settore)
     */
    public function list($settoreId = null) {
        if ($settoreId) {
            // Lista tipi di un settore specifico
            $stmt = $this->db->prepare("
                SELECT 
                    t.id,
                    t.settore_id,
                    t.nome,
                    t.icona,
                    t.colore,
                    t.puo_collegare_documento,
                    t.ordine,
                    s.nome AS settore_nome
                FROM tipi_attivita t
                JOIN settori s ON t.settore_id = s.id
                WHERE t.user_id = ? AND t.settore_id = ?
                ORDER BY t.ordine ASC, t.id ASC
            ");
            $stmt->bind_param("ii", $this->userId, $settoreId);
        } else {
            // Lista tutti i tipi raggruppati per settore
            $stmt = $this->db->prepare("
                SELECT 
                    t.id,
                    t.settore_id,
                    t.nome,
                    t.icona,
                    t.colore,
                    t.puo_collegare_documento,
                    t.ordine,
                    s.nome AS settore_nome,
                    s.icona AS settore_icona
                FROM tipi_attivita t
                JOIN settori s ON t.settore_id = s.id
                WHERE t.user_id = ?
                ORDER BY s.ordine ASC, t.ordine ASC
            ");
            $stmt->bind_param("i", $this->userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Crea nuovo tipo attività
     */
    public function create($settoreId, $nome, $icona = '📌', $colore = null, $puoCollegareDoc = false) {
        // Verifica ownership settore
        if (!$this->userOwnsSettore($settoreId)) {
            throw new Exception("Settore non trovato");
        }
        
        // Verifica limiti
        $role = $this->getUserRole();
        $maxTipi = ($role === 'pro') ? self::MAX_TIPI_PER_SETTORE_PRO : self::MAX_TIPI_PER_SETTORE_FREE;
        
        $count = $this->countTipiInSettore($settoreId);
        
        if ($count >= $maxTipi) {
            throw new Exception("Limite tipi raggiunto per questo settore. Passa a PRO per tipi illimitati.");
        }
        
        // Validazione
        $nome = trim($nome);
        if (empty($nome)) {
            throw new Exception("Nome tipo attività obbligatorio");
        }
        
        // Check duplicati
        if ($this->tipoExists($settoreId, $nome)) {
            throw new Exception("Tipo attività già esistente in questo settore");
        }
        
        // Colore default da settore se non specificato
        if (!$colore) {
            $colore = $this->getSettoreColore($settoreId);
        }
        
        // Prossimo ordine
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(ordine), 0) + 1 AS next_ordine FROM tipi_attivita WHERE user_id = ? AND settore_id = ?");
        $stmt->bind_param("ii", $this->userId, $settoreId);
        $stmt->execute();
        $ordine = $stmt->get_result()->fetch_assoc()['next_ordine'];
        
        // Insert
        $stmt = $this->db->prepare("
            INSERT INTO tipi_attivita (user_id, settore_id, nome, icona, colore, puo_collegare_documento, ordine) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssii", $this->userId, $settoreId, $nome, $icona, $colore, $puoCollegareDoc, $ordine);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore creazione tipo: " . $stmt->error);
        }
        
        return [
            'id' => $this->db->insert_id,
            'settore_id' => $settoreId,
            'nome' => $nome,
            'icona' => $icona,
            'colore' => $colore,
            'puo_collegare_documento' => $puoCollegareDoc,
            'ordine' => $ordine
        ];
    }
    
    /**
     * Aggiorna tipo attività
     */
    public function update($tipoId, $data) {
        if (!$this->userOwnsTipo($tipoId)) {
            throw new Exception("Accesso negato");
        }
        
        $updates = [];
        $types = "";
        $values = [];
        
        if (isset($data['nome'])) {
            $nome = trim($data['nome']);
            if (empty($nome)) throw new Exception("Nome non può essere vuoto");
            
            // Check duplicati
            $tipo = $this->getTipo($tipoId);
            $stmt = $this->db->prepare("SELECT id FROM tipi_attivita WHERE user_id = ? AND settore_id = ? AND nome = ? AND id != ?");
            $stmt->bind_param("iisi", $this->userId, $tipo['settore_id'], $nome, $tipoId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Nome tipo già esistente in questo settore");
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
        
        if (isset($data['puo_collegare_documento'])) {
            $updates[] = "puo_collegare_documento = ?";
            $types .= "i";
            $values[] = $data['puo_collegare_documento'] ? 1 : 0;
        }
        
        if (isset($data['ordine'])) {
            $updates[] = "ordine = ?";
            $types .= "i";
            $values[] = intval($data['ordine']);
        }
        
        if (empty($updates)) {
            throw new Exception("Nessun campo da aggiornare");
        }
        
        $sql = "UPDATE tipi_attivita SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
        $types .= "ii";
        $values[] = $tipoId;
        $values[] = $this->userId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore aggiornamento: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    /**
     * Elimina tipo attività
     */
    public function delete($tipoId) {
        if (!$this->userOwnsTipo($tipoId)) {
            throw new Exception("Accesso negato");
        }
        
        $stmt = $this->db->prepare("DELETE FROM tipi_attivita WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $tipoId, $this->userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore eliminazione: " . $stmt->error);
        }
        
        return ['success' => true];
    }
    
    // Helper methods
    private function countTipiInSettore($settoreId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM tipi_attivita WHERE user_id = ? AND settore_id = ?");
        $stmt->bind_param("ii", $this->userId, $settoreId);
        $stmt->execute();
        return intval($stmt->get_result()->fetch_assoc()['cnt']);
    }
    
    private function tipoExists($settoreId, $nome) {
        $stmt = $this->db->prepare("SELECT id FROM tipi_attivita WHERE user_id = ? AND settore_id = ? AND nome = ?");
        $stmt->bind_param("iis", $this->userId, $settoreId, $nome);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function userOwnsSettore($settoreId) {
        $stmt = $this->db->prepare("SELECT id FROM settori WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $settoreId, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function userOwnsTipo($tipoId) {
        $stmt = $this->db->prepare("SELECT id FROM tipi_attivita WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $tipoId, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function getTipo($tipoId) {
        $stmt = $this->db->prepare("SELECT * FROM tipi_attivita WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $tipoId, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function getSettoreColore($settoreId) {
        $stmt = $this->db->prepare("SELECT colore FROM settori WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $settoreId, $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['colore'] ?? '#7c3aed';
    }
    
    private function getUserRole() {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['role'] ?? 'free';
    }
}
