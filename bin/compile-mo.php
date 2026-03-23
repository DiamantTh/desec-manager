<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\PoToMoCompiler;

$localeDir = __DIR__ . '/../locale';
$locales = ['en_US', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pl_PL', 'nl_NL', 'pt_PT', 'cs_CZ', 'ro_RO', 'hu_HU', 'sv_SE'];

echo "Compiling .po files to .mo format...\n\n";

foreach ($locales as $locale) {
    $poFile = $localeDir . '/' . $locale . '/LC_MESSAGES/desec-manager.po';
    $moFile = $localeDir . '/' . $locale . '/LC_MESSAGES/desec-manager.mo';
    
    if (!file_exists($poFile)) {
        echo "⚠ Skipping {$locale}: .po file not found\n";
        continue;
    }
    
    try {
        if (PoToMoCompiler::compile($poFile, $moFile)) {
            echo "✓ Compiled {$locale}\n";
        } else {
            echo "✗ Failed to compile {$locale}\n";
        }
    } catch (Exception $e) {
        echo "✗ Error compiling {$locale}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
