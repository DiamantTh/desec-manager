<?php if (!empty($cacheStatus)): ?>
    <div class="columns">
        <?php foreach (['opcache' => 'OPcache', 'apcu' => 'APCu'] as $key => $label): ?>
            <?php $status = $cacheStatus[$key] ?? null; ?>
            <?php if (!$status) continue; ?>
            <div class="column">
                <?php $hasIssue = !empty($status['message']); ?>
                <article class="message <?= $hasIssue ? 'is-warning' : 'is-success' ?>">
                    <div class="message-header">
                        <p><?= $label ?></p>
                    </div>
                    <div class="message-body">
                        <?php if ($hasIssue): ?>
                            <p><?= htmlspecialchars($status['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p><?= $label ?> ist aktiv<?= !empty($status['jit']) ? ' (JIT eingeschaltet)' : '' ?>.</p>
                        <?php endif; ?>
                        <p class="is-size-7 has-text-grey mt-2">
                            SAPI: <?= htmlspecialchars($status['details']['sapi'] ?? '', ENT_QUOTES, 'UTF-8') ?> ·
                            <?= htmlspecialchars($status['details']['config_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>=
                            <?= htmlspecialchars((string)($status['details']['config_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="columns">
    <div class="column">
        <div class="box has-text-centered">
            <p class="heading">Domains</p>
            <p class="title"><?= (int) $stats['domains'] ?></p>
        </div>
    </div>
    <div class="column">
        <div class="box has-text-centered">
            <p class="heading">API Keys</p>
            <p class="title"><?= (int) $stats['apiKeys'] ?></p>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    Letzte Domains
                </p>
            </header>
            <div class="card-content">
                <?php if ($domains): ?>
                    <table class="table is-fullwidth">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Erstellt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                                <tr>
                                    <td><?= htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($domain['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey">Noch keine Domains hinterlegt.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    Letzte API Keys
                </p>
            </header>
            <div class="card-content">
                <?php if ($apiKeys): ?>
                    <table class="table is-fullwidth">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Erstellt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apiKeys as $key): ?>
                                <tr>
                                    <td><?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="tag <?= !empty($key['is_active']) ? 'is-success' : 'is-light' ?>">
                                            <?= !empty($key['is_active']) ? 'aktiv' : 'deaktiviert' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($key['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey">Noch keine API Keys hinterlegt.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
