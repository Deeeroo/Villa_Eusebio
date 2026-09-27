<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

function audit_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function audit_time_label($value): string {
    if (!$value) return 'Not recorded';

    try {
        $date = new DateTimeImmutable((string)$value, new DateTimeZone('Asia/Manila'));
    } catch (Exception $e) {
        return (string)$value;
    }

    $label = $date->format('M d, Y h:i:s A');
    if (preg_match('/\.(\d+)/', (string)$value, $match) && trim($match[1], '0') !== '') {
        $label .= '.' . substr($match[1], 0, 6);
    }
    return $label;
}

function audit_browser_label($userAgent): string {
    $ua = (string)$userAgent;
    if ($ua === '' || $ua === 'Unknown') return 'Not captured';

    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
        $browser = 'Microsoft Edge';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    $platform = '';
    if (stripos($ua, 'Windows') !== false) {
        $platform = ' on Windows';
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $platform = ' on macOS';
    } elseif (stripos($ua, 'Android') !== false) {
        $platform = ' on Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $platform = ' on iOS';
    }

    return $browser . $platform;
}

$auditRows = [];
$auditResult = mysqli_query($conn, "SELECT
        l.log_id,
        l.admin_id,
        l.setting_area,
        l.action_note,
        l.source_ip,
        l.source_user_agent,
        l.source_page,
        l.request_method,
        l.created_at,
        a.full_name,
        a.username
    FROM settings_logs l
    LEFT JOIN admins a ON a.id = l.admin_id
    ORDER BY l.created_at DESC, l.log_id DESC
    LIMIT 200");
if ($auditResult) {
    while ($row = mysqli_fetch_assoc($auditResult)) {
        $auditRows[] = $row;
    }
}

$totalLogs = 0;
$todayLogs = 0;
$latestLog = null;

$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM settings_logs");
if ($totalResult && $row = mysqli_fetch_assoc($totalResult)) {
    $totalLogs = (int)($row['total'] ?? 0);
}

$todayResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM settings_logs WHERE DATE(created_at) = CURDATE()");
if ($todayResult && $row = mysqli_fetch_assoc($todayResult)) {
    $todayLogs = (int)($row['total'] ?? 0);
}

$latestResult = mysqli_query($conn, "SELECT created_at FROM settings_logs ORDER BY created_at DESC, log_id DESC LIMIT 1");
if ($latestResult && $row = mysqli_fetch_assoc($latestResult)) {
    $latestLog = $row['created_at'] ?? null;
}
?>
<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link"><span class="nav-icon">S</span><span>Sales Record</span></a>
    <a href="announcements.php" class="nav-link"><span class="nav-icon">A</span><span>Announcement</span></a>
    <a href="subscribers.php" class="nav-link"><span class="nav-icon">EM</span><span>Subscribers</span></a>
    <a href="archive.php" class="nav-link"><span class="nav-icon">AR</span><span>Archive</span></a>
    <a href="settings.php" class="nav-link active"><span class="nav-icon">ST</span><span>Settings</span></a>
</div>

<div class="admin-dashboard">
    <div class="admin-topbar">
        <div class="admin-brand">
            <button id="menuToggle" class="menu-btn">Menu</button>
            <div><h1>Villa Eusebio</h1><p>Audit Trail</p></div>
        </div>
        <div class="admin-userbar"><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content settings-page audit-page">
        <div class="admin-page-heading audit-heading">
            <div>
                <h2>Audit Trail</h2>
                <p class="reservation-helper-text">Precise timestamps, action details, and source information for admin changes.</p>
            </div>
            <a class="settings-undo-btn" href="settings.php">Back to Settings</a>
        </div>

        <div class="audit-summary-grid">
            <div class="audit-summary-card"><span>Total Logs</span><strong><?php echo $totalLogs; ?></strong></div>
            <div class="audit-summary-card"><span>Today</span><strong><?php echo $todayLogs; ?></strong></div>
            <div class="audit-summary-card"><span>Latest Activity</span><strong><?php echo audit_h($latestLog ? audit_time_label($latestLog) : 'None'); ?></strong></div>
        </div>

        <section class="audit-log-panel">
            <div class="audit-log-panel-head">
                <div>
                    <h3>Recent Admin Activity</h3>
                    <p>Showing the latest 200 recorded actions.</p>
                </div>
            </div>

            <?php if ($auditRows): ?>
                <div class="audit-table">
                    <div class="audit-table-header">
                        <span>Precise Timestamp</span>
                        <span>Action Details</span>
                        <span>Source Information</span>
                    </div>
                    <?php foreach ($auditRows as $row):
                        $adminName = trim((string)($row['full_name'] ?? ''));
                        $username = trim((string)($row['username'] ?? ''));
                        if ($adminName === '') $adminName = $username !== '' ? $username : 'Admin #' . (int)($row['admin_id'] ?? 0);
                        $method = trim((string)($row['request_method'] ?? ''));
                        $sourcePage = trim((string)($row['source_page'] ?? ''));
                        $sourceIp = trim((string)($row['source_ip'] ?? ''));
                        $sourceBrowser = audit_browser_label($row['source_user_agent'] ?? '');
                    ?>
                        <article class="audit-row">
                            <div class="audit-time">
                                <strong><?php echo audit_h(audit_time_label($row['created_at'] ?? '')); ?></strong>
                                <small>Log #<?php echo (int)$row['log_id']; ?></small>
                            </div>
                            <div class="audit-action">
                                <span><?php echo audit_h($row['setting_area'] ?? 'System'); ?></span>
                                <p><?php echo audit_h($row['action_note'] ?? 'No action details recorded.'); ?></p>
                            </div>
                            <div class="audit-source">
                                <strong><?php echo audit_h($adminName); ?></strong>
                                <small>IP: <?php echo audit_h($sourceIp !== '' ? $sourceIp : 'Not captured'); ?></small>
                                <small><?php echo audit_h(($method !== '' ? $method . ' ' : '') . ($sourcePage !== '' ? $sourcePage : 'Source page not captured')); ?></small>
                                <small title="<?php echo audit_h($row['source_user_agent'] ?? ''); ?>"><?php echo audit_h($sourceBrowser); ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="subscriber-empty-state audit-empty-state">
                    <strong>No audit records yet</strong>
                    <p>New settings, gallery, and subscriber admin actions will appear here.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded',function(){const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}});</script>
