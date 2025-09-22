<?php
declare(strict_types=1);

if (!function_exists('phpinfo')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'phpinfo() is not available on this system.';
    exit;
}

ob_start();
phpinfo();
$raw = ob_get_clean() ?: '';

$body = preg_replace('#^.*<body[^>]*>#is', '', $raw, 1);
$body = preg_replace('#</body>.*$#is', '', $body, 1);
$body = preg_replace('#<style.*?</style>#is', '', $body);
$body = preg_replace('#<script.*?</script>#is', '', $body);
$body = preg_replace('#style="[^"]*"#i', '', $body);
$body = preg_replace('#class="p"#i', '', $body);
$body = str_replace('<hr />', '', $body);
$body = preg_replace('#<h1.*?</h1>#is', '', $body);
$body = preg_replace('#<img[^>]*>#i', '', $body);
$body = preg_replace('#<table[^>]*>#i', '<table class="phpinfo-table">', $body);
$body = preg_replace('#<tr class="h">#i', '<tr>', $body);
$body = preg_replace('#<td class="e">#i', '<td>', $body);
$body = preg_replace('#<td class="v">#i', '<td>', $body);
$body = preg_replace('#<td class="v i">#i', '<td>', $body);
$body = preg_replace('#<td class="e i">#i', '<td>', $body);

$version = PHP_VERSION;
$sapi = PHP_SAPI;
$zendVersion = zend_version();

$sections = preg_split('#(<h2[^>]*>.*?</h2>)#is', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
$cards = [];
$currentTitle = 'Allgemein';
$currentContent = '';

foreach ($sections as $section) {
    if (trim($section) === '') {
        continue;
    }
    if (preg_match('#<h2[^>]*>(.*?)</h2>#is', $section, $match)) {
        if (trim($currentContent) !== '') {
            $cards[] = ['title' => $currentTitle, 'content' => $currentContent];
        }
        $currentTitle = trim(strip_tags($match[1]));
        $currentContent = '';
    } else {
        $currentContent .= $section;
    }
}

if (trim($currentContent) !== '') {
    $cards[] = ['title' => $currentTitle, 'content' => $currentContent];
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Info – <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/phpinfo.css">
</head>
<body>
    <main class="phpinfo-shell">
        <header class="phpinfo-header">
            <div class="header-main">
                <div class="header-title">
                    <h1>PHP Info</h1>
                    <p class="meta">Status vom <?= htmlspecialchars(gmdate('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') ?> UTC</p>
                </div>
                <div class="header-tags">
                    <span class="pill">PHP <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="pill">SAPI <?= htmlspecialchars($sapi, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="pill">Zend <?= htmlspecialchars($zendVersion, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </header>

        <section class="phpinfo-body">
            <?php foreach ($cards as $card): ?>
                <article class="phpinfo-section">
                    <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="table-wrapper">
                        <?= $card['content'] ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
