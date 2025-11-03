# 🚀 Gefarm Database Migration v4 → v5

## 📋 Sommario Modifiche

### ✅ **Tabelle Aggiornate**
- `UsersGefarm` → Aggiunti campi per sincronizzazione backend (email, nome, cognome, etc.)
- `Devices` → Aggiunti campi backend (nomeDispositivo, ssidAp, chain2Active, firmwareVersion, etc.)

### 🆕 **Nuove Tabelle**
- `UserDevices` → Junction table per relazione many-to-many (famiglia/azienda)
- `UserSessions` → Gestione JWT token persistenti
- `PasswordResetTokens` → Flusso reset password
- `DeviceMeterData` → Dati contatore Chain2

### 🔄 **Modifiche Architetturali**
- **Device Ownership**: Da 1:N a N:M (un device può avere più utenti)
- **Avatar Color**: Rimosso da UsersGefarm, mantenuto solo in UserPreferences
- **User Authentication**: Ora supporta email, nome, cognome (necessari per backend)

---

## 📁 Struttura File Generati

```
gefarm_updated/
├── tables/
│   ├── users_gefarm.dart          ✅ UPDATED
│   ├── device.dart                 ✅ UPDATED
│   ├── user_devices.dart           🆕 NEW
│   ├── user_sessions.dart          🆕 NEW
│   ├── password_reset_tokens.dart  🆕 NEW
│   └── device_meter_data.dart      🆕 NEW
│
├── dao/
│   ├── user_devices_dao.dart           🆕 NEW
│   ├── user_sessions_dao.dart          🆕 NEW
│   ├── password_reset_tokens_dao.dart  🆕 NEW
│   └── device_meter_data_dao.dart      🆕 NEW
│
└── app_database.dart               ✅ UPDATED (schema v5)
```

---

## 🔧 Come Integrare nel Progetto

### **Passo 1: Backup Database Esistente**

Prima di procedere, è FONDAMENTALE fare backup:

```dart
// In un file di utilità
Future<void> backupDatabase() async {
  final dir = await getApplicationDocumentsDirectory();
  final dbFile = File(path.join(dir.path, 'gefarm.db'));
  
  if (await dbFile.exists()) {
    final backupFile = File(path.join(dir.path, 'gefarm_v4_backup.db'));
    await dbFile.copy(backupFile.path);
    print('✅ Backup creato: ${backupFile.path}');
  }
}
```

### **Passo 2: Copia File nel Progetto**

1. Sostituisci i file nella tua struttura:
   ```
   lib/modules/gefarm/data/data_source/localdb/
   ├── tables/          → Copia qui le tabelle aggiornate
   ├── dao/             → Copia qui i DAO nuovi
   └── app_database.dart → Sostituisci questo file
   ```

2. **IMPORTANTE**: Mantieni i file esistenti che non ho modificato:
   - `device_families.dart` ✅ Nessuna modifica
   - `device_types.dart` ✅ Nessuna modifica
   - `user_preferences.dart` ✅ Nessuna modifica
   - `user_device_context.dart` ✅ Nessuna modifica
   - `daily_energy.dart` ✅ Nessuna modifica
   - `monthly_energy.dart` ✅ Nessuna modifica
   - `quarter_hourly_energy.dart` ✅ Nessuna modifica

### **Passo 3: Rigenera Codice Drift**

Esegui il build runner:

```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

Se ci sono errori, verifica:
- Import path corretti
- Tutti i file .dart presenti
- Nessun typo nei nomi delle tabelle

### **Passo 4: Aggiorna Dependency Injection**

Se usi GetIt o similar, registra i nuovi DAO:

```dart
// Di module / Service Locator
final db = AppDatabase();

// Nuovi DAO da registrare
getIt.registerSingleton<UserDevicesDao>(db.userDevicesDao);
getIt.registerSingleton<UserSessionsDao>(db.userSessionsDao);
getIt.registerSingleton<PasswordResetTokensDao>(db.passwordResetTokensDao);
getIt.registerSingleton<DeviceMeterDataDao>(db.deviceMeterDataDao);
```

### **Passo 5: Test Migration**

Prima del deploy, testa la migration:

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // 1. Apri database esistente v4
  final db = AppDatabase();
  
  // 2. La migration v4→v5 parte automaticamente
  await db.usersGefarmDao.getAllUsers();
  
  // 3. Verifica nuove tabelle
  final sessions = await db.userSessionsDao.getActiveSessions(1);
  print('Sessions: ${sessions.length}');
  
  // 4. Test UserDevices
  await db.userDevicesDao.associateDeviceToUser(
    userId: 1,
    deviceId: 1,
    role: 'owner',
  );
  
  print('✅ Migration test passed!');
}
```

---

## ⚠️ Problemi Noti e Soluzioni

### **1. "Column user_id already exists"**

Se la migration fallisce su devices.userId:
- La colonna viene mantenuta per backward compatibility
- È deprecata ma non eliminata (SQLite < 3.35 non supporta DROP COLUMN)
- Ignora la colonna, usa UserDevices per le associazioni

### **2. "Foreign key constraint failed"**

Se hai devices con userId NULL:
- La migration li migra automaticamente a UserDevices
- Se fallisce, controlla che gli userId esistano in users_gefarm

