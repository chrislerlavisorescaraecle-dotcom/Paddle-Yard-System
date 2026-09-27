<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

if (($_GET['ajax'] ?? '') === 'submitted_payment_count') {
    header('Content-Type: application/json; charset=utf-8');
    $submitted_count = (int) $conn->query("SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'submitted'")->fetch_assoc()['total'];
    echo json_encode(['count' => $submitted_count]);
    exit;
}

$upload_directory = __DIR__ . '/../image/payment_qr/';
$upload_public_path = 'image/payment_qr/';
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_file_size = 5 * 1024 * 1024;

$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $status = $_POST['payment_status'] ?? '';
        $valid_statuses = ['pending', 'submitted', 'verified', 'rejected', 'refunded'];

        if ($payment_id && in_array($status, $valid_statuses, true)) {
            $conn->begin_transaction();
            $statement = $conn->prepare('UPDATE payments SET payment_status = ? WHERE payment_id = ?');
            $statement->bind_param('si', $status, $payment_id);

            if ($statement->execute()) {
                    $statement->close();
                    $reservation_updated = true;
                    if ($status === 'verified') {
                        $reservation_statement = $conn->prepare(
                            "UPDATE reservations r
                             JOIN payments p ON p.reservation_id = r.reservation_id
                             SET r.reservation_status = 'confirmed'
                             WHERE p.payment_id = ? AND r.reservation_status <> 'cancelled'"
                        );
                        $reservation_statement->bind_param('i', $payment_id);
                        $reservation_updated = $reservation_statement->execute();
                        $reservation_statement->close();
                    }

                    if ($reservation_updated) {
                        $conn->commit();
                        $alert_message = 'Payment status updated successfully.';
                        $alert_type = 'success';
                    } else {
                        $conn->rollback();
                        $alert_message = 'Unable to confirm the reservation.';
                        $alert_type = 'error';
                    }
            } else {
                    $statement->close();
                    $conn->rollback();
                $alert_message = 'Unable to update the payment status.';
                $alert_type = 'error';
            }
        }
    }

    if ($action === 'save_qr') {
        $qr_id = filter_input(INPUT_POST, 'qr_id', FILTER_VALIDATE_INT);
        $qr_account_name = trim($_POST['qr_account_name'] ?? '');
        $qr_number = trim($_POST['qr_number'] ?? '');
        $mark_active = isset($_POST['is_active']) && $_POST['is_active'] == '1';
        $existing_qr = null;

        if ($qr_id) {
            $lookup = $conn->prepare('SELECT qr_image_path FROM banking_payment_qrs WHERE qr_id = ?');
            $lookup->bind_param('i', $qr_id);
            $lookup->execute();
            $existing_qr = $lookup->get_result()->fetch_assoc();
            $lookup->close();
        }

        $qr_image_path = $existing_qr['qr_image_path'] ?? '';
        $has_new_image = isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($qr_id && !$existing_qr) {
            $alert_message = 'The selected payment QR could not be found.';
            $alert_type = 'error';
        } elseif ($qr_account_name === '' || $qr_number === '') {
            $alert_message = 'Please provide the account name and number for the payment QR.';
            $alert_type = 'error';
        } elseif (!$qr_id && !$has_new_image) {
            $alert_message = 'Please upload a QR image for the new payment method.';
            $alert_type = 'error';
        } else {
            $old_image_path = $qr_image_path;

            if ($has_new_image) {
                $qr_image = $_FILES['qr_image'];

                if ($qr_image['error'] !== UPLOAD_ERR_OK || $qr_image['size'] > $max_file_size) {
                    $alert_message = 'Please upload a JPG, PNG, or WEBP QR image under 5 MB.';
                    $alert_type = 'error';
                } else {
                    $mime_type = mime_content_type($qr_image['tmp_name']);

                    if (!in_array($mime_type, $allowed_types, true)) {
                        $alert_message = 'Please upload a JPG, PNG, or WEBP QR image under 5 MB.';
                        $alert_type = 'error';
                    } else {
                        if (!is_dir($upload_directory)) {
                            mkdir($upload_directory, 0755, true);
                        }

                        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        $file_name = 'qr-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime_type];
                        $target_path = $upload_directory . $file_name;

                        if (move_uploaded_file($qr_image['tmp_name'], $target_path)) {
                            $qr_image_path = $upload_public_path . $file_name;
                        } else {
                            $alert_message = 'The QR image could not be uploaded.';
                            $alert_type = 'error';
                        }
                    }
                }
            }

            if ($alert_type !== 'error') {
                $conn->begin_transaction();

                if ($mark_active && !$conn->query('UPDATE banking_payment_qrs SET is_active = 0')) {
                    $conn->rollback();
                    $alert_message = 'Unable to update the active payment QR.';
                    $alert_type = 'error';
                } else {
                    $is_active = $mark_active ? 1 : 0;

                    if ($qr_id) {
                        $statement = $conn->prepare('UPDATE banking_payment_qrs SET qr_image_path = ?, qr_account_name = ?, qr_number = ?, is_active = ? WHERE qr_id = ?');
                        $statement->bind_param('sssii', $qr_image_path, $qr_account_name, $qr_number, $is_active, $qr_id);
                    } else {
                        $statement = $conn->prepare('INSERT INTO banking_payment_qrs (qr_image_path, qr_account_name, qr_number, is_active) VALUES (?, ?, ?, ?)');
                        $statement->bind_param('sssi', $qr_image_path, $qr_account_name, $qr_number, $is_active);
                    }

                    if ($statement->execute()) {
                        $conn->commit();
                        $alert_message = $qr_id ? 'Payment QR updated successfully.' : 'Bank QR details saved successfully.';
                        $alert_type = 'success';

                        if ($has_new_image && $old_image_path !== '') {
                            $old_file_name = basename(str_replace('\\', '/', $old_image_path));
                            $old_file_path = $upload_directory . $old_file_name;
                            $old_path_check = $conn->prepare('SELECT COUNT(*) AS total FROM banking_payment_qrs WHERE qr_image_path = ?');
                            $old_path_check->bind_param('s', $old_image_path);
                            $old_path_check->execute();
                            $old_path_in_use = (int) $old_path_check->get_result()->fetch_assoc()['total'] > 0;
                            $old_path_check->close();

                            if (!$old_path_in_use && is_file($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                    } else {
                        $conn->rollback();
                        $alert_message = $qr_id ? 'Unable to update the payment QR.' : 'Unable to save the QR details.';
                        $alert_type = 'error';
                    }
                    $statement->close();
                }
            }
        }
    }

    if ($action === 'delete_qr') {
        $qr_id = filter_input(INPUT_POST, 'qr_id', FILTER_VALIDATE_INT);

        if ($qr_id) {
            $lookup = $conn->prepare('SELECT qr_image_path, is_active FROM banking_payment_qrs WHERE qr_id = ?');
            $lookup->bind_param('i', $qr_id);
            $lookup->execute();
            $qr_to_delete = $lookup->get_result()->fetch_assoc();
            $lookup->close();

            if ($qr_to_delete) {
                $statement = $conn->prepare('DELETE FROM banking_payment_qrs WHERE qr_id = ?');
                $statement->bind_param('i', $qr_id);

                if ($statement->execute()) {
                    $alert_message = 'Payment QR deleted successfully.';
                    $alert_type = 'success';

                    if ((int) $qr_to_delete['is_active'] === 1) {
                        $next_qr = $conn->query('SELECT qr_id FROM banking_payment_qrs ORDER BY date_uploaded DESC, qr_id DESC LIMIT 1')->fetch_assoc();

                        if ($next_qr) {
                            $activate = $conn->prepare('UPDATE banking_payment_qrs SET is_active = 1 WHERE qr_id = ?');
                            $activate->bind_param('i', $next_qr['qr_id']);
                            $activate->execute();
                            $activate->close();
                        }
                    }

                    $deleted_file_name = basename(str_replace('\\', '/', $qr_to_delete['qr_image_path']));
                    $deleted_file_path = $upload_directory . $deleted_file_name;
                    $path_check = $conn->prepare('SELECT COUNT(*) AS total FROM banking_payment_qrs WHERE qr_image_path = ?');
                    $path_check->bind_param('s', $qr_to_delete['qr_image_path']);
                    $path_check->execute();
                    $image_still_used = (int) $path_check->get_result()->fetch_assoc()['total'] > 0;
                    $path_check->close();

                    if (!$image_still_used && is_file($deleted_file_path)) {
                        unlink($deleted_file_path);
                    }
                } else {
                    $alert_message = 'Unable to delete the payment QR.';
                    $alert_type = 'error';
                }
                $statement->close();
            } else {
                $alert_message = 'The selected payment QR could not be found.';
                $alert_type = 'error';
            }
        }
    }
}

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$where = ['1=1'];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where[] = 'p.payment_status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(a.full_name LIKE ? OR c.court_name LIKE ? OR p.payment_id LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$query = "SELECT p.payment_id, p.amount, p.payment_method, p.payment_status, p.payment_date,
                 r.reservation_id, a.full_name, c.court_name, p.payment_screenshot_path
          FROM payments p
          JOIN reservations r ON r.reservation_id = p.reservation_id
          JOIN accounts a ON a.account_id = r.account_id
          JOIN courts c ON c.court_id = r.court_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY p.payment_date DESC";

$statement = $conn->prepare($query);
if ($params) {
    $statement->bind_param($types, ...$params);
}
$statement->execute();
$payments = $statement->get_result();
$statement->close();

$submitted_payment_count = (int) $conn->query("SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'submitted'")->fetch_assoc()['total'];
$active_qr = $conn->query("SELECT qr_id, qr_image_path, qr_account_name, qr_number, is_active FROM banking_payment_qrs WHERE is_active = 1 ORDER BY qr_id DESC LIMIT 1")->fetch_assoc();
$payment_qrs = $conn->query('SELECT qr_id, qr_image_path, qr_account_name, qr_number, is_active FROM banking_payment_qrs ORDER BY date_uploaded DESC, qr_id DESC');
$edit_qr_id = filter_input(INPUT_GET, 'edit_qr', FILTER_VALIDATE_INT);
$editing_qr = null;

if ($edit_qr_id) {
    $edit_statement = $conn->prepare('SELECT qr_id, qr_image_path, qr_account_name, qr_number, is_active FROM banking_payment_qrs WHERE qr_id = ?');
    $edit_statement->bind_param('i', $edit_qr_id);
    $edit_statement->execute();
    $editing_qr = $edit_statement->get_result()->fetch_assoc();
    $edit_statement->close();
}

$qr_image_url = static function ($path) {
    $relative_path = preg_replace('#^(?:\.\./)+#', '', $path);
    return '../' . ltrim($relative_path, '/');
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | PaddleYard</title>
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
            <a href="admin_payment.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"></path><line x1="2" y1="12" x2="22" y2="12"></line><circle cx="7" cy="15" r="1"></circle></svg><span>Payments</span></a>
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
                <h1>Payment Management</h1>
                <p>Review payment submissions and confirm whether each booking is cleared for play.</p>
            </section>

            <?php if ($alert_message !== ''): ?>
                <p class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($alert_message); ?></p>
            <?php endif; ?>
            <p class="alert admin-payment-notice" id="admin-payment-notice" role="status" aria-live="polite" <?php echo $submitted_payment_count === 0 ? 'hidden' : ''; ?>>
                <span id="admin-payment-notice-message"><?php echo $submitted_payment_count; ?> submitted payment<?php echo $submitted_payment_count === 1 ? '' : 's'; ?> awaiting review.</span>
                <a href="admin_payment.php?status=submitted#payment-log">View payment details and receipts</a>
            </p>

            <section class="filter-bar table-card">
                <form method="get" action="admin_payment.php" class="compact-form">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                    <label for="search">Search</label>
                    <input id="search" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer, court or payment ID">
                    <button type="submit" class="btn btn-sm">Apply</button>
                </form>
            </section>

            <section class="table-card">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Payment Method</p>
                        <h2>Bank QR / Other Payment Method</h2>
                    </section>
                </div>

                <form method="post" action="admin_payment.php" enctype="multipart/form-data" class="settings-form">
                    <input type="hidden" name="action" value="save_qr">
                    <?php if ($editing_qr): ?>
                        <input type="hidden" name="qr_id" value="<?php echo (int) $editing_qr['qr_id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="qr_account_name">Account Name</label>
                            <input id="qr_account_name" type="text" name="qr_account_name" value="<?php echo htmlspecialchars($editing_qr['qr_account_name'] ?? ''); ?>" placeholder="Owner / account holder name" required>
                        </div>
                        <div class="form-field">
                            <label for="qr_number">Account / QR Number</label>
                            <input id="qr_number" type="text" name="qr_number" value="<?php echo htmlspecialchars($editing_qr['qr_number'] ?? ''); ?>" placeholder="GCash / PayMaya / Bank number" required>
                        </div>
                        <div class="form-field full-width">
                            <label for="qr_image">QR Image</label>
                            <input id="qr_image" type="file" name="qr_image" accept="image/jpeg,image/png,image/webp" <?php echo $editing_qr ? '' : 'required'; ?>>
                            <?php if ($editing_qr): ?>
                                <p>Current image is shown below. Choose a new file only to replace it.</p>
                            <?php endif; ?>
                        </div>
                        <div class="form-field full-width">
                            <label for="is_active">
                                <input id="is_active" type="checkbox" name="is_active" value="1" <?php echo !empty($editing_qr['is_active']) ? 'checked' : ''; ?>> Set as active payment QR
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn"><?php echo $editing_qr ? 'Update QR' : 'Save QR'; ?></button>
                        <?php if ($editing_qr): ?>
                            <a href="admin_payment.php" class="btn btn-sm">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($active_qr): ?>
                    <div class="table-card" style="margin-top: 20px; background: rgba(255,255,255,0.02);">
                        <div class="section-heading" style="margin-bottom: 12px;">
                            <section>
                                <p class="section-kicker">Active</p>
                                <h2>Current QR for customers</h2>
                            </section>
                        </div>
                        <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
                            <img src="<?php echo htmlspecialchars($qr_image_url($active_qr['qr_image_path'])); ?>" alt="Active banking QR" style="width: 220px; height: 220px; object-fit: contain; border-radius: 18px; border: 1px solid rgba(31,41,55,0.12); background: white;">
                            <div>
                                <p><strong>Account Name:</strong> <?php echo htmlspecialchars($active_qr['qr_account_name']); ?></p>
                                <p><strong>Account / QR Number:</strong> <?php echo htmlspecialchars($active_qr['qr_number']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Saved Methods</p>
                        <h2>Uploaded Payment QRs</h2>
                    </section>
                </div>
                <?php if ($payment_qrs && $payment_qrs->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Account Name</th>
                                <th>Account / QR Number</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($qr = $payment_qrs->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($qr_image_url($qr['qr_image_path'])); ?>" alt="QR for <?php echo htmlspecialchars($qr['qr_account_name']); ?>" style="width: 72px; height: 72px; object-fit: contain; background: white;"></td>
                                    <td><?php echo htmlspecialchars($qr['qr_account_name']); ?></td>
                                    <td><?php echo htmlspecialchars($qr['qr_number']); ?></td>
                                    <td><?php echo (int) $qr['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>
                                    <td>
                                        <a href="admin_payment.php?edit_qr=<?php echo (int) $qr['qr_id']; ?>" class="proof-link">Edit</a>
                                        <form method="post" action="admin_payment.php" class="inline-form" onsubmit="return confirm('Delete this payment QR?');">
                                            <input type="hidden" name="action" value="delete_qr">
                                            <input type="hidden" name="qr_id" value="<?php echo (int) $qr['qr_id']; ?>">
                                            <button type="submit" class="btn btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No payment QR images have been uploaded.</div>
                <?php endif; ?>
            </section>

            <section class="table-card" id="payment-log">
                <div class="section-heading">
                    <section>
                        <p class="section-kicker">Transactions</p>
                        <h2>Payment Log</h2>
                    </section>
                </div>

                <?php if ($payments && $payments->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Court</th>
                                <th>Reservation</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Proof</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['court_name']); ?></td>
                                    <td>#<?php echo (int) $payment['reservation_id']; ?></td>
                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                    <td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><span class="status-pill status-<?php echo htmlspecialchars($payment['payment_status']); ?>"><?php echo htmlspecialchars(ucfirst($payment['payment_status'])); ?></span></td>
                                    <td>
                                        <?php if (!empty($payment['payment_screenshot_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($payment['payment_screenshot_path']); ?>" target="_blank" rel="noreferrer" class="proof-link">View</a>
                                        <?php else: ?>
                                            <span class="muted-text">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="admin_payment.php" class="inline-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="payment_id" value="<?php echo (int) $payment['payment_id']; ?>">
                                            <select name="payment_status" class="inline-select">
                                                <option value="pending" <?php echo $payment['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="submitted" <?php echo $payment['payment_status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                <option value="verified" <?php echo $payment['payment_status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                                <option value="rejected" <?php echo $payment['payment_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="refunded" <?php echo $payment['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No payments match the current filters.</div>
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

        const paymentNotice = document.querySelector('#admin-payment-notice');
        const paymentNoticeMessage = document.querySelector('#admin-payment-notice-message');
        const updateSubmittedPayments = async () => {
            try {
                const response = await fetch('admin_payment.php?ajax=submitted_payment_count', { cache: 'no-store' });
                if (!response.ok) return;

                const data = await response.json();
                const count = Number(data.count) || 0;
                paymentNotice.hidden = count === 0;
                paymentNoticeMessage.textContent = `${count} submitted payment${count === 1 ? '' : 's'} awaiting review.`;
            } catch {
                return;
            }
        };

        updateSubmittedPayments();
        window.setInterval(updateSubmittedPayments, 30000);
    </script>
</body>
</html>
