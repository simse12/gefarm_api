
-- QUERY UTILI
-- OTTIENI TUTTO PER UTENTE
SELECT 
    u.id as user_id,
    u.nome as user_nome,
    u.email as user_email,
    
    d.device_id,
    d.device_type,
    d.ssid_ap,
    d.du,
    d.k1,
    d.k2,
    
    ud.role,
    ud.nickname,
    
    m.cf,
    m.nome as intestatario_nome,
    m.cognome as intestatario_cognome,
    m.email as intestatario_email,
    m.indirizzo,
    m.pod,
    m.is_user_owner
    
FROM gefarm_users u
JOIN gefarm_user_devices ud ON u.id = ud.user_id
JOIN gefarm_devices d ON ud.device_id = d.id
LEFT JOIN gefarm_device_meter_data m ON d.id = m.device_id AND m.is_active = 1
WHERE u.id = ?;

-- VERIFICA SE DEVICE GIà ASSOCIATO
SELECT COUNT(*) 
FROM gefarm_user_devices 
WHERE device_id = (SELECT id FROM gefarm_devices WHERE device_id = device_id;);

-- OTTIENI SSID E PASSWORD PER CONNESSIONE
SELECT ssid_ap, ssid_password 
FROM gefarm_devices 
WHERE device_id = device_id;
