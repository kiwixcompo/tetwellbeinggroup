<?php
/**
 * Tet Wellbeing Group - Reset Password
 */
require_once 'db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!$token || !$email) {
    header('Location: login.php');
    exit;
}

$is_valid = false;
$user_id = null;

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE email = ? AND reset_token = ?");
        $stmt->execute([$email, $token]);
        $user = $stmt->fetch();
        if ($user) {
            if (strtotime($user['reset_token_expires']) >= time()) {
                $is_valid = true;
                $user_id = $user['id'];
            } else {
                $error = "This password reset link has expired.";
            }
        } else {
            $error = "Invalid password reset link.";
        }
    } catch (PDOException $e) {}
} else {
    if (isset($_SESSION['mock_users'][$email])) {
        $mock_user = $_SESSION['mock_users'][$email];
        if (($mock_user['reset_token'] ?? '') === $token) {
            if (strtotime($mock_user['reset_token_expires'] ?? '1970-01-01') >= time()) {
                $is_valid = true;
                $user_id = $mock_user['id'];
            } else {
                $error = "This password reset link has expired.";
            }
        } else {
            $error = "Invalid password reset link.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        if ($db_connected && $pdo) {
            try {
                $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                $upd->execute([$hashed_pass, $user_id]);
                $success = "Password reset successfully. You can now log in.";
                $is_valid = false;
            } catch (PDOException $e) {
                $error = "Database error resetting password.";
            }
        } else {
            $_SESSION['mock_users'][$email]['password'] = $hashed_pass;
            $_SESSION['mock_users'][$email]['reset_token'] = null;
            $_SESSION['mock_users'][$email]['reset_token_expires'] = null;
            $success = "Password reset successfully. You can now log in.";
            $is_valid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { bg: '#F7F5F0', sage: '#5E8C71', sageHover: '#4D755D', slate: '#264653', coral: '#E76F51' } }, fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], outfit: ['Outfit', 'sans-serif'] } } }
        }
    </script>
</head>
<body class="bg-brand-bg min-h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <a href="index.php" class="inline-flex items-center">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-28 w-auto">
            </a>
            <h2 class="mt-6 text-3xl font-bold font-outfit text-brand-slate">Set New Password</h2>
        </div>

        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100">
            <?php if (!empty($success)): ?>
                <div class="p-4 mb-6 rounded-2xl bg-brand-sageLight text-brand-sage border border-brand-sage/20 text-sm">
                    <strong>Success!</strong> <?php echo $success; ?>
                </div>
                <div class="text-center">
                    <a href="login.php" class="inline-block px-6 py-3 rounded-xl bg-brand-sage text-white font-bold transition-all hover:bg-brand-sageHover">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="p-4 mb-6 rounded-2xl bg-brand-coral/10 text-brand-slate border border-brand-coral/20 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_valid): ?>
            <form method="POST" action="reset-password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>" class="space-y-4">
                <div>
                    <label for="password" class="block text-xs font-semibold text-brand-slate mb-1">New Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 pr-10 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate" placeholder="Minimum 6 characters">
                        <button type="button" onclick="togglePassword('password', 'eye-icon-1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-brand-sage focus:outline-none">
                            <svg id="eye-icon-1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-xs font-semibold text-brand-slate mb-1">Confirm Password</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" required class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 pr-10 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate" placeholder="Re-enter password">
                        <button type="button" onclick="togglePassword('confirm_password', 'eye-icon-2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-brand-sage focus:outline-none">
                            <svg id="eye-icon-2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 mt-4 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95">
                    Reset Password
                </button>
            </form>
            <?php elseif (empty($success)): ?>
                <div class="text-center mt-4">
                    <a href="forgot-password.php" class="text-brand-sage hover:underline font-medium text-sm">Request a new link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
</body>
</html>
