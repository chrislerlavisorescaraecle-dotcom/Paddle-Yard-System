<?php
include '../server-side/customer_auth_guard.php';

$account_id = (int) $_SESSION['account_id'];
$reservation_ids = $_SESSION['checkout_reservation_ids'] ?? [];
$reservation_ids = is_array($reservation_ids)
	? array_values(array_unique(array_filter(array_map('intval', $reservation_ids), static fn($id) => $id > 0)))
	: [];

if ($reservation_ids === []) {
	header('Location: court.php');
	exit;
}

$id_list = implode(',', $reservation_ids);
$reservations = [];
$reservation_result = $conn->query(
	"SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time,
	        c.court_id, c.court_name, c.location, c.hourly_rate, c.court_type, c.court_photo_path
	 FROM reservations r
	 JOIN courts c ON c.court_id = r.court_id
	 WHERE r.account_id = $account_id
	   AND r.reservation_id IN ($id_list)
	   AND r.reservation_status <> 'cancelled'
	 ORDER BY r.reservation_date, r.start_time"
);
while ($reservation = $reservation_result->fetch_assoc()) {
	$reservations[] = $reservation;
}

if (count($reservations) !== count($reservation_ids)) {
	unset($_SESSION['checkout_reservation_ids']);
	header('Location: court.php');
	exit;
}

$account_statement = $conn->prepare('SELECT full_name, email, contact_number FROM accounts WHERE account_id = ? AND role = ?');
$customer_role = 'customer';
$account_statement->bind_param('is', $account_id, $customer_role);
$account_statement->execute();
$account = $account_statement->get_result()->fetch_assoc();
$account_statement->close();

if (!$account) {
	http_response_code(404);
	exit('Customer account not found.');
}

$available_paddles = [];
$paddle_result = $conn->query("SELECT paddle_id, rental_price FROM paddles WHERE paddle_status = 'available' ORDER BY paddle_id");
while ($paddle = $paddle_result->fetch_assoc()) {
	$available_paddles[] = $paddle;
}

$payment_qr = $conn->query(
	"SELECT qr_id, qr_image_path, qr_account_name, qr_number
	 FROM banking_payment_qrs
	 ORDER BY is_active DESC, date_uploaded DESC, qr_id DESC
	 LIMIT 1"
)->fetch_assoc();

