<?php
/**
 * Response Utility - Gefarm API v2.2
 * Helper per risposte JSON standardizzate
 */

class Response {
    
    /**
     * Risposta di successo
     * 
     * @param mixed $data Dati da restituire
     * @param int $code HTTP status code (default: 200)
     * @return void
     */
    public static function success($data = null, $code = 200) {
        http_response_code($code);
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Risposta di errore
     * 
     * @param string $message Messaggio di errore
     * @param int $code HTTP status code (default: 400)
     * @param mixed $data Dati aggiuntivi opzionali
     * @return void
     */
    public static function error($message = "Errore", $code = 400, $data = null) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Aggiungi dati aggiuntivi se presenti
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Risposta non autorizzato (401)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function unauthorized($message = "Accesso non autorizzato") {
        self::error($message, 401);
    }
    
    /**
     * Risposta forbidden (403)
     * 
     * @param string $message Messaggio di errore
     * @param mixed $data Dati aggiuntivi opzionali
     * @return void
     */
    public static function forbidden($message = "Accesso negato", $data = null) {
        self::error($message, 403, $data);
    }
    
    /**
     * Risposta non trovato (404)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function notFound($message = "Risorsa non trovata") {
        self::error($message, 404);
    }
    
    /**
     * Risposta validazione fallita (422)
     * 
     * @param array $errors Errori di validazione
     * @param string $message Messaggio principale
     * @return void
     */
    public static function validationError($errors, $message = "Dati non validi") {
        self::error($message, 422, $errors);
    }
    
    /**
     * Risposta conflitto (409)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function conflict($message = "Conflitto con risorsa esistente") {
        self::error($message, 409);
    }
    
    /**
     * Risposta token scaduto (410)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function gone($message = "Risorsa scaduta") {
        self::error($message, 410);
    }
    
    /**
     * Risposta rate limiting (429)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function tooManyRequests($message = "Troppi tentativi. Riprova più tardi.") {
        self::error($message, 429);
    }
    
    /**
     * Risposta errore server (500)
     * 
     * @param string $message Messaggio di errore
     * @return void
     */
    public static function serverError($message = "Errore interno del server") {
        self::error($message, 500);
    }
}
?>