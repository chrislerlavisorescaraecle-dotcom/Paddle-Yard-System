<?php
include '../server-side/customer-sign-up.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Paddle Yard</title>
    <link href="https://fonts.googleapis.com/css2?family=Castoro:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1 class="logo">
        <a href="index.php">
            <img src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
            <span>Paddle Yard</span>
        </a>
    </h1>
    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="sign-in.php">Sign In</a></li>
            <li><a class="nav-button" href="sign-up.php">Book A Court</a></li>
        </ul>
    </nav>
</header>

<main>
    <section class="auth-wrapper">
        <section class="auth-card">
            <header class="auth-header">
                <img class="auth-logo" src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
                <h2>Sign Up</h2>
                <p>Create an account to reserve courts and rent paddles.</p>
            </header>
            <?php if ($error !== ''): ?>
                <p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <p class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form action="sign-up.php" method="post">
                <section class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </section>
                <section class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </section>
                <section class="form-group">
                    <label for="contact_number">Contact number</label>
                    <input type="tel" id="contact_number" name="contact_number" maxlength="30" autocomplete="tel" required>
                </section>
                <section class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </section>
                <section class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </section>
                <input type="submit" value="Sign Up">
            </form>
            <p class="auth-footer">Already have an account? <a href="sign-in.php">Sign In</a></p>
        </section>
    </section>
</main>

</body>
</html>
