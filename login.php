<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $auth = Supabase::signIn($email, $password);
            $accessToken = $auth['access_token'];
            $authUser = $auth['user'];

            // Fetch this user's profile row (role, full_name) from the profiles table.
            $profileRows = Supabase::select(TBL_PROFILES, [
                'id' => 'eq.' . $authUser['id'],
                'select' => '*',
            ], $accessToken);

            $profile = $profileRows[0] ?? null;
            $role = $profile['role'] ?? 'staff';

            // Restrict web admin login to admin role only (staff and mechanics use the mobile app)
            if ($role !== 'admin') {
                $error = 'This account does not have access to the admin web portal.';
            } else {
                $_SESSION['sb_access_token'] = $accessToken;
                $_SESSION['sb_refresh_token'] = $auth['refresh_token'] ?? null;
                $_SESSION['sb_user'] = [
                    'id' => $authUser['id'],
                    'email' => $authUser['email'],
                    'name' => $profile['full_name'] ?? $authUser['email'],
                ];
                $_SESSION['sb_role'] = $role;

                header('Location: dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · WorkshopX Admin</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<video autoplay muted loop playsinline id="bg-video">
  <source src="assets/videos/bg.mp4" type="video/mp4">
</video>
<div class="login-wrap">
  <div class="login-card">
    <h1>Workshop<span style="color:#329f9f">X</span></h1>
    <p class="tag">Admin Portal</p>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group" style="text-align:left;">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>
      </div>
      <div class="form-group" style="text-align:left;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>
  </div>
</div>
</body>
</html>
