<?php
/**
 * Limiti AI per piano Free vs Pro
 */

class AILimits {
    
    public static function getMonthlyLimits($isPro = false) {
        return [
            'free' => [
                'gemini_analysis' => 10,        // 10 analisi documenti/mese
                'gemini_questions' => 50,       // 50 domande assistant/mese  
                'document_size_mb' => 5,        // Max 5MB per documento
                'total_documents' => 5          // Max 5 documenti totali
            ],
            'pro' => [
                'gemini_analysis' => 500,       // 500 analisi documenti/mese
                'gemini_questions' => 2000,     // 2000 domande assistant/mese
                'document_size_mb' => 50,       // Max 50MB per documento  
                'total_documents' => 200        // Max 200 documenti totali
            ]
        ];
    }
    
    public static function checkAnalysisLimit($userId) {
        $user = user();
        $isPro = is_pro();
        $limits = self::getMonthlyLimits($isPro);
        $plan = $isPro ? 'pro' : 'free';
        
        // Conta analisi questo mese - gestisce caso in cui colonna analysis_status non esiste ancora
        $db = db();
        
        // Prova prima con i nuovi campi
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM documents 
                WHERE user_id = ? 
                AND analysis_status = 'completed' 
                AND analyzed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $used = $result['count'] ?? 0;
        } catch (Exception $e) {
            // Fallback: se i campi non esistono, conta tutti i documenti del mese come proxy
            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM documents 
                    WHERE user_id = ? 
                    AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $used = $result['count'] ?? 0;
            } catch (Exception $e2) {
                // Ultimo fallback: considera 0 analisi usate
                $used = 0;
            }
        }
        
        $limit = $limits[$plan]['gemini_analysis'];
        
        return [
            'allowed' => $used < $limit,
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'plan' => $plan
        ];
    }
    
    public static function checkDocumentSizeLimit($fileSize, $isPro = null) {
        if ($isPro === null) $isPro = is_pro();
        $limits = self::getMonthlyLimits($isPro);
        $plan = $isPro ? 'pro' : 'free';
        
        $maxBytes = $limits[$plan]['document_size_mb'] * 1024 * 1024;
        
        return [
            'allowed' => $fileSize <= $maxBytes,
            'max_mb' => $limits[$plan]['document_size_mb'],
            'file_mb' => round($fileSize / (1024 * 1024), 2)
        ];
    }
}