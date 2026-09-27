<?php
include '../server-side/admin_auth_guard.php';
include '../database/connect.php';

$upload_directory = '../image/courts/';
$upload_path = __DIR__ . '/../image/courts/';
$allowed_types = array('image/jpeg', 'image/png', 'image/webp');
$max_file_size = 5 * 1024 * 1024;
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'delete') {
		$court_id = filter_input(INPUT_POST, 'court_id', FILTER_VALIDATE_INT);

		if ($court_id) {
			$court_statement = $conn->prepare('SELECT court_photo_path FROM courts WHERE court_id = ?');
			$court_statement->bind_param('i', $court_id);
			$court_statement->execute();
			$court = $court_statement->get_result()->fetch_assoc();
			$court_statement->close();

			$delete_statement = $conn->prepare('DELETE FROM courts WHERE court_id = ?');
			$delete_statement->bind_param('i', $court_id);

			if ($delete_statement->execute()) {
				if ($court && !empty($court['court_photo_path'])) {
					$photo_path = __DIR__ . '/../' . ltrim($court['court_photo_path'], './');
					if (is_file($photo_path)) {
						unlink($photo_path);
					}
				}
				$message = 'Court deleted successfully.';
				$message_type = 'success';
			} else {
				$message = 'Unable to delete this court. It may have existing reservations.';
				$message_type = 'error';
			}
			$delete_statement->close();
		}
	}

	if ($action === 'save') {
		$court_id = filter_input(INPUT_POST, 'court_id', FILTER_VALIDATE_INT);
		$court_name = trim($_POST['court_name'] ?? '');
		$location = trim($_POST['location'] ?? '');
		$court_type = $_POST['court_type'] ?? '';
		$hourly_rate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);
		$court_status = $_POST['court_status'] ?? '';
		$current_photo_path = trim($_POST['current_photo_path'] ?? '');
		$photo_path = $current_photo_path;

		if ($court_name === '' || $location === '' || !in_array($court_type, array('indoor', 'outdoor'), true) || $hourly_rate === false || $hourly_rate < 0 || !in_array($court_status, array('available', 'maintenance', 'inactive'), true)) {
			$message = 'Please complete all court fields with valid values.';
			$message_type = 'error';
		} else {
			if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] !== UPLOAD_ERR_NO_FILE) {
				$image = $_FILES['court_image'];
				$image_type = mime_content_type($image['tmp_name']);

				if ($image['error'] !== UPLOAD_ERR_OK || !in_array($image_type, $allowed_types, true) || $image['size'] > $max_file_size) {
					$message = 'Please upload a JPG, PNG, or WEBP image up to 5 MB.';
					$message_type = 'error';
				} else {
					if (!is_dir($upload_path)) {
						mkdir($upload_path, 0755, true);
					}

					$extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
					$file_name = 'court-' . bin2hex(random_bytes(8)) . '.' . $extension;
					$file_path = $upload_path . $file_name;

					if (move_uploaded_file($image['tmp_name'], $file_path)) {
						$photo_path = 'image/courts/' . $file_name;
					} else {
						$message = 'The court image could not be uploaded.';
						$message_type = 'error';
					}
				}
			}

			if ($message_type !== 'error') {
				if ($court_id) {
					$statement = $conn->prepare('UPDATE courts SET court_name = ?, location = ?, court_status = ?, hourly_rate = ?, court_type = ?, court_photo_path = ? WHERE court_id = ?');
					$statement->bind_param('sssdssi', $court_name, $location, $court_status, $hourly_rate, $court_type, $photo_path, $court_id);
				} else {
					$statement = $conn->prepare('INSERT INTO courts (court_name, location, court_status, hourly_rate, court_type, court_photo_path) VALUES (?, ?, ?, ?, ?, ?)');
					$statement->bind_param('sssdss', $court_name, $location, $court_status, $hourly_rate, $court_type, $photo_path);
				}

				if ($statement->execute()) {
					$message = $court_id ? 'Court updated successfully.' : 'Court added successfully.';
					$message_type = 'success';
				} else {
					$message = 'Unable to save the court. Make sure the name and location are unique.';
					$message_type = 'error';
				}
				$statement->close();
			}
		}
	}
}

