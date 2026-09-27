<?php
include '../server-side/customer-sign-in.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Paddle Yard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            <li><a href="sign-up.php">Sign Up</a></li>
            <li><a class="nav-button" href="sign-up.php">Book A Court</a></li>
        </ul>
    </nav>
</header>

<main>
    <section class="auth-wrapper">
        <section class="auth-card">
            <header class="auth-header">
                <img class="auth-logo" src="../image/PaddleYardLogo_NoBackground.jpg" alt="Paddle Yard logo">
                <h2>Sign In</h2>
                <p>Welcome back. Book your next court in a few clicks.</p>
            </header>
            <?php if ($success !== ''): ?>
                <p class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form action="sign-in.php" method="post">
                <section class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </section>
                <section class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </section>
                <input type="submit" value="Sign In">
            </form>
            <p class="auth-footer">New to Paddle Yard? <a href="sign-up.php">Create an account</a></p>
        </section>
    </section>
</main>

</body>
</html>
