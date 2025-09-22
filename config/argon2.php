<?php
/**
 * Argon2id Konfiguration
 * Empfohlene Werte basierend auf OWASP Password Storage Cheat Sheet
 */
return [
    'algo' => PASSWORD_ARGON2ID,
    'options' => [
        'memory_cost' => 65536,    // 64MB (minimum für sensitive Daten)
        'time_cost' => 4,          // 4 Iterationen
        'threads' => 2             // 2 Threads
    ]
];
