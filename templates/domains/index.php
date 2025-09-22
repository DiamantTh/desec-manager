<?php /** @var array $domains */ ?>
<?php if ($message): ?>
    <div class="notification <?= $messageType ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="columns">
    <div class="column is-two-thirds">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    <span class="icon"><i class="fas fa-globe"></i></span>
                    <span class="ml-2">Domains</span>
                </p>
            </header>
            <div class="card-content">
                <?php if ($domains): ?>
                    <table class="table is-striped is-fullwidth" id="domain-list">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Erstellt</th>
                                <th class="has-text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                                <tr>
                                    <td><?= htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($domain['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="has-text-right">
                                        <?php if ($apiKeys): ?>
                                            <form method="post" class="is-inline-block">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="domain" value="<?= htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="field has-addons is-justify-content-flex-end">
                                                    <div class="control">
                                                        <div class="select is-small">
                                                            <select name="api_key_id" required>
                                                                <option value="">API-Key</option>
                                                                <?php foreach ($apiKeys as $key): ?>
                                                                    <option value="<?= (int) $key['id'] ?>">
                                                                        <?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="control">
                                                        <button type="submit" class="button is-danger is-small">
                                                            <span class="icon"><i class="fas fa-trash"></i></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span class="tag is-light">Keine API-Keys</span>
                                        <?php endif; ?>
                                    </td>
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
        <div class="card mb-5">
            <header class="card-header">
                <p class="card-header-title">Domain hinzufügen</p>
            </header>
            <div class="card-content">
                <?php if ($apiKeys): ?>
                    <form method="post" id="add-domain-form">
                        <input type="hidden" name="action" value="add">
                        <div class="field">
                            <label class="label" for="domain">Domain</label>
                            <div class="control">
                                <input class="input" id="domain" name="domain" placeholder="example.com" required>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="api_key_id">API Key</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="api_key_id" name="api_key_id" required>
                                        <option value="">Bitte wählen</option>
                                        <?php foreach ($apiKeys as $key): ?>
                                            <option value="<?= (int) $key['id'] ?>">
                                                <?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <button type="submit" class="button is-primary is-fullwidth">
                                    <span class="icon"><i class="fas fa-plus"></i></span>
                                    <span>Domain hinzufügen</span>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="has-text-grey">Es werden zuerst API Keys benötigt.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">Mit deSEC synchronisieren</p>
            </header>
            <div class="card-content">
                <?php if ($apiKeys): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="sync">
                        <div class="field">
                            <label class="label" for="sync-api-key">API Key</label>
                            <div class="select is-fullwidth">
                                <select id="sync-api-key" name="api_key_id" required>
                                    <option value="">Bitte wählen</option>
                                    <?php foreach ($apiKeys as $key): ?>
                                        <option value="<?= (int) $key['id'] ?>">
                                            <?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <button type="submit" class="button is-link is-fullwidth">
                                <span class="icon"><i class="fas fa-sync-alt"></i></span>
                                <span>Synchronisieren</span>
                            </button>
                        </div>
                        <p class="help">Holt Domains vom deSEC-Account und legt fehlende Einträge lokal an.</p>
                    </form>
                <?php else: ?>
                    <p class="has-text-grey">Es werden API Keys benötigt.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