if (empty($_SESSION['checkout_csrf_token'])) {
	$_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = $_SESSION['checkout_notice'] ?? '';
unset($_SESSION['checkout_notice']);
$rental_quantity = 0;
$court_total = array_sum(array_map(static fn($reservation) => (float) $reservation['hourly_rate'], $reservations));
$rental_prices = array_map(static fn($paddle) => (float) $paddle['rental_price'], $available_paddles);
$rental_total = 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$csrf_token = $_POST['csrf_token'] ?? '';
	$full_name = trim((string) ($_POST['full_name'] ?? ''));
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$contact_number = trim((string) ($_POST['contact_number'] ?? ''));
	$posted_rental_quantity = filter_var($_POST['rental_quantity'] ?? '0', FILTER_VALIDATE_INT);

	if (!is_string($csrf_token) || !hash_equals($_SESSION['checkout_csrf_token'], $csrf_token)) {
		$error_message = 'Your session expired. Refresh the page and try again.';
	} elseif ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $contact_number === '' || strlen($contact_number) > 30) {
		$error_message = 'Enter a valid name, email address, and contact number.';
	} elseif ($posted_rental_quantity === false || $posted_rental_quantity < 0 || $posted_rental_quantity > count($available_paddles)) {
		$error_message = 'Choose a valid paddle rental quantity.';
	} elseif (!$payment_qr) {
		$error_message = 'Payment is unavailable because no QR has been configured.';
	} elseif (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
		$error_message = 'Upload your payment receipt before continuing.';
	} elseif ($_FILES['payment_receipt']['size'] > 5 * 1024 * 1024) {
		$error_message = 'Your receipt must be 5 MB or smaller.';
	} elseif (!in_array(mime_content_type($_FILES['payment_receipt']['tmp_name']), ['image/jpeg', 'image/png', 'image/webp'], true)) {
		$error_message = 'Upload your receipt as a JPG, PNG, or WEBP image.';
	} else {
		$rental_quantity = $posted_rental_quantity;
		$rental_prices = array_slice($rental_prices, 0, $rental_quantity);
		$rental_total = array_sum($rental_prices) * count($reservations);

		$email_statement = $conn->prepare('SELECT account_id FROM accounts WHERE email = ? AND account_id <> ? LIMIT 1');
		$email_statement->bind_param('si', $email, $account_id);
		$email_statement->execute();
		$email_in_use = $email_statement->get_result()->fetch_assoc() !== null;
		$email_statement->close();

		if ($email_in_use) {
			$error_message = 'That email address is already used by another account.';
		} else {
			$receipt_mime = mime_content_type($_FILES['payment_receipt']['tmp_name']);
			$receipt_extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
			$receipt_file_name = 'receipt-' . bin2hex(random_bytes(12)) . '.' . $receipt_extensions[$receipt_mime];
			$receipt_directory = __DIR__ . '/../image/payment_receipts/';
			$receipt_target_path = $receipt_directory . $receipt_file_name;
			$receipt_public_path = 'image/payment_receipts/' . $receipt_file_name;

			if (!is_dir($receipt_directory) && !mkdir($receipt_directory, 0755, true) && !is_dir($receipt_directory)) {
				$error_message = 'Unable to prepare receipt storage. Please try again.';
			} elseif (!move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $receipt_target_path)) {
				$error_message = 'Unable to save your payment receipt. Please try again.';
			}

			if ($error_message === '') {
				$conn->begin_transaction();
				try {
				$update_account = $conn->prepare('UPDATE accounts SET full_name = ?, email = ?, contact_number = ? WHERE account_id = ?');
				$update_account->bind_param('sssi', $full_name, $email, $contact_number, $account_id);
				if (!$update_account->execute()) {
					throw new RuntimeException('Unable to update customer details.');
				}
				$update_account->close();

				if ($rental_quantity > 0) {
					$rental_paddles = array_slice($available_paddles, 0, $rental_quantity);
					$rental_insert = $conn->prepare("INSERT INTO paddle_rentals (account_id, paddle_id, quantity, rental_status) VALUES (?, ?, 1, 'requested')");
					foreach ($rental_paddles as $paddle) {
						$paddle_id = (int) $paddle['paddle_id'];
						$rental_insert->bind_param('ii', $account_id, $paddle_id);
						if (!$rental_insert->execute()) {
							throw new RuntimeException('Unable to request paddle rental.');
						}
					}
					$rental_insert->close();
				}

				$payment_insert = $conn->prepare("INSERT INTO payments (reservation_id, qr_id, amount, payment_method, payment_screenshot_path, payment_status) VALUES (?, ?, ?, 'bank_qr', ?, 'submitted')");
				$qr_id = (int) $payment_qr['qr_id'];
				foreach ($reservations as $index => $reservation) {
					$reservation_id = (int) $reservation['reservation_id'];
					$amount = (float) $reservation['hourly_rate'] + ($index === 0 ? $rental_total : 0);
					$payment_insert->bind_param('iids', $reservation_id, $qr_id, $amount, $receipt_public_path);
					if (!$payment_insert->execute()) {
						throw new RuntimeException('Unable to submit payment request.');
					}
				}
				$payment_insert->close();
				$conn->commit();

				$_SESSION['full_name'] = $full_name;
				$_SESSION['payment_confirmation_reservation_ids'] = array_map(
					static fn($reservation) => (int) $reservation['reservation_id'],
					$reservations
				);
				unset($_SESSION['checkout_reservation_ids']);
				header('Location: customer-landing-page.php?payment_submitted=1');
				exit;
				} catch (Throwable $exception) {
					$conn->rollback();
					if (is_file($receipt_target_path)) {
						unlink($receipt_target_path);
					}
					$error_message = 'Unable to submit checkout right now. Please try again.';
				}
			}
		}
	}
}

