<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
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
        <div class="admin-userbar"><a href="settings.php" class="admin-avatar-link" aria-label="Open settings" title="Open settings"></a><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content subscribers-page">
        <div class="admin-page-heading subscriber-heading">
            <div>
                <h2>Subscribers</h2>
                <p class="reservation-helper-text">View website email subscribers and remove entries when needed.</p>
            </div>
            <form class="subscriber-search-form" method="GET" action="subscribers.php">
                <label for="subscriberSearchEmail">Find subscriber</label>
                <div class="subscriber-search-row">
                    <input id="subscriberSearchEmail" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search email address">
                    <button class="subscriber-search-btn" type="submit">Search</button>
                    <?php if ($search !== ''): ?><a class="subscriber-clear-btn" href="subscribers.php">Clear</a><?php endif; ?>
                </div>
            </form>
        </div>

        <?php if(isset($_GET['success'])): ?><div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?>
        <?php if(isset($_GET['error'])): ?><div class="admin-alert error-alert"><?php echo htmlspecialchars($_GET['error']); ?></div><?php endif; ?>

        <section class="settings-card subscriber-broadcast-card">
            <div class="subscriber-card-header">
                <div>
                    <h3>Email Subscribers</h3>
                    <p>Send one update to all subscribers, or choose specific emails from the list below.</p>
                </div>
            </div>
            <form id="subscriberBroadcastForm" class="subscriber-broadcast-form" method="POST" action="../api/update_subscriber.php" data-total-subscribers="<?php echo (int)$totalSubscribers; ?>">
                <input type="hidden" name="action" value="broadcast">
                <label for="subscriberBroadcastSubject">Subject</label>
                <input id="subscriberBroadcastSubject" name="subject" maxlength="150" placeholder="Example: New Villa Eusebio announcement" required>
                <label for="subscriberBroadcastMessage">Message</label>
                <textarea id="subscriberBroadcastMessage" name="message" rows="7" maxlength="5000" placeholder="Write the update subscribers should receive..." required></textarea>
                <div class="subscriber-broadcast-actions">
                    <small id="subscriberSelectionHint"><?php echo $totalSubscribers > 0 ? 'No specific emails selected. This will send to all ' . (int)$totalSubscribers . ' subscriber' . ($totalSubscribers === 1 ? '' : 's') . '.' : 'No subscribers yet.'; ?></small>
                    <button id="subscriberSendButton" class="modal-btn btn-approve" type="submit" <?php echo $totalSubscribers <= 0 ? 'disabled' : ''; ?>>Send Email</button>
                </div>
            </form>
        </section>

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
                <?php if ($subscriberRows): ?>
                    <div class="subscriber-select-actions">
                        <button type="button" id="selectAllSubscribers">Select All</button>
                        <button type="button" id="clearSubscriberSelection">Unselect All</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($subscriberRows): ?>
                <div class="subscriber-table">
                    <?php foreach ($subscriberRows as $subscriber): ?>
                        <article class="subscriber-row">
                            <label class="subscriber-select-wrap">
                                <input form="subscriberBroadcastForm" class="subscriber-select-checkbox" type="checkbox" name="subscriber_ids[]" value="<?php echo (int)$subscriber['subscriber_id']; ?>">
                            </label>
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

<script>
document.addEventListener('DOMContentLoaded',function(){
    const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');
    if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}
    if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}

    const broadcastForm = document.getElementById('subscriberBroadcastForm');
    const selectionHint = document.getElementById('subscriberSelectionHint');
    const selectAllBtn = document.getElementById('selectAllSubscribers');
    const clearBtn = document.getElementById('clearSubscriberSelection');
    const checkboxes = Array.from(document.querySelectorAll('.subscriber-select-checkbox'));
    const totalSubscribers = broadcastForm ? Number(broadcastForm.dataset.totalSubscribers || 0) : 0;

    function updateSelectionHint() {
        const selected = checkboxes.filter(function(input) { return input.checked; }).length;
        if (!selectionHint) return;
        if (selected > 0) {
            selectionHint.textContent = selected + ' selected. Email will only be sent to selected subscriber' + (selected === 1 ? '.' : 's.');
        } else if (totalSubscribers > 0) {
            selectionHint.textContent = 'No specific emails selected. This will send to all ' + totalSubscribers + ' subscriber' + (totalSubscribers === 1 ? '.' : 's.');
        } else {
            selectionHint.textContent = 'No subscribers yet.';
        }
    }

    checkboxes.forEach(function(input) {
        input.addEventListener('change', updateSelectionHint);
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(function(input) { input.checked = true; });
            updateSelectionHint();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            checkboxes.forEach(function(input) { input.checked = false; });
            updateSelectionHint();
        });
    }

    if (broadcastForm) {
        broadcastForm.addEventListener('submit', function(event) {
            const selected = checkboxes.filter(function(input) { return input.checked; }).length;
            const message = selected > 0
                ? 'Send this email to ' + selected + ' selected subscriber' + (selected === 1 ? '?' : 's?')
                : 'Send this email to all subscribers?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    }

    updateSelectionHint();
});
</script>
