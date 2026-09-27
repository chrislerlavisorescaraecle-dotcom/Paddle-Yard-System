<?php
include '../server-side/customer_auth_guard.php';

date_default_timezone_set('Asia/Manila');

$account_id = (int) $_SESSION['account_id'];
$today = date('Y-m-d');
$error_message = '';
$success_message = $_SESSION['court_booking_notice'] ?? '';
unset($_SESSION['court_booking_notice']);

if (!isset($_SESSION['court_booking_token'])) {
	$_SESSION['court_booking_token'] = bin2hex(random_bytes(32));
}
$booking_token = $_SESSION['court_booking_token'];

function is_valid_booking_date($date, $today)
{
	$parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
	$date_errors = DateTimeImmutable::getLastErrors();

	return $parsed_date !== false
		&& ($date_errors === false || ($date_errors['warning_count'] === 0 && $date_errors['error_count'] === 0))
		&& $parsed_date->format('Y-m-d') === $date
		&& $date >= $today;
}

$court_value = $_GET['court_id'] ?? $_POST['court_id'] ?? '';
$selected_court_id = filter_var($court_value, FILTER_VALIDATE_INT);
$selected_court_id = $selected_court_id === false ? 0 : (int) $selected_court_id;
$selected_date = trim((string) ($_GET['reservation_date'] ?? $_POST['reservation_date'] ?? $today));
$date_was_provided = isset($_GET['reservation_date']) || isset($_POST['reservation_date']);

if (!is_valid_booking_date($selected_date, $today)) {
	if ($date_was_provided) {
		$error_message = 'Choose today or a future date.';
	}
	$selected_date = $today;
}

$allowed_start_times = [];
for ($hour = 5; $hour < 23; $hour++) {
	$allowed_start_times[] = sprintf('%02d:00:00', $hour);
}

if (($_GET['ajax'] ?? '') === 'availability') {
	header('Content-Type: application/json; charset=utf-8');
	$availability_court_id = filter_var($_GET['court_id'] ?? '', FILTER_VALIDATE_INT);
	$availability_date = trim((string) ($_GET['reservation_date'] ?? ''));

	if ($availability_court_id === false || $availability_court_id < 1 || !is_valid_booking_date($availability_date, $today)) {
		http_response_code(422);
		echo json_encode(['error' => 'Choose an available court and a valid future date.']);
		exit;
	}

	$court_check = $conn->prepare("SELECT court_id FROM courts WHERE court_id = ? AND court_status = 'available'");
	$court_check->bind_param('i', $availability_court_id);
	$court_check->execute();
	$court_exists = $court_check->get_result()->fetch_assoc() !== null;
	$court_check->close();

	if (!$court_exists) {
		http_response_code(404);
		echo json_encode(['error' => 'That court is no longer available.']);
		exit;
	}

	$busy_statement = $conn->prepare(
		"SELECT reservations.start_time, reservations.end_time
		 FROM reservations
		 WHERE reservations.court_id = ?
		   AND reservations.reservation_date = ?
		   AND reservations.reservation_status <> 'cancelled'
		   AND (reservations.reservation_status IN ('confirmed', 'checked_in', 'completed')
			OR EXISTS (
				SELECT 1
				FROM payments
				WHERE payments.reservation_id = reservations.reservation_id
				  AND payments.payment_status = 'verified'
			))"
	);
	$busy_statement->bind_param('is', $availability_court_id, $availability_date);
	$busy_statement->execute();
	$busy_result = $busy_statement->get_result();
	$busy_intervals = $busy_result->fetch_all(MYSQLI_ASSOC);
	$busy_statement->close();

	$availability_slots = [];
	$current_time = date('H:i:s');
	foreach ($allowed_start_times as $slot_start) {
		$slot_end = date('H:i:s', strtotime($slot_start . ' +1 hour'));
		$is_booked = false;

		foreach ($busy_intervals as $busy_interval) {
			if ($slot_start < $busy_interval['end_time'] && $slot_end > $busy_interval['start_time']) {
				$is_booked = true;
				break;
			}
		}

		$is_past = $availability_date === $today && $slot_start <= $current_time;
		$availability_slots[] = [
			'start' => $slot_start,
			'end' => $slot_end,
			'available' => !$is_booked && !$is_past,
			'status' => $is_booked ? 'Booked' : ($is_past ? 'Time passed' : 'Available'),
		];
	}

	echo json_encode([
		'date_label' => date('l, F j, Y', strtotime($availability_date)),
		'slots' => $availability_slots,
	]);
	exit;
}