$booking = $reservations[0];
$total = $court_total + $rental_total;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Checkout | Paddle Yard</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="checkout-page">
	<?php $customer_active_nav = 'court'; include __DIR__ . '/customer-header.php'; ?>

	<main class="checkout-main" data-rental-prices="<?php echo htmlspecialchars(json_encode(array_map('floatval', array_column($available_paddles, 'rental_price'))), ENT_QUOTES); ?>" data-court-total="<?php echo htmlspecialchars((string) $court_total, ENT_QUOTES); ?>" data-booking-hours="<?php echo count($reservations); ?>">
		<header class="checkout-heading">
			<p class="checkout-eyebrow">Checkout</p>
			<h1>Complete Your Booking</h1>
			<p>Review your booking details and enter your information.</p>
		</header>

		<?php if ($error_message !== ''): ?>
			<p class="checkout-alert" role="alert"><?php echo htmlspecialchars($error_message); ?></p>
		<?php endif; ?>
		<?php if ($success_message !== ''): ?>
			<p class="checkout-alert checkout-alert-success" role="status"><?php echo htmlspecialchars($success_message); ?></p>
		<?php endif; ?>

		<div class="checkout-layout">
			<aside class="checkout-summary" aria-labelledby="checkout-summary-title">
				<h2 id="checkout-summary-title">Booking Summary</h2>
				<?php if (!empty($booking['court_photo_path'])): ?>
					<img class="checkout-court-image" src="../<?php echo htmlspecialchars($booking['court_photo_path']); ?>" alt="<?php echo htmlspecialchars($booking['court_name']); ?>">
				<?php endif; ?>
				<dl class="checkout-details">
					<div><dt>Court</dt><dd><?php echo htmlspecialchars($booking['court_name']); ?></dd></div>
					<div><dt>Date</dt><dd><?php echo htmlspecialchars(date('D, M j, Y', strtotime($booking['reservation_date']))); ?></dd></div>
					<?php foreach ($reservations as $reservation): ?>
						<div><dt>Time</dt><dd><?php echo htmlspecialchars(date('g:i A', strtotime($reservation['start_time'])) . ' - ' . date('g:i A', strtotime($reservation['end_time']))); ?></dd></div>
					<?php endforeach; ?>
					<div><dt>Location</dt><dd><?php echo htmlspecialchars($booking['location']); ?></dd></div>
					<div><dt>Rate</dt><dd>₱<?php echo number_format((float) $booking['hourly_rate'], 2); ?> / hour</dd></div>
				</dl>
				<div class="checkout-line-item"><span>Court fee (<?php echo count($reservations); ?> hour<?php echo count($reservations) === 1 ? '' : 's'; ?>)</span><strong>₱<?php echo number_format($court_total, 2); ?></strong></div>
				<div class="checkout-line-item"><span>Paddle rental</span><strong id="checkout-rental-total">₱<?php echo number_format($rental_total, 2); ?></strong></div>
				<div class="checkout-grand-total"><span>Total amount</span><strong id="checkout-total">₱<?php echo number_format($total, 2); ?></strong></div>
			</aside>

			<section class="checkout-form-panel" aria-labelledby="checkout-information-title">
				<h2 id="checkout-information-title">Your Information</h2>
				<form method="post" action="payment.php" enctype="multipart/form-data">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['checkout_csrf_token']); ?>">
					<div class="checkout-fields">
						<div class="checkout-field">
							<label for="full_name">Full name</label>
							<input id="full_name" name="full_name" type="text" maxlength="150" value="<?php echo htmlspecialchars($_POST['full_name'] ?? $account['full_name']); ?>" required>
						</div>
						<div class="checkout-field">
							<label for="email">Email address</label>
							<input id="email" name="email" type="email" maxlength="254" value="<?php echo htmlspecialchars($_POST['email'] ?? $account['email']); ?>" required>
						</div>
						<div class="checkout-field">
							<label for="contact_number">Phone number</label>
							<input id="contact_number" name="contact_number" type="tel" maxlength="30" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? $account['contact_number'] ?? ''); ?>" required>
						</div>
					</div>

					<section class="checkout-payment-method" aria-labelledby="checkout-payment-title">
						<h3 id="checkout-payment-title">Pay with QR</h3>
						<?php if ($payment_qr): ?>
							<img class="checkout-qr-image" src="../<?php echo htmlspecialchars($payment_qr['qr_image_path']); ?>" alt="Payment QR code">
							<p><strong>Account name:</strong> <?php echo htmlspecialchars($payment_qr['qr_account_name']); ?></p>
							<p><strong>Account / QR number:</strong> <?php echo htmlspecialchars($payment_qr['qr_number']); ?></p>
						<?php else: ?>
							<p>QR payment is not available yet. Please contact Paddle Yard staff.</p>
						<?php endif; ?>
					</section>

					<div class="checkout-receipt-field">
						<label for="payment_receipt">Payment receipt</label>
						<input id="payment_receipt" name="payment_receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
						<p>Upload a JPG, PNG, or WEBP receipt (5 MB maximum).</p>
					</div>

					<div class="checkout-addon">
						<div>
							<h3>Paddle rental</h3>
							<p><?php echo count($available_paddles) > 0 ? '₱' . number_format((float) $available_paddles[0]['rental_price'], 2) . ' per paddle, per hour' : 'No paddles are currently available'; ?></p>
						</div>
						<label class="checkout-quantity" for="rental_quantity">
							<span class="visually-hidden">Paddle quantity</span>
							<input id="rental_quantity" name="rental_quantity" type="number" min="0" max="<?php echo count($available_paddles); ?>" value="<?php echo (int) $rental_quantity; ?>" <?php echo count($available_paddles) === 0 ? 'disabled' : ''; ?>>
						</label>
					</div>

					<div class="checkout-actions">
						<a class="checkout-back" href="court.php?court_id=<?php echo (int) $booking['court_id']; ?>&amp;reservation_date=<?php echo urlencode($booking['reservation_date']); ?>&amp;check_availability=1">Back to courts</a>
						<button class="checkout-submit" type="submit" <?php echo $payment_qr ? '' : 'disabled'; ?>>Submit Receipt</button>
					</div>
					<p class="checkout-note">Your reservation will remain pending until payment is verified.</p>
				</form>
			</section>
		</div>
	</main>
	<script>
		const checkout = document.querySelector('.checkout-main');
		const quantityInput = document.querySelector('#rental_quantity');
		const rentalTotal = document.querySelector('#checkout-rental-total');
		const checkoutTotal = document.querySelector('#checkout-total');
		const prices = JSON.parse(checkout.dataset.rentalPrices);
		const courtTotal = Number(checkout.dataset.courtTotal);
		const bookingHours = Number(checkout.dataset.bookingHours);

		quantityInput.addEventListener('input', () => {
			const quantity = Math.max(0, Math.min(Number(quantityInput.value) || 0, prices.length));
			const rentals = prices.slice(0, quantity).reduce((sum, price) => sum + Number(price), 0) * bookingHours;
			rentalTotal.textContent = `₱${rentals.toFixed(2)}`;
			checkoutTotal.textContent = `₱${(courtTotal + rentals).toFixed(2)}`;
		});
	</script>
</body>
</html>
