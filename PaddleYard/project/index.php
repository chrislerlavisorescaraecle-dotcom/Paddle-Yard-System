<?php
if (!isset($conn)) {
    include '../database/connect.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_customer = ($_SESSION['role'] ?? '') === 'customer';
$customer_name = $_SESSION['full_name'] ?? 'Customer';
$payment_confirmation_reservations = [];

if ($is_customer && ($_GET['payment_submitted'] ?? '') === '1') {
    $confirmation_ids = $_SESSION['payment_confirmation_reservation_ids'] ?? [];
    $confirmation_ids = is_array($confirmation_ids)
        ? array_values(array_unique(array_filter(array_map('intval', $confirmation_ids), static fn($id) => $id > 0)))
        : [];
    unset($_SESSION['payment_confirmation_reservation_ids']);

    if ($confirmation_ids !== []) {
        $confirmation_id_list = implode(',', $confirmation_ids);
        $confirmation_account_id = (int) $_SESSION['account_id'];
        $confirmation_result = $conn->query(
            "SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time, r.reservation_status,
                    c.court_name, c.location, p.amount, p.payment_status, p.payment_screenshot_path
             FROM reservations r
             JOIN courts c ON c.court_id = r.court_id
             LEFT JOIN payments p ON p.payment_id = (
                 SELECT MAX(latest_payment.payment_id)
                 FROM payments latest_payment
                 WHERE latest_payment.reservation_id = r.reservation_id
             )
             WHERE r.account_id = $confirmation_account_id
               AND r.reservation_id IN ($confirmation_id_list)
             ORDER BY r.reservation_date, r.start_time"
        );
        while ($confirmation = $confirmation_result->fetch_assoc()) {
            $payment_confirmation_reservations[] = $confirmation;
        }
    }
}

$court_result = $conn->query("SELECT court_id, court_name, location, court_status, court_type, court_photo_path FROM courts WHERE court_status = 'available' ORDER BY court_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_customer ? 'Customer Home' : 'Paddle Yard | Play, Connect, Elevate'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="landing-page <?php echo $is_customer ? 'customer-home' : ''; ?>">

<?php if ($is_customer): ?>
    <?php $customer_active_nav = 'home'; include __DIR__ . '/../customer/customer-header.php'; ?>
<?php else: ?>
    <header>
        <h1 class="logo">
            <a href="index.php">
                <img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
                <span>Paddle Yard</span>
            </a>
        </h1>
        <nav aria-label="Main Navigation">
            <ul>
                <li><a class="active" href="index.php">Home</a></li>
                <li><a href="#about-us">About</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#courts">Court</a></li>
                <li><a class="nav-button" href="sign-up.php">Book A Court</a></li>
            </ul>
        </nav>
    </header>
<?php endif; ?>

<main>
    <section class="hero">
        <section class="hero-content">
            <p class="eyebrow"><?php echo $is_customer ? 'Welcome back, ' . htmlspecialchars($customer_name) : 'Welcome to'; ?></p>
            <h2>Paddle<br>Yard</h2>
            <p class="hero-motto">Play <span>•</span> Connect <span>•</span> Elevate</p>
            <p class="hero-description"><?php echo $is_customer ? 'Your next match starts here.' : 'Your new favorite place to play.'; ?></p>
            <nav class="hero-actions" aria-label="Hero actions">
                <?php if (!$is_customer): ?>
                    <a href="sign-up.php" class="btn">Book A Court</a>
                <?php endif; ?>
                <a href="#courts" class="btn btn-outline">View Courts</a>
            </nav>
        </section>
        <figure class="hero-image">
            <img src="../image/yard1.jpeg" alt="Paddle Yard indoor pickleball court">
        </figure>
    </section>

    <section class="landing-section about-us" id="about-us">
        <section class="about-us-content">
            <figure class="about-us-image">
                <img src="../image/yard1.jpeg" alt="Paddle Yard indoor pickleball facility">
            </figure>
            <article class="about-us-text">
                <p class="section-kicker">Made for every match</p>
                <h2>More than a court.<br><em>A community for everyone.</em></h2>
                <p class="about-us-lead">We built Paddle Yard for players who want it all great courts, seamless bookings, and an inclusive community that celebrates the love of the game.</p>
                <p class="about-us-description">From your first reservation to your hundredth match, experience the Paddle Yard difference. With flexible scheduling, premium facilities, and a welcoming atmosphere, we've created the perfect place to play, connect, and elevate your game.</p>
            </article>
        </section>
    </section>

    <section class="landing-section how-it-works" id="how-it-works">
        <header class="section-heading section-heading-centered">
            <section>
                <p class="section-kicker">Simple steps</p>
                <h2>How It Works</h2>
            </section>
        </header>
        <section class="steps">
            <article>
                <span class="step-number">1</span>
                <h3>Choose Court</h3>
                <p>Browse and select your preferred court.</p>
            </article>
            <article>
                <span class="step-number">2</span>
                <h3>Pick Schedule</h3>
                <p>Choose the date and time that works for you.</p>
            </article>
            <article>
                <span class="step-number">3</span>
                <h3>Pay Online</h3>
                <p>Securely pay online to confirm your booking.</p>
            </article>
            <article>
                <span class="step-number">4</span>
                <h3>Get QR Code</h3>
                <p>Receive your QR ticket and show it at check-in.</p>
            </article>
        </section>
    </section>

    <section class="landing-section featured-courts" id="courts">
        <header class="section-heading">
            <section>
                <p class="section-kicker">Play your way</p>
                <h2>Featured Courts</h2>
            </section>
            <a href="<?php echo $is_customer ? 'court.php' : '#courts'; ?>">View All Courts</a>
        </header>
        <section class="court-cards<?php echo $court_result->num_rows >= 3 ? ' court-cards--three-column' : ''; ?>">
            <?php while ($court = $court_result->fetch_assoc()): ?>
                <article class="court-card">
                    <?php if (!empty($court['court_photo_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($court['court_photo_path']); ?>" alt="<?php echo htmlspecialchars($court['court_name']); ?>">
                    <?php endif; ?>
                    <section class="court-card-content">
                        <span class="court-type <?php echo htmlspecialchars($court['court_type']); ?>"><?php echo ucfirst(htmlspecialchars($court['court_type'])); ?></span>
                        <header class="court-card-title"><h3><?php echo htmlspecialchars($court['court_name']); ?></h3></header>
                        <p><?php echo htmlspecialchars($court['location']); ?></p>
                        <a class="btn" href="<?php echo $is_customer ? 'court.php?court_id=' . (int) $court['court_id'] : 'sign-up.php'; ?>"><?php echo $is_customer ? 'Choose Court' : 'Reserve'; ?></a>
                    </section>
                </article>
            <?php endwhile; ?>
        </section>
    </section>

    <section class="landing-cta" id="customer-account">
        <img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo" class="cta-logo">
        <section>
            <h2><?php echo $is_customer ? 'Welcome back, ' . htmlspecialchars($customer_name) . '.' : 'Ready to Play?'; ?></h2>
            <p><?php echo $is_customer ? 'Browse available courts and find a spot for your next match.' : 'Book a court, invite your group, and make Paddle Yard your regular match.'; ?></p>
        </section>
        <a class="btn" href="<?php echo $is_customer ? 'court.php' : 'sign-up.php'; ?>"><?php echo $is_customer ? 'Browse Courts' : 'Book A Court'; ?></a>
    </section>
</main>

<footer class="landing-footer">
    <section>
        <strong>Paddle Yard</strong>
        <p>Play • Connect • Elevate</p>
    </section>
    <nav aria-label="Footer navigation">
        <h3>Quick Links</h3>
        <a href="<?php echo $is_customer ? '../customer/customer-landing-page.php' : 'index.php'; ?>">Home</a>
        <a href="#courts">Courts</a>
        <a href="#how-it-works">How It Works</a>
        <?php if ($is_customer): ?>
            <a href="#customer-account">My Account</a>
        <?php else: ?>
            <a href="sign-in.php">Sign In</a>
        <?php endif; ?>
    </nav>
    <section>
        <h3>Hours</h3>
        <p>Open daily</p>
        <p>5:00 AM - 11:00 PM</p>
        <p>Indoor courts</p>
    </section>
    <nav aria-label="Footer booking links">
        <h3>Play</h3>
        <?php if ($is_customer): ?>
            <a href="#courts"><?php echo $is_customer ? 'Court' : 'Book a court'; ?></a>
        <?php else: ?>
            <a href="sign-up.php">Create an account</a>
        <?php endif; ?>
        <a href="<?php echo $is_customer ? 'court.php' : '#courts'; ?>">View courts</a>
    </nav>
    <p class="copyright">&copy; 2026 Paddle Yard. All rights reserved.</p>
</footer>

<?php if ($payment_confirmation_reservations): ?>
    <dialog class="payment-confirmation-dialog" id="payment-confirmation" aria-labelledby="payment-confirmation-title">
        <header class="payment-confirmation-heading">
            <div>
                <p class="section-kicker">Payment submitted</p>
                <h2 id="payment-confirmation-title">Your receipt is on its way to review</h2>
            </div>
            <button class="payment-confirmation-close" type="button" aria-label="Close payment confirmation">&times;</button>
        </header>
        <p class="payment-confirmation-message">Your reservation will be confirmed after the payment receipt is reviewed.</p>
        <?php $confirmation_receipt = $payment_confirmation_reservations[0]['payment_screenshot_path'] ?? ''; ?>
        <?php if ($confirmation_receipt !== ''): ?>
            <figure class="payment-confirmation-receipt">
                <img src="../<?php echo htmlspecialchars($confirmation_receipt); ?>" alt="Uploaded payment receipt">
                <figcaption>Payment receipt</figcaption>
            </figure>
        <?php endif; ?>
        <section class="payment-confirmation-bookings" aria-label="Reservation details">
            <?php foreach ($payment_confirmation_reservations as $confirmation): ?>
                <article class="payment-confirmation-booking">
                    <h3><?php echo htmlspecialchars($confirmation['court_name']); ?></h3>
                    <p><?php echo htmlspecialchars(date('D, M j, Y', strtotime($confirmation['reservation_date']))); ?></p>
                    <p><?php echo htmlspecialchars(date('g:i A', strtotime($confirmation['start_time'])) . ' - ' . date('g:i A', strtotime($confirmation['end_time']))); ?></p>
                    <p><?php echo htmlspecialchars($confirmation['location']); ?></p>
                    <p class="payment-confirmation-status">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $confirmation['reservation_status']))); ?>
                        · <?php echo htmlspecialchars(ucfirst($confirmation['payment_status'] ?? 'pending')); ?> payment
                        <?php if ($confirmation['amount'] !== null): ?>
                            · ₱<?php echo number_format((float) $confirmation['amount'], 2); ?>
                        <?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </section>
        <footer class="payment-confirmation-actions">
            <a href="../customer/book-history.php">View booking history</a>
            <button class="payment-confirmation-done" type="button">Back to home</button>
        </footer>
    </dialog>
<?php endif; ?>

<script>
    // Update active nav link based on scroll position
    const navLinks = document.querySelectorAll('nav a:not(.nav-button):not(.profile-dialog-link)');
    const sections = document.querySelectorAll('section[id]');
    const homeLink = navLinks[0];

    // Prevent default and add smooth scroll for Home link
    homeLink.addEventListener('click', function(e) {
        e.preventDefault();
        navLinks.forEach(l => l.classList.remove('active'));
        homeLink.classList.add('active');
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    function updateActiveLink() {
        let current = '';

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;

            if (scrollY >= sectionTop - 100) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach((link, index) => {
            link.classList.remove('active');
            if (index === 0) {
                // Home link
                if (scrollY < 300) {
                    homeLink.classList.add('active');
                }
            }
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', updateActiveLink);
    updateActiveLink();

    const paymentConfirmation = document.querySelector('#payment-confirmation');
    const paymentConfirmationClose = document.querySelector('.payment-confirmation-close');
    const paymentConfirmationDone = document.querySelector('.payment-confirmation-done');

    if (paymentConfirmation) {
        paymentConfirmation.showModal();
        paymentConfirmationClose.addEventListener('click', () => paymentConfirmation.close());
        paymentConfirmationDone.addEventListener('click', () => paymentConfirmation.close());
        paymentConfirmation.addEventListener('click', (event) => {
            if (event.target === paymentConfirmation) {
                paymentConfirmation.close();
            }
        });
    }
</script>

</body>
</html>
