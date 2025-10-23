<?php
/**
 * EmailHelper - Classe per l'invio di email
 * Gestisce tutti i tipi di comunicazioni email dell'applicazione
 */

class EmailHelper {
    
    // Configurazione (potresti spostare questi valori nel .env o config)
    private static $sender_email = 'noreply@gefarm.com';
    private static $sender_name = 'Gefarm Support';
    private static $support_email = 'support@gefarm.com';
    
    /**
     * Invia email di reset password con token
     * 
     * @param string $email Email destinatario
     * @param string $name Nome destinatario
     * @param string $token Token di reset
     * @return bool Esito invio
     */
    public static function sendPasswordResetToken($email, $name, $token) {
        // URL frontend per il reset (da configurare)
      // URL frontend per il reset
        $reset_url = "https://simonaserra.altervista.org/gefarm_api_v2/reset-password.php?token=$token";
        
        $subject = "Gefarm - Reset Password";
        
        $message = "Gentile $name,\n\n";
        $message .= "Abbiamo ricevuto una richiesta di reset della tua password.\n\n";
        $message .= "Per completare il reset, clicca sul link seguente o copialo nel tuo browser:\n";
        $message .= "$reset_url\n\n";
        $message .= "Questo link scadrà tra 60 minuti.\n\n";
        $message .= "Se non hai richiesto il reset password, ignora questa email.\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Team Gefarm";
        
        // Headers per email
        $headers = "From: " . self::$sender_name . " <" . self::$sender_email . ">\r\n";
        $headers .= "Reply-To: " . self::$support_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Logging per debug (solo su test)
        error_log("Invio email reset password a: $email con token: $token");
        
        // In produzione: usa la funzione mail() di PHP o una libreria SMTP
        // Per ora, usiamo mail() di PHP
        try {
            return mail($email, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Errore invio email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia email di conferma cambio password
     * 
     * @param string $email Email destinatario
     * @param string $name Nome destinatario
     * @return bool Esito invio
     */
    public static function sendPasswordChanged($email, $name) {
        $subject = "Gefarm - Password Cambiata";
        
        $message = "Gentile $name,\n\n";
        $message .= "La tua password è stata cambiata con successo.\n\n";
        $message .= "Se non sei stato tu a richiedere questo cambiamento, contatta immediatamente il supporto.\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Team Gefarm";
        
        $headers = "From: " . self::$sender_name . " <" . self::$sender_email . ">\r\n";
        $headers .= "Reply-To: " . self::$support_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Logging per debug (solo su test)
        error_log("Invio email conferma cambio password a: $email");
        
        try {
            return mail($email, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Errore invio email: " . $e->getMessage());
            return false;
        }
    }
}
?>