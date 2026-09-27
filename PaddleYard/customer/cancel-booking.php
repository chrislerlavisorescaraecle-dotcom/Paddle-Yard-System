<?php
include '../server-side/customer_auth_guard.php';

date_default_timezone_set('Asia/Manila');
$account_id = (int) $_SESSION['account_id'];
$reservation_id = filter_input(INPUT_GET, 'reservation_id', FILTER_VALIDATE_INT);
$reservation_id = $reservation_id ?: filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
$error_message = '';
$reason = '';

if (empty($_SESSION['cancellation_request_token'])) {
	$_SESSION['cancellation_request_token'] = bin2hex(random_bytes(32));
}

if (!$reservation_id || $reservation_id < 1) {
	http_response_code(404);
	exit('Reservation not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$posted_token = (string) ($_POST['csrf_token'] ?? '');
	$reason = trim((string) ($_POST['reason'] ?? ''));

	if (!hash_equals($_SESSION['cancellation_request_token'], $posted_token)) {
		$error_message = 'Your session expired. Refresh the page and try again.';
	} elseif ($reason === '' || strlen($reason) > 1000) {
		$error_message = 'Enter a cancellation reason of up to 1,000 characters.';
	} else {
		$conn->begin_transaction();
		try {
			$lock_statement = $conn->prepare('SELECT reservation_date, start_time, end_time, reservation_status FROM reservations WHERE reservation_id = ? AND account_id = ? FOR UPDATE');
			$lock_statement->bind_param('ii', $reservation_id, $account_id);
			$lock_statement->execute();
			$reservation = $lock_statement->get_result()->fetch_assoc();
			$lock_statement->close();

			if (!$reservation || !in_array($reservation['reservation_status'], ['pending', 'confirmed'], true)
				|| strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']) <= time()) {
				throw new RuntimeException('This reservation can no longer be cancelled.');
			}

			$existing_statement = $conn->prepare('SELECT cancellation_id FROM cancellations WHERE reservation_id = ? LIMIT 1');
			$existing_statement->bind_param('i', $reservation_id);
			$existing_statement->execute();
			$already_requested = $existing_statement->get_result()->fetch_assoc() !== null;
			$existing_statement->close();
			if ($already_requested) {
				throw new RuntimeException('A cancellation request is already awaiting review.');
			}
			$pending_reschedule = $conn->prepare("SELECT reschedule_id FROM reschedules WHERE reservation_id = ? AND status = 'pending' LIMIT 1");
			$pending_reschedule->bind_param('i', $reservation_id);
			$pending_reschedule->execute();
			$has_pending_reschedule = $pending_reschedule->get_result()->fetch_assoc() !== null;
			$pending_reschedule->close();
			if ($has_pending_reschedule) {
				throw new RuntimeException('A reschedule request is already awaiting review.');
			}

			$starts_at = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
			$cutoff_status = $starts_at - time() >= 86400 ? 'eligible' : 'late';
			$insert_statement = $conn->prepare('INSERT INTO cancellations (reservation_id, reason, cutoff_status) VALUES (?, ?, ?)');
			$insert_statement->bind_param('iss', $reservation_id, $reason, $cutoff_status);
			if (!$insert_statement->execute()) {
				throw new RuntimeException('Unable to submit your cancellation request.');
			}
			$insert_statement->close();
			$conn->commit();
			$_SESSION['booking_history_notice'] = 'Your cancellation request was sent for review.';
			header('Location: book-history.php');
			exit;
		} catch (Throwable $exception) {
			$conn->rollback();
			$error_message = $exception->getMessage();
		}
	}
}

$reservation_statement = $conn->prepare(
	"SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time, r.reservation_status,
	        c.court_name
	 FROM reservations r
	 JOIN courts c ON c.court_id = r.court_id
	 WHERE r.reservation_id = ? AND r.account_id = ?"
);
$reservation_statement->bind_param('ii', $reservation_id, $account_id);
$reservation_statement->execute();
$reservation = $reservation_statement->get_result()->fetch_assoc();
$reservation_statement->close();

if (!$reservation) {
	http_response_code(404);
	exit('Reservation not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Cancel Booking | Paddle Yard</title>
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="booking-page booking-history-page">
	<?php $customer_active_nav = 'history'; include __DIR__ . '/customer-header.php'; ?>
	<main class="booking-history-main">
		<header class="booking-history-heading">
			<p class="booking-eyebrow">Reservation #<?php echo (int) $reservation['reservation_id']; ?></p>
			<h1>Request cancellation</h1>
			<p><?php echo htmlspecialchars($reservation['court_name']); ?>, <?php echo htmlspecialchars(date('D, M j, Y', strtotime($reservation['reservation_date']))); ?> at <?php echo htmlspecialchars(date('g:i A', strtotime($reservation['start_time']))); ?></p>
		</header>
		<?php if ($error_message !== ''): ?><p class="booking-history-notice booking-history-error" role="alert"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>
		<form class="booking-action-form" method="post">
			<input type="hidden" name="reservation_id" value="<?php echo (int) $reservation_id; ?>">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['cancellation_request_token']); ?>">
			<label for="reason">Reason for cancellation</label>
			<textarea id="reason" name="reason" rows="5" maxlength="1000" required><?php echo htmlspecialchars($reason); ?></textarea>
			<p>Requests made within 24 hours of the booking are marked late for staff review.</p>
			<div class="booking-action-form-buttons">
				<a class="history-receipt-link" href="book-history.php">Back to history</a>
				<button class="history-receipt-link" type="submit">Send cancellation request</button>
			</div>
		</form>
	</main>
</body>
</html>
