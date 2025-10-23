<?php
/**
 * Response Utility - GeFarm API
 * Helper per risposte JSON standardizzate
 */

class Response {
    
    /**
     * Risposta di successo
     */
    public static function success($data = null, $message = "Operazione completata", $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Risposta di errore
     */
    public static function error($message = "Errore", $code = 400, $details = null) {
        http_response_code($code);
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Risposta non autorizzato
     */
    public static function unauthorized($message = "Accesso non autorizzato") {
        self::error($message, 401);
    }
    
    /**
     * Risposta non trovato
     */
    public static function notFound($message = "Risorsa non trovata") {
        self::error($message, 404);
    }
    
    /**
     * Risposta validazione fallita
     */
    public static function validationError($errors, $message = "Dati non validi") {
        self::error($message, 422, $errors);
    }
    
    /**
     * Risposta errore server
     */
    public static function serverError($message = "Errore interno del server") {
        self::error($message, 500);
    }
}
?>
