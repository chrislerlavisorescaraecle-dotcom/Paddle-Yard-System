<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_reschedule_status') {
        $reschedule_id = filter_input(INPUT_POST, 'reschedule_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'approved', 'rejected', 'cancelled'];

        if ($reschedule_id && in_array($status, $allowed, true)) {
            $statement = $conn->prepare('UPDATE reschedules SET status = ? WHERE reschedule_id = ?');
            $statement->bind_param('si', $status, $reschedule_id);

            if ($statement->execute()) {
                $alert_message = 'Reschedule request updated successfully.';
                $alert_type = 'success';
            } else {
                $alert_message = 'Unable to update this reschedule request.';
                $alert_type = 'error';
            }
            $statement->close();
        }
    }

    if ($action === 'approve_cancellation') {
        $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);

        if ($reservation_id) {
            $conn->begin_transaction();
            $update_reservation = $conn->prepare('UPDATE reservations SET reservation_status = ? WHERE reservation_id = ?');
            $cancelled = 'cancelled';
            $update_reservation->bind_param('si', $cancelled, $reservation_id);

            if ($update_reservation->execute()) {
                $delete_cancellation = $conn->prepare('DELETE FROM cancellations WHERE reservation_id = ?');
                $delete_cancellation->bind_param('i', $reservation_id);
                $delete_cancellation->execute();
                $delete_cancellation->close();
                $conn->commit();
                $alert_message = 'Cancellation approved and reservation marked cancelled.';
                $alert_type = 'success';
            } else {
                $conn->rollback();
                $alert_message = 'Unable to process this cancellation.';
                $alert_type = 'error';
            }
            $update_reservation->close();
        }
    }

    if ($action === 'reject_cancellation') {
        $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);

        if ($reservation_id) {
            $conn->begin_transaction();
            $update_reservation = $conn->prepare('UPDATE reservations SET reservation_status = ? WHERE reservation_id = ?');
            $confirmed = 'confirmed';
            $update_reservation->bind_param('si', $confirmed, $reservation_id);

            if ($update_reservation->execute()) {
                $delete_cancellation = $conn->prepare('DELETE FROM cancellations WHERE reservation_id = ?');
                $delete_cancellation->bind_param('i', $reservation_id);
                $delete_cancellation->execute();
                $delete_cancellation->close();
                $conn->commit();
                $alert_message = 'Cancellation request rejected and reservation restored.';
                $alert_type = 'success';
            } else {
                $conn->rollback();
                $alert_message = 'Unable to reject this cancellation request.';
                $alert_type = 'error';
            }
            $update_reservation->close();
        }
    }
}

$cancellations = $conn->query(
    "SELECT c.cancellation_id, c.reservation_id, c.reason, c.request_date, c.cutoff_status,
            r.reservation_date, r.start_time, r.end_time, r.reservation_status,
            a.full_name, ct.court_name
     FROM cancellations c
     JOIN reservations r ON r.reservation_id = c.reservation_id
     JOIN accounts a ON a.account_id = r.account_id
     JOIN courts ct ON ct.court_id = r.court_id
     ORDER BY c.request_date DESC"
);

$reschedules = $conn->query(
    "SELECT rs.reschedule_id, rs.reservation_id, rs.new_date, rs.new_start_time, rs.new_end_time,
            rs.request_date, rs.cutoff_status, rs.status,
            r.reservation_date, r.start_time, r.end_time, r.reservation_status,
            a.full_name, ct.court_name
     FROM reschedules rs
     JOIN reservations r ON r.reservation_id = rs.reservation_id
     JOIN accounts a ON a.account_id = r.account_id
     JOIN courts ct ON ct.court_id = r.court_id
     ORDER BY rs.request_date DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation &amp; Reschedule | PaddleYard</title>
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
            <a href="admin_cancellation_reschedule.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"></path><polyline points="21 3 21 8 16 8"></polyline><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"></path><polyline points="3 21 3 16 8 16"></polyline></svg><span>Cancellation &amp; Reschedule</span></a>
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
                <h1>Cancellation &amp; Reschedule Requests</h1>
                <p>Review customer changes and keep court schedules accurate.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Pending</p>
                        <h2>Cancellation Requests</h2>
                    </section>
                </div>

                <?php if ($cancellations && $cancellations->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Court</th>
                                <th>Reservation</th>
                                <th>Time</th>
                                <th>Cutoff</th>
                                <th>Reason</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cancellation = $cancellations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cancellation['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cancellation['court_name']); ?></td>
                                    <td>#<?php echo (int) $cancellation['reservation_id']; ?></td>
                                    <td><?php echo htmlspecialchars($cancellation['reservation_date']); ?><br><?php echo htmlspecialchars($cancellation['start_time']); ?> - <?php echo htmlspecialchars($cancellation['end_time']); ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($cancellation['cutoff_status']); ?>"><?php echo htmlspecialchars(ucfirst($cancellation['cutoff_status'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($cancellation['reason']); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <form method="post" action="admin_cancellation_reschedule.php">
                                                <input type="hidden" name="action" value="approve_cancellation">
                                                <input type="hidden" name="reservation_id" value="<?php echo (int) $cancellation['reservation_id']; ?>">
                                                <button type="submit" class="btn btn-sm">Approve</button>
                                            </form>
                                            <form method="post" action="admin_cancellation_reschedule.php">
                                                <input type="hidden" name="action" value="reject_cancellation">
                                                <input type="hidden" name="reservation_id" value="<?php echo (int) $cancellation['reservation_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No cancellation requests found.</div>
                <?php endif; ?>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Scheduling</p>
                        <h2>Reschedule Requests</h2>
                    </section>
                </div>

                <?php if ($reschedules && $reschedules->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Court</th>
                                <th>Current Booking</th>
                                <th>Requested Slot</th>
                                <th>Cutoff</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($schedule = $reschedules->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['court_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['reservation_date']); ?><br><?php echo htmlspecialchars($schedule['start_time']); ?> - <?php echo htmlspecialchars($schedule['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['new_date']); ?><br><?php echo htmlspecialchars($schedule['new_start_time']); ?> - <?php echo htmlspecialchars($schedule['new_end_time']); ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($schedule['cutoff_status']); ?>"><?php echo htmlspecialchars(ucfirst($schedule['cutoff_status'])); ?></span></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($schedule['status']); ?>"><?php echo htmlspecialchars(ucfirst($schedule['status'])); ?></span></td>
                                    <td>
                                        <form method="post" action="admin_cancellation_reschedule.php">
                                            <input type="hidden" name="action" value="update_reschedule_status">
                                            <input type="hidden" name="reschedule_id" value="<?php echo (int) $schedule['reschedule_id']; ?>">
                                            <select name="status" class="inline-select">
                                                <option value="pending" <?php echo $schedule['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $schedule['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="rejected" <?php echo $schedule['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="cancelled" <?php echo $schedule['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No reschedule requests found.</div>
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