$selected_start_times = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$posted_court_id = filter_var($_POST['court_id'] ?? '', FILTER_VALIDATE_INT);
	$posted_date = trim((string) ($_POST['reservation_date'] ?? ''));
	$posted_start_times = $_POST['start_times'] ?? [];
	$selected_start_times = is_array($posted_start_times)
		? array_values(array_unique(array_filter($posted_start_times, 'is_string')))
		: [];
	sort($selected_start_times);
	$posted_token = (string) ($_POST['booking_token'] ?? '');
	$selected_court_id = $posted_court_id === false ? 0 : (int) $posted_court_id;
	$selected_date = $posted_date;

	if (!hash_equals($booking_token, $posted_token)) {
		$error_message = 'Your session expired. Refresh the page and try again.';
	} elseif ($selected_court_id < 1 || !is_valid_booking_date($selected_date, $today)) {
		$error_message = 'Choose an available court and a valid future date.';
	} elseif (count($selected_start_times) < 1 || count($selected_start_times) !== count($posted_start_times)
		|| array_diff($selected_start_times, $allowed_start_times)) {
		$error_message = 'Choose one or more available hourly time slots.';
	} elseif ($selected_date === $today && array_filter($selected_start_times, static fn($time) => $time <= date('H:i:s'))) {
		$error_message = 'Remove time slots that have already started.';
	} else {
		$conn->begin_transaction();

		try {
			$court_statement = $conn->prepare("SELECT court_id FROM courts WHERE court_id = ? AND court_status = 'available' FOR UPDATE");
			$court_statement->bind_param('i', $selected_court_id);
			$court_statement->execute();
			$court_is_available = $court_statement->get_result()->fetch_assoc() !== null;
			$court_statement->close();

			if (!$court_is_available) {
				$conn->rollback();
				$error_message = 'That court is no longer available. Choose another court.';
			} else {
				$conflict_statement = $conn->prepare(
					"SELECT reservation_id
					 FROM reservations
					 WHERE court_id = ?
					   AND reservation_date = ?
					   AND reservation_status <> 'cancelled'
					   AND start_time < ?
					   AND end_time > ?
					   AND (reservation_status IN ('confirmed', 'checked_in', 'completed')
						OR EXISTS (
							SELECT 1
							FROM payments
							WHERE payments.reservation_id = reservations.reservation_id
							  AND payments.payment_status = 'verified'
						))
					 LIMIT 1
					 FOR UPDATE"
				);
				$insert_statement = $conn->prepare(
					"INSERT INTO reservations
						(court_id, account_id, reservation_date, start_time, end_time, reservation_status)
					 VALUES (?, ?, ?, ?, ?, 'pending')"
				);
				$checkout_reservation_ids = [];
				$has_conflict = false;

				foreach ($selected_start_times as $start_time) {
					$end_time = date('H:i:s', strtotime($start_time . ' +1 hour'));
					$conflict_statement->bind_param('isss', $selected_court_id, $selected_date, $end_time, $start_time);
					$conflict_statement->execute();
					if ($conflict_statement->get_result()->fetch_assoc() !== null) {
						$has_conflict = true;
						break;
					}

					$insert_statement->bind_param('iisss', $selected_court_id, $account_id, $selected_date, $start_time, $end_time);
					if (!$insert_statement->execute()) {
						throw new RuntimeException('Unable to create reservation.');
					}
					$checkout_reservation_ids[] = (int) $conn->insert_id;
				}

				$conflict_statement->close();
				$insert_statement->close();

				if ($has_conflict) {
					$conn->rollback();
					$error_message = 'A selected time was just booked. Please choose another slot.';
				} else {
					$conn->commit();
					$_SESSION['checkout_reservation_ids'] = $checkout_reservation_ids;
					header('Location: payment.php');
					exit;
				}
			}
		} catch (Throwable $exception) {
			$conn->rollback();
			$error_message = 'Unable to submit your reservation. Please try again.';
		}
	}
}

