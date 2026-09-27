<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_report') {
        $range_start = $_POST['range_start'] ?? date('Y-m-d');
        $range_end = $_POST['range_end'] ?? date('Y-m-d');
        $report_type = $_POST['report_type'] ?? 'reservation_summary';

        if ($range_start && $range_end) {
            $statement = $conn->prepare('INSERT INTO reports (account_id, report_type, range_start, range_end) VALUES (?, ?, ?, ?)');
            $statement->bind_param('isss', $_SESSION['account_id'], $report_type, $range_start, $range_end);

            if ($statement->execute()) {
                $alert_message = 'Report generated and saved.';
                $alert_type = 'success';
            } else {
                $alert_message = 'Unable to generate the report.';
                $alert_type = 'error';
            }
            $statement->close();
        }
    }
}

$range_start = $_GET['range_start'] ?? date('Y-m-d', strtotime('-30 days'));
$range_end = $_GET['range_end'] ?? date('Y-m-d');

$reservation_count = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE reservation_date BETWEEN '$range_start' AND '$range_end'")->fetch_assoc()['total'];
$payment_total = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_date BETWEEN '$range_start 00:00:00' AND '$range_end 23:59:59'")->fetch_assoc()['total'];
$paid_reservations = $conn->query("SELECT COUNT(*) AS total FROM payments p JOIN reservations r ON r.reservation_id = p.reservation_id WHERE p.payment_status = 'verified' AND r.reservation_date BETWEEN '$range_start' AND '$range_end'")->fetch_assoc()['total'];
$cancellation_count = $conn->query("SELECT COUNT(*) AS total FROM cancellations c JOIN reservations r ON r.reservation_id = c.reservation_id WHERE r.reservation_date BETWEEN '$range_start' AND '$range_end'")->fetch_assoc()['total'];
$reschedule_count = $conn->query("SELECT COUNT(*) AS total FROM reschedules rs JOIN reservations r ON r.reservation_id = rs.reservation_id WHERE r.reservation_date BETWEEN '$range_start' AND '$range_end'")->fetch_assoc()['total'];

$report_history = $conn->query('SELECT report_id, report_type, date_generated, range_start, range_end FROM reports ORDER BY date_generated DESC LIMIT 10');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | PaddleYard</title>
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
            <a href="reports.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span>Reports</span></a>
            <a href="system_settings.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H4.4v-2.6h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1.9-1.5V4.4h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z"></path></svg><span>System Settings</span></a>
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
                <h1>Operational Reports</h1>
                <p>Review activity, revenue, and customer requests for your selected time period.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Filters</p>
                        <h2>Generate Report</h2>
                    </section>
                </div>
                <form method="post" action="reports.php" class="compact-form">
                    <input type="hidden" name="action" value="generate_report">
                    <label for="report_type">Type</label>
                    <select id="report_type" name="report_type">
                        <option value="reservation_summary">Reservation Summary</option>
                        <option value="payment_summary">Payment Summary</option>
                        <option value="operations_summary">Operations Summary</option>
                    </select>
                    <label for="range_start">From</label>
                    <input id="range_start" type="date" name="range_start" value="<?php echo htmlspecialchars($range_start); ?>">
                    <label for="range_end">To</label>
                    <input id="range_end" type="date" name="range_end" value="<?php echo htmlspecialchars($range_end); ?>">
                    <button type="submit" class="btn btn-sm">Generate</button>
                </form>
            </section>

            <section class="stats-grid">
                <article>
                    <h3>Reservations</h3>
                    <p class="stat-value"><?php echo (int) $reservation_count; ?></p>
                </article>
                <article>
                    <h3>Revenue</h3>
                    <p class="stat-value">₱<?php echo number_format((float) $payment_total, 2); ?></p>
                </article>
                <article>
                    <h3>Verified Payments</h3>
                    <p class="stat-value"><?php echo (int) $paid_reservations; ?></p>
                </article>
                <article>
                    <h3>Cancel / Reschedule</h3>
                    <p class="stat-value"><?php echo (int) $cancellation_count + (int) $reschedule_count; ?></p>
                </article>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">History</p>
                        <h2>Recent Reports</h2>
                    </section>
                </div>

                <?php if ($report_history && $report_history->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Generated</th>
                                <th>From</th>
                                <th>To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($report = $report_history->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($report['report_type']))); ?></td>
                                    <td><?php echo htmlspecialchars($report['date_generated']); ?></td>
                                    <td><?php echo htmlspecialchars($report['range_start']); ?></td>
                                    <td><?php echo htmlspecialchars($report['range_end']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No report history yet.</div>
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
