<?php
/**
 * Tet Wellbeing Group - Login Page (login.php)
 * Authenticates users using database queries or mock session accounts.
 */
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'specialist') {
        header('Location: specialist_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

// Check if redirected from a successful signup
if (isset($_GET['signup_success'])) {
    $success = "Registration successful! You can now log in using your credentials.";
}

// Handle resend code request
if (isset($_GET['resend'])) {
    $resend_email = filter_var($_GET['resend'], FILTER_VALIDATE_EMAIL);
    if ($resend_email) {
        require_once __DIR__ . '/app/Helpers/EmailHelper.php';
        $emailHelper = new \App\Helpers\EmailHelper();
        
        $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        if ($db_connected && $pdo) {
            try {
                $upd = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE email = ?");
                $upd->execute([$verificationCode, $verificationExpires, $resend_email]);
            } catch (PDOException $e) {}
        } else {
            if (isset($_SESSION['mock_users'][$resend_email])) {
                $_SESSION['mock_users'][$resend_email]['verification_code'] = $verificationCode;
                $_SESSION['mock_users'][$resend_email]['verification_expires'] = $verificationExpires;
            }
        }
        
        try {
            $emailHelper->sendVerificationEmail($resend_email, $verificationCode);
        } catch (\Exception $e) {}
        
        header('Location: verify-email.php?email=' . urlencode($resend_email) . '&resent=1');
        exit;
    }
}

// Check if verified successfully
if (isset($_GET['verified'])) {
    $success = "Email verified successfully! You can now log in.";
}

if (isset($_GET['suspended'])) {
    $error = "Your account has been suspended by an administrator. Please contact support.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $authenticated = false;
        $user_data = null;
        $is_suspended = false;

        // 1. ATTEMPT MYSQL DATABASE AUTHENTICATION
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if (isset($user['is_suspended']) && $user['is_suspended'] == 1) {
                        $is_suspended = true;
                    } else {
                        $authenticated = true;
                        $user_data = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'role' => $user['role'] ?? 'client'
                        ];
                    }
                }
            } catch (PDOException $e) {
                // Ignore DB error, fall back to session
            }
        }

        // 2. FALLBACK SESSION MOCK AUTHENTICATION
        if (!$authenticated && !$is_suspended && isset($_SESSION['mock_users'][$email])) {
            $mock_user = $_SESSION['mock_users'][$email];
            if (password_verify($password, $mock_user['password'])) {
                if (isset($mock_user['is_suspended']) && $mock_user['is_suspended'] == 1) {
                    $is_suspended = true;
                } else {
                    $authenticated = true;
                    $user_data = [
                        'id' => $mock_user['id'] ?? 999,
                        'name' => $mock_user['name'],
                        'email' => $mock_user['email'],
                        'role' => $mock_user['role'] ?? 'client'
                    ];
                }
            }
        }

        // 3. COMPLETE AUTHENTICATION OR ERROR
        if ($authenticated && $user_data) {
            // LOGIN GUARD: Check if email is verified
            $email_verified = false;
            
            // Re-fetch from DB if available
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
                    $stmt->execute([$user_data['id']]);
                    $email_verified = (bool) $stmt->fetchColumn();
                } catch (PDOException $e) {}
            } else {
                if (isset($_SESSION['mock_users'][$email]['email_verified'])) {
                    $email_verified = (bool) $_SESSION['mock_users'][$email]['email_verified'];
                }
            }

            // Bypass verification for test accounts
            if ($email === 'mark@tetwellbeing.com' || strpos($email, '@tetwellbeing') !== false || strpos(strtolower($email), 'test') !== false) {
                $email_verified = true;
            }

            if (!$email_verified) {
                // Generate fresh code and redirect
                require_once __DIR__ . '/app/Helpers/EmailHelper.php';
                $emailHelper = new \App\Helpers\EmailHelper();
                
                $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
                $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                if ($db_connected && $pdo) {
                    try {
                        $upd = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
                        $upd->execute([$verificationCode, $verificationExpires, $user_data['id']]);
                    } catch (PDOException $e) {}
                } else {
                    $_SESSION['mock_users'][$email]['verification_code'] = $verificationCode;
                    $_SESSION['mock_users'][$email]['verification_expires'] = $verificationExpires;
                }
                
                try {
                    $emailHelper->sendVerificationEmail($email, $verificationCode);
                } catch (\Exception $e) {}
                
                header('Location: verify-email.php?email=' . urlencode($email));
                exit;
            }

            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user_name'] = $user_data['name'];
            $_SESSION['user_email'] = $user_data['email'];
            $_SESSION['user_role'] = $user_data['role'];

            if ($user_data['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } elseif ($user_data['role'] === 'specialist') {
                header('Location: specialist_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            if ($is_suspended) {
                $error = "Your account has been suspended by an administrator. Please contact support.";
            } else {
                $error = "Invalid email address or password combination.";
            }
        }
    } else {
        $error = "Please provide both a valid email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',
                            sage: '#5E8C71',
                            slate: '#264653',
                            sky: '#8ECAE6',
                            coral: '#E76F51',
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            sageLight: '#E8EFEA'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-bg min-h-full flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Logo Header -->
        <div class="text-center mb-6">
            <a href="index.php" class="inline-flex items-center">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-28 w-auto">
            </a>
            <p class="text-gray-500 mt-2 text-sm">Welcome back. Access your personal care space.</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100 space-y-6">
            
            <!-- Dynamic Notifications -->
            <?php if (!empty($success)): ?>
                <div class="p-4 rounded-2xl bg-brand-sageLight text-brand-slate border border-brand-sage/20 text-xs">
                    <strong>Success:</strong> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="p-4 rounded-2xl bg-brand-coral/10 text-brand-slate border border-brand-coral/20 text-xs">
                    <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Demo helper credentials box -->
            <div class="p-4 bg-brand-sageLight/50 rounded-2xl border border-brand-sage/20 text-xs text-brand-slate space-y-2">
                <div class="flex items-center gap-2 font-bold text-brand-sage">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span>Demo Account Details</span>
                </div>
                <p class="text-gray-500 leading-relaxed">
                    We've set up a mock/demo account so you can evaluate the platform instantly. Click below to autofill or type:
                </p>
                <div class="bg-white p-2.5 rounded-xl border border-gray-100 flex items-center justify-between">
                    <div>
                        <div>Email: <code class="font-semibold text-brand-sage">mark@tetwellbeing.com</code></div>
                        <div class="mt-0.5">Password: <code class="font-semibold text-brand-sage">password123</code></div>
                    </div>
                    <button type="button" onclick="autofillDemo()" class="px-2.5 py-1.5 rounded-lg bg-brand-sage text-white text-[10px] font-bold shadow-sm hover:bg-brand-sageHover transition-all">
                        Autofill
                    </button>
                </div>
            </div>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-semibold text-brand-slate mb-1">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        required 
                        class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate"
                        placeholder="e.g. mark@example.com"
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-brand-slate mb-1">Password</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            required 
                            class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 pr-10 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate"
                            placeholder="••••••••"
                        >
                        <button type="button" onclick="togglePassword('password', 'eye-icon-login')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-brand-sage focus:outline-none">
                            <svg id="eye-icon-login" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-500 pt-1">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="hover:underline">Forgot password?</a>
                </div>

                <button type="submit" class="w-full py-3 mt-2 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95">
                    Sign In
                </button>
            </form>

            <hr class="border-gray-100">

            <div class="text-center text-xs text-gray-500">
                Don't have an account? 
                <a href="signup.php" class="font-bold text-brand-sage hover:underline">Register Here</a>
            </div>

        </div>

        <!-- Home link -->
        <div class="text-center mt-6">
            <a href="index.php" class="text-xs text-gray-500 hover:text-brand-sage font-medium flex items-center justify-center gap-1">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                Back to Homepage
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function autofillDemo() {
            document.getElementById('email').value = 'mark@tetwellbeing.com';
            document.getElementById('password').value = 'password123';
        }

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