$edit_court = null;
$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($edit_id) {
	$statement = $conn->prepare('SELECT court_id, court_name, location, court_status, hourly_rate, court_type, court_photo_path FROM courts WHERE court_id = ?');
	$statement->bind_param('i', $edit_id);
	$statement->execute();
	$edit_court = $statement->get_result()->fetch_assoc();
	$statement->close();
}

$search = trim($_GET['search'] ?? '');
$search_value = '%' . $search . '%';
$statement = $conn->prepare('SELECT court_id, court_name, location, court_status, hourly_rate, court_type, court_photo_path FROM courts WHERE court_name LIKE ? OR location LIKE ? ORDER BY court_id DESC');
$statement->bind_param('ss', $search_value, $search_value);
$statement->execute();
$courts = $statement->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Court Setup | PaddleYard</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-layout court-admin-page">
<aside class="admin-sidebar">
	<header class="sidebar-header">
		<a href="admin-landing-page.php" class="sidebar-logo">
			<img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
			<span>PaddleYard</span>
		</a>
	</header>
	<nav class="sidebar-nav">
		<a href="admin-landing-page.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg><span>Dashboard</span></a>
		<a href="reservation_setup.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span>Reservations</span></a>
		<a href="admin_payment.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"></path><line x1="2" y1="12" x2="22" y2="12"></line><circle cx="7" cy="15" r="1"></circle></svg><span>Payments</span></a>
		<a href="court_setup.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="12" y1="3" x2="12" y2="21"></line></svg><span>Courts</span></a>
		<a href="paddle_rentals.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"></path><path d="M5 8h14"></path><path d="M5 16h14"></path><path d="M7 3h10"></path></svg><span>Paddle Rentals</span></a>
		<a href="admin_cancellation_reschedule.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"></path><polyline points="21 3 21 8 16 8"></polyline><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"></path><polyline points="3 21 3 16 8 16"></polyline></svg><span>Cancellation &amp; Reschedule</span></a>
		<a href="reports.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span>Reports</span></a>
		<a href="system_settings.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H4.4v-2.6h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1.9-1.5V4.4h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z"></path></svg><span>System Settings</span></a>
	</nav>
	<div class="sidebar-footer">
		<div class="profile-section">
			<div class="profile-info">
				<p class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></p>
				<p class="profile-role">Administrator</p>
			</div>
		</div>
		<form action="../server-side/logout.php" method="post" class="logout-form">
			<input type="hidden" name="logout" value="1">
			<button type="submit" class="logout-btn" title="Log out" aria-label="Log out">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M13 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-3"></path>
					<path d="M7 12h14l-3-3m0 6 3-3"></path>
				</svg>
			</button>
		</form>
	</div>
</aside>

