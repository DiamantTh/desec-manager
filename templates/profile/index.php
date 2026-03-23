<?php if (!$user): ?>
    <div class="notification is-danger"><?= __('User data could not be loaded.') ?></div>
<?php else: ?>
    <div class="box">
        <h2 class="title is-4"><?= __('Profile') ?></h2>
        <div class="columns">
            <div class="column is-half">
                <p class="has-text-weight-semibold"><?= __('Username') ?></p>
                <p><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4"><?= __('Email') ?></p>
                <p><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="column is-half">
                <p class="has-text-weight-semibold"><?= __('Created at') ?></p>
                <p><?= htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4"><?= __('Last Login') ?></p>
                <p><?= htmlspecialchars($user['last_login'] ?? __('Never'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
