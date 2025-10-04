<?php
/**
 * GM_V3 - Input Validator
 * 
 * Validazione sicura degli input utente
 */

if (!defined('GM_V3_INIT')) {
    http_response_code(403);
    die('Accesso negato');
}

class Validator {
    
    private $errors = [];
    
    /**
     * Valida email
     */
    public function email($value, $fieldName = 'email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = 'Email non valida';
            return false;
        }
        return true;
    }
    
    /**
     * Campo obbligatorio
     */
    public function required($value, $fieldName) {
        if (empty($value) && $value !== '0') {
            $this->errors[$fieldName] = "Il campo $fieldName è obbligatorio";
            return false;
        }
        return true;
    }
    
    /**
     * Lunghezza minima
     */
    public function minLength($value, $min, $fieldName) {
        if (strlen($value) < $min) {
            $this->errors[$fieldName] = "Il campo $fieldName deve avere almeno $min caratteri";
            return false;
        }
        return true;
    }
    
    /**
     * Lunghezza massima
     */
    public function maxLength($value, $max, $fieldName) {
        if (strlen($value) > $max) {
            $this->errors[$fieldName] = "Il campo $fieldName non può superare $max caratteri";
            return false;
        }
        return true;
    }
    
    /**
     * Valida dimensione file
     */
    public function fileSize($fileSize, $maxSize, $fieldName = 'file') {
        if ($fileSize > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 2);
            $this->errors[$fieldName] = "Il file non può superare $maxMB MB";
            return false;
        }
        return true;
    }
    
    /**
     * Valida tipo file
     */
    public function fileType($mimeType, $extension, $fieldName = 'file') {
        if (!isFileTypeAllowed($mimeType, $extension)) {
            $allowed = implode(', ', ALLOWED_FILE_EXTENSIONS);
            $this->errors[$fieldName] = "Tipo file non permesso. Tipi consentiti: $allowed";
            return false;
        }
        return true;
    }
    
    /**
     * Valida formato data
     */
    public function date($value, $format = 'Y-m-d', $fieldName = 'date') {
        $d = DateTime::createFromFormat($format, $value);
        if (!$d || $d->format($format) !== $value) {
            $this->errors[$fieldName] = "Data non valida. Formato richiesto: $format";
            return false;
        }
        return true;
    }
    
    /**
     * Valida enum (valore tra opzioni specifiche)
     */
    public function enum($value, $allowedValues, $fieldName) {
        if (!in_array($value, $allowedValues)) {
            $allowed = implode(', ', $allowedValues);
            $this->errors[$fieldName] = "Valore non valido per $fieldName. Valori permessi: $allowed";
            return false;
        }
        return true;
    }
    
    /**
     * Sanitizza stringa
     */
    public function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Verifica se ci sono errori
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Ottieni tutti gli errori
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Reset errori
     */
    public function reset() {
        $this->errors = [];
    }
    
    /**
     * Valida file upload
     */
    public function validateUpload($file, $userTier) {
        $limits = getLimitsForTier($userTier);
        
        // Verifica errori PHP upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors['file'] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // Verifica dimensione
        if (!$this->fileSize($file['size'], $limits['maxFileSize'], 'file')) {
            return false;
        }
        
        // Verifica tipo
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($file['tmp_name']);
        
        if (!$this->fileType($mimeType, $extension, 'file')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Messaggi errore upload PHP
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File troppo grande';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incompleto';
            case UPLOAD_ERR_NO_FILE:
                return 'Nessun file caricato';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Cartella temporanea mancante';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Impossibile scrivere il file';
            default:
                return 'Errore durante l\'upload';
        }
    }
}