<main class="admin-content dashboard-container court-admin-content">
		<section class="intro">
			<p class="eyebrow">Admin</p>
			<h1>Court Setup</h1>
			<p>Add, update, remove, and search the courts shown on the public website.</p>
		</section>

		<?php if ($message !== ''): ?>
			<p class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></p>
		<?php endif; ?>

		<section class="auth-card court-admin-form">
			<h2><?php echo $edit_court ? 'Edit Court' : 'Add Court'; ?></h2>
			<form action="court_setup.php<?php echo $edit_court ? '?edit=' . (int) $edit_court['court_id'] : ''; ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="save">
				<input type="hidden" name="court_id" value="<?php echo $edit_court ? (int) $edit_court['court_id'] : ''; ?>">
				<input type="hidden" name="current_photo_path" value="<?php echo htmlspecialchars($edit_court['court_photo_path'] ?? ''); ?>">
				<section class="form-field"><label for="court_name">Court Name</label><input id="court_name" type="text" name="court_name" placeholder="Enter court name" required value="<?php echo htmlspecialchars($edit_court['court_name'] ?? ''); ?>"></section>
				<section class="form-field"><label for="location">Location</label><input id="location" type="text" name="location" placeholder="Paddle Yard" required value="<?php echo htmlspecialchars($edit_court['location'] ?? 'Paddle Yard'); ?>"></section>
				<section class="form-field"><label for="court_type">Court Type</label><select id="court_type" name="court_type" required>
					<option value="indoor" <?php echo ($edit_court['court_type'] ?? '') === 'indoor' ? 'selected' : ''; ?>>Indoor</option>
					<option value="outdoor" <?php echo ($edit_court['court_type'] ?? '') === 'outdoor' ? 'selected' : ''; ?>>Outdoor</option>
				</select></section>
				<section class="form-field"><label for="hourly_rate">Hourly Rate</label><input id="hourly_rate" type="number" name="hourly_rate" min="0" step="0.01" placeholder="Enter hourly rate" required value="<?php echo htmlspecialchars($edit_court['hourly_rate'] ?? ''); ?>"></section>
				<section class="form-field"><label for="court_status">Status</label><select id="court_status" name="court_status" required>
					<?php foreach (array('available', 'maintenance', 'inactive') as $status): ?>
						<option value="<?php echo $status; ?>" <?php echo ($edit_court['court_status'] ?? 'available') === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
					<?php endforeach; ?>
				</select></section>
				<section class="form-field"><label for="court_image">Court Image</label><input id="court_image" type="file" name="court_image" accept="image/jpeg,image/png,image/webp">
				<?php if (!empty($edit_court['court_photo_path'])): ?>
					<p>Current image: <?php echo htmlspecialchars(basename($edit_court['court_photo_path'])); ?></p>
				<?php endif; ?>
				</section><section class="form-actions"><button class="btn" type="submit"><?php echo $edit_court ? 'Update Court' : 'Add Court'; ?></button><?php if ($edit_court): ?><a class="btn btn-outline" href="court_setup.php">Cancel Edit</a><?php endif; ?></section>
			</form>
		</section>

		<section class="section-heading court-search-panel">
			<section>
				<p class="section-kicker">Court list</p>
				<h2>Manage Courts</h2>
			</section>
			<form action="court_setup.php" method="get">
				<label for="search">Search Courts</label>
				<input id="search" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or location">
				<button class="btn btn-sm" type="submit">Search</button>
			</form>
		</section>

		<section class="court-cards court-list" id="court-list" aria-live="polite">
			<?php while ($court = $courts->fetch_assoc()): ?>
				<article class="court-card" data-court-search="<?php echo htmlspecialchars(strtolower($court['court_name'] . ' ' . $court['location'] . ' ' . $court['court_type'] . ' ' . $court['court_status'])); ?>">
					<?php if (!empty($court['court_photo_path'])): ?>
						<img src="../<?php echo htmlspecialchars($court['court_photo_path']); ?>" alt="<?php echo htmlspecialchars($court['court_name']); ?>">
					<?php endif; ?>
					<section class="court-card-content">
						<span class="court-type <?php echo htmlspecialchars($court['court_type']); ?>"><?php echo ucfirst(htmlspecialchars($court['court_type'])); ?></span>
						<header class="court-card-title"><h3><?php echo htmlspecialchars($court['court_name']); ?></h3><strong>₱<?php echo number_format((float) $court['hourly_rate'], 2); ?> / hr</strong></header>
						<p><?php echo htmlspecialchars($court['location']); ?> · <?php echo ucfirst(htmlspecialchars($court['court_status'])); ?></p>
						<a class="btn" href="court_setup.php?edit=<?php echo (int) $court['court_id']; ?>">Edit</a>
						<form action="court_setup.php" method="post">
							<input type="hidden" name="action" value="delete">
							<input type="hidden" name="court_id" value="<?php echo (int) $court['court_id']; ?>">
							<button class="btn btn-outline" type="submit">Delete</button>
						</form>
					</section>
				</article>
			<?php endwhile; ?>
		</section>
</main>
<script src="../front_end/script.js"></script>
</body>
</html>
