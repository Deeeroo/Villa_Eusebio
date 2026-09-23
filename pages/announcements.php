<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: owner.php'); exit; }
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

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
$announcements = mysqli_query($conn, "SELECT * FROM announcements WHERE archived_at IS NULL ORDER BY is_active DESC, updated_at DESC, announcement_id DESC");
$currentAnnouncement = null;
$currentAnnouncementResult = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 AND archived_at IS NULL ORDER BY updated_at DESC, announcement_id DESC LIMIT 1");
if ($currentAnnouncementResult) {
    $currentAnnouncement = mysqli_fetch_assoc($currentAnnouncementResult);
}
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
        <div class="admin-userbar"><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content announcements-page">
        <div class="admin-page-heading">
            <div>
                <h2>Announcements</h2>
                <p class="reservation-helper-text">Choose which announcement is visible to customers on the homepage.</p>
            </div>
            <a href="announcements.php" class="admin-export-btn">New Announcement</a>
        </div>
        <?php if(isset($_GET['success'])): ?><div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?>

        <div class="announcement-admin-grid">
            <form class="settings-card announcement-editor" method="POST" action="../api/update_announcement.php" enctype="multipart/form-data">
                <input type="hidden" name="announcement_id" value="<?php echo (int)($editing['announcement_id'] ?? 0); ?>">
                <h3><?php echo $editing ? 'Edit Announcement' : 'Create Announcement'; ?></h3>
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
                <label class="announcement-visibility-check">
                    <input type="checkbox" name="show_to_customers" value="1" <?php echo (!$editing || !empty($editing['is_active'])) ? 'checked' : ''; ?>>
                    <span>Show this announcement to customers</span>
                </label>
                <button type="submit" class="modal-btn btn-approve">Save Announcement</button>
            </form>

            <div class="announcement-preview-panel">
                <span class="announcement-preview-kicker">Homepage Announcement</span>
                <?php if (!empty($currentAnnouncement['image_path'])): ?>
                    <img class="announcement-preview-image" src="../<?php echo htmlspecialchars($currentAnnouncement['image_path']); ?>" alt="Announcement image">
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($currentAnnouncement['title'] ?? 'No active announcement'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($currentAnnouncement['message'] ?? 'No announcement is currently visible to customers.')); ?></p>
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
                <article class="announcement-admin-item <?php echo !empty($row['is_active']) ? 'active' : ''; ?>">
                    <div>
                        <span><?php echo !empty($row['is_active']) ? 'Visible to customers' : 'Hidden from customers'; ?></span>
                        <?php if (!empty($row['image_path'])): ?><img class="announcement-history-image" src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="Announcement image"><?php endif; ?>
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                        <small>Updated <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['updated_at']))); ?></small>
                    </div>
                    <div class="announcement-actions">
                        <a class="modal-btn btn-approve" href="announcements.php?edit=<?php echo (int)$row['announcement_id']; ?>">Edit</a>
                        <form method="POST" action="../api/update_announcement.php">
                            <input type="hidden" name="action" value="<?php echo !empty($row['is_active']) ? 'hide' : 'show'; ?>">
                            <input type="hidden" name="announcement_id" value="<?php echo (int)$row['announcement_id']; ?>">
                            <button type="submit" class="modal-btn <?php echo !empty($row['is_active']) ? 'btn-cancel-action' : 'btn-approve'; ?>"><?php echo !empty($row['is_active']) ? 'Hide' : 'Show'; ?></button>
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




