<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: owner.php'); exit; }
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);
$currentIcon = ve_setting($conn, 'site_icon', 'assets/icon.jpg');
$homeGalleryCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_images WHERE show_on_home = 1 AND archived_at IS NULL");
$homeGalleryCountRow = $homeGalleryCountResult ? mysqli_fetch_assoc($homeGalleryCountResult) : ['total' => 0];
$homeGalleryCount = (int)($homeGalleryCountRow['total'] ?? 0);
$images = mysqli_query($conn, "SELECT * FROM gallery_images WHERE archived_at IS NULL ORDER BY show_on_home DESC, image_id ASC");
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
<div class="admin-dashboard"><div class="admin-topbar"><div class="admin-brand"><button id="menuToggle" class="menu-btn">Menu</button><div><h1>Villa Eusebio</h1><p>Settings</p></div></div><div class="admin-userbar"><strong>Owner</strong><button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button><a href="../api/logout.php" class="logout-btn">Logout</a></div></div>
<div class="main-content settings-page"><div class="settings-title-row"><div><h2>Settings</h2><p class="reservation-helper-text">Manage site content, contact links, and gallery images.</p></div><form method="POST" action="../api/undo_settings.php" onsubmit="return confirm('Undo the last settings change?');"><button type="submit" class="settings-undo-btn">Undo Last Change</button></form></div><?php if(isset($_GET['success'])): ?><div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?><?php if(isset($_GET['error'])): ?><div class="admin-alert error-alert"><?php echo htmlspecialchars($_GET['error']); ?></div><?php endif; ?>
<div class="settings-grid">
<form class="settings-card" method="POST" action="../api/update_settings.php" enctype="multipart/form-data"><input type="hidden" name="action" value="site"><h3>Contact and Social Links</h3><label>Phone</label><input name="contact_phone" value="<?php echo htmlspecialchars(ve_setting($conn,'contact_phone','+63 912 345 6789')); ?>"><label>Email</label><input name="contact_email" value="<?php echo htmlspecialchars(ve_setting($conn,'contact_email','info@villaeusebio.com')); ?>"><label>Address</label><textarea name="contact_address"><?php echo htmlspecialchars(ve_setting($conn,'contact_address','Antipolo, Rizal')); ?></textarea><label>Facebook Link</label><input name="facebook_link" value="<?php echo htmlspecialchars(ve_setting($conn,'facebook_link','')); ?>"><label>Instagram Link</label><input name="instagram_link" value="<?php echo htmlspecialchars(ve_setting($conn,'instagram_link','')); ?>"><label>Homepage Bio</label><textarea name="bio_text"><?php echo htmlspecialchars(ve_setting($conn,'bio_text','A nature-inspired sanctuary.')); ?></textarea><label>Icon Image</label><div class="settings-icon-preview"><img src="../<?php echo htmlspecialchars(ltrim($currentIcon, '/')); ?>" alt="Current icon"><span>Current site icon</span></div><input type="file" name="site_icon" accept="image/*"><button class="modal-btn btn-approve" type="submit">Save Site Info</button></form>
<form class="settings-card" method="POST" action="../api/update_settings.php" enctype="multipart/form-data"><input type="hidden" name="action" value="gallery_upload"><h3>Gallery Images</h3><label>Upload Image</label><input type="file" name="gallery_image" accept="image/*" required><label>Image Name</label><input name="caption" placeholder="Example: Pool at Night" required><label>Description</label><textarea name="description" rows="3" placeholder="Optional image description"></textarea><p class="settings-note">New images are added to the gallery page only.</p><button class="modal-btn btn-approve" type="submit">Add Image</button></form>
</div>
<div class="settings-card gallery-settings-card">
    <h3>Current Gallery Images</h3>
    <p class="settings-note">Right-click an image card to edit its title or description. Homepage images: <?php echo $homeGalleryCount; ?>/3.</p>
    <div class="gallery-admin-list">
        <?php if($images): while($img=mysqli_fetch_assoc($images)):
            $isHomeImage = !empty($img['show_on_home']);
            $homepageLimitReached = !$isHomeImage && $homeGalleryCount >= 3;
        ?>
        <div class="gallery-admin-item" data-image-id="<?php echo (int)$img['image_id']; ?>" data-image-src="../<?php echo htmlspecialchars($img['image_path']); ?>" data-caption="<?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?>" data-description="<?php echo htmlspecialchars($img['description'] ?? ''); ?>">
            <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
            <p><?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?></p>
            <?php if(!empty($img['description'])): ?><small class="gallery-admin-desc"><?php echo htmlspecialchars($img['description']); ?></small><?php endif; ?>
            <?php if($isHomeImage): ?><small class="home-gallery-flag">Homepage image</small><?php endif; ?>
            <?php if($homepageLimitReached): ?><small class="gallery-admin-desc">Homepage limit reached. Remove one homepage image first.</small><?php endif; ?>
            <div class="gallery-admin-actions">
                <form method="POST" action="../api/update_settings.php">
                    <input type="hidden" name="action" value="gallery_home">
                    <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                    <input type="hidden" name="show_on_home" value="<?php echo $isHomeImage ? 0 : 1; ?>">
                    <button class="modal-btn btn-cancel-action" type="submit" <?php echo $homepageLimitReached ? 'disabled title="Homepage is limited to 3 images."' : ''; ?>><?php echo $isHomeImage ? 'Remove from Homepage' : 'Set as Homepage'; ?></button>
                </form>
                <form method="POST" action="../api/update_settings.php" onsubmit="return <?php echo $isHomeImage ? "alert('This image cannot be archived because it is currently shown on the homepage. Remove it from Homepage first.'), false" : "confirm('Archive this gallery image?')" ?>;">
                    <input type="hidden" name="action" value="gallery_delete">
                    <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                    <button class="modal-btn btn-reject" type="submit">Archive</button>
                </form>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>
