<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$defaults = [
    'business_name' => 'PaddleYard',
    'business_phone' => '+63 912 345 6789',
    'business_email' => 'hello@paddleyard.ph',
    'reservation_notice_hours' => '24',
    'cancellation_cutoff_hours' => '12',
    'reschedule_cutoff_hours' => '24',
    'rental_deposit' => '150.00',
    'maintenance_mode' => 'off',
    'currency_symbol' => '₱',
];

$alert_message = '';
$alert_type = '';

$existing_settings = [];
$settings_query = $conn->query("SELECT setting_name, setting_value FROM system_settings");
while ($row = $settings_query->fetch_assoc()) {
    $existing_settings[$row['setting_name']] = $row['setting_value'];
}

foreach ($defaults as $name => $value) {
    if (!array_key_exists($name, $existing_settings)) {
        $insert = $conn->prepare('INSERT INTO system_settings (account_id, setting_name, setting_value) VALUES (?, ?, ?)');
        $insert->bind_param('iss', $_SESSION['account_id'], $name, $value);
        $insert->execute();
        $insert->close();
        $existing_settings[$name] = $value;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        foreach ($defaults as $name => $default_value) {
            $value = trim($_POST[$name] ?? $default_value);
            $upsert = $conn->prepare('INSERT INTO system_settings (account_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), account_id = VALUES(account_id), date_updated = CURRENT_TIMESTAMP');
            $upsert->bind_param('iss', $_SESSION['account_id'], $name, $value);
            $upsert->execute();
            $upsert->close();
            $existing_settings[$name] = $value;
        }

        $alert_message = 'System settings updated successfully.';
        $alert_type = 'success';
    }

    if ($action === 'reset_defaults') {
        $delete = $conn->prepare('DELETE FROM system_settings WHERE account_id = ?');
        $delete->bind_param('i', $_SESSION['account_id']);
        $delete->execute();
        $delete->close();

        foreach ($defaults as $name => $value) {
            $insert = $conn->prepare('INSERT INTO system_settings (account_id, setting_name, setting_value) VALUES (?, ?, ?)');
            $insert->bind_param('iss', $_SESSION['account_id'], $name, $value);
            $insert->execute();
            $insert->close();
            $existing_settings[$name] = $value;
        }

        $alert_message = 'Settings were reset to the default configuration.';
        $alert_type = 'success';
    }
}

$settings = array_merge($defaults, $existing_settings);
$recent_updates = $conn->query('SELECT setting_name, setting_value, date_updated FROM system_settings ORDER BY date_updated DESC LIMIT 8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | PaddleYard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <a href="admin-landing-page.php" class="sidebar-logo">
                <img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
                <span>PaddleYard</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <a href="admin-landing-page.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg><span>Dashboard</span></a>
            <a href="reservation_setup.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span>Reservations</span></a>
            <a href="admin_payment.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"></path><line x1="2" y1="12" x2="22" y2="12"></line><circle cx="7" cy="15" r="1"></circle></svg><span>Payments</span></a>
            <a href="court_setup.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="12" y1="3" x2="12" y2="21"></line></svg><span>Courts</span></a>
            <a href="paddle_rentals.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"></path><path d="M5 8h14"></path><path d="M5 16h14"></path><path d="M7 3h10"></path></svg><span>Paddle Rentals</span></a>
            <a href="admin_cancellation_reschedule.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"></path><polyline points="21 3 21 8 16 8"></polyline><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"></path><polyline points="3 21 3 16 8 16"></polyline></svg><span>Cancellation &amp; Reschedule</span></a>
            <a href="reports.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span>Reports</span></a>
            <a href="system_settings.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H4.4v-2.6h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1.9-1.5V4.4h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z"></path></svg><span>System Settings</span></a>
        </nav>

        <div class="sidebar-footer">
            <div class="profile-section">
                <div class="profile-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="profile-info">
                    <p class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></p>
                    <p class="profile-role">Administrator</p>
                </div>
            </div>
            <form action="../server-side/logout.php" method="post" class="logout-form">
                <input type="hidden" name="logout" value="1">
                <button type="submit" class="logout-btn" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4"></path><polyline points="17 16 21 12 17 8"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-content">
        <main class="dashboard-container admin-management-page">
            <section class="intro">
                <p class="eyebrow">Admin</p>
                <h1>System Settings</h1>
                <p>Configure the core operating preferences for booking, cancellations, and customer-facing workflows.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Business</p>
                        <h2>General Settings</h2>
                    </section>
                </div>

                <form method="post" action="system_settings.php" class="settings-form">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="business_name">Business Name</label>
                            <input id="business_name" type="text" name="business_name" value="<?php echo htmlspecialchars($settings['business_name']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="business_phone">Phone</label>
                            <input id="business_phone" type="text" name="business_phone" value="<?php echo htmlspecialchars($settings['business_phone']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="business_email">Email</label>
                            <input id="business_email" type="email" name="business_email" value="<?php echo htmlspecialchars($settings['business_email']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="currency_symbol">Currency</label>
                            <input id="currency_symbol" type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="reservation_notice_hours">Reservation Notice (hours)</label>
                            <input id="reservation_notice_hours" type="number" min="1" step="1" name="reservation_notice_hours" value="<?php echo htmlspecialchars($settings['reservation_notice_hours']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="cancellation_cutoff_hours">Cancellation Cutoff (hours)</label>
                            <input id="cancellation_cutoff_hours" type="number" min="0" step="1" name="cancellation_cutoff_hours" value="<?php echo htmlspecialchars($settings['cancellation_cutoff_hours']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="reschedule_cutoff_hours">Reschedule Cutoff (hours)</label>
                            <input id="reschedule_cutoff_hours" type="number" min="0" step="1" name="reschedule_cutoff_hours" value="<?php echo htmlspecialchars($settings['reschedule_cutoff_hours']); ?>">
                        </div>
                        <div class="form-field">
                            <label for="rental_deposit">Rental Deposit</label>
                            <input id="rental_deposit" type="number" min="0" step="0.01" name="rental_deposit" value="<?php echo htmlspecialchars($settings['rental_deposit']); ?>">
                        </div>
                        <div class="form-field full-width">
                            <label for="maintenance_mode">Maintenance Mode</label>
                            <select id="maintenance_mode" name="maintenance_mode">
                                <option value="off" <?php echo $settings['maintenance_mode'] === 'off' ? 'selected' : ''; ?>>Off</option>
                                <option value="on" <?php echo $settings['maintenance_mode'] === 'on' ? 'selected' : ''; ?>>On</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">Save Settings</button>
                        <button type="submit" class="btn btn-outline" name="action" value="reset_defaults">Reset Defaults</button>
                    </div>
                </form>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Snapshot</p>
                        <h2>Recent Updates</h2>
                    </section>
                </div>

                <?php if ($recent_updates && $recent_updates->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_updates->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($row['setting_name']))); ?></td>
                                    <td><?php echo htmlspecialchars($row['setting_value']); ?></td>
                                    <td><?php echo htmlspecialchars($row['date_updated']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No settings have been updated yet.</div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        const currentPage = window.location.pathname.split('/').pop() || 'admin-landing-page.php';
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            item.classList.toggle('active', href === currentPage);
        });
    </script>
</body>
</html>
