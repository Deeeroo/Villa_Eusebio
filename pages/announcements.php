<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);
date_default_timezone_set('Asia/Manila');

function announcement_is_visible_to_customers(array $row): bool {
    if (empty($row['is_active']) || !empty($row['archived_at'])) {
        return false;
    }
    if (empty($row['expires_at'])) {
        return true;
    }
    return strtotime($row['expires_at']) > time();
}

function announcement_is_expired(array $row): bool {
    return !empty($row['is_active']) && !empty($row['expires_at']) && strtotime($row['expires_at']) <= time();
}

function announcement_duration_label(array $row): string {
    $duration = $row['duration_type'] ?? 'never';
    if ($duration === 'day') return 'Duration: 1 day';
    if ($duration === 'week') return 'Duration: 1 week';
    if ($duration === 'month') return !empty($row['expires_at']) ? 'Duration: until ' . date('F Y', strtotime($row['expires_at'])) : 'Duration: selected month';
    return 'Duration: never expires';
}

function announcement_expiry_label(array $row): string {
    if (empty($row['expires_at'])) {
        return 'No expiry date';
    }
    return (strtotime($row['expires_at']) <= time() ? 'Expired ' : 'Expires ') . date('M d, Y h:i A', strtotime($row['expires_at']));
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM announcements WHERE announcement_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editing = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}
$announcements = mysqli_query($conn, "SELECT * FROM announcements WHERE archived_at IS NULL ORDER BY CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 0 WHEN is_active = 0 THEN 1 ELSE 2 END, updated_at DESC, announcement_id DESC");
$currentAnnouncement = null;
$currentAnnouncementResult = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 AND archived_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY updated_at DESC, announcement_id DESC LIMIT 1");
if ($currentAnnouncementResult) {
    $currentAnnouncement = mysqli_fetch_assoc($currentAnnouncementResult);
}
$editingDuration = $editing['duration_type'] ?? 'never';
if (!in_array($editingDuration, ['day', 'week', 'month', 'never'], true)) {
    $editingDuration = 'never';
}
$editingDurationMonth = (!empty($editing['expires_at']) && strtotime($editing['expires_at']) > time()) ? date('Y-m', strtotime($editing['expires_at'])) : date('Y-m');
?>
<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link"><span class="nav-icon">S</span><span>Sales Record</span></a>
    <a href="announcements.php" class="nav-link active"><span class="nav-icon">A</span><span>Announcement</span></a>
    <a href="subscribers.php" class="nav-link"><span class="nav-icon">EM</span><span>Subscribers</span></a>
    <a href="archive.php" class="nav-link"><span class="nav-icon">AR</span><span>Archive</span></a>
    <a href="settings.php" class="nav-link"><span class="nav-icon">ST</span><span>Settings</span></a>
</div>

