<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/weather.php';

$otpMode = getAppSetting($pdo, 'otp_mode', 'off');
$weatherMode = getAppSetting($pdo, 'weather_mode', 'manual');
$manualWeather = getManualWeatherCondition($pdo);
$effectiveWeather = getEffectiveWeatherCondition($pdo);
$checkedAt = (int) getAppSetting($pdo, 'weather_auto_checked', '0');

require_once __DIR__ . '/../../includes/header.php';

function selected(string $current, string $option): string
{
    return $current === $option ? ' selected' : '';
}
?>

<div class="page-container">

    <div class="page-header">
        <h1>Platform Settings</h1>
        <p>
            Weather affects the priority score of active issues.
            Changing it re-scores them immediately.
        </p>
    </div>

    <div class="dashboard-section">
        <div class="dashboard-card">

            <p>
                Weather currently used for scoring:
                <strong><?= htmlspecialchars($effectiveWeather) ?></strong>
                <?php if ($weatherMode === 'auto' && $checkedAt > 0): ?>
                    (live data, checked
                    <?= htmlspecialchars(date('d M Y, H:i', $checkedAt)) ?>)
                <?php endif; ?>
            </p>

            <form id="settings-form">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="weather_mode">Weather source</label>
                    <select id="weather_mode" name="weather_mode">
                        <option value="manual"<?= selected($weatherMode, 'manual') ?>>
                            Manual (set below)
                        </option>
                        <option value="auto"<?= selected($weatherMode, 'auto') ?>>
                            Automatic (live weather for Delhi)
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="weather_condition">
                        Manual weather. Used only when the source is Manual,
                        or as the fallback if live data is unavailable.
                    </label>
                    <select id="weather_condition" name="weather_condition">
                        <option value="None"<?= selected($manualWeather, 'None') ?>>None</option>
                        <option value="Rain"<?= selected($manualWeather, 'Rain') ?>>Rain</option>
                        <option value="Snow"<?= selected($manualWeather, 'Snow') ?>>Snow</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="otp_mode">Citizen OTP</label>
                    <select id="otp_mode" name="otp_mode">
                        <option value="demo"<?= selected($otpMode, 'demo') ?>>
                            Demo (code shown on screen)
                        </option>
                        <option value="off"<?= selected($otpMode, 'off') ?>>
                            Off (citizen login disabled)
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save settings</button>
                <p id="settings-message" class="form-message"></p>
            </form>

            <!-- Navigation -->

            <div class="dashboard-section">

                <a
                    href="dashboard.php"
                    class="btn btn-secondary"
                >
                    Back to Dashboard
                </a>

            </div>

            <div class="dashboard-section">
                <div class="dashboard-card">
                    <h3>Database backup</h3>
                    <p>
                        Download a full copy of the database as an .sql file.
                        Keep it private: it contains phone numbers and password hashes.
                    </p>
                    <form method="post" action="../../api/admin/backup-database.php">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-secondary">
                            Download database backup
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.getElementById('settings-form').addEventListener('submit', async function (event) {
    event.preventDefault();

    const message = document.getElementById('settings-message');
    message.textContent = 'Saving...';

    try {
        const response = await fetch('../../api/admin/update-settings.php', {
            method: 'POST',
            body: new FormData(event.target)
        });
        const data = await response.json();

        message.textContent = data.success
            ? data.message + ' Weather in use: ' + data.effective_weather
                + (data.recalculated_issues
                    ? '. Re-scored ' + data.recalculated_issues + ' issue(s).'
                    : '.')
            : (data.message || 'Could not save settings.');
    } catch (error) {
        message.textContent = 'Something went wrong. Please try again.';
    }
});
</script>

<script>
    const themeToggle = document.getElementById('theme-toggle');

    if (themeToggle) {
        themeToggle.checked =
            localStorage.getItem('infrafix-theme') === 'dark';

        themeToggle.addEventListener('change', () => {
            if (themeToggle.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('infrafix-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('infrafix-theme', 'light');
            }
        });
    }
</script>
