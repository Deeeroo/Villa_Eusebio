<?php
require_once __DIR__ . '/url_helper.php';
const VE_ADMIN_SESSION_TIMEOUT = 1800;
const VE_ADMIN_LOCKOUT_ATTEMPTS = 5;
const VE_ADMIN_LOCKOUT_SECONDS = 300;

function admin_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ve_base_path() ?: '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function admin_is_json_request(): bool {
    return (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch');
}

function admin_client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function admin_user_agent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
}

function admin_end_session(): void {
    admin_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function admin_redirect_to_login(string $reason = ''): void {
    $query = $reason !== '' ? '?' . http_build_query(['error' => $reason]) : '';
    header('Location: ' . ve_url('pages/owner.php') . $query);
    exit;
}

function admin_require_login(bool $redirect = true): void {
    admin_start_session();
    admin_security_headers();

    $loggedIn = !empty($_SESSION['admin_logged_in']);
    $lastActivity = (int)($_SESSION['admin_last_activity_at'] ?? 0);
    $expired = $lastActivity > 0 && (time() - $lastActivity) > VE_ADMIN_SESSION_TIMEOUT;
    $agentHash = $_SESSION['admin_user_agent_hash'] ?? '';
    $agentChanged = $agentHash !== '' && !hash_equals($agentHash, hash('sha256', admin_user_agent()));

    if (!$loggedIn || $expired || $agentChanged) {
        admin_end_session();
        if (admin_is_json_request()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Your admin session expired. Please log in again.']);
            exit;
        }
        if ($redirect) {
            admin_redirect_to_login($expired ? 'timeout' : 'auth');
        }
        http_response_code(401);
        die('Unauthorized access.');
    }

    $_SESSION['admin_last_activity_at'] = time();
}

function admin_is_logged_in(): bool {
    admin_start_session();
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }

    $lastActivity = (int)($_SESSION['admin_last_activity_at'] ?? 0);
    $expired = $lastActivity > 0 && (time() - $lastActivity) > VE_ADMIN_SESSION_TIMEOUT;
    $agentHash = $_SESSION['admin_user_agent_hash'] ?? '';
    $agentChanged = $agentHash !== '' && !hash_equals($agentHash, hash('sha256', admin_user_agent()));
    if ($expired || $agentChanged) {
        admin_end_session();
        return false;
    }

    return true;
}

function admin_csrf_token(): string {
    admin_start_session();
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf_token(?string $token): bool {
    admin_start_session();
    return is_string($token)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function admin_submitted_csrf_token(): ?string {
    if (isset($_POST['csrf_token'])) {
        return (string)$_POST['csrf_token'];
    }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return null;
}

function admin_require_post_csrf(): void {
    admin_require_login(false);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_verify_csrf_token(admin_submitted_csrf_token())) {
        return;
    }

    if (admin_is_json_request()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
        exit;
    }

    http_response_code(403);
    die('Invalid security token. Please refresh and try again.');
}
function admin_login_attempts_table(mysqli $conn): void {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_login_attempts (
        attempt_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(120) NOT NULL,
        source_ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_login_lookup (username, source_ip, success, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function admin_login_lock_remaining(mysqli $conn, string $username): int {
    admin_login_attempts_table($conn);
    $username = strtolower(trim($username));
    $sourceIp = admin_client_ip();

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS failed_count, UNIX_TIMESTAMP(MAX(attempted_at)) AS latest_failed
        FROM admin_login_attempts
        WHERE username = ? AND source_ip = ? AND success = 0
            AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
    if (!$stmt) {
        return 0;
    }

    $window = VE_ADMIN_LOCKOUT_SECONDS;
    mysqli_stmt_bind_param($stmt, 'ssi', $username, $sourceIp, $window);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $failedCount = (int)($row['failed_count'] ?? 0);
    $latestFailed = (int)($row['latest_failed'] ?? 0);
    if ($failedCount < VE_ADMIN_LOCKOUT_ATTEMPTS || $latestFailed <= 0) {
        return 0;
    }

    return max(0, ($latestFailed + VE_ADMIN_LOCKOUT_SECONDS) - time());
}

function admin_record_login_attempt(mysqli $conn, string $username, bool $success): void {
    admin_login_attempts_table($conn);
    $username = strtolower(trim($username));
    $sourceIp = admin_client_ip();
    $userAgent = admin_user_agent();
    $successInt = $success ? 1 : 0;

    $stmt = mysqli_prepare($conn, "INSERT INTO admin_login_attempts (username, source_ip, user_agent, success) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssi', $username, $sourceIp, $userAgent, $successInt);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if ($success) {
        $cleanup = mysqli_prepare($conn, "DELETE FROM admin_login_attempts WHERE username = ? AND source_ip = ? AND success = 0");
        if ($cleanup) {
            mysqli_stmt_bind_param($cleanup, 'ss', $username, $sourceIp);
            mysqli_stmt_execute($cleanup);
            mysqli_stmt_close($cleanup);
        }
    }
}

function admin_login_success(array $admin): void {
    admin_start_session();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = (string)$admin['username'];
    $_SESSION['admin_full_name'] = (string)$admin['full_name'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_activity_at'] = time();
    $_SESSION['admin_user_agent_hash'] = hash('sha256', admin_user_agent());
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
