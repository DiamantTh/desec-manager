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
                    <?= __('API Keys') ?>
                </p>
            </header>
            <div class="card-content">
                <?php if ($apiKeys): ?>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th><?= __('Name') ?></th>
                                <th><?= __('Status') ?></th>
                                <th><?= __('Created') ?></th>
                                <th class="has-text-right"><?= __('Actions') ?></th>
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
                                    <td class="has-text-right">
                                        <?php if (!empty($key['is_active'])): ?>
                                            <form method="post" class="is-inline">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                                                <button type="submit" class="button is-warning is-small">
                                                    <?= __('Deactivate') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey"><?= __('No API keys found.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title"><?= __('Add new API key') ?></p>
            </header>
            <div class="card-content">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="field">
                        <label class="label" for="name"><?= __('Label') ?></label>
                        <div class="control">
                            <input id="name" name="name" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="api_key">API Key</label>
                        <div class="control">
                            <textarea id="api_key" name="api_key" class="textarea" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary is-fullwidth">
                                <?= __('Save') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
