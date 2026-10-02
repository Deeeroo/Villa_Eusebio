<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$counts = [
    'reservation' => 0,
    'sales' => 0,
    'announcement' => 0,
    'settings' => 0
];
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE COALESCE(archived,0)=1");
if ($result) $counts['reservation'] = $counts['sales'] = (int)(mysqli_fetch_assoc($result)['total'] ?? 0);
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM announcements WHERE archived_at IS NOT NULL");
if ($result) $counts['announcement'] = (int)(mysqli_fetch_assoc($result)['total'] ?? 0);
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_images WHERE archived_at IS NOT NULL");
if ($result) $counts['settings'] = (int)(mysqli_fetch_assoc($result)['total'] ?? 0);
?>
<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link"><span class="nav-icon">S</span><span>Sales Record</span></a>
    <a href="announcements.php" class="nav-link"><span class="nav-icon">A</span><span>Announcement</span></a>
    <a href="subscribers.php" class="nav-link"><span class="nav-icon">EM</span><span>Subscribers</span></a>
    <a href="archive.php" class="nav-link active"><span class="nav-icon">AR</span><span>Archive</span></a>
    <a href="settings.php" class="nav-link"><span class="nav-icon">ST</span><span>Settings</span></a>
</div>
<div class="admin-dashboard">
    <div class="admin-topbar"><div class="admin-brand"><button id="menuToggle" class="menu-btn">Menu</button><div><h1>Villa Eusebio</h1><p>Archive</p></div></div><div class="admin-userbar"><a href="settings.php" class="admin-avatar-link" aria-label="Open settings" title="Open settings"></a><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div></div>
    <div class="main-content">
        <h2>Archive</h2>
        <p class="reservation-helper-text">Choose the archive category you want to review. Deleted items are stored here for organization and recovery.</p>
        <div class="archive-category-grid">
            <a href="archive_reservation.php" class="archive-category-card"><span>R</span><strong>Reservation</strong><small><?php echo $counts['reservation']; ?> archived bookings</small></a>
            <a href="archive_sales_record.php" class="archive-category-card"><span>S</span><strong>Sales Record</strong><small><?php echo $counts['sales']; ?> archived sales rows</small></a>
            <a href="archive_announcement.php" class="archive-category-card"><span>A</span><strong>Announcement</strong><small><?php echo $counts['announcement']; ?> archived notices</small></a>
            <a href="archive_settings.php" class="archive-category-card"><span>G</span><strong>Settings / Gallery</strong><small><?php echo $counts['settings']; ?> archived images</small></a>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}});</script>



