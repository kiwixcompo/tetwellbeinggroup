<?php

namespace App\Helpers;

// PHPMailer classes - will be loaded if available

class EmailHelper
{
    private $config;
    private $mailer;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/mail.php';
        $this->setupMailer();
    }

   private function setupMailer(): void
{
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $this->mailer = null;
        error_log("PHPMailer not found - using PHP mail() function as fallback");
        return;
    }
    
    try {
        $smtpConfig = $this->config['mailers']['smtp'];
        
        // Check if SMTP is properly configured
        if (empty($smtpConfig['host']) || empty($smtpConfig['username']) || empty($smtpConfig['password'])) {
            error_log("SMTP not configured - using PHP mail() function as fallback");
            $this->mailer = null;
            return;
        }
        
        $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Enable verbose debug output (only in debug mode)
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = function($str, $level) {
                error_log("SMTP Debug [$level]: $str");
            };
        }
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $smtpConfig['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $smtpConfig['username'];
        $this->mailer->Password = $smtpConfig['password'];
        
        // Use STARTTLS encryption for port 587
        if ($smtpConfig['port'] == 587) {
            $this->mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPSecure = $smtpConfig['encryption'];
        }
        
        $this->mailer->Port = $smtpConfig['port'];
        
        // SSL options for compatibility
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => false
            )
        );
        
        $this->mailer->Timeout = 30;
        $this->mailer->SMTPKeepAlive = false;
        
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Encoding = 'base64';
        
        $this->mailer->setFrom(
            $this->config['from']['address'],
            $this->config['from']['name']
        );
        
        $this->mailer->isHTML(true);
        
        error_log("PHPMailer configured successfully with SMTP: " . $smtpConfig['host'] . ":" . $smtpConfig['port']);
        
    } catch (\Exception $e) {
        error_log("PHPMailer setup failed: " . $e->getMessage());
        $this->mailer = null;
    }
}

   public function sendVerificationEmail(string $email, string $code): bool
{
    error_log("=== SENDING VERIFICATION EMAIL ===");
    error_log("To: $email");
    error_log("Code: $code");
    
    try {
        // Save verification code to file for easy access
        $this->saveVerificationCode($email, $code);
        
        $subject = 'Verify Your Tet Wellbeing Group Account';
        $body = $this->getEmailTemplate('verification', [
            'code' => $code,
            'email' => $email,
        ]);
        
        if ($this->mailer === null) {
            // Use simple mail() function as fallback
            return $this->sendSimpleEmail($email, $subject, $body);
        }
        
        // Use PHPMailer
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($email);
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $body;
        $this->mailer->AltBody = strip_tags($body);
        
        return $this->mailer->send();
        
    } catch (\Exception $e) {
        error_log("Email sending exception: " . $e->getMessage());
        
        // Fallback to simple mail
        try {
            return $this->sendSimpleEmail($email, $subject ?? 'Verify Your Account', $body ?? '');
        } catch (\Exception $e2) {
            error_log("Fallback email also failed: " . $e2->getMessage());
        }
        
        return true; // Return true so registration continues gracefully
    }
}

    public function sendRawEmail(string $to, string $subject, string $body): bool
    {
        try {
            $this->saveEmailForDevelopment($to, $subject, $body);

            if ($this->mailer === null) {
                return $this->sendSimpleEmail($to, $subject, $body);
            }

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);
            return $this->mailer->send();
        } catch (\Exception $e) {
            error_log("sendRawEmail failed to $to: " . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetEmail(string $email, string $token): bool
    {
        try {
            $resetUrl = ($_ENV['APP_URL'] ?? 'http://localhost/TetWellbeingGroup') . '/reset-password.php?token=' . $token;
            
            $this->savePasswordResetLink($email, $resetUrl);
            
            $subject = 'Reset Your Tet Wellbeing Group Password';
            $body = $this->getEmailTemplate('password_reset', [
                'reset_url' => $resetUrl,
                'email' => $email,
            ]);
            
            if ($this->mailer === null) {
                return $this->sendSimpleEmail($email, $subject, $body);
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);
            
            return $this->mailer->send();
            
        } catch (\Exception $e) {
            error_log("Password reset email failed: " . $e->getMessage());
            try {
                return $this->sendSimpleEmail($email, $subject ?? 'Reset Password', $body ?? '');
            } catch (\Exception $e2) {
            }
            return false;
        }
    }

    private function sendSimpleEmail(string $to, string $subject, string $body): bool
    {
        // Save to file for backup/debugging
        $this->saveEmailForDevelopment($to, $subject, $body);
        
        if (strpos($subject, 'Verify') !== false) {
            preg_match('/(\d{6})/', strip_tags($body), $matches);
            if (!empty($matches[1])) {
                $this->saveVerificationCode($to, $matches[1]);
            }
        }
        
        if (strpos($subject, 'Reset') !== false || strpos($subject, 'Password') !== false) {
            preg_match('/https?:\/\/[^\s<>"]+reset-password[^\s<>"]*/', $body, $matches);
            if (!empty($matches[0])) {
                $this->savePasswordResetLink($to, $matches[0]);
            }
        }
        
        try {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . $this->config['from']['name'] . " <" . $this->config['from']['address'] . ">" . "\r\n";
            $headers .= "Reply-To: " . $this->config['from']['address'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            return @mail($to, $subject, $body, $headers);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function saveEmailForDevelopment(string $to, string $subject, string $body): void
    {
        try {
            $emailDir = __DIR__ . '/../../storage/emails';
            if (!is_dir($emailDir)) {
                mkdir($emailDir, 0755, true);
            }
            
            $filename = $emailDir . '/' . date('Y-m-d_H-i-s') . '_' . md5($to . $subject) . '.html';
            
            $emailContent = "
            <div style='background: #F7F5F0; padding: 20px; margin-bottom: 20px; border-radius: 5px; color: #264653; font-family: sans-serif;'>
                <h3 style='color: #5E8C71;'>📧 Tet Wellbeing Email Sandbox</h3>
                <p><strong>To:</strong> {$to}</p>
                <p><strong>Subject:</strong> {$subject}</p>
                <p><strong>Sent:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Method:</strong> " . ($this->mailer ? 'PHPMailer/SMTP' : 'PHP mail()') . "</p>
            </div>
            {$body}
            ";
            
            file_put_contents($filename, $emailContent);
        } catch (\Exception $e) {}
    }

    private function saveVerificationCode(string $email, string $code): void
    {
        try {
            $codesFile = __DIR__ . '/../../public/verification_codes.txt';
            $entry = date('Y-m-d H:i:s') . " | {$email} | {$code}\n";
            file_put_contents($codesFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {}
    }

    private function savePasswordResetLink(string $email, string $link): void
    {
        try {
            $linksFile = __DIR__ . '/../../public/reset_links.txt';
            $entry = date('Y-m-d H:i:s') . " | {$email} | {$link}\n";
            file_put_contents($linksFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {}
    }

    private function getEmailTemplate(string $template, array $data): string
    {
        switch ($template) {
            case 'verification':
                return $this->getVerificationTemplate($data);
            case 'password_reset':
                return $this->getPasswordResetTemplate($data);
            default:
                throw new \Exception("Unknown email template: {$template}");
        }
    }

    private function getVerificationTemplate(array $data): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Verify Your Account</title>
            <style>
                body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; line-height: 1.6; color: #264653; background-color: #F7F5F0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                .header { background: #5E8C71; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 40px 30px; }
                .code { font-size: 36px; font-weight: 800; color: #5E8C71; text-align: center; 
                        background: #E8EFEA; padding: 20px; border-radius: 12px; margin: 30px 0; 
                        letter-spacing: 8px; border: 1px dashed #5E8C71; }
                .footer { padding: 20px; text-align: center; color: #9CA3AF; font-size: 13px; border-top: 1px solid #EBE8E0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin:0; font-size: 24px;'>Tet Wellbeing Group</h1>
                    <p style='margin:10px 0 0 0; opacity: 0.9;'>Account Verification</p>
                </div>
                <div class='content'>
                    <h2 style='margin-top:0; color: #264653;'>Welcome to our community!</h2>
                    <p>Thank you for registering with Tet Wellbeing Group. To protect your privacy and secure your account, please verify your email address using the code below:</p>
                    
                    <div class='code'>{$data['code']}</div>
                    
                    <p style='color: #E76F51; font-size: 14px;'><strong>Note:</strong> This verification code will expire in 24 hours.</p>
                    <p>If you did not request this account creation, you can safely ignore this email.</p>
                </div>
                <div class='footer'>
                    <p style='margin:0;'>&copy; " . date('Y') . " Tet Wellbeing Group. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getPasswordResetTemplate(array $data): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Reset Your Password</title>
            <style>
                body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; line-height: 1.6; color: #264653; background-color: #F7F5F0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                .header { background: #5E8C71; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 40px 30px; }
                .button { display: inline-block; padding: 14px 32px; background: #5E8C71; 
                         color: white; text-decoration: none; border-radius: 12px; margin: 20px 0; font-weight: bold; }
                .footer { padding: 20px; text-align: center; color: #9CA3AF; font-size: 13px; border-top: 1px solid #EBE8E0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin:0; font-size: 24px;'>Tet Wellbeing Group</h1>
                    <p style='margin:10px 0 0 0; opacity: 0.9;'>Password Reset Request</p>
                </div>
                <div class='content'>
                    <h2 style='margin-top:0; color: #264653;'>Reset Your Password</h2>
                    <p>We received a request to reset your password for your Tet Wellbeing Group account.</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$data['reset_url']}' class='button'>Reset Password</a>
                    </div>
                    
                    <p style='color: #E76F51; font-size: 14px;'><strong>Note:</strong> This link will expire in 1 hour.</p>
                    <p>If you didn't request a password reset, please ignore this email. Your password will remain safely unchanged.</p>
                </div>
                <div class='footer'>
                    <p style='margin:0;'>&copy; " . date('Y') . " Tet Wellbeing Group. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
