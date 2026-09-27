<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        $status = $_POST['reservation_status'] ?? '';
        $allowed_statuses = ['pending', 'confirmed', 'checked_in', 'cancelled', 'completed', 'no_show'];

        if ($reservation_id && in_array($status, $allowed_statuses, true)) {
            $statement = $conn->prepare('UPDATE reservations SET reservation_status = ? WHERE reservation_id = ?');
            $statement->bind_param('si', $status, $reservation_id);

            if ($statement->execute()) {
                $alert_message = 'Reservation status updated successfully.';
                $alert_type = 'success';
            } else {
                $alert_message = 'Unable to update the reservation status.';
                $alert_type = 'error';
            }
            $statement->close();
        }
    }
}

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$where = ['1=1'];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where[] = 'r.reservation_status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(a.full_name LIKE ? OR c.court_name LIKE ? OR r.reservation_id LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$query = "SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time, r.reservation_status,
                 a.full_name, c.court_name, c.location, r.created_at
          FROM reservations r
          JOIN accounts a ON a.account_id = r.account_id
          JOIN courts c ON c.court_id = r.court_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY r.reservation_date DESC, r.start_time DESC";

$statement = $conn->prepare($query);
if ($params) {
    $statement->bind_param($types, ...$params);
}
$statement->execute();
$reservations = $statement->get_result();
$statement->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations | PaddleYard</title>
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
            <a href="reservation_setup.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span>Reservations</span></a>
            <a href="admin_payment.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"></path><line x1="2" y1="12" x2="22" y2="12"></line><circle cx="7" cy="15" r="1"></circle></svg><span>Payments</span></a>
            <a href="court_setup.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="12" y1="3" x2="12" y2="21"></line></svg><span>Courts</span></a>
            <a href="paddle_rentals.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"></path><path d="M5 8h14"></path><path d="M5 16h14"></path><path d="M7 3h10"></path></svg><span>Paddle Rentals</span></a>
            <a href="admin_cancellation_reschedule.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"></path><polyline points="21 3 21 8 16 8"></polyline><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"></path><polyline points="3 21 3 16 8 16"></polyline></svg><span>Cancellation &amp; Reschedule</span></a>
            <a href="reports.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span>Reports</span></a>
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
                <h1>Reservation Setup</h1>
                <p>Track bookings, review status updates, and keep courts synchronized.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>

            <section class="filter-bar table-card">
                <form method="get" action="reservation_setup.php" class="compact-form">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="checked_in" <?php echo $status_filter === 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                    <label for="search">Search</label>
                    <input id="search" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer, court or reservation ID">
                    <button type="submit" class="btn btn-sm">Apply</button>
                </form>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Bookings</p>
                        <h2>Reservation List</h2>
                    </section>
                </div>

                <?php if ($reservations && $reservations->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Court</th>
                                <th>Reservation</th>
                                <th>Booking Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($reservation = $reservations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['court_name']); ?><br><span class="muted-text"><?php echo htmlspecialchars($reservation['location']); ?></span></td>
                                    <td>#<?php echo (int) $reservation['reservation_id']; ?><br><?php echo htmlspecialchars($reservation['start_time']); ?> - <?php echo htmlspecialchars($reservation['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['reservation_date']); ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($reservation['reservation_status']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reservation['reservation_status']))); ?></span></td>
                                    <td>
                                        <form method="post" action="reservation_setup.php" class="inline-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['reservation_id']; ?>">
                                            <select name="reservation_status" class="inline-select">
                                                <option value="pending" <?php echo $reservation['reservation_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $reservation['reservation_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="checked_in" <?php echo $reservation['reservation_status'] === 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                                <option value="cancelled" <?php echo $reservation['reservation_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="completed" <?php echo $reservation['reservation_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="no_show" <?php echo $reservation['reservation_status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No reservations match the current filter.</div>
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
