# Gefarm API - Backend PHP v2.0

API REST completa per Gefarm App - Gestione utenti, dispositivi e dati contatori.

## 📋 Caratteristiche

✅ **Autenticazione JWT** sicura  
✅ **Criptazione AES-256** per dati sensibili (CF)  
✅ **Password hashing** con bcrypt  
✅ **Validazione input** robusta  
✅ **Gestione errori** standardizzata  
✅ **CORS** configurato  
✅ **Struttura MVC** pulita  

## 🗂️ Struttura Progetto

```
gefarm_api_v2/
├── config/
│   ├── database.php          # Connessione DB (singleton)
│   ├── jwt_config.php         # Configurazione JWT
│   └── encryption_config.php  # Criptazione AES-256
├── models/
│   ├── User.php              # Modello utenti (gefarm_users)
│   ├── Device.php            # Modello dispositivi (gefarm_devices)
│   └── DeviceMeterData.php   # Modello dati contatori
├── middleware/
│   └── auth.php              # Middleware autenticazione JWT
├── utils/
│   ├── response.php          # Helper risposte JSON
│   ├── jwt_helper.php        # Helper JWT
│   └── validator.php         # Validazione input
├── api/
│   ├── test.php              # Test endpoint
│   ├── auth/
│   │   ├── register.php      # POST - Registrazione
│   │   └── login.php         # POST - Login
│   ├── user/
│   │   ├── profile.php       # GET - Profilo (auth)
│   │   └── update_profile.php # PUT - Aggiorna profilo (auth)
│   ├── devices/
│   │   ├── list.php          # GET - Lista dispositivi (auth)
│   │   ├── add.php           # POST - Aggiungi dispositivo (auth)
│   │   └── details.php       # GET - Dettagli dispositivo (auth)
│   └── debug/
│       └── database_structure.php # GET - Struttura DB (DEBUG)
├── .htaccess                 # Configurazione Apache
└── README.md                 # Questo file
```

## 🚀 Installazione

### 1. Carica i File

Carica l'intera cartella `gefarm_api_v2` sul tuo hosting via FTP/SFTP:

```
/membri/gefarmdb/gefarm_api_v2/
```

### 2. Configura Database

**File: `config/database.php`**

Modifica le credenziali del database:

```php
private $host = "localhost";
private $db_name = "my_gefarmdb";
private $username = "gefarmdb";
private $password = "TUA_PASSWORD_MYSQL"; // ⚠️ INSERISCI LA TUA PASSWORD!
```

### 3. Configura Chiavi di Sicurezza

⚠️ **CRITICO**: Cambia le chiavi prima di andare in produzione!

**File: `config/jwt_config.php`**

```php
public static $secret_key = "TUA_CHIAVE_SEGRETA_RANDOM";
```

Genera con: `openssl rand -base64 32`

**File: `config/encryption_config.php`**

```php
private static $encryption_key = "12345678901234567890123456789012"; // ESATTAMENTE 32 caratteri
```

Genera con: `openssl rand -hex 16`

### 4. Verifica Permessi File

Assicurati che i permessi siano corretti:

```bash
chmod 755 gefarm_api_v2
chmod 644 gefarm_api_v2/.htaccess
chmod 644 gefarm_api_v2/api/**/*.php
chmod 644 gefarm_api_v2/config/*.php
```

### 5. Testa l'API

Apri il browser:

```
https://gefarmdb.it/gefarm_api_v2/api/test
```

Se vedi JSON con `"api_status": "OK"` → **FUNZIONA!** ✅

## 📡 Endpoint API

### 🔓 Pubblici (Senza Autenticazione)

#### Test API
```http
GET /api/test
```

**Risposta:**
```json
{
  "success": true,
  "message": "Gefarm API is running",
  "data": {
    "api_status": "OK",
    "php_version": "8.0.22",
    "database_connection": "Connected",
    "users_count": 1
  }
}
```

#### Registrazione
```http
POST /api/auth/register
Content-Type: application/json

{
  "email": "mario.rossi@example.com",
  "password": "Password123!",
  "nome": "Mario",
  "cognome": "Rossi",
  "avatar_color": "#00853d"
}
```

**Risposta:**
```json
{
  "success": true,
  "message": "Registrazione completata con successo",
  "data": {
    "user": {
      "id": 1,
      "email": "mario.rossi@example.com",
      "nome": "Mario",
      "cognome": "Rossi"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "mario.rossi@example.com",
  "password": "Password123!"
}
```

**Risposta:**
```json
{
  "success": true,
  "message": "Login effettuato con successo",
  "data": {
    "user": { ... },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

### 🔒 Protetti (Richiedono Token JWT)

Per tutti gli endpoint protetti, aggiungi l'header:

```http
Authorization: Bearer <il_tuo_token_jwt>
```

#### Profilo Utente
```http
GET /api/user/profile
Authorization: Bearer <token>
```

#### Aggiorna Profilo
```http
PUT /api/user/update_profile
Authorization: Bearer <token>
Content-Type: application/json

