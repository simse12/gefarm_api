<?php

// Importa le classi di PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Richiede l'autoload di Composer (che si trova nella cartella 'vendor')
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {

    /**
     * Invia l'email per la richiesta Chain2
     *
     * @param string $userEmail Email del cliente (per la copia)
     * @param array $templateData Dati da inserire nel template (es. ['NOME_COGNOME' => 'Mario Rossi', ...])
     * @return bool
     */
    public static function sendChain2Request($userEmail, $templateData) {
        
        $mail = new PHPMailer(true);
        $template_path = __DIR__ . '/../assets/templates/chain2_request_template.html';
        
        // --- CORREZIONE LOGO ---
        // Il tuo template HTML si aspetta 'logo-gefarm.png'
        $logo_path = __DIR__ . '/../assets/images/logo-gefarm.png';

        try {
            // --- 1. CONFIGURAZIONE SERVER (MODIFICATA) ---
            
            // Altervista blocca le connessioni SMTP esterne.
            // Usiamo la funzione mail() di PHP come "trasporto".
            $mail->isMail();
            
            // Rimuoviamo/commentiamo tutte le impostazioni SMTP
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            // $mail->isSMTP();
            // $mail->Host       = 'in-v3.mailjet.com';
            // $mail->SMTPAuth   = true;
            // $mail->Username   = 'LA_TUA_API_KEY_MAILJET';
            // $mail->Password   = 'LA_TUA_SECRET_KEY_MAILJET';
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // $mail->Port       = 465;

            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            

            // --- 2. MITTENTE E DESTINATARI ---
            // Usiamo un'email del dominio di Altervista per evitare filtri antispam
            $mail->setFrom('noreply@gefarmdb.altervista.org', 'Gefarm Support');
            // Chi riceve l'email, se clicca "Rispondi", scriverà a gmail
            $mail->addReplyTo('gefarmapp@gmail.com', 'Gefarm Support');
            
            // Destinatario principale (Gefarm)
            $mail->addAddress('gefarmapp@gmail.com', 'Gefarm Admin');
            
            // Copia per conoscenza al cliente
            $mail->addCC($userEmail);

            // --- 3. CONTENUTO EMAIL ---
            $mail->isHTML(true);
            $mail->Subject = 'Nuova Richiesta Abilitazione Chain2 - POD: ' . $templateData['{{POD_SCAMBIO}}'];

            // Carica il template HTML
            if (!file_exists($template_path)) {
                error_log("Template email non trovato: $template_path");
                return false;
            }
            $htmlBody = file_get_contents($template_path);

            // Sostituisci i placeholder
            foreach ($templateData as $key => $value) {
                $htmlBody = str_replace($key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $htmlBody);
            }
            
            $mail->Body = $htmlBody;

            // Incorpora il logo
            if (file_exists($logo_path)) {
                // 'gefarmlogo' è il CID (Content ID) a cui il template HTML <img src="cid:gefarmlogo"> fa riferimento
                $mail->addEmbeddedImage($logo_path, 'gefarmlogo');
            } else {
                error_log("Logo non trovato. Percorso cercato: $logo_path");
            }

            // Testo alternativo per client non-HTML
            $mail->AltBody = 'Nuova richiesta di abilitazione Chain2. Dati nei campi allegati.';

            // --- 4. INVIO ---
            $mail->send();
            error_log("Email Chain2 inviata (via mail()) a gefarmapp@gmail.com e $userEmail");
            return true;

        } catch (Exception $e) {
            // L'errore ora includerà il log di debug completo
            error_log("Errore invio email Chain2 (mail()): {$mail->ErrorInfo}");
            // Rilancia l'eccezione per farla catturare da submit.php
            throw new Exception($mail->ErrorInfo);
        }
    }
}

