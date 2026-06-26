<?php
/**
 * Tet Wellbeing Group - Forgot Password
 */
require_once 'db.php';
require_once __DIR__ . '/app/Helpers/EmailHelper.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if ($email) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $user_found = false;

        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $user_found = true;
                    $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                    $upd->execute([$token, $expires, $email]);
                }
            } catch (PDOException $e) {}
        } else {
            if (isset($_SESSION['mock_users'][$email])) {
                $user_found = true;
                $_SESSION['mock_users'][$email]['reset_token'] = $token;
                $_SESSION['mock_users'][$email]['reset_token_expires'] = $expires;
            }
        }

        // Send email regardless of user_found to prevent email enumeration attacks
        if ($user_found) {
            $emailHelper = new \App\Helpers\EmailHelper();
            try {
                $emailHelper->sendPasswordResetEmail($email, $token);
            } catch (\Exception $e) {}
        }
        
        $success = "If an account exists with that email, a password reset link has been sent.";
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Tet Wellbeing Group</title>
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
            <h2 class="mt-6 text-3xl font-bold font-outfit text-brand-slate">Reset Password</h2>
            <p class="text-gray-500 mt-2 text-sm">Enter your email to receive a password reset link.</p>
        </div>

        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100">
            <?php if (!empty($success)): ?>
                <div class="p-4 mb-6 rounded-2xl bg-brand-sageLight text-brand-sage border border-brand-sage/20 text-sm">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="p-4 mb-6 rounded-2xl bg-brand-coral/10 text-brand-slate border border-brand-coral/20 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php" class="space-y-6">
                <div>
                    <label for="email" class="block text-xs font-semibold text-brand-slate mb-1">Email Address</label>
                    <input type="email" name="email" id="email" required class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate" placeholder="e.g. mark@example.com">
                </div>

                <button type="submit" class="w-full py-3 mt-2 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95">
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Remember your password? <a href="login.php" class="font-bold text-brand-sage hover:underline">Log in</a>
            </div>
            
            <?php if (($_ENV['APP_DEBUG'] ?? 'true') === 'true'): ?>
            <div class="mt-8 p-4 bg-gray-50 rounded-xl text-xs text-gray-400 border border-dashed border-gray-200">
                <strong>Sandbox:</strong> Check <code>public/reset_links.txt</code>.
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