{
  "nome": "Mario",
  "cognome": "Rossi",
  "avatar_color": "#00853d"
}
```

#### Lista Dispositivi
```http
GET /api/devices/list
Authorization: Bearer <token>
```

#### Aggiungi Dispositivo
```http
POST /api/devices/add
Authorization: Bearer <token>
Content-Type: application/json

{
  "device_id": "EMC-001-123456",
  "role": "owner",
  "nickname": "Il mio EMC"
}
```

#### Dettagli Dispositivo
```http
GET /api/devices/details?device_id=EMC-001-123456
Authorization: Bearer <token>
```

### 🐛 Debug (Solo Sviluppo)

```http
GET /api/debug/database_structure
```

⚠️ **RIMUOVI QUESTO ENDPOINT IN PRODUZIONE!**

## 🧪 Test con Postman

### Importa Collection

1. Apri Postman
2. File → Import
3. Seleziona il file `Gefarm_API_Postman.json` (se fornito)
4. O crea manualmente le richieste seguendo gli esempi sopra

### Configura Variabili

Crea una variabile `base_url`:

```
base_url = https://gefarmdb.it/gefarm_api_v2
```

### Test Flow Completo

1. **Test API**: `GET {{base_url}}/api/test`
2. **Registrazione**: `POST {{base_url}}/api/auth/register`
3. Copia il `token` dalla risposta
4. **Profilo**: `GET {{base_url}}/api/user/profile` (con Bearer token)
5. **Lista Dispositivi**: `GET {{base_url}}/api/devices/list` (con Bearer token)

## 🔐 Sicurezza

### Implementato

✅ Password hashate con **bcrypt** (cost 12)  
✅ Codici fiscali criptati con **AES-256-CBC**  
✅ Autenticazione **JWT** con scadenza 24h  
✅ Validazione input su tutti gli endpoint  
✅ Protezione **SQL injection** (prepared statements)  
✅ Protezione **XSS** (htmlspecialchars)  
✅ Headers di sicurezza (.htaccess)  

### Da Fare Prima della Produzione

⚠️ **Cambia le chiavi segrete** in `jwt_config.php` e `encryption_config.php`  
⚠️ **Abilita HTTPS** (decommenta nel .htaccess)  
⚠️ **Rimuovi endpoint debug** (`/api/debug/database_structure.php`)  
⚠️ **Configura rate limiting** (opzionale, ma consigliato)  
⚠️ **Backup automatico** del database  

## 🗄️ Database

Le tabelle esistenti sono:

- `gefarm_users` - Utenti registrati
- `gefarm_devices` - Dispositivi EMC
- `gefarm_user_devices` - Associazione utenti-dispositivi
- `gefarm_device_meter_data` - Dati contatori (CF, POD, indirizzo)
- `gefarm_user_sessions` - Sessioni attive
- `gefarm_password_reset_tokens` - Token reset password
- `gefarm_thingsboard_configs` - Configurazioni ThingsBoard

## 📝 Validazioni

### Password
- Minimo 8 caratteri
- Almeno 1 lettera maiuscola
- Almeno 1 lettera minuscola
- Almeno 1 numero

### Email
- Formato email valido

### Codice Fiscale
- Esattamente 16 caratteri
- Pattern italiano valido

### CAP
- 5 cifre numeriche

### Telefono
- Formato italiano (+39 o senza prefisso)
- 9-11 cifre

## 🆘 Troubleshooting

### Errore "Failed opening required"

Verifica i percorsi nei `require_once`. Devono essere relativi alla posizione corretta:

```php
// Esempio da /api/auth/register.php
require_once __DIR__ . '/../../models/User.php';  // OK
require_once __DIR__ . '/../config/database.php'; // SBAGLIATO
```

### Errore "Database connection failed"

1. Verifica credenziali in `config/database.php`
2. Controlla che il database `my_gefarmdb` esista
3. Verifica permessi utente MySQL

### Errore "Token non valido"

1. Verifica di aver incluso l'header `Authorization: Bearer <token>`
2. Controlla che il token non sia scaduto (24h)
3. Verifica che la chiave JWT non sia cambiata

### Errore 500

1. Controlla i log PHP del server
2. Verifica permessi file (644 per PHP, 755 per directory)
3. Controlla errori syntax nei file PHP

## 📞 Supporto

Per problemi o domande, contatta il team di sviluppo.

## 📄 Licenza

Copyright © 2025 Gefarm. Tutti i diritti riservati.
# gefarm_api_v2
# gefarm_api
