<?php
require_once __DIR__ . '/app/Helpers/EmailHelper.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        $helper = new \App\Helpers\EmailHelper();
        
        $subject = "Tet Wellbeing Group - Test Email";
        $body = "
            <h2>Email Configuration Test Successful!</h2>
            <p>Hello,</p>
            <p>If you are seeing this email, the mail settings for the Tet Wellbeing Group platform are functioning correctly.</p>
            <p><strong>Time of send:</strong> " . date('Y-m-d H:i:s') . "</p>
        ";
        
        $sent = $helper->sendRawEmail($email, $subject, $body);
        
        if ($sent) {
            $message = "Test email sent successfully to <strong>" . htmlspecialchars($email) . "</strong>!<br><br>Check your inbox (or the local <code>storage/emails/</code> directory if running in local sandbox mode).";
            $messageType = 'success';
        } else {
            $message = "Failed to send test email to <strong>" . htmlspecialchars($email) . "</strong>. Please check your PHP error logs or mail configuration.";
            $messageType = 'error';
        }
    } else {
        $message = "Please enter a valid email address.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2 { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-[#264653] mb-2">Email Testing Tool</h1>
            <p class="text-sm text-gray-500">Send a quick test message to verify the platform's current email configuration.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-xl text-sm <?php echo $messageType === 'success' ? 'bg-[#E8EFEA] text-[#5E8C71] border border-[#5E8C71]/20' : 'bg-[#FCEBE6] text-[#E76F51] border border-[#E76F51]/20'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Recipient Email Address</label>
                <input type="email" id="email" name="email" required placeholder="name@example.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#5E8C71] focus:ring-1 focus:ring-[#5E8C71] outline-none transition-colors text-sm bg-[#FAF9F6]">
            </div>

            <button type="submit" class="w-full bg-[#5E8C71] hover:bg-[#4D755D] text-white font-bold py-3.5 px-4 rounded-xl transition-colors shadow-md active:scale-[0.98]">
                Send Demo Mail
            </button>
        </form>
        
        <div class="mt-8 text-center">
            <a href="index.php" class="text-xs font-semibold text-[#5E8C71] hover:underline">← Back to Platform</a>
        </div>
    </div>

</body>
</html>