### **3. Avatar color duplicato**

Se hai utenti con avatarColor sia in UsersGefarm che in UserPreferences:
- UsersGefarm.avatarColor viene droppato
- Mantieni solo UserPreferences.avatarColor (TEXT hex)

---

## 🔄 Sincronizzazione con Backend PHP

### **Mapping Campi**

| Drift (locale) | PHP Backend | Note |
|----------------|-------------|------|
| `UsersGefarm.userId` | `gefarm_users.id` | ✅ |
| `UsersGefarm.email` | `gefarm_users.email` | ✅ |
| `Devices.serialNumber` | `gefarm_devices.device_id` | ⚠️ Nome diverso! |
| `Devices.deviceId` | `gefarm_devices.id` | ✅ |
| `UserDevices.*` | `gefarm_user_devices.*` | ✅ |

### **Device Type Mapping**

Backend PHP usa ENUM:
```php
device_type ENUM('emcengine', 'emcinverter', 'emcbox')
```

Flutter usa DeviceFamily + DeviceType:
```dart
// Mapping suggerito:
DeviceFamily.emc + DeviceType('EMC001') → 'emcengine'
DeviceFamily.emc + DeviceType('EMC002') → 'emcinverter'
DeviceFamily.emc + DeviceType('EMC003') → 'emcbox'
```

**Soluzione**: Aggiungi campo `backendType` a DeviceTypes o usa mapper nel repository.

---

## 📊 Schema ER Aggiornato

```
┌─────────────────┐
│  UsersGefarm    │
│  - userId (PK)  │  1:N
│  - email*       │◄─────┐
│  - nome*        │      │
│  - cognome*     │      │
└─────────────────┘      │
                         │
                   ┌─────┴──────────┐
                   │  UserDevices   │  N:M Junction
                   │  - userId (FK) │
                   │  - deviceId(FK)│
                   │  - role        │
                   │  - nickname    │
                   └─────┬──────────┘
                         │
                         │ N:1
┌─────────────────┐      │
│  Devices        │◄─────┘
│  - deviceId(PK) │  1:N
│  - serialNumber │◄─────┐
│  - deviceTypeId │      │
│  - nomeDisp*    │      │
└─────────────────┘      │
                         │
                ┌────────┴────────────┐
                │ DeviceMeterData     │
                │ - deviceId (FK)     │
                │ - cf (encrypted)    │
                │ - nome, cognome     │
                │ - indirizzo, pod    │
                └─────────────────────┘

* = Nuovi campi aggiunti in v5
```

---

## 🎯 Prossimi Passi

1. **✅ FATTO**: Schema Drift aggiornato
2. **TODO**: Creare DTO models per API
3. **TODO**: Creare Mappers (Drift ↔ DTO ↔ Domain)
4. **TODO**: Implementare API Service (Dio + endpoints PHP)
5. **TODO**: Implementare Repositories con sincronizzazione
6. **TODO**: Implementare flussi autenticazione (login, register, reset password)
7. **TODO**: UI per gestione dispositivi condivisi

---

## 💡 Best Practices

### **Sincronizzazione Offline-First**

```dart
// Pattern consigliato nei Repository
Future<User> getUser(int userId) async {
  // 1. Carica da locale (veloce)
  final local = await _localDao.getUserById(userId);
  
  // 2. Se online, sync con backend
  if (await _connectivity.isOnline) {
    try {
      final remote = await _apiService.getProfile();
      await _localDao.upsertUser(remote.toDrift());
      return remote;
    } catch (e) {
      // Fallback su locale se API fallisce
      return local;
    }
  }
  
  return local;
}
```

### **Gestione Sessioni**

```dart
// All'avvio app
Future<bool> restoreSession() async {
  final session = await _sessionsDao.getActiveSession(currentUserId);
  
  if (session != null && session.expiresAt.isAfter(DateTime.now())) {
    // Token ancora valido
    await _apiService.setAuthToken(session.token);
    return true;
  }
  
  // Token scaduto → logout
  return false;
}
```

### **Chain2 Data Security**

```dart
// NON salvare CF in plain text nei log!
void logMeterData(DeviceMeterDataEntry data) {
  print('Meter Data: ${data.nome} ${data.cognome}');
  // ❌ print('CF: ${data.cf}'); // MAI fare questo!
  print('CF: ${data.cf.substring(0, 4)}****'); // ✅ Masked
}
```

---

## 🆘 Support

Se hai problemi durante la migrazione:

1. Verifica log console per errori specifici
2. Controlla che tutti gli import siano corretti
3. Rigenera codice Drift
4. Se tutto fallisce, ripristina backup e contattami

---

## 📝 Changelog

### v5 (2025-01-20)
- ✅ Aggiornata UsersGefarm con campi backend
- ✅ Aggiornata Devices con campi backend
- 🆕 Aggiunta UserDevices (many-to-many)
- 🆕 Aggiunta UserSessions (JWT)
- 🆕 Aggiunta PasswordResetTokens
- 🆕 Aggiunta DeviceMeterData (Chain2)
- 🔧 Migration automatica da v4 a v5

### v4 (2024-XX-XX)
- Aggiunta DeviceFamilies, DeviceTypes
- Aggiunta UserPreferences, UserDeviceContext

---

**🎉 Buona migrazione!**
