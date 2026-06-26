<?php
/**
 * Tet Wellbeing Group - Email Verification Page
 */
require_once 'db.php';

$error = '';
$success = '';

$email = $_GET['email'] ?? '';

if (!$email && isset($_SESSION['user_id'])) {
    // If logged in but checking verification, use session email
    $email = $_SESSION['email'] ?? '';
}

if (!$email) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = preg_replace('/[^0-9]/', '', $_POST['code1'] . $_POST['code2'] . $_POST['code3'] . $_POST['code4'] . $_POST['code5'] . $_POST['code6']);
    
    if (strlen($submittedCode) !== 6) {
        $error = "Please enter the complete 6-digit code.";
    } else {
        $verified = false;
        
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    if ($user['email_verified']) {
                        $error = "This account is already verified. You can log in.";
                    } elseif ($user['verification_code'] === $submittedCode) {
                        if (strtotime($user['verification_expires']) >= time()) {
                            // Success!
                            $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL, account_status = 'active' WHERE id = ?");
                            $upd->execute([$user['id']]);
                            $verified = true;
                        } else {
                            $error = "This verification code has expired. Please log in to request a new one.";
                        }
                    } else {
                        $error = "Invalid verification code. Please try again.";
                    }
                } else {
                    $error = "Account not found.";
                }
            } catch (PDOException $e) {
                $error = "Database error verifying code.";
            }
        } else {
            // Mock session logic
            if (isset($_SESSION['mock_users'][$email])) {
                $user = &$_SESSION['mock_users'][$email];
                if ($user['email_verified'] ?? 0) {
                    $error = "This account is already verified. You can log in.";
                } elseif (($user['verification_code'] ?? '') === $submittedCode) {
                    if (strtotime($user['verification_expires'] ?? date('Y-m-d H:i:s', time()-3600)) >= time()) {
                        $user['email_verified'] = 1;
                        $user['verification_code'] = null;
                        $user['verification_expires'] = null;
                        $user['account_status'] = 'active';
                        $verified = true;
                    } else {
                        $error = "This verification code has expired. Please log in to request a new one.";
                    }
                } else {
                    $error = "Invalid verification code. Please try again.";
                }
            } else {
                $error = "Account not found in local sandbox.";
            }
        }
        
        if ($verified) {
            header('Location: login.php?verified=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { bg: '#F7F5F0', sage: '#5E8C71', slate: '#264653', coral: '#E76F51' } }, fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], outfit: ['Outfit', 'sans-serif'] } } }
        }
    </script>
</head>
<body class="bg-brand-bg min-h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <a href="index.php" class="inline-flex items-center">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-28 w-auto">
            </a>
            <h2 class="mt-6 text-3xl font-bold font-outfit text-brand-slate">Verify your email</h2>
            <p class="text-gray-500 mt-2 text-sm">We've sent a 6-digit verification code to<br><span class="font-medium text-brand-slate"><?php echo htmlspecialchars($email); ?></span></p>
        </div>

        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100">
            <?php if (!empty($error)): ?>
                <div class="p-4 mb-6 rounded-2xl bg-brand-coral/10 text-brand-slate border border-brand-coral/20 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="verify-email.php?email=<?php echo urlencode($email); ?>" class="space-y-6">
                <div class="flex justify-between gap-2 sm:gap-4">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" name="code<?php echo $i; ?>" maxlength="1" required
                        class="w-10 h-12 sm:w-12 sm:h-14 text-center text-2xl font-bold rounded-xl border border-gray-200 focus:border-brand-sage focus:ring-brand-sage bg-gray-50 focus:bg-white transition-colors"
                        onkeyup="if(this.value.length === 1) { this.nextElementSibling ? this.nextElementSibling.focus() : this.blur(); }">
                    <?php endfor; ?>
                </div>

                <button type="submit" class="w-full py-3.5 px-4 rounded-xl shadow-lg bg-brand-sage hover:bg-brand-sage/90 text-white font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-sage transition-all">
                    Verify Account
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Didn't receive the code? <br>
                <a href="login.php?resend=<?php echo urlencode($email); ?>" class="font-medium text-brand-sage hover:text-brand-sage/80">Log in to resend</a>
            </div>
            
            <?php if (($_ENV['APP_DEBUG'] ?? 'true') === 'true'): ?>
            <div class="mt-8 p-4 bg-gray-50 rounded-xl text-xs text-gray-400 border border-dashed border-gray-200">
                <strong>Developer Sandbox:</strong> Check <code>public/verification_codes.txt</code> or <code>storage/emails/</code> for the code.
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
