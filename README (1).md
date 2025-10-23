# 🎯 GeFarm Database v5 - Complete Backend Integration

## 📦 Package Contents

Questo package contiene **TUTTE le modifiche necessarie** per integrare il database locale Drift con il backend PHP GeFarm API.

### 📁 File Inclusi

```
gefarm_updated/
│
├── 📄 README.md                        ← Questo file
├── 📄 MIGRATION_GUIDE.md               ← Guida migrazione dettagliata
├── 📄 IMPLEMENTATION_CHECKLIST.md      ← Checklist implementazione completa
├── 📄 USAGE_EXAMPLES.dart              ← Esempi di utilizzo API
│
├── 📂 tables/                          ← Tabelle Drift aggiornate
│   ├── users_gefarm.dart               ✅ UPDATED (+ email, nome, cognome)
│   ├── device.dart                     ✅ UPDATED (+ backend fields)
│   ├── user_devices.dart               🆕 NEW (junction table)
│   ├── user_sessions.dart              🆕 NEW (JWT tokens)
│   ├── password_reset_tokens.dart      🆕 NEW (reset password)
│   └── device_meter_data.dart          🆕 NEW (Chain2 data)
│
├── 📂 dao/                             ← Data Access Objects
│   ├── user_devices_dao.dart           🆕 NEW
│   ├── user_sessions_dao.dart          🆕 NEW
│   ├── password_reset_tokens_dao.dart  🆕 NEW
│   └── device_meter_data_dao.dart      🆕 NEW
│
└── 📄 app_database.dart                ✅ UPDATED (schema v5 + migration)
```

---

## 🎯 Cosa Risolve Questo Update

### ✅ **Problemi Critici Risolti**

1. **Autenticazione Impossibile** ❌ → ✅
   - Prima: UsersGefarm senza email → impossibile fare login
   - Ora: UsersGefarm con email, nome, cognome, emailVerified

2. **Condivisione Dispositivi** ❌ → ✅
   - Prima: Un device = un utente (relazione 1:N)
   - Ora: Many-to-many con ruoli (owner, user, technician, viewer)
   - Caso d'uso: Famiglia o azienda con dispositivi condivisi

3. **Sessioni Non Persistenti** ❌ → ✅
   - Prima: Login ogni volta che apri l'app
   - Ora: JWT token salvati in UserSessions

4. **Reset Password Mancante** ❌ → ✅
   - Prima: Nessun supporto per reset password
   - Ora: Flusso completo con token + deep link

5. **Chain2 Non Salvato** ❌ → ✅
   - Prima: Dati contatore solo nel backend
   - Ora: DeviceMeterData con CF, POD, indirizzo, etc.

6. **Avatar Color Duplicato** ❌ → ✅
   - Prima: In UsersGefarm (INT) + UserPreferences (TEXT)
   - Ora: Solo in UserPreferences (TEXT hex)

---

## 🚀 Quick Start

### Step 1: Backup Database Esistente

**IMPORTANTE**: Prima di integrare, fai backup!

```dart
import 'package:path_provider/path_provider.dart';
import 'dart:io';

Future<void> backupDatabase() async {
  final dir = await getApplicationDocumentsDirectory();
  final dbFile = File('${dir.path}/gefarm.db');
  
  if (await dbFile.exists()) {
    final backup = File('${dir.path}/gefarm_v4_backup.db');
    await dbFile.copy(backup.path);
    print('✅ Backup salvato: ${backup.path}');
  }
}
```

### Step 2: Integra i File

1. Copia le cartelle nel tuo progetto:
   ```
   CP tables/* → lib/modules/gefarm/data/data_source/localdb/tables/
   CP dao/* → lib/modules/gefarm/data/data_source/localdb/dao/
   CP app_database.dart → lib/modules/gefarm/data/data_source/localdb/
   ```

2. **MANTIENI** i file esistenti che non ho modificato:
   - `device_families.dart`
   - `device_types.dart`
   - `user_preferences.dart`
   - `user_device_context.dart`
   - `daily_energy.dart`
   - `monthly_energy.dart`
   - `quarter_hourly_energy.dart`

### Step 3: Rigenera Codice Drift

