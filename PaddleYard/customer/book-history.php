<?php
include '../server-side/customer_auth_guard.php';

date_default_timezone_set('Asia/Manila');
$account_id = (int) $_SESSION['account_id'];
$history_statement = $conn->prepare(
	"SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time, r.reservation_status,
	        c.court_name, c.location, p.amount, p.payment_status, p.payment_method,
	        p.payment_screenshot_path, p.payment_date,
	        (SELECT cancellation_id FROM cancellations WHERE reservation_id = r.reservation_id LIMIT 1) AS pending_cancellation_id,
	        (SELECT reschedule_id FROM reschedules WHERE reservation_id = r.reservation_id AND status = 'pending' LIMIT 1) AS pending_reschedule_id
	 FROM reservations r
	 JOIN courts c ON c.court_id = r.court_id
	 LEFT JOIN payments p ON p.payment_id = (
		 SELECT MAX(latest_payment.payment_id)
		 FROM payments latest_payment
		 WHERE latest_payment.reservation_id = r.reservation_id
	 )
	 WHERE r.account_id = ?
	 ORDER BY r.reservation_date DESC, r.start_time DESC"
);
$history_statement->bind_param('i', $account_id);
$history_statement->execute();
$reservations = $history_statement->get_result()->fetch_all(MYSQLI_ASSOC);
$history_statement->close();
$history_notice = $_SESSION['booking_history_notice'] ?? '';
unset($_SESSION['booking_history_notice']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Booking History | Paddle Yard</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="booking-page booking-history-page">
	<?php include __DIR__ . '/customer-header.php'; ?>

	<main class="booking-history-main">
		<header class="booking-history-heading">
			<p class="booking-eyebrow">Your reservations</p>
			<h1>Booking History</h1>
			<p>Current and previous court bookings, payment status, and submitted receipts.</p>
		</header>

		<?php if ($reservations): ?>
			<?php if ($history_notice !== ''): ?>
				<p class="booking-history-notice" role="status"><?php echo htmlspecialchars($history_notice); ?></p>
			<?php endif; ?>
			<div class="booking-history-table-wrap">
				<table class="booking-history-table">
					<thead>
						<tr>
							<th scope="col">Reservation</th>
							<th scope="col">Court</th>
							<th scope="col">Date &amp; time</th>
							<th scope="col">Reservation status</th>
							<th scope="col">Payment</th>
							<th scope="col">Receipt</th>
							<th scope="col">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($reservations as $reservation): ?>
							<tr>
								<td>#<?php echo (int) $reservation['reservation_id']; ?></td>
								<td>
									<strong><?php echo htmlspecialchars($reservation['court_name']); ?></strong>
									<span><?php echo htmlspecialchars($reservation['location']); ?></span>
								</td>
								<td>
									<strong><?php echo htmlspecialchars(date('D, M j, Y', strtotime($reservation['reservation_date']))); ?></strong>
									<span><?php echo htmlspecialchars(date('g:i A', strtotime($reservation['start_time'])) . ' - ' . date('g:i A', strtotime($reservation['end_time']))); ?></span>
								</td>
								<td><span class="history-status history-status-<?php echo htmlspecialchars($reservation['reservation_status']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reservation['reservation_status']))); ?></span></td>
								<td>
									<?php if ($reservation['payment_status'] !== null): ?>
										<strong>₱<?php echo number_format((float) $reservation['amount'], 2); ?></strong>
										<span class="history-status history-status-<?php echo htmlspecialchars($reservation['payment_status']); ?>"><?php echo htmlspecialchars(ucfirst($reservation['payment_status'])); ?></span>
									<?php else: ?>
										<span>Not submitted</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($reservation['payment_screenshot_path'])): ?>
										<a class="history-receipt-link" href="../<?php echo htmlspecialchars($reservation['payment_screenshot_path']); ?>" target="_blank" rel="noreferrer">View receipt</a>
									<?php else: ?>
										<span>None</span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$reservation_is_upcoming = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']) > time();
									$reservation_is_active = in_array($reservation['reservation_status'], ['pending', 'confirmed'], true);
									?>
									<?php if ($reservation_is_upcoming && $reservation_is_active && !$reservation['pending_cancellation_id'] && !$reservation['pending_reschedule_id']): ?>
										<div class="history-action-links">
											<a class="history-receipt-link" href="cancel-booking.php?reservation_id=<?php echo (int) $reservation['reservation_id']; ?>">Cancel</a>
											<a class="history-receipt-link" href="rescedule-booking.php?reservation_id=<?php echo (int) $reservation['reservation_id']; ?>">Reschedule</a>
										</div>
									<?php elseif ($reservation['pending_cancellation_id'] || $reservation['pending_reschedule_id']): ?>
										<span>Request awaiting review</span>
									<?php else: ?>
										<span>Unavailable</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php if ($history_notice !== ''): ?>
				<p class="booking-history-notice" role="status"><?php echo htmlspecialchars($history_notice); ?></p>
			<?php endif; ?>
			<p class="booking-history-empty">No reservations yet.</p>
		<?php endif; ?>
	</main>
</body>
</html>
