<?php
/**
 * EmailHelper - Classe per l'invio di email
 * Gestisce tutti i tipi di comunicazioni email dell'applicazione
 * Supporta sia email in testo semplice che HTML (template-based)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailHelper {

    private static $sender_name = 'Gefarm Support';
    private static $sender_email = 'noreply@gefarmdb.altervista.org';
    private static $reply_to_email = 'gefarmapp@gmail.com';

    /**
     * Crea un'istanza configurata di PHPMailer
     */
    private static function createMailer(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isMail(); // Usato su Altervista
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(self::$sender_email, self::$sender_name);
        $mail->addReplyTo(self::$reply_to_email, self::$sender_name);
        return $mail;
    }

    /**
     * Invia un'email generica (testo semplice)
     */
    private static function sendPlainTextEmail(string $to, string $name, string $subject, string $body): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($to, $name);
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            error_log("[EmailHelper] Email inviata a: $to - Oggetto: $subject");
            return true;
        } catch (Exception $e) {
            error_log("[EmailHelper] Errore invio email a $to: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Invia un'email HTML (per template premium)
     */
    private static function sendHtmlEmail(string $to, string $name, string $subject, string $htmlBody): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            error_log("[EmailHelper] Email HTML inviata a: $to - Oggetto: $subject");
            return true;
        } catch (Exception $e) {
            error_log("[EmailHelper] Errore invio email HTML a $to: " . $mail->ErrorInfo);
            return false;
        }
    }

    // ───────────────────────────────────────────────
    // METODI PUBBLICI
    // ───────────────────────────────────────────────

    public static function sendVerificationEmail(string $email, string $name, string $token): bool {
        $subject = "Gefarm - Verifica il tuo Account";
        $body = "Gentile $name,\n\n"
              . "Benvenuto in Gefarm!\n\n"
              . "Per completare la registrazione, inserisci il seguente codice di verifica nell'app:\n\n"
              . "CODICE: $token\n\n"
              . "Questo codice scadrà tra 24 ore.\n\n"
              . "Se non hai richiesto questa registrazione, ignora questa email.\n\n"
              . "Cordiali saluti,\n"
              . "Team Gefarm";

        return self::sendPlainTextEmail($email, $name, $subject, $body);
    }

    public static function sendPasswordResetEmail(string $email, string $name, string $token): bool {
        $subject = "Gefarm - Reset Password";
        $body = "Gentile $name,\n\n"
              . "Abbiamo ricevuto una richiesta di reset della tua password.\n\n"
              . "Per completare il reset, inserisci il seguente codice nell'app:\n\n"
              . "CODICE: $token\n\n"
              . "Questo codice scadrà tra 1 ora.\n\n"
              . "Se non hai richiesto il reset password, ignora questa email.\n\n"
              . "Cordiali saluti,\n"
              . "Team Gefarm";

        return self::sendPlainTextEmail($email, $name, $subject, $body);
    }

    // Alias per retrocompatibilità
    public static function sendPasswordResetToken(string $email, string $name, string $token): bool {
        return self::sendPasswordResetEmail($email, $name, $token);
    }

    public static function sendPasswordChanged(string $email, string $name): bool {
        $subject = "Gefarm - Password Cambiata";
        $body = "Gentile $name,\n\n"
              . "La tua password è stata cambiata con successo.\n\n"
              . "Se non sei stato tu a richiedere questo cambiamento, contatta immediatamente il supporto.\n\n"
              . "Cordiali saluti,\n"
              . "Team Gefarm";

        return self::sendPlainTextEmail($email, $name, $subject, $body);
    }

    public static function sendPasswordChangedNotification(string $email, string $name): bool {
        return self::sendPasswordChanged($email, $name);
    }

    // ───────────────────────────────────────────────
    // METODI FUTURI (es. con link invece di codice)
    // ───────────────────────────────────────────────

    /**
     * Esempio: invia email di reset con link (utile per web)
     */
    public static function sendPasswordResetLink(string $email, string $name, string $resetUrl): bool {
        $subject = "Gefarm - Resetta la tua password";

        // Puoi sostituire con un template HTML reale (es. caricato da file)
        $html = "
        <div style='font-family: Inter, sans-serif; max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
            <h2 style='color: #00853d; text-align: center;'>Reimposta la tua password</h2>
            <p>Ciao <strong>$name</strong>,</p>
            <p>Hai richiesto di reimpostare la tua password. Clicca sul pulsante qui sotto per procedere:</p>
            <div style='text-align: center; margin: 25px 0;'>
                <a href='$resetUrl' 
                   style='display: inline-block; background: #00853d; color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;'>
                    Reimposta Password
                </a>
            </div>
            <p style='font-size: 0.9em; color: #666;'>
                Il link scadrà tra 1 ora.<br>
                Se non hai richiesto questa azione, ignora questa email.
            </p>
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            <p style='text-align: center; color: #888; font-size: 0.85em;'>© " . date('Y') . " Gefarm. Tutti i diritti riservati.</p>
        </div>
        ";

        return self::sendHtmlEmail($email, $name, $subject, $html);
    }
}
?>