<?php
/**
 * Universal Deployment & Sync Agent
 * Safe, multi-action, self-contained remote handler.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept, User-Agent, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M');
if (function_exists('set_time_limit')) @set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');


/**
 * MalwareScanner.php
 * Thorough security and malware scanner for web projects (PHP, JS, HTML, configs, etc.)
 * Provides signature & heuristic analysis, suspicious file detection, inspection, and remediation.
 */

class MalwareScanner {
    private string $targetDir;
    private array $excludePatterns;

    // File extensions to scan
    private array $scannableExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'inc',
        'js', 'jsx', 'ts', 'tsx', 'html', 'htm', 'shtml',
        'htaccess', 'htpasswd', 'ini', 'conf', 'sh', 'bash', 'py', 'pl', 'cgi',
        'svg', 'ico', 'txt'
    ];

    // High risk / dangerous extensions often used in webshells
    private array $dangerousExecutableExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'cgi', 'pl', 'py', 'sh'
    ];

    public function __construct(string $targetDir, array $excludePatterns = []) {
        $this->targetDir = rtrim(str_replace('\\', '/', $targetDir), '/');
        $this->excludePatterns = $excludePatterns;
    }

    /**
     * Get compiled regex patterns for malware detection with classification & severity
     */
    public static function getRules(): array {
        return [
            // --- CRITICAL WEBSHELL & BACKDOOR SIGNATURES ---
            [
                'id' => 'WEBSHELL_KNOWN_SIGNATURE',
                'name' => 'Known Web Shell Signature',
                'severity' => 'critical',
                'category' => 'Webshell / Backdoor',
                'description' => 'Known hacker shell strings or copyright headers (c99, r57, b374k, WSO, FilesMan, Weevely, etc.).',
                'pattern' => '/(c99shell|r57shell|WSO\s*shell|b374k|FilesMan|Weevely|ALFA\s*TEaM|IndoXploit|Mini\s*Shell|bypass403|simattacker|pouya_server|hacker\s*root|rootshell)/i'
            ],
            [
                'id' => 'EVAL_BASE64_DECODE',
                'name' => 'Obfuscated Code Execution (eval + base64_decode)',
                'severity' => 'critical',
                'category' => 'Obfuscation / Execution',
                'description' => 'Dynamic execution of base64/gzinflate/str_rot13/hex decoded payload, common backdoor pattern.',
                'pattern' => '/\b(eval|assert|passthru|shell_exec|system|popen|proc_open)\s*\(\s*(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|hex2bin|chr)\s*\(/i'
            ],
            [
                'id' => 'VARIABLE_FUNCTION_INJECTION',
                'name' => 'Dynamic Variable Function Execution',
                'severity' => 'high',
                'category' => 'Code Execution',
                'description' => 'Executing dynamic function calls via superglobals e.g. $_POST["cmd"]($_GET["x"]).',
                'pattern' => '/\$(?:_POST|_GET|_REQUEST|_COOKIE|_SERVER)\s*\[[^\]]+\]\s*\(\s*\$(?:_POST|_GET|_REQUEST|_COOKIE|_SERVER)\s*\[/i'
            ],
            [
                'id' => 'COMMAND_EXEC_SUPERGLOBAL',
                'name' => 'Direct Superglobal Command Execution',
                'severity' => 'critical',
                'category' => 'Command Injection',
                'description' => 'Directly passing user input from $_GET/$_POST/$_COOKIE into system command functions.',
                'pattern' => '/\b(system|shell_exec|passthru|exec|popen|proc_open)\s*\(\s*\$(?:_POST|_GET|_REQUEST|_COOKIE)\s*\[/i'
            ],
            [
                'id' => 'BACKTICK_SUPERGLOBAL',
                'name' => 'Backtick Shell Execution via Request Variable',
                'severity' => 'critical',
                'category' => 'Command Injection',
                'description' => 'Executing system command using backtick syntax with request variables.',
                'pattern' => '/`\s*\$(?:_POST|_GET|_REQUEST|_COOKIE)\s*\[/i'
            ],
            [
                'id' => 'PREG_REPLACE_E_MODIFIER',
                'name' => 'Deprecated preg_replace PHP Code Execution (/e modifier)',
                'severity' => 'critical',
                'category' => 'Remote Code Execution',
                'description' => 'preg_replace using the dangerous /e modifier with variable evaluation.',
                'pattern' => '/preg_replace\s*\(\s*([\'\"]).*?\/[a-z]*e[a-z]*\1\s*,\s*(?:\$_POST|\$_GET|\$_REQUEST|\$_(?:COOKIE|SERVER))/is'
            ],
            [
                'id' => 'CREATE_FUNCTION_EXPLOIT',
                'name' => 'create_function() Backdoor / Code Execution',
                'severity' => 'high',
                'category' => 'Code Execution',
                'description' => 'create_function called with request variables, allowing arbitrary code execution.',
                'pattern' => '/create_function\s*\([^,]*,\s*\$(?:_POST|_GET|_REQUEST|_COOKIE)/i'
            ],
            [
                'id' => 'HIDDEN_UPLOADER',
                'name' => 'Arbitrary File Upload Backdoor',
                'severity' => 'high',
                'category' => 'File Uploader',
                'description' => 'move_uploaded_file with directly user-supplied destination filename and no sanitization.',
                'pattern' => '/move_uploaded_file\s*\(\s*\$_FILES\[[^\]]+\]\[[\'"]tmp_name[\'"]\]\s*,\s*(?:\$_POST|\$_GET|\$_FILES\[[^\]]+\]\[[\'"]name[\'"]\]|\$_(?:REQUEST|COOKIE))/i'
            ],
            [
                'id' => 'UNSERIALIZE_USER_INPUT',
                'name' => 'Insecure Deserialization via Superglobal',
                'severity' => 'high',
                'category' => 'Deserialization Exploit',
                'description' => 'Direct unserialize on user input, which can trigger PHP Object Injection & RCE chains.',
                'pattern' => '/unserialize\s*\(\s*\$(?:_POST|_GET|_REQUEST|_COOKIE)\s*\[/i'
            ],
            [
                'id' => 'SUSPICIOUS_OBFUSCATION_CHRLIST',
                'name' => 'Heavy Character Code Concatenation Obfuscation',
                'severity' => 'medium',
                'category' => 'Obfuscation',
                'description' => 'Long sequences of chr() or hex concatenation typically used to hide malicious functions.',
                'pattern' => '/(chr\s*\(\s*\d+\s*\)\s*\.\s*){6,}/i'
            ],
            [
                'id' => 'SUSPICIOUS_HEX_STRING',
                'name' => 'Heavy Hex-Encoded String Sequence',
                'severity' => 'medium',
                'category' => 'Obfuscation',
                'description' => 'Dense hex-encoded string literals (\x65\x76\x61\x6c...).',
                'pattern' => '/(\\\\x[0-9a-fA-F]{2}){8,}/'
            ],
            [
                'id' => 'PHP_INCLUDE_REMOTE',
                'name' => 'Remote File Inclusion / Remote URL Include',
                'severity' => 'critical',
                'category' => 'Remote File Inclusion',
                'description' => 'Including remote HTTP/HTTPS resources or user inputs directly.',
                'pattern' => '/\b(include|require|include_once|require_once)\s*\(?\s*(?:[\'"]https?:\/\/|\$(?:_POST|_GET|_REQUEST|_COOKIE))/i'
            ],
            [
                'id' => 'HTACCESS_PHP_HANDLER',
                'name' => 'Dangerous .htaccess Handler Manipulation',
                'severity' => 'critical',
                'category' => 'Webserver Hijack',
                'description' => '.htaccess directive making image/txt/png files execute as PHP scripts.',
                'pattern' => '/(AddType\s+application\/x-httpd-php\s+\.|\bAddHandler\s+application\/x-httpd-php\b|\bSetHandler\s+application\/x-httpd-php\b|php_value\s+auto_prepend_file|php_value\s+auto_append_file)/i'
            ],
            [
                'id' => 'JS_CRYPTO_MINER',
                'name' => 'Cryptocurrency Miner Signature',
                'severity' => 'high',
                'category' => 'Cryptojacking',
                'description' => 'Coinhive or browser-based cryptocurrency miner scripts.',
                'pattern' => '/(coinhive(\.min)?\.js|CoinHive\.Anonymous|cryptonight|stratum\+tcp|monero-miner)/i'
            ],
            [
                'id' => 'MALICIOUS_REDIRECT_IFRAME',
                'name' => 'Hidden / Zero-dimension Malicious Iframe',
                'severity' => 'high',
                'category' => 'SEO / Drive-by Malware',
                'description' => 'Invisible or zero-sized iframe pointing to external URL.',
                'pattern' => '/<iframe[^>]+(style=[\'"][^\'"]*(display\s*:\s*none|visibility\s*:\s*hidden|width\s*:\s*0|height\s*:\s*0)[^\'"]*|width=[\'"]0[\'"]|height=[\'"]0[\'"])[^>]*src=[\'"]https?:\/\/[^\'"]+[\'"]/i'
            ],
            [
                'id' => 'SUSPICIOUS_MAILER_SPAM',
                'name' => 'Spam Mailer Backdoor Script',
                'severity' => 'medium',
                'category' => 'Spambot',
                'description' => 'Mass mailing scripts commonly used for phishing or spam relaying.',
                'pattern' => '/\bmail\s*\([^)]*\).*?for\s*\(.*?\$.*?\)\s*\{.*?mail\s*\(/is'
            ],
            [
                'id' => 'DEFACEMENT_SIGNATURE',
                'name' => 'Defacement Marker',
                'severity' => 'high',
                'category' => 'Defacement',
                'description' => 'Common hacker defacement phrases (Hacked By, Pwned By, Security Team).',
                'recommendation' => 'Remove defacement text, verify file integrity, and inspect surrounding scripts for webshell entrypoints.',
                'pattern' => '/(hacked\s*by|pwned\s*by|defaced\s*by|cyber\s*army\s*of|greetz\s*to\s*:)/i'
            ],
            [
                'id' => 'SHELL_EXEC_POINTERS',
                'name' => 'Obfuscated Command Array / String Pointer Execution',
                'severity' => 'critical',
                'category' => 'Backdoor / Execution',
                'description' => 'Assembling command runner strings dynamically e.g. ("s"."h"."e"."l"."l"."_"."e"."x"."e"."c").',
                'recommendation' => 'Remove the obfuscated execution block immediately as this is used to evade static antivirus detection.',
                'pattern' => '/([\'"]s[\'"]\s*\.\s*[\'"]h[\'"]\s*\.\s*[\'"]e[\'"]\s*\.\s*[\'"]l[\'"]\s*\.\s*[\'"]l[\'"]|[\'"]p[\'"]\s*\.\s*[\'"]a[\'"]\s*\.\s*[\'"]s[\'"]\s*\.\s*[\'"]s[\'"]\s*\.\s*[\'"]t[\'"]|[\'"]e[\'"]\s*\.\s*[\'"]v[\'"]\s*\.\s*[\'"]a[\'"]\s*\.\s*[\'"]l[\'"])/i'
            ],
            [
                'id' => 'DANGEROUS_FILE_PUT_CONTENTS',
                'name' => 'Unauthenticated Dynamic Script Dropper (file_put_contents)',
                'severity' => 'high',
                'category' => 'Script Dropper',
                'description' => 'Writing user-controlled input directly into a PHP or executable file using file_put_contents or fwrite.',
                'recommendation' => 'Prevent arbitrary file creation or ensure files are stored outside webroot with randomized names and no execution permissions.',
                'pattern' => '/file_put_contents\s*\(\s*(?:\$_POST|\$_GET|\$_REQUEST|\$_(?:COOKIE|SERVER)|[^,]*?\.(?:php|phtml|phar)[\'"])\s*,\s*(?:\$_POST|\$_GET|\$_REQUEST|\$_(?:COOKIE|SERVER))/i'
            ],
            [
                'id' => 'SUSPICIOUS_CURL_EXEC_EVAL',
                'name' => 'Remote Payload Stager (curl/file_get_contents + eval)',
                'severity' => 'critical',
                'category' => 'Malware Stager',
                'description' => 'Fetching remote payload via curl/file_get_contents and passing immediately to eval/assert.',
                'recommendation' => 'Delete the remote payload stager line; websites should never execute unauthenticated code streamed over HTTP.',
                'pattern' => '/\b(eval|assert)\s*\(\s*(?:file_get_contents|curl_exec)\s*\(/i'
            ],
            [
                'id' => 'PARASITE_SEO_SPAM_INJECTION',
                'name' => 'Parasite SEO Spam Injection (Gambling / Toto / Casino / Pharma)',
                'severity' => 'critical',
                'category' => 'SEO Poisoning / Parasite Spam',
                'description' => 'Spam keywords such as slot gacor, toto slot, judi bola, poker online, or pharmacy spam injected into page title/content.',
                'recommendation' => 'Delete this cloaked spam file immediately. Inspect access logs for recent file uploads and review FTP/cPanel access.',
                'pattern' => '/\b(jacktoto|toto\s*slot|slot\s*gacor|situs\s*judi|daftar\s*slot|agen\s*togel|bandar\s*judi|viagra|cialis|poker\s*online|judi\s*online|slot\s*online|rtp\s*live\s*slot)\b/i'
            ],
            [
                'id' => 'SUSPICIOUS_LOOKALIKE_DOMAIN_LINK',
                'name' => 'Suspicious Lookalike / Domain Spoofing Link',
                'severity' => 'critical',
                'category' => 'Domain Hijacking / Traffic Funneling',
                'description' => 'External links referencing lookalike domains (e.g. tsuniversity.us, tsuniversity.cc, etc.) intended to funnel domain authority.',
                'recommendation' => 'Remove foreign domain linkages and inspect DNS / Google Search Console for unauthorized properties.',
                'pattern' => '/(https?:\/\/(?:[a-z0-9_\-\.]*\.)?tsuniversity\.(?:us|cc|to|pw|top|xyz|biz|tk|ga|gq|cf|ml)[\/\b\'\"])/i'
            ],
            [
                'id' => 'CLOAKED_MARKETPLACE_TEMPLATE',
                'name' => 'Cloaked Envato / Foreign Marketplace Template',
                'severity' => 'high',
                'category' => 'SEO Poisoning / Impersonation',
                'description' => 'Scraped ThemeForest/Envato marketplace header/footer loaded with third-party tracking tokens disguised on institution server.',
                'recommendation' => 'Verify origin of this HTML page. If not intentionally uploaded, it is likely a cloaked doorway page dropped by an attacker.',
                'pattern' => '/(public-assets\.envato-static\.com|envato_rollbar_token|envato_datadog_token)/i'
            ]
        ];
    }

    /**
     * Run complete scan over local target directory
     */
    public function scan(bool $includeIgnored = false): array {
        $startTime = microtime(true);
        $rules = self::getRules();

        $stats = [
            'total_files_scanned' => 0,
            'total_size_bytes' => 0,
            'infected_files_count' => 0,
            'suspicious_files_count' => 0,
            'clean_files_count' => 0,
            'duration_sec' => 0,
            'target_dir' => $this->targetDir,
        ];

        $threats = [];

        if (!is_dir($this->targetDir)) {
            throw new Exception("Target directory does not exist: {$this->targetDir}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) continue;

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $relPath = substr($fullPath, strlen($this->targetDir) + 1);

            // Exclusion check
            if ($this->shouldExclude($relPath, $includeIgnored)) {
                continue;
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $filename = basename($fullPath);

            // Check for hidden or dangerous filenames
            $isDangerousExtInUploads = $this->checkUploadDirPhp($relPath, $extension);
            $hasDoubleExtension = $this->checkDoubleExtension($filename);

            $fileSize = $item->getSize();
            $stats['total_files_scanned']++;
            $stats['total_size_bytes'] += $fileSize;

            // Don't scan huge files (> 8MB) for string regex to avoid PHP memory exhaustion
            if ($fileSize > 8 * 1024 * 1024) {
                continue;
            }

            // If not in scannable extensions and not dangerous, skip content scan
            $isScannable = in_array($extension, $this->scannableExtensions) || $extension === '' || $filename === '.htaccess';
            if (!$isScannable && !$isDangerousExtInUploads && !$hasDoubleExtension) {
                continue;
            }

            $content = @file_get_contents($fullPath);
            if ($content === false) continue;

            $fileMatches = [];

            // 1. Structural file anomalies
            if ($isDangerousExtInUploads) {
                $fileMatches[] = [
                    'rule_id' => 'PHP_IN_STATIC_DIR',
                    'rule_name' => 'Executable PHP script located in upload/asset directory',
                    'severity' => 'high',
                    'category' => 'File Placement Anomaly',
                    'description' => 'PHP files placed in upload/public static directories are frequently dropped webshells.',
                    'line' => 1,
                    'snippet' => 'File: ' . $relPath
                ];
            }

            if ($hasDoubleExtension) {
                $fileMatches[] = [
                    'rule_id' => 'DOUBLE_EXTENSION',
                    'rule_name' => 'Dangerous Double Extension (e.g. .php.jpg)',
                    'severity' => 'critical',
                    'category' => 'Bypass Attempt',
                    'description' => 'Files with double extensions are used to bypass simple upload filters.',
                    'line' => 1,
                    'snippet' => $filename
                ];
            }

            // Suspicious root files (e.g. index__.html, index_1.html, shell.php, test.php in public root)
            $suspiciousRootFile = $this->checkSuspiciousRootFilename($relPath, $filename);
            if ($suspiciousRootFile) {
                $fileMatches[] = $suspiciousRootFile;
            }

            // 2. Check content against rules
            foreach ($rules as $rule) {
                if (preg_match_all($rule['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $matchedText = $match[0];
                        $offset = $match[1];

                        // Calculate line number
                        $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                        // Get surrounding context snippet
                        $lines = explode("\n", $content);
                        $start = max(0, $lineNumber - 2);
                        $slice = array_slice($lines, $start, 4);
                        $snippet = implode("\n", $slice);

                        $fileMatches[] = [
                            'rule_id' => $rule['id'],
                            'rule_name' => $rule['name'],
                            'severity' => $rule['severity'],
                            'category' => $rule['category'],
                            'description' => $rule['description'],
                            'recommendation' => $rule['recommendation'] ?? 'Inspect the highlighted line and remove the malicious function or parameter.',
                            'line' => $lineNumber,
                            'snippet' => mb_substr($snippet, 0, 400),
                            'matched' => mb_substr($matchedText, 0, 150)
                        ];

                        // Cap matches per rule per file to avoid huge payloads
                        break;
                    }
                }
            }

            // 3. Heuristic Entropy / Extremely long single line check (often minified or encrypted webshell)
            if (in_array($extension, ['php', 'phtml', 'inc'])) {
                $longLineCheck = $this->checkSuspiciousLongLine($content);
                if ($longLineCheck) {
                    $fileMatches[] = $longLineCheck;
                }
            }

            if (!empty($fileMatches)) {
                $highestSeverity = 'low';
                foreach ($fileMatches as $m) {
                    if ($m['severity'] === 'critical') {
                        $highestSeverity = 'critical';
                        break;
                    } elseif ($m['severity'] === 'high' && $highestSeverity !== 'critical') {
                        $highestSeverity = 'high';
                    } elseif ($m['severity'] === 'medium' && $highestSeverity === 'low') {
                        $highestSeverity = 'medium';
                    }
                }

                if ($highestSeverity === 'critical') {
                    $stats['infected_files_count']++;
                } else {
                    $stats['suspicious_files_count']++;
                }

                $threats[] = [
                    'rel_path' => $relPath,
                    'file_name' => $filename,
                    'size' => $fileSize,
                    'mtime' => $item->getMTime(),
                    'severity' => $highestSeverity,
                    'matches' => $fileMatches
                ];
            } else {
                $stats['clean_files_count']++;
            }
        }

        $stats['duration_sec'] = round(microtime(true) - $startTime, 2);

        return [
            'success' => true,
            'stats' => $stats,
            'threats' => $threats
        ];
    }

    /**
     * Inspect file content & details for safe viewing
     */
    public function inspectFile(string $relPath): array {
        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
        $fullPath = $this->targetDir . '/' . $cleanPath;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return ['success' => false, 'error' => "File not found: {$relPath}"];
        }

        $size = filesize($fullPath);
        if ($size > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File too large to inspect (>5MB)'];
        }

        $content = file_get_contents($fullPath);
        $isBinary = (bool)preg_match('~[^\x20-\x7E\t\r\n]~', substr($content, 0, 512));

        return [
            'success' => true,
            'rel_path' => $cleanPath,
            'file_name' => basename($fullPath),
            'size' => $size,
            'mtime' => filemtime($fullPath),
            'is_binary' => $isBinary,
            'content' => $isBinary ? '[Binary File]' : $content
        ];
    }

    /**
     * Delete suspicious/infected file with quarantine backup
     */
    public function deleteFile(string $relPath): array {
        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
        $fullPath = $this->targetDir . '/' . $cleanPath;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return ['success' => false, 'error' => "File not found: {$relPath}"];
        }

        // Create backup snapshot before deleting
        $backup = $this->quarantineFile($cleanPath, $fullPath);

        if (@unlink($fullPath)) {
            return [
                'success' => true,
                'message' => "File {$cleanPath} deleted successfully",
                'quarantine' => $backup
            ];
        }

        return ['success' => false, 'error' => "Failed to delete file. Check filesystem permissions."];
    }

    /**
     * Clean / sanitize infected file content (e.g. strip eval base64 payload or malicious prepend)
     */
    public function cleanFile(string $relPath, ?string $cleanedContent = null): array {
        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
        $fullPath = $this->targetDir . '/' . $cleanPath;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return ['success' => false, 'error' => "File not found: {$relPath}"];
        }

        // Backup first
        $backup = $this->quarantineFile($cleanPath, $fullPath);

        if ($cleanedContent !== null) {
            // User provided corrected content
            if (@file_put_contents($fullPath, $cleanedContent) !== false) {
                return [
                    'success' => true,
                    'message' => "File {$cleanPath} updated and cleaned successfully",
                    'quarantine' => $backup
                ];
            }
            return ['success' => false, 'error' => "Failed to write cleaned file."];
        }

        // Automated neutralization: comment out detected eval/assert/system blocks
        $content = file_get_contents($fullPath);
        $neutralized = preg_replace_callback(
            '/\b(eval|assert|system|passthru|shell_exec)\s*\(\s*(?:base64_decode|gzinflate|gzuncompress|hex2bin|chr)\s*\([^\)]*\)\s*\);?/i',
            function ($m) {
                return '/* NEUTRALIZED BY APPSYNC MALWARE SCANNER: ' . addslashes($m[0]) . ' */';
            },
            $content
        );

        if ($neutralized !== $content && @file_put_contents($fullPath, $neutralized) !== false) {
            return [
                'success' => true,
                'message' => "Automated neutralization applied to {$cleanPath}",
                'quarantine' => $backup
            ];
        }

        return [
            'success' => false,
            'error' => "Could not automatically neutralize. Please inspect file and edit or remove manually."
        ];
    }

    /**
     * Remove or comment out a specific detected snippet from a file
     */
    public function removeSnippet(string $relPath, string $snippetToRemove, string $actionType = 'comment'): array {
        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
        $fullPath = $this->targetDir . '/' . $cleanPath;

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return ['success' => false, 'error' => "File not found: {$relPath}"];
        }

        $content = file_get_contents($fullPath);
        $snippetTrimmed = trim($snippetToRemove);

        if (empty($snippetTrimmed)) {
            return ['success' => false, 'error' => "Snippet string cannot be empty."];
        }

        // Search for snippet in file content
        $pos = strpos($content, $snippetTrimmed);
        if ($pos === false) {
            // Try with normalized line endings
            $contentNorm = str_replace("\r\n", "\n", $content);
            $snippetNorm = str_replace("\r\n", "\n", $snippetTrimmed);
            $pos = strpos($contentNorm, $snippetNorm);
            if ($pos !== false) {
                $content = $contentNorm;
                $snippetTrimmed = $snippetNorm;
            }
        }

        if ($pos === false) {
            return ['success' => false, 'error' => "Snippet was not found exactly in file content."];
        }

        $backup = $this->quarantineFile($cleanPath, $fullPath);

        if ($actionType === 'delete') {
            $updated = substr_replace($content, '', $pos, strlen($snippetTrimmed));
        } else {
            // Comment out the snippet safely
            $commented = "/* [APPSYNC REMOVAL RECOMMENDATION]:\n" . $snippetTrimmed . "\n*/";
            $updated = substr_replace($content, $commented, $pos, strlen($snippetTrimmed));
        }

        if (@file_put_contents($fullPath, $updated) !== false) {
            return [
                'success' => true,
                'message' => "Suspicious code was successfully " . ($actionType === 'delete' ? 'removed' : 'commented out') . " from {$cleanPath}.",
                'quarantine' => $backup
            ];
        }

        return ['success' => false, 'error' => "Failed to save updated file. Check write permissions."];
    }

    /**
     * Quarantine file safely into temp/quarantine
     */
    private function quarantineFile(string $relPath, string $fullPath): ?string {
        $qDir = dirname(__DIR__) . '/temp/quarantine/' . date('Y_m_d');
        if (!file_exists($qDir)) {
            @mkdir($qDir, 0777, true);
        }

        $safeName = str_replace(['/', '\\'], '__', $relPath) . '_' . time() . '.bak';
        $dest = $qDir . '/' . $safeName;
        if (@copy($fullPath, $dest)) {
            return $dest;
        }
        return null;
    }

    private function shouldExclude(string $relPath, bool $includeIgnored): bool {
        // Never scan .git internal repository files or deploy-agent
        if (strpos($relPath, '.git/') === 0 || $relPath === '.git' || basename($relPath) === 'deploy-agent.php') {
            return true;
        }

        // When scanning for malware, audit all files in the directory.
        // Only skip heavy dependency/build caches if includeIgnored is false.
        if (!$includeIgnored) {
            if (strpos($relPath, 'node_modules/') === 0 || strpos($relPath, '.next/') === 0 || strpos($relPath, 'vendor/') === 0) {
                return true;
            }
        }

        return false;
    }

    private function checkUploadDirPhp(string $relPath, string $extension): bool {
        if (!in_array($extension, $this->dangerousExecutableExtensions)) {
            return false;
        }
        $lower = strtolower($relPath);
        return (strpos($lower, 'upload') !== false ||
                strpos($lower, 'uploads') !== false ||
                strpos($lower, 'images') !== false ||
                strpos($lower, 'assets') !== false ||
                strpos($lower, 'media') !== false);
    }

    private function checkDoubleExtension(string $filename): bool {
        return (bool)preg_match('/\.(php|phtml|phar|pl|py|cgi)\.[a-zA-Z0-9]{2,4}$/i', $filename);
    }

    private function checkSuspiciousRootFilename(string $relPath, string $filename): ?array {
        // Files with abnormal masquerade naming e.g. index__.html, index_1.html, index-copy.html, .index.php
        if (preg_match('/^(index[_\-\.]{2,}[a-zA-Z0-9_\-]*\.(html?|php)|[a-z0-9]{8,32}\.(php|html)|shell\.(php|html)|c99\.(php|html)|wso\.(php|html)|alfa\.(php|html)|up\.(php|html)|mar\.(php|html)|bypass\.(php|html)|priv\.(php|html))$/i', $filename)) {
            return [
                'rule_id' => 'SUSPICIOUS_MASQUERADE_FILENAME',
                'rule_name' => 'Suspicious Cloaked / Masquerading Root File (' . $filename . ')',
                'severity' => 'critical',
                'category' => 'Cloaked File / Backdoor Drop',
                'description' => 'Unusual filename pattern designed to mimic standard index pages or common dropped webshell naming conventions.',
                'recommendation' => 'This file is not part of your normal build. Delete it immediately and investigate recent access logs.',
                'line' => 1,
                'snippet' => 'File: ' . $relPath
            ];
        }
        return null;
    }

    private function checkSuspiciousLongLine(string $content): ?array {
        $lines = explode("\n", $content);
        foreach ($lines as $idx => $line) {
            $len = strlen($line);
            // Single continuous line > 3500 characters containing eval/base64/chr/hex
            if ($len > 3500 && preg_match('/(eval|base64_decode|gzinflate|assert)\s*\(/i', $line)) {
                return [
                    'rule_id' => 'SUSPICIOUS_HIGH_ENTROPY_LINE',
                    'rule_name' => 'Abnormally Long Obfuscated Line (>3500 chars)',
                    'severity' => 'high',
                    'category' => 'Obfuscation / Packed Code',
                    'description' => 'Unusually long single code line packed with obfuscated functions, characteristic of stealth webshells.',
                    'line' => $idx + 1,
                    'snippet' => substr($line, 0, 300) . '... [truncated]'
                ];
            }
        }
        return null;
    }
}

