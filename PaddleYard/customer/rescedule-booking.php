<?php
include '../server-side/customer_auth_guard.php';

date_default_timezone_set('Asia/Manila');
$account_id = (int) $_SESSION['account_id'];
$reservation_id = filter_input(INPUT_GET, 'reservation_id', FILTER_VALIDATE_INT);
$reservation_id = $reservation_id ?: filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
$today = date('Y-m-d');
$error_message = '';
$new_date = trim((string) ($_POST['new_date'] ?? $today));
$new_start_time = trim((string) ($_POST['new_start_time'] ?? ''));

if (empty($_SESSION['reschedule_request_token'])) {
	$_SESSION['reschedule_request_token'] = bin2hex(random_bytes(32));
}

if (!$reservation_id || $reservation_id < 1) {
	http_response_code(404);
	exit('Reservation not found.');
}

$reservation_statement = $conn->prepare(
	"SELECT r.reservation_id, r.court_id, r.reservation_date, r.start_time, r.end_time, r.reservation_status,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$posted_token = (string) ($_POST['csrf_token'] ?? '');
	$parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $new_date);
	$date_errors = DateTimeImmutable::getLastErrors();
	$date_is_valid = $parsed_date !== false
		&& ($date_errors === false || ($date_errors['warning_count'] === 0 && $date_errors['error_count'] === 0))
		&& $parsed_date->format('Y-m-d') === $new_date
		&& $new_date >= $today;
	$allowed_times = array_map(static fn($hour) => sprintf('%02d:00:00', $hour), range(5, 22));

	if (!hash_equals($_SESSION['reschedule_request_token'], $posted_token)) {
		$error_message = 'Your session expired. Refresh the page and try again.';
	} elseif (!$date_is_valid || !in_array($new_start_time, $allowed_times, true)) {
		$error_message = 'Choose a valid future date and an available hourly start time.';
	} elseif ($new_date === $today && $new_start_time <= date('H:i:s')) {
		$error_message = 'Choose a time that has not already started.';
	} else {
		$new_end_time = date('H:i:s', strtotime($new_start_time . ' +1 hour'));
		$conn->begin_transaction();
		try {
			$lock_statement = $conn->prepare('SELECT reservation_date, start_time, end_time, reservation_status FROM reservations WHERE reservation_id = ? AND account_id = ? FOR UPDATE');
			$lock_statement->bind_param('ii', $reservation_id, $account_id);
			$lock_statement->execute();
			$locked_reservation = $lock_statement->get_result()->fetch_assoc();
			$lock_statement->close();

			if (!$locked_reservation || !in_array($locked_reservation['reservation_status'], ['pending', 'confirmed'], true)
				|| strtotime($locked_reservation['reservation_date'] . ' ' . $locked_reservation['start_time']) <= time()) {
				throw new RuntimeException('This reservation can no longer be rescheduled.');
			}

			$pending_statement = $conn->prepare("SELECT reschedule_id FROM reschedules WHERE reservation_id = ? AND status = 'pending' LIMIT 1");
			$pending_statement->bind_param('i', $reservation_id);
			$pending_statement->execute();
			$has_pending_request = $pending_statement->get_result()->fetch_assoc() !== null;
			$pending_statement->close();
			if ($has_pending_request) {
				throw new RuntimeException('A reschedule request is already awaiting review.');
			}
			$cancellation_statement = $conn->prepare('SELECT cancellation_id FROM cancellations WHERE reservation_id = ? LIMIT 1');
			$cancellation_statement->bind_param('i', $reservation_id);
			$cancellation_statement->execute();
			$has_cancellation_request = $cancellation_statement->get_result()->fetch_assoc() !== null;
			$cancellation_statement->close();
			if ($has_cancellation_request) {
				throw new RuntimeException('A cancellation request is already awaiting review.');
			}

			$conflict_statement = $conn->prepare(
				"SELECT reservation_id
				 FROM reservations
				 WHERE court_id = ? AND reservation_id <> ? AND reservation_date = ?
				   AND reservation_status <> 'cancelled' AND start_time < ? AND end_time > ?
				   AND (reservation_status IN ('confirmed', 'checked_in', 'completed')
					OR EXISTS (SELECT 1 FROM payments WHERE payments.reservation_id = reservations.reservation_id AND payments.payment_status = 'verified'))
				 LIMIT 1 FOR UPDATE"
			);
			$court_id = (int) $reservation['court_id'];
			$conflict_statement->bind_param('iisss', $court_id, $reservation_id, $new_date, $new_end_time, $new_start_time);
			$conflict_statement->execute();
			$has_conflict = $conflict_statement->get_result()->fetch_assoc() !== null;
			$conflict_statement->close();
			if ($has_conflict) {
				throw new RuntimeException('That time is already booked. Choose another slot.');
			}

			$starts_at = strtotime($locked_reservation['reservation_date'] . ' ' . $locked_reservation['start_time']);
			$cutoff_status = $starts_at - time() >= 86400 ? 'eligible' : 'late';
			$insert_statement = $conn->prepare("INSERT INTO reschedules (reservation_id, new_date, new_start_time, new_end_time, cutoff_status, status) VALUES (?, ?, ?, ?, ?, 'pending')");
			$insert_statement->bind_param('issss', $reservation_id, $new_date, $new_start_time, $new_end_time, $cutoff_status);
			if (!$insert_statement->execute()) {
				throw new RuntimeException('Unable to submit your reschedule request.');
			}
			$insert_statement->close();
			$conn->commit();
			$_SESSION['booking_history_notice'] = 'Your reschedule request was sent for review.';
			header('Location: book-history.php');
			exit;
		} catch (Throwable $exception) {
			$conn->rollback();
			$error_message = $exception->getMessage();
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reschedule Booking | Paddle Yard</title>
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="booking-page booking-history-page">
	<?php $customer_active_nav = 'history'; include __DIR__ . '/customer-header.php'; ?>
	<main class="booking-history-main">
		<header class="booking-history-heading">
			<p class="booking-eyebrow">Reservation #<?php echo (int) $reservation['reservation_id']; ?></p>
			<h1>Request reschedule</h1>
			<p><?php echo htmlspecialchars($reservation['court_name']); ?>, currently <?php echo htmlspecialchars(date('D, M j, Y', strtotime($reservation['reservation_date']))); ?> at <?php echo htmlspecialchars(date('g:i A', strtotime($reservation['start_time']))); ?></p>
		</header>
		<?php if ($error_message !== ''): ?><p class="booking-history-notice booking-history-error" role="alert"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>
		<form class="booking-action-form" method="post">
			<input type="hidden" name="reservation_id" value="<?php echo (int) $reservation_id; ?>">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['reschedule_request_token']); ?>">
			<label for="new_date">New date</label>
			<input id="new_date" type="date" name="new_date" min="<?php echo htmlspecialchars($today); ?>" value="<?php echo htmlspecialchars($new_date); ?>" required>
			<label for="new_start_time">New start time</label>
			<select id="new_start_time" name="new_start_time" required>
				<option value="">Choose a start time</option>
				<?php for ($hour = 5; $hour < 23; $hour++): ?>
					<?php $slot_time = sprintf('%02d:00:00', $hour); ?>
					<option value="<?php echo $slot_time; ?>" <?php echo $new_start_time === $slot_time ? 'selected' : ''; ?>><?php echo htmlspecialchars(date('g:i A', strtotime($slot_time))); ?></option>
				<?php endfor; ?>
			</select>
			<p>Requests within 24 hours of the current booking are marked late for staff review. The new slot is checked for conflicts before submission.</p>
			<div class="booking-action-form-buttons">
				<a class="history-receipt-link" href="book-history.php">Back to history</a>
				<button class="history-receipt-link" type="submit">Send reschedule request</button>
			</div>
		</form>
	</main>
</body>
</html>
