<?php
/**
 * Tet Wellbeing Group - Signup/Registration Page (signup.php)
 * Creates a new user profile in the database or mock session array.
 */
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_DEFAULT);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted = isset($_POST['terms_accepted']);

    if (!$terms_accepted) {
        $error = "You must agree to the Terms of Service to create an account.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($name && $email && $password) {
        $role = filter_input(INPUT_POST, 'role', FILTER_DEFAULT);
        if ($role !== 'specialist') {
            $role = 'client';
        }

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $registered = false;
            $email_taken = false;

            // 1. ATTEMPT DATABASE REGISTRATION
            if ($db_connected && $pdo) {
                try {
                    // Check if email already registered
                    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $check->execute([$email]);
                    
                    if ($check->fetchColumn() > 0) {
                        $email_taken = true;
                    } else {
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $hashed_pass, $role]);
                        $registered = true;
                    }
                } catch (PDOException $e) {
                    // Fall back to session registration
                }
            }

            // 2. FALLBACK SESSION MOCK REGISTRATION
            if (!$registered && !$email_taken) {
                if (isset($_SESSION['mock_users'][$email])) {
                    $email_taken = true;
                } else {
                    $mock_id = count($_SESSION['mock_users']) + 10;
                    $_SESSION['mock_users'][$email] = [
                        'id' => $mock_id,
                        'name' => $name,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'is_approved' => ($role === 'specialist' ? 0 : 1),
                        'escrow_balance' => 0.00,
                        'clearance_balance' => 0.00,
                        'license_no' => NULL,
                        'bio' => NULL,
                        'hourly_rate' => NULL,
                        'archetype' => NULL
                    ];
                    $registered = true;
                }
            }

            // 3. HANDLE REDIRECT ON SUCCESS
            if ($registered) {
                require_once __DIR__ . '/app/Helpers/EmailHelper.php';
                $emailHelper = new \App\Helpers\EmailHelper();
                
                $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
                $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Update DB or Session
                if ($db_connected && $pdo) {
                    try {
                        $upd = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ?, account_status = 'pending' WHERE email = ?");
                        $upd->execute([$verificationCode, $verificationExpires, $email]);
                    } catch (PDOException $e) {}
                } else {
                    if (isset($_SESSION['mock_users'][$email])) {
                        $_SESSION['mock_users'][$email]['verification_code'] = $verificationCode;
                        $_SESSION['mock_users'][$email]['verification_expires'] = $verificationExpires;
                        $_SESSION['mock_users'][$email]['account_status'] = 'pending';
                        $_SESSION['mock_users'][$email]['email_verified'] = 0;
                    }
                }
                
                // Dispatch Email
                try {
                    $emailHelper->sendVerificationEmail($email, $verificationCode);
                } catch (\Exception $e) {
                    error_log("Email dispatch failed: " . $e->getMessage());
                }
                
                header('Location: verify-email.php?email=' . urlencode($email));
                exit;
            } elseif ($email_taken) {
                $error = "An account is already registered with this email address.";
            } else {
                $error = "An unexpected registration error occurred. Please try again.";
            }
        }
    } else {
        $error = "Please fill in all the registration fields correctly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Tet Wellbeing Group</title>
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
            <p class="text-gray-500 mt-2 text-sm">Join our digital mental health and care community.</p>
        </div>

        <!-- Registration Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-gray-100 space-y-5">

            <?php if (!empty($error)): ?>
                <div class="p-4 rounded-2xl bg-brand-coral/10 text-brand-slate border border-brand-coral/20 text-xs">
                    <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="signup.php" class="space-y-4">
                <div>
                    <label for="name" class="block text-xs font-semibold text-brand-slate mb-1">Full Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name" 
                        required 
                        class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate"
                        placeholder="e.g. Mark Mercer"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    >
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-brand-slate mb-1">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        required 
                        class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate"
                        placeholder="e.g. mark@example.com"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div>
                    <label for="role" class="block text-xs font-semibold text-brand-slate mb-1">Account Type</label>
                    <select 
                        name="role" 
                        id="role" 
                        required 
                        class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate font-semibold"
                    >
                        <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?>>Client (Seeking Support)</option>
                        <option value="specialist" <?php echo (isset($_POST['role']) && $_POST['role'] === 'specialist') ? 'selected' : ''; ?>>Clinical Specialist (Provider)</option>
                    </select>
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
                            placeholder="Minimum 6 characters"
                        >
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
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm_password" 
                            required 
                            class="w-full rounded-2xl border border-gray-200 bg-[#FAF9F6] p-3 pr-10 text-sm focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/10 transition-all text-brand-slate"
                            placeholder="Re-enter password"
                        >
                        <button type="button" onclick="togglePassword('confirm_password', 'eye-icon-2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-brand-sage focus:outline-none">
                            <svg id="eye-icon-2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Terms Acceptance -->
                <div class="flex items-start gap-2 pt-1">
                    <input 
                        type="checkbox" 
                        name="terms_accepted" 
                        id="terms_accepted" 
                        required 
                        class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-1"
                        <?php echo isset($_POST['terms_accepted']) ? 'checked' : ''; ?>
                    >
                    <label for="terms_accepted" class="text-xs text-gray-500 leading-normal">
                        I agree to the 
                        <a href="index.php" target="_blank" class="font-semibold text-brand-sage hover:underline">Terms of Service</a> 
                        and 
                        <a href="#" class="font-semibold text-brand-sage hover:underline">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="w-full py-3 mt-2 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95">
                    Create Account
                </button>
            </form>

            <hr class="border-gray-100">

            <div class="text-center text-xs text-gray-500">
                Already have an account? 
                <a href="login.php" class="font-bold text-brand-sage hover:underline">Log In</a>
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
