<?php
include '../server-side/customer_auth_guard.php';

$account_id = (int) $_SESSION['account_id'];
$statement = $conn->prepare('SELECT full_name, email, contact_number, password_hash FROM accounts WHERE account_id = ? AND role = ?');
$customer_role = 'customer';
$statement->bind_param('is', $account_id, $customer_role);
$statement->execute();
$account = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$account) {
	http_response_code(404);
	exit('Customer account not found.');
}

if (empty($_SESSION['profile_csrf_token'])) {
	$_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = isset($_GET['contact_updated'])
	? 'Your contact number has been updated.'
	: (isset($_GET['updated']) ? 'Your password has been updated.' : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$csrf_token = $_POST['csrf_token'] ?? '';

	if (!is_string($csrf_token) || !hash_equals($_SESSION['profile_csrf_token'], $csrf_token)) {
		$error_message = 'Your session expired. Refresh the page and try again.';
	} elseif (($_POST['action'] ?? '') === 'update_contact') {
		$contact_number = trim((string) ($_POST['contact_number'] ?? ''));
		if ($contact_number === '' || strlen($contact_number) > 30) {
			$error_message = 'Enter a contact number up to 30 characters long.';
		} else {
			$update_contact = $conn->prepare('UPDATE accounts SET contact_number = ? WHERE account_id = ? AND role = ?');
			$update_contact->bind_param('sis', $contact_number, $account_id, $customer_role);

			if ($update_contact->execute()) {
				$update_contact->close();
				header('Location: profile.php?contact_updated=1');
				exit;
			}

			$error_message = 'Unable to update your contact number right now.';
			$update_contact->close();
		}
	} else {
		$current_password = $_POST['current_password'] ?? '';
		$new_password = $_POST['new_password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';

		if (!is_string($current_password) || !is_string($new_password) || !is_string($confirm_password)) {
			$error_message = 'Please enter valid password values.';
		} elseif (!password_verify($current_password, $account['password_hash'])) {
			$error_message = 'Your current password is incorrect.';
		} elseif (strlen($new_password) < 8) {
			$error_message = 'Your new password must be at least 8 characters long.';
		} elseif ($new_password !== $confirm_password) {
			$error_message = 'The new passwords do not match.';
		} elseif (password_verify($new_password, $account['password_hash'])) {
			$error_message = 'Choose a new password different from your current password.';
		} else {
			$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
			$update = $conn->prepare('UPDATE accounts SET password_hash = ? WHERE account_id = ? AND role = ?');
			$update->bind_param('sis', $new_password_hash, $account_id, $customer_role);

			if ($update->execute() && $update->affected_rows === 1) {
				$update->close();
				header('Location: profile.php?updated=1');
				exit;
			}

			$error_message = 'Unable to update your password right now.';
			$update->close();
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Profile | PaddleYard</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
</head>
<body class="landing-page booking-page profile-page">
	<?php include __DIR__ . '/customer-header.php'; ?>

	<main class="auth-wrapper">
		<section class="auth-card">
			<header class="auth-header">
				<img class="auth-logo" src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
				<h2>My Profile</h2>
				<p>Manage your account and password.</p>
			</header>

			<?php if ($error_message !== ''): ?>
				<p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error_message); ?></p>
			<?php elseif ($success_message !== ''): ?>
				<p class="alert alert-success" role="status"><?php echo htmlspecialchars($success_message); ?></p>
			<?php endif; ?>

			<div class="form-group">
				<label>Full Name</label>
				<p><?php echo htmlspecialchars($account['full_name']); ?></p>
			</div>
			<div class="form-group">
				<label>Email</label>
				<p><?php echo htmlspecialchars($account['email']); ?></p>
			</div>
			<form action="profile.php" method="post">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
				<input type="hidden" name="action" value="update_contact">
				<div class="form-group">
					<label for="contact_number">Contact Number</label>
					<input id="contact_number" type="tel" name="contact_number" maxlength="30" autocomplete="tel" value="<?php echo htmlspecialchars($account['contact_number'] ?? ''); ?>" required>
				</div>
				<input type="submit" value="Update Contact Number">
			</form>

			<form action="profile.php" method="post">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
				<div class="form-group">
					<label for="current_password">Current Password</label>
					<input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
				</div>
				<div class="form-group">
					<label for="new_password">New Password</label>
					<input id="new_password" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
				</div>
				<div class="form-group">
					<label for="confirm_password">Confirm New Password</label>
					<input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
				</div>
				<input type="submit" value="Update Password">
			</form>

			<p class="auth-footer"><a href="customer-landing-page.php">Back to PaddleYard</a></p>
		</section>
	</main>
</body>
</html>
