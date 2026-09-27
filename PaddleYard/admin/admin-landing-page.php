<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';
include '../server-side/admin-landingpage-server.php';
include '../server-side/logout.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PaddleYard</title>
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
        <a href="admin-landing-page.php" class="nav-item active">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Dashboard</span>
        </a>
        <a href="reservation_setup.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Reservations</span>
        </a>
        <a href="admin_payment.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"></path>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <circle cx="7" cy="15" r="1"></circle>
            </svg>
            <span>Payments</span>
        </a>
        <a href="court_setup.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="9" x2="21" y2="9"></line>
                <line x1="12" y1="3" x2="12" y2="21"></line>
            </svg>
            <span>Courts</span>
        </a>
        <a href="paddle_rentals.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 3v18"></path>
                <path d="M5 8h14"></path>
                <path d="M5 16h14"></path>
                <path d="M7 3h10"></path>
            </svg>
            <span>Paddle Rentals</span>
        </a>
        <a href="admin_cancellation_reschedule.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"></path>
                <polyline points="21 3 21 8 16 8"></polyline>
                <path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"></path>
                <polyline points="3 21 3 16 8 16"></polyline>
            </svg>
            <span>Cancellation &amp; Reschedule</span>
        </a>
        <a href="reports.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span>Reports</span>
        </a>
        <a href="system_settings.php" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H4.4v-2.6h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V4.4h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z"></path>
            </svg>
            <span>System Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="profile-section">
            <div class="profile-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <div class="profile-info">
                <p class="profile-name"><?php echo htmlspecialchars($full_name); ?></p>
                <p class="profile-role">Administrator</p>
            </div>
        </div>
        <form action="admin-landing-page.php" method="post" class="logout-form">
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="logout-btn" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4"></path>
                    <polyline points="17 16 21 12 17 8"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </button>
        </form>
    </div>
</aside>

<div class="admin-content">
    <header class="admin-header">
        <div class="header-placeholder"></div>
    </header>

    <main class="dashboard-container">

        <section class="intro">
            <p class="eyebrow">Admin</p>
            <h1>Welcome, <?php echo htmlspecialchars($full_name); ?>.</h1>
            <p>Review accounts, courts, reservations, and payments from one place.</p>
        </section>

        <section class="stats-grid">
            <article>
                <h3>Total Accounts</h3>
                <p><?php echo $account_count; ?></p>
            </article>

            <article>
                <h3>Total Courts</h3>
                <p><?php echo $court_count; ?></p>
            </article>

            <article>
                <h3>Total Reservations</h3>
                <p><?php echo $reservation_count; ?></p>
            </article>

            <article>
                <h3>Total Payments</h3>
                <p><?php echo $payment_count; ?></p>
            </article>
        </section>

        <section class="actions">
            <a class="action" href="reservation_setup.php">
                <h2>Manage Reservations</h2>
                <p>Set up and manage customer reservations.</p>
            </a>
            <a class="action" href="court_setup.php">
                <h2>Manage Courts</h2>
                <p>Update court details, rates, and availability.</p>
            </a>
            <a class="action" href="admin_payment.php">
                <h2>View Payments</h2>
                <p>Review payment status and history.</p>
            </a>
            <a class="action" href="reports.php">
                <h2>View Reports</h2>
                <p>Review activity and performance reports.</p>
            </a>
        </section>

    </main>

    <footer>
        <p>PaddleYard Admin Dashboard</p>
    </footer>
</div>

<script>
    // Set active nav item based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'admin-landing-page.php';
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
</script>

</body>
</html>
