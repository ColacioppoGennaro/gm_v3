<?php
/**
 * _core/EventCategoriesManager.php
 * Gestione categorie evento per Tipo (per-utente, per-tipo)
 * Limite fisso: max 50 categorie per tipo (per tutti i piani)
 */

require_once __DIR__ . '/helpers.php';

class EventCategoriesManager {
    private $db;
    private $userId;

    const MAX_CATS_PER_TIPO = 50;

    public function __construct($userId) {
        if (!$userId || !is_numeric($userId)) {
            throw new Exception('User ID non valido');
        }
        $this->userId = intval($userId);
        $this->db = db();
        $this->ensureTable();
    }

    public function list($tipoId) {
        $tipoId = intval($tipoId);
        if (!$this->userOwnsTipo($tipoId)) {
            throw new Exception('Accesso negato');
        }
        $stmt = $this->db->prepare("SELECT id, nome, created_at FROM event_categories WHERE user_id=? AND tipo_id=? ORDER BY nome ASC");
        $stmt->bind_param('ii', $this->userId, $tipoId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function create($tipoId, $nome) {
        $tipoId = intval($tipoId);
        if (!$this->userOwnsTipo($tipoId)) {
            throw new Exception('Accesso negato');
        }
        $nome = trim($nome ?? '');
        if ($nome === '') throw new Exception('Nome obbligatorio');
        if (strlen($nome) > 50) throw new Exception('Nome troppo lungo (max 50)');

        // limite per tipo
        $count = $this->countForTipo($tipoId);
        if ($count >= self::MAX_CATS_PER_TIPO) {
            throw new Exception('Limite categorie per tipo raggiunto (max 50)');
        }

        // univocità per tipo
        $stmt = $this->db->prepare('SELECT id FROM event_categories WHERE user_id=? AND tipo_id=? AND nome=?');
        $stmt->bind_param('iis', $this->userId, $tipoId, $nome);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Categoria già esistente per questo tipo');
        }

        $stmt = $this->db->prepare('INSERT INTO event_categories(user_id, tipo_id, nome) VALUES(?,?,?)');
        $stmt->bind_param('iis', $this->userId, $tipoId, $nome);
        if (!$stmt->execute()) {
            throw new Exception('Errore creazione categoria');
        }
        return ['id' => $this->db->insert_id, 'nome' => $nome];
    }

    public function delete($id) {
        $id = intval($id);
        if (!$id) throw new Exception('ID non valido');
        // verifica ownership
        $stmt = $this->db->prepare('SELECT tipo_id FROM event_categories WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $id, $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) throw new Exception('Categoria non trovata');

        $stmt = $this->db->prepare('DELETE FROM event_categories WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $id, $this->userId);
        if (!$stmt->execute()) {
            throw new Exception('Errore eliminazione categoria');
        }
        return ['success' => true];
    }

    private function countForTipo($tipoId) {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM event_categories WHERE user_id=? AND tipo_id=?');
        $stmt->bind_param('ii', $this->userId, $tipoId);
        $stmt->execute();
        return intval($stmt->get_result()->fetch_assoc()['cnt']);
    }

    private function userOwnsTipo($tipoId) {
        $stmt = $this->db->prepare('SELECT id FROM tipi_attivita WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $tipoId, $this->userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function ensureTable() {
        $sql = "CREATE TABLE IF NOT EXISTS event_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tipo_id INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_tipo_nome(user_id, tipo_id, nome),
            KEY idx_user(user_id),
            KEY idx_tipo(tipo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }
}