```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

### Step 4: Test Migrazione

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  final db = AppDatabase();
  
  // Test: migration parte automaticamente
  final users = await db.usersGefarmDao.getAllUsers();
  print('✅ Users migrated: ${users.length}');
  
  // Test: nuove tabelle
  await db.userDevicesDao.associateDeviceToUser(
    userId: 1,
    deviceId: 1,
    role: 'owner',
  );
  
  print('🎉 Migration successful!');
}
```

---

## 📊 Schema Comparativo

### PRIMA (v4)

```
UsersGefarm
  └─ userId (PK)
  └─ userName
  └─ avatarColor (INT) ⚠️

Devices
  └─ deviceId (PK)
  └─ serialNumber
  └─ userId (FK) ⚠️ 1:N only
```

### DOPO (v5)

```
UsersGefarm
  └─ userId (PK)
  └─ email ✨ NEW
  └─ nome ✨ NEW
  └─ cognome ✨ NEW
  └─ emailVerified ✨ NEW

UserDevices (Junction) ✨ NEW
  └─ userId (FK) ─┐
  └─ deviceId (FK)├─ Many-to-Many!
  └─ role         │
  └─ nickname ────┘

Devices
  └─ deviceId (PK)
  └─ serialNumber
  └─ nomeDispositivo ✨ NEW
  └─ chain2Active ✨ NEW
  └─ ssidAp ✨ NEW

DeviceMeterData ✨ NEW
  └─ deviceId (FK)
  └─ cf (encrypted)
  └─ pod, indirizzo, etc.

UserSessions ✨ NEW
  └─ userId (FK)
  └─ token (JWT)
  └─ expiresAt

PasswordResetTokens ✨ NEW
  └─ userId (FK)
  └─ token
  └─ expiresAt
```

---

## 🔄 Mapping Backend PHP

| Drift Table | PHP Table | Note |
|-------------|-----------|------|
| `UsersGefarm` | `gefarm_users` | ✅ Allineati |
| `Devices` | `gefarm_devices` | ✅ Allineati |
| `UserDevices` | `gefarm_user_devices` | ✅ Allineati |
| `DeviceMeterData` | `gefarm_device_meter_data` | ✅ Allineati |
| `UserSessions` | `gefarm_user_sessions` | ✅ Allineati |
| `PasswordResetTokens` | `gefarm_password_reset_tokens` | ✅ Allineati |

### ⚠️ Mapping Speciali

**Device Identifier:**
- Drift: `Devices.serialNumber` (es: "EMC-001-123456")
- PHP: `gefarm_devices.device_id` (VARCHAR)
- Stesso valore, nomi diversi!

**Device Type:**
- Drift: Usa enum `DeviceFamily` + tabella `DeviceTypes`
- PHP: Usa ENUM `('emcengine', 'emcinverter', 'emcbox')`
- Serve mapper nel repository

---

## 📚 Documentazione

### 🔍 Guide Dettagliate

1. **MIGRATION_GUIDE.md** - Guida completa migrazione database
   - Step-by-step integration
   - Troubleshooting
   - Schema ER
   - Best practices

2. **IMPLEMENTATION_CHECKLIST.md** - Checklist completa implementazione
   - Tutte le fasi (DTO, Mappers, API Service, Repositories, UI)
   - Stima tempi
   - Priorità
   - Testing strategy

3. **USAGE_EXAMPLES.dart** - Esempi pratici di utilizzo
   - Auth flows (register, login, password reset)
   - Device management (register, share, list)
   - Chain2 data management
   - Offline-first sync pattern
   - UI widgets examples

---

## 🎯 Prossimi Step

### Fase 1: DTO & Mappers (2-3 giorni)

Creare i Data Transfer Objects per comunicare con le API PHP:

```dart
lib/modules/gefarm/data/models/dto/
├── user_dto.dart
├── device_dto.dart
├── meter_data_dto.dart
└── session_dto.dart
```

Vedi `IMPLEMENTATION_CHECKLIST.md` → Fase 2 per dettagli.

### Fase 2: API Service (2-3 giorni)

Implementare servizio HTTP con Dio:

