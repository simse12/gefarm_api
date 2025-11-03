<?php
header('Content-Type: text/plain; charset=utf-8');

// Controlla se OPcache è abilitato
if (function_exists('opcache_reset')) {
    
    // Prova a svuotare la cache
    if (opcache_reset()) {
        echo "SUCCESS:\n";
        echo "La cache OPcache è stata svuotata con successo.\n\n";
        echo "Il server ora leggerà le versioni più recenti dei tuoi file PHP.\n";
        echo "Timestamp: " . date('Y-m-d H:i:s');
    } else {
        http_response_code(500);
        echo "ERROR:\n";
        echo "La funzione opcache_reset() è fallita.\n";
        echo "Controlla i permessi o le impostazioni del server.";
    }
} else {
    http_response_code(404);
    echo "WARNING:\n";
    echo "La funzione opcache_reset() non esiste.\n";
    echo "OPcache potrebbe non essere installato o non essere abilitato sul tuo server Altervista.\n";
    echo "In questo caso, i file dovrebbero aggiornarsi da soli dopo qualche minuto.";
}
?>