try {
    $API_SECRET_KEY = 'TSU_Sync_Secret_9x2K1m8P4vLms';

    // Extract authorization token (supports standard Bearer header, redirected header, and fallback param)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    $token = '';
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
    } elseif (!empty($_POST['auth_token'])) {
        $token = trim($_POST['auth_token']);
    } elseif (!empty($_GET['auth_token'])) {
        $token = trim($_GET['auth_token']);
    }

    if (empty($token) || $token !== $API_SECRET_KEY) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: Invalid or missing security token']);
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if (empty($action)) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if ($input && isset($input['action'])) {
            $action = $input['action'];
            $_POST = array_merge($_POST, $input);
        }
    }

    $rootDir = dirname(__FILE__);

    function getDbConn() {
        global $rootDir;
        
        $dbHost = $_POST['db_host'] ?? null;
        $dbName = $_POST['db_name'] ?? null;
        $dbUser = $_POST['db_user'] ?? null;
        $dbPass = $_POST['db_pass'] ?? null;

        if ($dbHost && $dbName && $dbUser) {
            return new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        }

        $cfg = $rootDir . '/config/config.php';
        if (file_exists($cfg)) {
            $c = file_get_contents($cfg);
            // Match the production branch if present
            if (preg_match('/tsuniver_tsu_lms/', $c)) {
                return new PDO("mysql:host=localhost;dbname=tsuniver_tsu_lms;charset=utf8mb4", "tsuniver_tsu_lms", 'FvMxSL8oP96!A-}d', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }
        }
        return null;
    }

    switch ($action) {
        case 'ping':
            $dbStatus = 'Not configured';
            try {
                $p = getDbConn();
                if ($p) $dbStatus = 'Connected';
            } catch (Throwable $ex) {
                $dbStatus = 'Unavailable: ' . $ex->getMessage();
            }

            $diskFree = function_exists('disk_free_space') ? round(@disk_free_space($rootDir) / (1024 * 1024), 2) : 'N/A';

            echo json_encode([
                'success' => true,
                'message' => 'Agent online and reachable',
                'server' => [
                    'php_version' => PHP_VERSION,
                    'os' => PHP_OS,
                    'root_dir' => $rootDir,
                    'disk_free_mb' => $diskFree,
                    'zip_enabled' => class_exists('ZipArchive'),
                    'db_status' => $dbStatus
                ]
            ]);
            break;

        case 'scan_files':
            $exclude = $_POST['exclude'] ?? ['.git', 'sessions', 'node_modules', 'error_log', basename(__FILE__)];
            if (is_string($exclude)) {
                $exclude = json_decode($exclude, true) ?: ['.git', 'node_modules'];
            }
            $exclude[] = basename(__FILE__);

            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $subPath = str_replace('\\', '/', substr($item->getPathname(), strlen($rootDir) + 1));
                
                $skip = false;
                foreach ($exclude as $pat) {
                    if (empty($pat)) continue;
                    if ($pat === $subPath || strpos($subPath, rtrim($pat, '/') . '/') === 0 || fnmatch($pat, $subPath) || fnmatch($pat, basename($subPath))) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                if ($item->isFile()) {
                    $files[$subPath] = [
                        'hash' => md5_file($item->getPathname()),
                        'size' => $item->getSize(),
                        'mtime' => $item->getMTime()
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'total_files' => count($files),
                'files' => $files
            ]);
            break;

        case 'deploy_files':
            if (!isset($_FILES['payload']) || $_FILES['payload']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No valid payload received']);
                exit;
            }

            if (!class_exists('ZipArchive')) {
                echo json_encode(['success' => false, 'error' => 'ZipArchive extension missing on server']);
                exit;
            }

            $zipPath = $_FILES['payload']['tmp_name'];
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                echo json_encode(['success' => false, 'error' => 'Failed to open uploaded ZIP payload']);
                exit;
            }

            $extractedFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $extractedFiles[] = $stat['name'];
            }

            $extractSuccess = $zip->extractTo($rootDir);
            $zip->close();

            if (!$extractSuccess) {
                echo json_encode(['success' => false, 'error' => 'Failed to extract payload']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Files deployed successfully',
                'count' => count($extractedFiles),
                'files' => $extractedFiles
            ]);
            break;

        case 'delete_files':
            $filesToDelete = $_POST['files'] ?? [];
            if (is_string($filesToDelete)) {
                $filesToDelete = json_decode($filesToDelete, true) ?: [];
            }

            $deleted = [];
            foreach ($filesToDelete as $relPath) {
                $fullPath = $rootDir . '/' . ltrim(str_replace(['..', '\\'], ['', '/'], $relPath), '/');
                if (file_exists($fullPath) && is_file($fullPath)) {
                    if (@unlink($fullPath)) {
                        $deleted[] = $relPath;
                    }
                }
            }

            echo json_encode(['success' => true, 'deleted' => $deleted]);
            break;

        case 'run_sql':
            $sql = $_POST['sql'] ?? '';
            if (empty(trim($sql))) {
                echo json_encode(['success' => false, 'error' => 'SQL script is empty']);
                exit;
            }

            $pdo = getDbConn();
            if (!$pdo) {
                echo json_encode(['success' => false, 'error' => 'Database connection could not be established']);
                exit;
            }

            // Clean BOM and comments
            $bom = pack('H*','EFBBBF');
            $sql = preg_replace("/^$bom/", '', $sql);
            $sql = preg_replace('/--.*$/m', '', $sql); // Remove line comments
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove block comments

            $queries = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0;
            $warnings = [];
            foreach ($queries as $q) {
                if (empty($q)) continue;
                try {
                    $pdo->exec($q);
                    $executed++;
                } catch (PDOException $e) {
                    // Check if error is simply "duplicate key/index already exists" (code 42000 / 1061 in MySQL/MariaDB)
                    if (strpos($e->getMessage(), 'Duplicate key name') !== false || $e->getCode() == '42S21' || strpos($e->getMessage(), 'already exists') !== false) {
                        $warnings[] = "Notice: index/column in '{$q}' already exists.";
                    } else {
                        throw $e;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Successfully executed {$executed} query(ies)." . (!empty($warnings) ? " (" . count($warnings) . " notices ignored)" : ""),
                'executed_count' => $executed,
                'notices' => $warnings
            ]);
            break;

        case 'backup':
            if (!class_exists('ZipArchive')) {
                echo json_encode(['success' => false, 'error' => 'ZipArchive not available']);
                exit;
            }

            $backupName = 'snapshot_' . date('Y_m_d_H_i_s') . '.zip';
            $backupPath = $rootDir . '/' . $backupName;

            $zip = new ZipArchive();
            if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                echo json_encode(['success' => false, 'error' => 'Failed to create backup ZIP']);
                exit;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $exclude = ['.git', 'sessions', 'node_modules', basename(__FILE__), $backupName];
            foreach ($iterator as $item) {
                $subPath = str_replace('\\', '/', substr($item->getPathname(), strlen($rootDir) + 1));
                $skip = false;
                foreach ($exclude as $pat) {
                    if ($pat === $subPath || strpos($subPath, $pat . '/') === 0 || fnmatch($pat, $subPath)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                if ($item->isFile()) {
                    $zip->addFile($item->getPathname(), $subPath);
                }
            }
            $zip->close();

            echo json_encode([
                'success' => true,
                'message' => 'Backup snapshot created',
                'backup_file' => $backupName,
                'size_mb' => round(filesize($backupPath) / (1024 * 1024), 2)
            ]);
            break;


        case "scan_malware":
            $includeIgnored = !empty($_POST["include_ignored"]) && $_POST["include_ignored"] !== "false" && $_POST["include_ignored"] !== "0";
            // Scan the entire webroot directory ($rootDir, e.g. public_html/)
            $scanner = new MalwareScanner($rootDir, []);
            $result = $scanner->scan($includeIgnored);
            echo json_encode($result);
            break;

        case "inspect_file":
            $relPath = $_POST["file"] ?? $_GET["file"] ?? "";
            $scanner = new MalwareScanner($rootDir);
            $res = $scanner->inspectFile($relPath);
            echo json_encode($res);
            break;

        case "delete_malware_file":
            $relPath = $_POST["file"] ?? "";
            $scanner = new MalwareScanner($rootDir);
            $res = $scanner->deleteFile($relPath);
            echo json_encode($res);
            break;

        case "clean_malware_file":
            $relPath = $_POST["file"] ?? "";
            $cleanedContent = isset($_POST["content"]) ? (string)$_POST["content"] : null;
            $scanner = new MalwareScanner($rootDir);
            $res = $scanner->cleanFile($relPath, $cleanedContent);
            echo json_encode($res);
            break;


        case "remove_suspicious_snippet":
            $relPath = $_POST["file"] ?? "";
            $snippet = $_POST["snippet"] ?? "";
            $actionType = $_POST["action_type"] ?? "comment";
            $scanner = new MalwareScanner($rootDir);
            $res = $scanner->removeSnippet($relPath, $snippet, $actionType);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal Error: ' . $e->getMessage()]);
}