```dart
class GefarmApiService {
  final Dio _dio;
  
  Future<UserDTO> login(String email, String password);
  Future<UserDTO> register(...);
  Future<List<DeviceDTO>> getMyDevices();
  Future<void> shareDevice(int deviceId, int userId, String role);
  // etc.
}
```

### Fase 3: Repositories (3-4 giorni)

Implementare pattern offline-first:

```dart
class UserRepositoryImpl implements UserRepository {
  Future<User> getUser(int id) async {
    // 1. Load from local (fast)
    // 2. Sync with backend (if online)
    // 3. Return merged result
  }
}
```

### Fase 4: UI Implementation (5-7 giorni)

- Login/Register screens
- Device list with roles
- Device sharing dialog
- Chain2 form
- Password reset flow

Vedi `IMPLEMENTATION_CHECKLIST.md` per checklist completa.

---

## ⚠️ Note Importanti

### 1. Password NON Salvata Locale

Per sicurezza, `password_hash` NON viene salvata in Drift. Solo il backend PHP gestisce le password.

### 2. CF (Codice Fiscale) Security

Nel backend PHP, il CF è criptato con AES-256. In Drift locale:
- **Default**: Plain text (protetto da OS + SQLite)
- **Opzionale**: Usa SQLCipher per cifratura database

### 3. JWT Token Storage

In produzione, usa `flutter_secure_storage` per salvare token:

```dart
final storage = FlutterSecureStorage();
await storage.write(key: 'auth_token', value: token);
```

### 4. SQLite Foreign Keys

Le FK sono ABILITATE di default. Non disabilitare mai:

```dart
await customStatement('PRAGMA foreign_keys = ON');
```

---

## 🐛 Troubleshooting

### "Column user_id already exists"

**Causa**: La migration mantiene `devices.userId` per backward compatibility.

**Soluzione**: Ignora, usa `UserDevices` per associazioni.

### "Foreign key constraint failed"

**Causa**: Stai cercando di inserire un deviceId che non esiste in Devices.

**Soluzione**: Inserisci prima il device, poi l'associazione.

### "Email already exists"

**Causa**: Stai cercando di registrare un utente con email duplicata.

**Soluzione**: Controlla con `emailExists()` prima di inserire.

---

## 📞 Support

Se hai problemi:

1. Leggi `MIGRATION_GUIDE.md`
2. Controlla `USAGE_EXAMPLES.dart`
3. Verifica log console
4. Rigenera codice Drift
5. Ripristina backup se necessario

---

## 📝 Changelog

### v5.0.0 (2025-01-20) - MAJOR UPDATE

**Added:**
- ✨ UsersGefarm: email, nome, cognome, emailVerified
- ✨ Devices: nomeDispositivo, ssidAp, chain2Active, firmwareVersion
- 🆕 UserDevices table (many-to-many relationships)
- 🆕 UserSessions table (JWT persistence)
- 🆕 PasswordResetTokens table
- 🆕 DeviceMeterData table (Chain2 data)

**Changed:**
- 🔄 Device-User relationship: 1:N → N:M
- 🔄 Avatar color: Removed from UsersGefarm (kept in UserPreferences)

**Migration:**
- ⬆️ Automatic migration from v4 to v5
- ✅ Backward compatible (preserves existing data)

---

## 🏆 Credits

- **Database Design**: Allineato con backend PHP GeFarm API v2
- **Architecture**: Clean Architecture + DDD + Offline-First
- **ORM**: Drift (SQLite per Flutter)

---

## 📄 License

Questo codice è parte del progetto GeFarm ed è fornito per uso interno.

---

**🎉 Buona integrazione! Se hai domande, consulta la documentazione o contattami.**

---

## 🔗 Quick Links

- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Guida migrazione
- [IMPLEMENTATION_CHECKLIST.md](IMPLEMENTATION_CHECKLIST.md) - Checklist implementazione
- [USAGE_EXAMPLES.dart](USAGE_EXAMPLES.dart) - Esempi codice
- [Backend PHP API](https://simonaserra.altervista.org/gefarm_api_v2) - Documentazione API

**Versione**: 5.0.0  
**Data**: 20 Gennaio 2025  
**Status**: ✅ Schema Completo | 🚧 API Integration TODO
