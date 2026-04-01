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
                            <p><?= $label ?> <?= __('is active') ?><?= !empty($status['jit']) ? ' (' . __('JIT enabled') . ')' : '' ?>.</p>
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
                    <?= __('Recent Domains') ?>
                </p>
            </header>
            <div class="card-content">
                <?php if ($domains): ?>
                    <table class="table is-fullwidth">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th><?= __('Created') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(domain_to_unicode($domain['domain_name']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (str_contains($domain['domain_name'], 'xn--')): ?>
                                            <span class="has-text-grey is-size-7">(<?= htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($domain['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey"><?= __('No domains registered yet.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    <?= __('Recent API Keys') ?>
                </p>
            </header>
            <div class="card-content">
                <?php if ($apiKeys): ?>
                    <table class="table is-fullwidth">
                        <thead>
                            <tr>
                                <th><?= __('Name') ?></th>
                                <th><?= __('Status') ?></th>
                                <th><?= __('Created') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apiKeys as $key): ?>
                                <tr>
                                    <td><?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="tag <?= !empty($key['is_active']) ? 'is-success' : 'is-light' ?>">
                                            <?= !empty($key['is_active']) ? __('Active') : __('Inactive') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($key['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey"><?= __('No API keys added yet.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