<div class="admin-dashboard">
    <div class="admin-topbar">
        <div class="admin-brand">
            <button id="menuToggle" class="menu-btn">Menu</button>
            
            <div><h1>Villa Eusebio</h1><p>Announcements</p></div>
        </div>
        <div class="admin-userbar"><a href="settings.php" class="admin-avatar-link" aria-label="Open settings" title="Open settings"></a><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content announcements-page">
        <div class="admin-page-heading">
            <div>
                <h2>Announcements</h2>
                <p class="reservation-helper-text">Create or edit announcements, then use Show or Hide to control what customers see.</p>
            </div>
        </div>
        <?php if(isset($_GET['success'])): ?><div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?>

        <div class="announcement-admin-grid">
            <form id="announcementEditor" class="settings-card announcement-editor <?php echo $editing ? 'is-editing' : ''; ?>" method="POST" action="../api/update_announcement.php" enctype="multipart/form-data">
                <input type="hidden" name="announcement_id" value="<?php echo (int)($editing['announcement_id'] ?? 0); ?>">
                <h3><?php echo $editing ? 'Edit Announcement' : 'Create Announcement'; ?></h3>
                <?php if ($editing): ?>
                    <p class="announcement-editing-note">You are editing an existing announcement. Its current customer visibility will stay the same unless you use the Show or Hide button below.</p>
                <?php endif; ?>
                <label>Title</label>
                <input name="title" maxlength="160" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" placeholder="Example: Pool maintenance notice" required>
                <label>Message</label>
                <textarea name="message" rows="7" placeholder="Write the announcement customers should see..." required><?php echo htmlspecialchars($editing['message'] ?? ''); ?></textarea>
                <label>Announcement Image</label>
                <?php if (!empty($editing['image_path'])): ?>
                    <div class="announcement-image-preview"><img src="../<?php echo htmlspecialchars($editing['image_path']); ?>" alt="Current announcement image"><span>Current image</span></div>
                <?php endif; ?>
                <input type="file" name="announcement_image" accept="image/*">
                <p class="settings-note">Optional. Leave blank if this announcement does not need an image.</p>
                <label>Announcement Duration</label>
                <div class="announcement-duration-grid">
                    <select name="duration_type" id="announcementDurationType">
                        <option value="day" <?php echo $editingDuration === 'day' ? 'selected' : ''; ?>>Show for 1 day</option>
                        <option value="week" <?php echo $editingDuration === 'week' ? 'selected' : ''; ?>>Show for 1 week</option>
                        <option value="month" <?php echo $editingDuration === 'month' ? 'selected' : ''; ?>>Show for a specific month</option>
                        <option value="never" <?php echo $editingDuration === 'never' ? 'selected' : ''; ?>>Never expire</option>
                    </select>
                    <input type="month" name="duration_month" id="announcementDurationMonth" value="<?php echo htmlspecialchars($editingDurationMonth); ?>" min="<?php echo htmlspecialchars(date('Y-m')); ?>">
                </div>
                <p class="settings-note">For a specific month, customers will see the announcement until the end of the selected month.</p>
                <div class="announcement-editor-actions">
                    <button type="submit" class="modal-btn btn-approve">Save Announcement</button>
                    <?php if ($editing): ?><a href="announcements.php" class="modal-btn btn-cancel-action">Cancel Edit</a><?php endif; ?>
                </div>
            </form>

            <div class="announcement-preview-panel">
                <span class="announcement-preview-kicker">Homepage Announcement</span>
                <?php if (!empty($currentAnnouncement['image_path'])): ?>
                    <img class="announcement-preview-image" src="../<?php echo htmlspecialchars($currentAnnouncement['image_path']); ?>" alt="Announcement image">
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($currentAnnouncement['title'] ?? 'No active announcement'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($currentAnnouncement['message'] ?? 'No announcement is currently visible to customers.')); ?></p>
                <?php if ($currentAnnouncement): ?><small><?php echo htmlspecialchars(announcement_expiry_label($currentAnnouncement)); ?></small><?php endif; ?>
            </div>
        </div>

        <div class="settings-card announcement-list-card">
            <div class="announcement-history-header">
                <div>
                    <h3>Announcement History</h3>
                    <p class="settings-note">Show or hide previous announcements while editing.</p>
                </div>
                <button type="button" class="announcement-history-toggle" id="announcementHistoryToggle" aria-expanded="true">Hide History</button>
            </div>
            <div class="announcement-admin-list" id="announcementHistoryList">
                <?php if($announcements && mysqli_num_rows($announcements) > 0): while($row = mysqli_fetch_assoc($announcements)): ?>
                <?php
                    $isVisible = announcement_is_visible_to_customers($row);
                    $isExpired = announcement_is_expired($row);
                    $statusText = $isVisible ? 'Visible to customers' : ($isExpired ? 'Expired' : 'Hidden from customers');
                    $toggleAction = $isVisible ? 'hide' : 'show';
                    $toggleText = $isVisible ? 'Hide' : ($isExpired ? 'Renew' : 'Show');
                ?>
                <article class="announcement-admin-item <?php echo $isVisible ? 'active' : ($isExpired ? 'expired' : ''); ?>">
                    <div>
                        <span><?php echo htmlspecialchars($statusText); ?></span>
                        <?php if (!empty($row['image_path'])): ?><img class="announcement-history-image" src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="Announcement image"><?php endif; ?>
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                        <small>Updated <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['updated_at']))); ?></small>
                        <small><?php echo htmlspecialchars(announcement_duration_label($row)); ?> · <?php echo htmlspecialchars(announcement_expiry_label($row)); ?></small>
                    </div>
                    <div class="announcement-actions">
                        <a class="modal-btn btn-approve announcement-edit-btn" href="announcements.php?edit=<?php echo (int)$row['announcement_id']; ?>#announcementEditor">Edit</a>
                        <form method="POST" action="../api/update_announcement.php">
                            <input type="hidden" name="action" value="<?php echo htmlspecialchars($toggleAction); ?>">
                            <input type="hidden" name="announcement_id" value="<?php echo (int)$row['announcement_id']; ?>">
                            <button type="submit" class="modal-btn <?php echo $isVisible ? 'btn-cancel-action' : 'btn-approve'; ?>"><?php echo htmlspecialchars($toggleText); ?></button>
                        </form>
                        <form method="POST" action="../api/update_announcement.php" onsubmit="return confirm('Archive this announcement?');"><input type="hidden" name="action" value="archive"><input type="hidden" name="announcement_id" value="<?php echo (int)$row['announcement_id']; ?>"><button type="submit" class="modal-btn btn-cancel-action">Archive</button></form>
                    </div>
                </article>
                <?php endwhile; else: ?>
                <p class="muted-text">No announcements yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded',function(){const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}const historyToggle=document.getElementById('announcementHistoryToggle'),historyList=document.getElementById('announcementHistoryList');function setHistoryState(hidden){if(!historyToggle||!historyList)return;historyList.classList.toggle('is-hidden',hidden);historyToggle.textContent=hidden?'Show History':'Hide History';historyToggle.setAttribute('aria-expanded',hidden?'false':'true');localStorage.setItem('announcementHistoryHidden',hidden?'yes':'no');}if(historyToggle&&historyList){setHistoryState(localStorage.getItem('announcementHistoryHidden')==='yes');historyToggle.addEventListener('click',function(){setHistoryState(!historyList.classList.contains('is-hidden'));});}});</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const durationType = document.getElementById('announcementDurationType');
    const durationMonth = document.getElementById('announcementDurationMonth');
    function syncDurationMonth() {
        if (!durationType || !durationMonth) return;
        const monthMode = durationType.value === 'month';
        durationMonth.disabled = !monthMode;
        durationMonth.closest('.announcement-duration-grid').classList.toggle('is-month-mode', monthMode);
    }
    if (durationType) {
        durationType.addEventListener('change', syncDurationMonth);
        syncDurationMonth();
    }
});
</script>