</div></div>
<div id="galleryEditModal" class="modal reservation-modal">
    <div class="modal-content reservation-confirm-content">
        <div class="modal-header reservation-modal-header">
            <div><p class="reservation-modal-kicker">Gallery Image</p><h3>Edit Image Details</h3></div>
            <button type="button" class="close-gallery-edit">&times;</button>
        </div>
        <form method="POST" action="../api/update_settings.php" class="gallery-edit-form">
            <input type="hidden" name="action" value="gallery_update">
            <input type="hidden" name="image_id" id="galleryEditId">
            <div class="gallery-edit-layout">
                <div class="gallery-edit-preview">
                    <img id="galleryEditPreview" src="" alt="Selected gallery image">
                    <span>Selected image</span>
                </div>
                <div class="gallery-edit-fields">
                    <label>Image Name</label>
                    <input name="caption" id="galleryEditCaption" required>
                    <label>Description</label>
                    <textarea name="description" id="galleryEditDescription" rows="5" placeholder="Optional image description"></textarea>
                </div>
            </div>
            <div class="modal-footer reservation-confirm-actions">
                <button type="button" class="modal-btn btn-cancel-action close-gallery-edit">Cancel</button>
                <button type="submit" class="modal-btn btn-approve">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){const btn=document.getElementById('menuToggle'),sidebar=document.getElementById('sidebar'),dash=document.querySelector('.admin-dashboard');if(localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('active');dash.classList.add('shift');}if(btn){btn.onclick=function(){sidebar.classList.toggle('active');dash.classList.toggle('shift');localStorage.setItem('sidebar',sidebar.classList.contains('active')?'collapsed':'expanded');};}});</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('galleryEditModal');
    const editId = document.getElementById('galleryEditId');
    const editCaption = document.getElementById('galleryEditCaption');
    const editDescription = document.getElementById('galleryEditDescription');
    const editPreview = document.getElementById('galleryEditPreview');
    document.querySelectorAll('.gallery-admin-item').forEach(function(card) {
        card.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            editId.value = card.dataset.imageId || '';
            editCaption.value = card.dataset.caption || '';
            editDescription.value = card.dataset.description || '';
            if (editPreview) editPreview.src = card.dataset.imageSrc || '';
            modal.classList.add('show');
        });
    });
    document.querySelectorAll('.close-gallery-edit').forEach(function(btn) {
        btn.addEventListener('click', function() { modal.classList.remove('show'); });
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.classList.remove('show');
    });
});
</script>







