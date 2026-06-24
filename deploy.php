<?php
/**
 * Tet Wellbeing Group - cPanel Git Auto-Deployment Webhook
 * Webhook URL: https://tetwellbeinggroup.com/deploy.php?token=tet_deploy_secret_2026
 * 
 * Place this file in your public_html directory. When called, it automatically
 * pulls the latest commits from GitHub and runs the cPanel deployment tasks.
 */

// Set header for plain text output
header('Content-Type: text/plain; charset=utf-8');

// 1. Security token check
$secret_token = 'tet_deploy_secret_2026';
$provided_token = $_GET['token'] ?? '';

if (empty($provided_token) || $provided_token !== $secret_token) {
    header('HTTP/1.1 403 Forbidden');
    die("❌ Access Denied: Invalid deployment token.\n");
}

echo "========================================================\n";
echo "       TET WELLBEING GROUP - CPANEL AUTO-DEPLOY         \n";
echo "========================================================\n\n";

// 2. Discover the repository root dynamically using cPanel UAPI
echo "🔍 Discovering Git repository on cPanel...\n";
$repos_json = shell_exec('uapi --output=json VersionControl list');
$repos = json_decode($repos_json, true);

$repo_root = '';
if (isset($repos['result']['data']) && is_array($repos['result']['data'])) {
    foreach ($repos['result']['data'] as $repo) {
        // Find repository containing 'tetwellbeing' in its root path
        if (strpos(strtolower($repo['repository_root']), 'tetwellbeing') !== false) {
            $repo_root = $repo['repository_root'];
            break;
        }
    }
}

// Fallback to default path if not found dynamically
if (empty($repo_root)) {
    $repo_root = '/home/tetwellb/repositories/tetwellbeinggroup';
    echo "⚠️ Could not auto-detect repo path. Falling back to default: $repo_root\n";
} else {
    echo "✅ Found repository root: $repo_root\n\n";
}

// 3. Update from Remote (Git Pull)
echo "📥 1. Pulling latest commits from GitHub...\n";
$pull_cmd = "uapi VersionControl update repository_root=" . escapeshellarg($repo_root) . " branch=main";
$pull_output = shell_exec($pull_cmd);
echo $pull_output . "\n";

// 4. Deploy HEAD Commit (Runs tasks in .cpanel.yml)
echo "🚀 2. Deploying files to public_html...\n";
$deploy_cmd = "uapi VersionControlDeployment create repository_root=" . escapeshellarg($repo_root);
$deploy_output = shell_exec($deploy_cmd);
echo $deploy_output . "\n";

echo "🎉 Deployment process completed successfully!\n";
echo "========================================================\n";
?>
