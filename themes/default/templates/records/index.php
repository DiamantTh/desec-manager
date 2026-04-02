<?php if ($message): ?>
    <div class="notification <?= $messageType ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!$domains): ?>
    <div class="notification is-info">
        <strong><?= __('No domains available.') ?></strong>
        <p><?= __('Please add a domain before managing DNS records.') ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (!$apiKeys): ?>
    <div class="notification is-warning">
        <strong><?= __('No API keys configured.') ?></strong>
        <p><?= __('Please save at least one deSEC API key to manage DNS records.') ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<form method="get" class="box mb-5">
    <input type="hidden" name="route" value="records">
    <div class="columns is-multiline">
        <div class="column is-5">
            <div class="field">
                <label class="label" for="domain">Domain</label>
                <div class="control">
                    <div class="select is-fullwidth">
                        <select name="domain" id="domain">
                            <?php foreach ($domains as $domain): ?>
                                <?php $name = $domain['domain_name']; ?>
                                <option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" <?= $name === $selectedDomain ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(domain_to_unicode($name), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="column is-5">
            <div class="field">
                <label class="label" for="api_key">API-Key</label>
                <div class="control">
                    <div class="select is-fullwidth">
                        <select name="api_key" id="api_key">
                            <?php foreach ($apiKeys as $key): ?>
                                <option value="<?= (int) $key['id'] ?>" <?= (int) $key['id'] === $selectedKeyId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="column is-2">
            <div class="field">
                <label class="label">&nbsp;</label>
                <div class="control">
                    <button type="submit" class="button is-primary is-fullwidth">
                        <?= __('Update') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if (!$selectedDomain || !$selectedKeyId): ?>
    <div class="notification is-info">
        <p><?= __('Please select a domain and an API key.') ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="columns">
    <div class="column is-two-thirds">
        <?php /* Props für die Svelte Records-App */ ?>
        <script id="svelte-records-props" type="application/json">
            <?= json_encode([
                'domain'   => $selectedDomain,
                'apiKeyId' => $selectedKeyId,
            ], JSON_HEX_TAG | JSON_HEX_AMP) ?>
        </script>
        <div id="svelte-records"></div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title"><?= __('New RRset') ?></p>
            </header>
            <div class="card-content">
                <form method="post" id="add-record-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="domain" value="<?= htmlspecialchars($selectedDomain, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="api_key_id" value="<?= (int) $selectedKeyId ?>">
                    <div class="field">
                        <label class="label" for="new-subname"><?= __('Subname') ?></label>
                        <div class="control">
                            <input class="input" id="new-subname" name="subname" placeholder="<?= __('@ for root') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="new-type"><?= __('Type') ?></label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="new-type" name="type" required>
                                    <option value="A">A</option>
                                    <option value="AAAA">AAAA</option>
                                    <option value="ALIAS">ALIAS</option>
                                    <option value="CNAME">CNAME</option>
                                    <option value="TXT">TXT</option>
                                    <option value="MX">MX</option>
                                    <option value="NS">NS</option>
                                    <option value="SRV">SRV</option>
                                    <option value="CAA">CAA</option>
                                    <option value="TLSA">TLSA</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="new-records"><?= __('Records') ?></label>
                        <div class="control">
                            <textarea class="textarea" id="new-records" name="records" rows="4" placeholder="<?= __('One entry per line') ?>" required></textarea>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="new-ttl">TTL</label>
                        <div class="control">
                            <input class="input" type="number" id="new-ttl" name="ttl" value="3600" min="30" max="86400" required>
                        </div>
                    </div>
                    <div class="field">
                        <button type="submit" class="button is-primary is-fullwidth">
                            <?= __('Save RRset') ?>
                        </button>
                    </div>
                    <p class="help"><?= __('Existing RRsets will be replaced.') ?></p>
                </form>
            </div>
        </div>
    </div>
</div>
