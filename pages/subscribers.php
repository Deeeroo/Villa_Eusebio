<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: owner.php'); exit; }
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$search = trim($_GET['q'] ?? '');
$subscriberRows = [];

if ($search !== '') {
    $likeSearch = '%' . $search . '%';
    $stmt = mysqli_prepare($conn, "SELECT subscriber_id, email, subscribed_at FROM email_subscribers WHERE email LIKE ? ORDER BY subscribed_at DESC, subscriber_id DESC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $likeSearch);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $subscriberRows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $result = mysqli_query($conn, "SELECT subscriber_id, email, subscribed_at FROM email_subscribers ORDER BY subscribed_at DESC, subscriber_id DESC");
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $subscriberRows[] = $row;
    }
}

$totalSubscribers = 0;
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM email_subscribers");
if ($totalResult && $row = mysqli_fetch_assoc($totalResult)) {
    $totalSubscribers = (int)$row['total'];
}

?>
<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link"><span class="nav-icon">S</span><span>Sales Record</span></a>
    <a href="announcements.php" class="nav-link"><span class="nav-icon">A</span><span>Announcement</span></a>
    <a href="subscribers.php" class="nav-link active"><span class="nav-icon">EM</span><span>Subscribers</span></a>
    <a href="archive.php" class="nav-link"><span class="nav-icon">AR</span><span>Archive</span></a>
    <a href="settings.php" class="nav-link"><span class="nav-icon">ST</span><span>Settings</span></a>
</div>

<div class="admin-dashboard">
    <div class="admin-topbar">
        <div class="admin-brand">
            <button id="menuToggle" class="menu-btn">Menu</button>
            <div><h1>Villa Eusebio</h1><p>Email Subscribers</p></div>
        </div>
        <div class="admin-userbar"><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content subscribers-page">
        <div class="admin-page-heading subscriber-heading">
            <div>
                <h2>Subscribers</h2>
                <p class="reservation-helper-text">View website email subscribers and remove entries when needed.</p>
            </div>
            <form class="subscriber-search-form" method="GET" action="subscribers.php">
                <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search email">
                <button class="admin-export-btn" type="submit">Search</button>
                <?php if ($search !== ''): ?><a class="settings-undo-btn" href="subscribers.php">Clear</a><?php endif; ?>
            </form>
        </div>

        <?php if(isset($_GET['success'])): ?><div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?>
        <?php if(isset($_GET['error'])): ?><div class="admin-alert error-alert"><?php echo htmlspecialchars($_GET['error']); ?></div><?php endif; ?>

        <div class="subscriber-count-bar">
            <span>Total Subscribers</span>
            <strong><?php echo $totalSubscribers; ?></strong>
        </div>

        <section class="subscriber-manage-card">
            <div class="subscriber-card-header">
                <div>
                    <h3>Subscriber List</h3>
                    <p><?php echo $search !== '' ? count($subscriberRows) . ' search result' . (count($subscriberRows) === 1 ? '' : 's') . ' found.' : 'Emails collected from the website subscribe form.'; ?></p>
                </div>
            </div>

            <?php if ($subscriberRows): ?>
                <div class="subscriber-table">
                    <?php foreach ($subscriberRows as $subscriber): ?>
                        <article class="subscriber-row">
                            <span class="subscriber-avatar"><?php echo htmlspecialchars(strtoupper(substr($subscriber['email'], 0, 1))); ?></span>
                            <div class="subscriber-readonly">
                                <strong><?php echo htmlspecialchars($subscriber['email']); ?></strong>
                                <small>Subscribed <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($subscriber['subscribed_at']))); ?></small>
                            </div>
                            <form class="subscriber-delete-form" method="POST" action="../api/update_subscriber.php" onsubmit="return confirm('Remove this subscriber?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="subscriber_id" value="<?php echo (int)$subscriber['subscriber_id']; ?>">
                                <button class="modal-btn btn-reject" type="submit">Delete</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="subscriber-empty-state">
                    <strong>No subscribers found</strong>
                    <p><?php echo $search !== '' ? 'Try a different search keyword.' : 'Customer emails will appear here after they use the footer subscribe form.'; ?></p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded',function(){const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}});</script>