$court_result = $conn->query(
	"SELECT court_id, court_name, location, hourly_rate, court_type, court_photo_path
	 FROM courts
	 WHERE court_status = 'available'
	 ORDER BY court_name ASC"
);
$courts = $court_result->fetch_all(MYSQLI_ASSOC);
$court_by_id = [];
foreach ($courts as $court) {
	$court_by_id[(int) $court['court_id']] = $court;
}

if ($selected_court_id > 0 && !isset($court_by_id[$selected_court_id])) {
	$selected_court_id = 0;
	if ($error_message === '') {
		$error_message = 'That court is not currently available. Choose another court.';
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Book a Court | Paddle Yard</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="booking-page">
	<?php $customer_active_nav = 'court'; include __DIR__ . '/customer-header.php'; ?>

	<main class="booking-main">
		<header class="booking-heading">
			<p class="booking-eyebrow">Find your next match</p>
			<h1>Reserve a court</h1>
			<p>Select a court, choose a date, then pick an open one-hour slot.</p>
		</header>

		<?php if ($success_message !== ''): ?>
			<p class="booking-message booking-message-success" role="status"><?php echo htmlspecialchars($success_message); ?></p>
		<?php endif; ?>
		<?php if ($error_message !== ''): ?>
			<p class="booking-message booking-message-error" role="alert"><?php echo htmlspecialchars($error_message); ?></p>
		<?php endif; ?>

		<section class="booking-layout" aria-label="Court booking">
			<section class="booking-panel" aria-labelledby="booking-details-title">
				<header class="booking-panel-heading">
					<p class="booking-step">01</p>
					<h2 id="booking-details-title">Choose court and date</h2>
				</header>

				<?php if (count($courts) > 0): ?>
					<div class="booking-filter">
						<label for="court_id">Court</label>
						<select id="court_id" name="court_id" required>
							<option value="">Select a court</option>
							<?php foreach ($courts as $court): ?>
								<option value="<?php echo (int) $court['court_id']; ?>" <?php echo $selected_court_id === (int) $court['court_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($court['court_name'] . ' - ' . $court['location']); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<label for="reservation_date">Date</label>
						<input id="reservation_date" name="reservation_date" type="date" value="<?php echo htmlspecialchars($selected_date); ?>" min="<?php echo htmlspecialchars($today); ?>" required>

					</div>
					<section class="booking-availability" id="booking-availability" aria-live="polite" hidden></section>
				<?php else: ?>
					<p>No courts are currently available to book.</p>
				<?php endif; ?>
			</section>

			<aside class="booking-summary" aria-labelledby="booking-summary-title">
				<p class="booking-step">Court details</p>
				<?php if (isset($court_by_id[$selected_court_id])): ?>
					<?php $selected_court = $court_by_id[$selected_court_id]; ?>
					<?php if (!empty($selected_court['court_photo_path'])): ?>
						<img class="booking-court-image" src="../<?php echo htmlspecialchars($selected_court['court_photo_path']); ?>" alt="<?php echo htmlspecialchars($selected_court['court_name']); ?>">
					<?php endif; ?>
					<h2 id="booking-summary-title"><?php echo htmlspecialchars($selected_court['court_name']); ?></h2>
					<p><?php echo htmlspecialchars($selected_court['location']); ?></p>
					<p class="booking-court-type"><?php echo htmlspecialchars(ucfirst($selected_court['court_type'])); ?> court</p>
					<p class="booking-rate"><?php echo htmlspecialchars(number_format((float) $selected_court['hourly_rate'], 2)); ?> per hour</p>
				<?php else: ?>
					<h2 id="booking-summary-title">Your court</h2>
					<p>Select an available court to see its details.</p>
				<?php endif; ?>
				<p class="booking-hours">Open daily, 5:00 AM - 11:00 PM</p>
			</aside>
		</section>
	</main>
	<script>
		(() => {
			const courtInput = document.querySelector('#court_id');
			const dateInput = document.querySelector('#reservation_date');
			const availability = document.querySelector('#booking-availability');
			if (!courtInput || !dateInput || !availability) return;

			const bookingToken = <?php echo json_encode($booking_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
			const initiallySelectedSlots = <?php echo json_encode($selected_start_times, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
			let requestNumber = 0;
			let isInitialRequest = true;
			let lastRequestKey = '';

			const makeElement = (tagName, className, text) => {
				const element = document.createElement(tagName);
				if (className) element.className = className;
				if (text) element.textContent = text;
				return element;
			};

			const addHiddenInput = (form, name, value) => {
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = name;
				input.value = value;
				form.append(input);
			};

			const showMessage = (message) => {
				availability.replaceChildren(makeElement('p', '', message));
				availability.hidden = false;
			};

			const loadAvailability = async () => {
				const courtId = courtInput.value;
				const reservationDate = dateInput.value;

				if (!courtId || !reservationDate) {
				requestNumber++;
					lastRequestKey = '';
					showMessage('Select a court and date to see available times.');
					return;
				}
				const requestKey = `${courtId}:${reservationDate}`;
				if (requestKey === lastRequestKey) return;
				lastRequestKey = requestKey;
				const currentRequest = ++requestNumber;

				showMessage('Loading available times...');
				const query = new URLSearchParams({
					ajax: 'availability',
					court_id: courtId,
					reservation_date: reservationDate
				});

				try {
					const response = await fetch(`court.php?${query}`, {
						headers: { Accept: 'application/json' }
					});
					const result = await response.json();
					if (currentRequest !== requestNumber) return;
					if (!response.ok) throw new Error(result.error || 'Unable to load available times.');

					const sectionHeading = makeElement('header', 'booking-panel-heading');
					sectionHeading.append(
						makeElement('p', 'booking-step', '02'),
						makeElement('h2', '', 'Available times'),
						makeElement('p', '', result.date_label)
					);

					const form = document.createElement('form');
					form.method = 'post';
					form.action = 'court.php';
					addHiddenInput(form, 'booking_token', bookingToken);
					addHiddenInput(form, 'court_id', courtId);
					addHiddenInput(form, 'reservation_date', reservationDate);

					const fieldset = makeElement('fieldset', 'booking-slot-fieldset');
					fieldset.append(makeElement('legend', '', 'One-hour sessions'));
					const slotList = makeElement('ol', 'booking-slots');
					let hasAvailableSlots = false;

					for (const slot of result.slots) {
						const isAvailable = slot.available === true;
						const listItem = makeElement('li', isAvailable ? 'slot-open' : 'slot-unavailable');
						const label = document.createElement('label');
						const checkbox = document.createElement('input');
						const start = new Date(`1970-01-01T${slot.start}`);
						const end = new Date(`1970-01-01T${slot.end}`);
						const timeFormat = { hour: 'numeric', minute: '2-digit' };
						const timeLabel = `${start.toLocaleTimeString([], timeFormat)} - ${end.toLocaleTimeString([], timeFormat)}`;

						checkbox.type = 'checkbox';
						checkbox.name = 'start_times[]';
						checkbox.value = slot.start;
						checkbox.disabled = !isAvailable;
						checkbox.checked = isInitialRequest && initiallySelectedSlots.includes(slot.start);
						label.append(checkbox, makeElement('span', '', timeLabel), makeElement('small', '', slot.status));
						listItem.append(label);
						slotList.append(listItem);
						hasAvailableSlots ||= isAvailable;
					}

					fieldset.append(slotList);
					if (hasAvailableSlots) {
						fieldset.append(makeElement('button', 'booking-button booking-submit', 'Request reservation'));
					}
					form.append(fieldset);
					availability.replaceChildren(sectionHeading, form);
					availability.hidden = false;
				} catch (error) {
					if (currentRequest === requestNumber) {
						lastRequestKey = '';
						showMessage(error.message || 'Unable to load available times. Please try again.');
					}
				} finally {
					isInitialRequest = false;
				}
			};

			courtInput.addEventListener('change', loadAvailability);
			dateInput.addEventListener('input', loadAvailability);
			dateInput.addEventListener('change', loadAvailability);
			loadAvailability();
		})();
	</script>
</body>
</html>
