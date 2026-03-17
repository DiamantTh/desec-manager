<?php if (!$user): ?>
    <div class="notification is-danger">Benutzerdaten konnten nicht geladen werden.</div>
<?php else: ?>
    <div class="box">
        <h2 class="title is-4">Profil</h2>
        <div class="columns">
            <div class="column is-half">
                <p class="has-text-weight-semibold">Benutzername</p>
                <p><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4">E-Mail</p>
                <p><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="column is-half">
                <p class="has-text-weight-semibold">Erstellt am</p>
                <p><?= htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4">Letzter Login</p>
                <p><?= htmlspecialchars($user['last_login'] ?? 'Noch nie', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
