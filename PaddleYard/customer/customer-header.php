<?php
$customer_active_nav = $customer_active_nav ?? '';
$customer_name = $_SESSION['full_name'] ?? 'Customer';
?>
<header class="customer-header booking-header">
	<h1 class="logo">
		<a href="customer-landing-page.php">
			<img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
			<span>Paddle Yard</span>
		</a>
	</h1>
	<nav aria-label="Main Navigation">
		<ul>
			<li><a class="<?php echo $customer_active_nav === 'home' ? 'active' : ''; ?>" href="customer-landing-page.php">Home</a></li>
			<li><a href="customer-landing-page.php#about-us">About</a></li>
			<li><a href="customer-landing-page.php#how-it-works">How It Works</a></li>
			<li><a class="<?php echo $customer_active_nav === 'court' ? 'active' : ''; ?>" href="court.php">Court</a></li>
			<li class="profile-menu-item">
				<button class="profile-trigger" type="button" aria-label="Open profile menu" aria-haspopup="dialog" aria-controls="customer-profile-menu">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"></path></svg>
				</button>
				<dialog class="profile-dialog" id="customer-profile-menu" aria-labelledby="profile-menu-title">
					<header class="profile-dialog-heading">
						<span class="profile-dialog-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"></path></svg>
						</span>
						<span class="profile-dialog-identity">
							<span id="profile-menu-title">Signed in as</span>
							<strong><?php echo htmlspecialchars($customer_name); ?></strong>
						</span>
						<button class="profile-dialog-close" type="button" aria-label="Close profile menu">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
						</button>
					</header>
					<nav class="profile-dialog-actions" aria-label="Profile actions">
						<a class="profile-dialog-link" href="book-history.php">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8l0 4l2 2"></path><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"></path></svg>
							<span>Booking History</span>
						</a>
						<a class="profile-dialog-link" href="profile.php">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path></svg>
							<span>Profile</span>
						</a>
						<form action="../server-side/logout.php" method="post">
							<input type="hidden" name="logout" value="1">
							<button class="profile-dialog-link profile-dialog-logout" type="submit">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 8v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"></path><path d="M7 12h14l-3-3m0 6l3-3"></path></svg>
								<span>Log Out</span>
							</button>
						</form>
					</nav>
				</dialog>
			</li>
		</ul>
	</nav>
</header>
<script>
	const profileTrigger = document.querySelector('.profile-trigger');
	const profileDialog = document.querySelector('#customer-profile-menu');
	const profileDialogClose = document.querySelector('.profile-dialog-close');

	if (profileTrigger && profileDialog && profileDialogClose) {
		profileTrigger.addEventListener('click', () => profileDialog.showModal());
		profileDialogClose.addEventListener('click', () => profileDialog.close());
		profileDialog.addEventListener('click', (event) => {
			if (event.target === profileDialog) {
				profileDialog.close();
			}
		});
	}
</script>