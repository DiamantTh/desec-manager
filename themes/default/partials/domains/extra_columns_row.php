<?php
/**
 * Partial: domains/extra_columns_row
 *
 * Extension-Point – wird pro Domain-Tabellenzeile aufgerufen.
 * Einfach in deinem eigenen Theme unter
 *   themes/meinTheme/partials/domains/extra_columns_row.php
 * anlegen.
 *
 * Verfügbare Variablen:
 *   $domain  – aktueller Daten-Array der Zeile (domain_name, created_at, …)
 *   $theme   – ThemeManager-Instanz
 *
 * Beispiel (Custom-Theme):
 *   <td><?= htmlspecialchars($domain['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
 */
?>
