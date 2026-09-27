<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_rental_status') {
        $rental_id = filter_input(INPUT_POST, 'rental_id', FILTER_VALIDATE_INT);
        $status = $_POST['rental_status'] ?? '';
        $valid_statuses = ['requested', 'approved', 'checked_out', 'returned', 'cancelled', 'overdue'];

        if ($rental_id && in_array($status, $valid_statuses, true)) {
            $statement = $conn->prepare('UPDATE paddle_rentals SET rental_status = ? WHERE paddle_rental_id = ?');
            $statement->bind_param('si', $status, $rental_id);

            if ($statement->execute()) {
                if ($status === 'returned') {
                    $update_paddle = $conn->prepare('UPDATE paddles SET paddle_status = ? WHERE paddle_id = (SELECT paddle_id FROM paddle_rentals WHERE paddle_rental_id = ?)');
                    $available = 'available';
                    $update_paddle->bind_param('si', $available, $rental_id);
                    $update_paddle->execute();
                    $update_paddle->close();
                }
                $alert_message = 'Rental status updated successfully.';
                $alert_type = 'success';
            } else {
                $alert_message = 'Unable to update the rental status.';
                $alert_type = 'error';
            }
            $statement->close();
        }
    }
}

$inventory_total = $conn->query('SELECT COUNT(*) as total FROM paddles')->fetch_assoc()['total'];
$available_paddles = $conn->query("SELECT COUNT(*) as total FROM paddles WHERE paddle_status = 'available'")->fetch_assoc()['total'];
$active_rentals = $conn->query("SELECT COUNT(*) as total FROM paddle_rentals WHERE rental_status IN ('requested', 'approved', 'checked_out', 'overdue')")->fetch_assoc()['total'];

$rentals = $conn->query(
    "SELECT pr.paddle_rental_id, pr.quantity, pr.rental_status, pr.rented_at, pr.returned_at,
            a.full_name, p.paddle_id, p.paddle_status, p.rental_price
     FROM paddle_rentals pr
     JOIN accounts a ON a.account_id = pr.account_id
     JOIN paddles p ON p.paddle_id = pr.paddle_id
     ORDER BY pr.rented_at DESC"
);

$inventory = $conn->query('SELECT paddle_id, paddle_status, rental_price, updated_at FROM paddles ORDER BY paddle_id DESC');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paddle Rentals | PaddleYard</title>
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
            <a href="paddle_rentals.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"></path><path d="M5 8h14"></path><path d="M5 16h14"></path><path d="M7 3h10"></path></svg><span>Paddle Rentals</span></a>
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
                <h1>Paddle Rental Inventory</h1>
                <p>Track paddle availability and manage customer rental requests.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>

            <section class="stats-grid">
                <article>
                    <h3>Total Paddles</h3>
                    <p class="stat-value"><?php echo (int) $inventory_total; ?></p>
                </article>
                <article>
                    <h3>Available</h3>
                    <p class="stat-value"><?php echo (int) $available_paddles; ?></p>
                </article>
                <article>
                    <h3>Active Rentals</h3>
                    <p class="stat-value"><?php echo (int) $active_rentals; ?></p>
                </article>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Inventory</p>
                        <h2>Paddle Stock</h2>
                    </section>
                </div>

                <?php if ($inventory && $inventory->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Paddle ID</th>
                                <th>Status</th>
                                <th>Rental Price</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($paddle = $inventory->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo (int) $paddle['paddle_id']; ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($paddle['paddle_status']); ?>"><?php echo htmlspecialchars(ucfirst($paddle['paddle_status'])); ?></span></td>
                                    <td>₱<?php echo number_format((float) $paddle['rental_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($paddle['updated_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No paddle inventory is available.</div>
                <?php endif; ?>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Requests</p>
                        <h2>Rental Requests</h2>
                    </section>
                </div>

                <?php if ($rentals && $rentals->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Paddle ID</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rental = $rentals->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rental['full_name']); ?></td>
                                    <td>#<?php echo (int) $rental['paddle_id']; ?></td>
                                    <td><?php echo (int) $rental['quantity']; ?></td>
                                    <td>₱<?php echo number_format((float) $rental['rental_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($rental['rented_at']); ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($rental['rental_status']); ?>"><?php echo htmlspecialchars(ucfirst($rental['rental_status'])); ?></span></td>
                                    <td>
                                        <form method="post" action="paddle_rentals.php" class="inline-form">
                                            <input type="hidden" name="action" value="update_rental_status">
                                            <input type="hidden" name="rental_id" value="<?php echo (int) $rental['paddle_rental_id']; ?>">
                                            <select name="rental_status" class="inline-select">
                                                <option value="requested" <?php echo $rental['rental_status'] === 'requested' ? 'selected' : ''; ?>>Requested</option>
                                                <option value="approved" <?php echo $rental['rental_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="checked_out" <?php echo $rental['rental_status'] === 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                                <option value="returned" <?php echo $rental['rental_status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                                <option value="cancelled" <?php echo $rental['rental_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="overdue" <?php echo $rental['rental_status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No rental requests at the moment.</div>
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
