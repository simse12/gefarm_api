<?php
/**
 * Validator Utility - Gefarm API
 * Validazione e sanitizzazione input
 */

class Validator {
    
    /**
     * Valida email
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida password (min 8 caratteri, almeno 1 lettera maiuscola, 1 minuscola, 1 numero)
     */
    public static function password($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password troppo corta (minimo 8 caratteri)'];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password deve contenere almeno una lettera maiuscola'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password deve contenere almeno una lettera minuscola'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password deve contenere almeno un numero'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Valida stringa obbligatoria
     */
    public static function required($value, $field_name = 'Campo') {
        if (empty(trim($value))) {
            return ['valid' => false, 'error' => "$field_name obbligatorio"];
        }
        return ['valid' => true];
    }
    
    /**
     * Valida lunghezza stringa
     */
    public static function length($value, $min, $max, $field_name = 'Campo') {
        $len = strlen($value);
        
        if ($len < $min) {
            return ['valid' => false, 'error' => "$field_name troppo corto (minimo $min caratteri)"];
        }
        
        if ($len > $max) {
            return ['valid' => false, 'error' => "$field_name troppo lungo (massimo $max caratteri)"];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Valida Codice Fiscale italiano
     */
    public static function validCF($cf) {
        $cf = strtoupper(trim($cf));
        
        // Lunghezza corretta
        if (strlen($cf) !== 16) {
            return ['valid' => false, 'error' => 'Codice Fiscale deve essere di 16 caratteri'];
        }
        
        // Pattern corretto
        if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf)) {
            return ['valid' => false, 'error' => 'Formato Codice Fiscale non valido'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Valida CAP italiano
     */
    public static function zipCode($zip) {
        if (!preg_match('/^[0-9]{5}$/', $zip)) {
            return ['valid' => false, 'error' => 'CAP deve essere di 5 cifre'];
        }
        return ['valid' => true];
    }
    
    /**
     * Valida telefono italiano
     */
    public static function phone($phone) {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        if (!preg_match('/^(\+39)?[0-9]{9,11}$/', $phone)) {
            return ['valid' => false, 'error' => 'Numero di telefono non valido'];
        }
        
        return ['valid' => true];
    }
    
    /**
 * Valida POD (Point of Delivery)
 * Formato tipico italiano: IT001E12345678
 */
public static function validPOD($pod) {
    if (!preg_match('/^IT\d{3}E\d{8,9}$/', $pod)) {
        return ['valid' => false, 'error' => 'Codice POD non valido'];
    }
    return ['valid' => true];
}
    /**
     * Sanitizza stringa
     */
    public static function sanitizeString($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valida array di dati
     */
    public static function validateData($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule_list) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            foreach ($rule_list as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if ($params && empty($value)) {
                            $errors[$field] = ucfirst($field) . " è obbligatorio";
                        }
                        break;
                        
                    case 'email':
                        if (!empty($value) && !self::email($value)) {
                            $errors[$field] = "Email non valida";
                        }
                        break;
                        
                    case 'min':
                        if (!empty($value) && strlen($value) < $params) {
                            $errors[$field] = ucfirst($field) . " deve essere almeno $params caratteri";
                        }
                        break;
                        
                    case 'max':
                        if (!empty($value) && strlen($value) > $params) {
                            $errors[$field] = ucfirst($field) . " non può superare $params caratteri";
                        }
                        break;
                }
            }
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
}
?>
