<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: owner.php'); exit; }
include '../includes/header.php';
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);
$currentIcon = ve_setting($conn, 'site_icon', 'assets/icon.jpg');
$currentPhone = ve_setting($conn, 'contact_phone', '+63 912 345 6789');
$currentEmail = ve_setting($conn, 'contact_email', 'info@villaeusebio.com');
$currentAddress = ve_setting($conn, 'contact_address', 'Antipolo, Rizal');
$currentFacebook = ve_setting($conn, 'facebook_link', '');
$currentInstagram = ve_setting($conn, 'instagram_link', '');
$currentBio = ve_setting($conn, 'bio_text', 'A nature-inspired sanctuary.');
$homeGalleryCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_images WHERE show_on_home = 1 AND archived_at IS NULL");
$homeGalleryCountRow = $homeGalleryCountResult ? mysqli_fetch_assoc($homeGalleryCountResult) : ['total' => 0];
$homeGalleryCount = (int)($homeGalleryCountRow['total'] ?? 0);
$homePreviewImages = [];
$homePreviewResult = mysqli_query($conn, "SELECT image_path, caption, description FROM gallery_images WHERE show_on_home = 1 AND archived_at IS NULL ORDER BY image_id ASC LIMIT 3");
if ($homePreviewResult) {
    while ($homePreviewRow = mysqli_fetch_assoc($homePreviewResult)) {
        $homePreviewImages[] = $homePreviewRow;
    }
}
if (empty($homePreviewImages)) {
    $homePreviewImages[] = [
        'image_path' => $currentIcon ?: 'assets/icon.jpg',
        'caption' => 'Villa Eusebio',
        'description' => 'Relax, unwind, enjoy.'
    ];
}
$previewHeroImage = $homePreviewImages[0]['image_path'] ?? $currentIcon;
$previewHeroCaption = $homePreviewImages[0]['caption'] ?: 'Villa Eusebio';
$images = mysqli_query($conn, "SELECT * FROM gallery_images WHERE archived_at IS NULL ORDER BY show_on_home DESC, image_id ASC");
$archivedImages = mysqli_query($conn, "SELECT * FROM gallery_images WHERE archived_at IS NOT NULL ORDER BY archived_at DESC, image_id DESC");
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
<form class="settings-card" method="POST" action="../api/update_settings.php" enctype="multipart/form-data"><input type="hidden" name="action" value="site"><h3>Contact and Social Links</h3><label>Phone</label><input name="contact_phone" value="<?php echo htmlspecialchars($currentPhone); ?>"><label>Email</label><input name="contact_email" value="<?php echo htmlspecialchars($currentEmail); ?>"><label>Address</label><textarea name="contact_address"><?php echo htmlspecialchars($currentAddress); ?></textarea><label>Facebook Link</label><input name="facebook_link" value="<?php echo htmlspecialchars($currentFacebook); ?>"><label>Instagram Link</label><input name="instagram_link" value="<?php echo htmlspecialchars($currentInstagram); ?>"><label>Homepage Bio</label><textarea name="bio_text"><?php echo htmlspecialchars($currentBio); ?></textarea><label>Icon Image</label><div class="settings-icon-preview"><img src="../<?php echo htmlspecialchars(ltrim($currentIcon, '/')); ?>" alt="Current icon"><span>Current site icon</span></div><input type="file" name="site_icon" accept="image/*"><button class="modal-btn btn-approve" type="submit">Save Site Info</button></form>
<form id="galleryUploadForm" class="settings-card" method="POST" action="../api/update_settings.php" enctype="multipart/form-data"><input type="hidden" name="action" value="gallery_upload"><h3>Gallery Images</h3><label>Upload Image</label><input type="file" name="gallery_image" accept="image/*" required><label>Image Name</label><input name="caption" placeholder="Example: Pool at Night" required><label>Description</label><textarea name="description" rows="3" placeholder="Optional image description"></textarea><p class="settings-note">New images are added to the gallery page only.</p><button class="modal-btn btn-approve" type="submit">Add Image</button></form>
<div class="settings-card homepage-preview-card">
    <h3>Customer Homepage Preview</h3>
    <p class="settings-note">This is a simple preview of the first screen customers see when they open the website.</p>
    <div class="settings-homepage-preview">
        <div class="settings-preview-nav">
            <img src="../<?php echo htmlspecialchars(ltrim($currentIcon, '/')); ?>" alt="Villa Eusebio icon">
            <div>
                <strong>Villa Eusebio</strong>
                <span><?php echo htmlspecialchars($currentAddress); ?></span>
            </div>
        </div>
        <div class="settings-preview-hero">
            <img src="../<?php echo htmlspecialchars(ltrim($previewHeroImage, '/')); ?>" alt="<?php echo htmlspecialchars($previewHeroCaption); ?>">
            <div class="settings-preview-overlay"></div>
            <div class="settings-preview-copy">
                <small>Private Resort</small>
                <h4>Villa Eusebio</h4>
                <p><?php echo htmlspecialchars($currentBio); ?></p>
            </div>
        </div>
        <div class="settings-preview-gallery">
            <?php foreach ($homePreviewImages as $previewImage): ?>
                <div>
                    <img src="../<?php echo htmlspecialchars(ltrim($previewImage['image_path'], '/')); ?>" alt="<?php echo htmlspecialchars($previewImage['caption'] ?: 'Villa Eusebio'); ?>">
                    <span><?php echo htmlspecialchars($previewImage['caption'] ?: 'Homepage Image'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="settings-preview-footer">
            <span><?php echo htmlspecialchars($currentPhone); ?></span>
            <span><?php echo htmlspecialchars($currentEmail); ?></span>
        </div>
    </div>
</div>
</div>
<div class="settings-card gallery-settings-card gallery-manager-card">
    <div class="gallery-manager-heading">
        <div>
            <h2>Gallery Images</h2>
            <p>Manage your resort image library and select up to 3 images for the homepage.</p>
        </div>
        <button type="button" class="gallery-add-btn" id="galleryAddImagesBtn"><span>+</span>Add Images</button>
    </div>
    <div class="gallery-home-countbar">
        <div><strong>Homepage images</strong><span><?php echo $homeGalleryCount; ?> of 3 selected</span></div>
        <div class="gallery-count-dots" aria-hidden="true">
            <i class="<?php echo $homeGalleryCount >= 1 ? 'active' : ''; ?>"></i>
            <i class="<?php echo $homeGalleryCount >= 2 ? 'active' : ''; ?>"></i>
            <i class="<?php echo $homeGalleryCount >= 3 ? 'active' : ''; ?>"></i>
        </div>
    </div>
    <div class="gallery-manager-toolbar">
        <div class="gallery-admin-tabs" role="tablist" aria-label="Gallery filters">
            <button type="button" class="active" data-gallery-filter="all">All Images</button>
            <button type="button" data-gallery-filter="homepage">Homepage Images</button>
            <button type="button" data-gallery-filter="archived">Archived</button>
        </div>
        <label class="gallery-search-wrap">
            <span>Search</span>
            <input type="search" id="gallerySearchInput" placeholder="Search images...">
        </label>
    </div>
    <div class="gallery-admin-list">
        <?php if($images): while($img=mysqli_fetch_assoc($images)):
            $isHomeImage = !empty($img['show_on_home']);
            $homepageLimitReached = !$isHomeImage && $homeGalleryCount >= 3;
        ?>
        <div class="gallery-admin-item modern-gallery-card" data-image-id="<?php echo (int)$img['image_id']; ?>" data-image-src="../<?php echo htmlspecialchars($img['image_path']); ?>" data-caption="<?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?>" data-description="<?php echo htmlspecialchars($img['description'] ?? ''); ?>" data-homepage="<?php echo $isHomeImage ? '1' : '0'; ?>">
            <button type="button" class="gallery-preview-trigger" aria-label="Preview <?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?>">
                <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                <?php if($isHomeImage): ?><span class="home-gallery-flag">✓ Homepage</span><?php endif; ?>
            </button>
            <div class="gallery-card-body">
                <p><?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?></p>
                <?php if(!empty($img['description'])): ?><small class="gallery-admin-desc"><?php echo htmlspecialchars($img['description']); ?></small><?php endif; ?>
                <?php if($homepageLimitReached): ?><small class="gallery-admin-desc gallery-limit-note">Homepage limit reached. Remove one homepage image first.</small><?php endif; ?>
            </div>
            <div class="gallery-admin-actions">
                <button type="button" class="modal-btn btn-cancel-action gallery-edit-btn">Edit</button>
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
        <?php if($archivedImages): while($img=mysqli_fetch_assoc($archivedImages)): ?>
        <div class="gallery-admin-item modern-gallery-card is-archived-card" data-image-id="<?php echo (int)$img['image_id']; ?>" data-image-src="../<?php echo htmlspecialchars($img['image_path']); ?>" data-caption="<?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?>" data-description="<?php echo htmlspecialchars($img['description'] ?? ''); ?>" data-homepage="0" data-archived="1">
            <button type="button" class="gallery-preview-trigger" aria-label="Preview archived <?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?>">
                <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                <span class="home-gallery-flag archive-gallery-flag">Archived <?php echo !empty($img['archived_at']) ? htmlspecialchars(date('M d, Y', strtotime($img['archived_at']))) : ''; ?></span>
            </button>
            <div class="gallery-card-body">
                <p><?php echo htmlspecialchars($img['caption'] ?: 'Villa Eusebio'); ?></p>
                <?php if(!empty($img['description'])): ?><small class="gallery-admin-desc"><?php echo htmlspecialchars($img['description']); ?></small><?php endif; ?>
            </div>
            <div class="gallery-admin-actions gallery-archive-actions">
                <form method="POST" action="../api/restore_gallery_image.php" class="gallery-restore-form">
                    <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                    <input type="hidden" name="redirect" value="settings">
                    <button class="modal-btn btn-approve" type="submit">Restore Image</button>
                </form>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
    <p class="gallery-empty-state" id="galleryEmptyState">No gallery images match your search.</p>
</div>
</div></div>
<div id="galleryPreviewModal" class="gallery-preview-modal" aria-hidden="true">
    <div class="gallery-preview-content">
        <button type="button" class="gallery-preview-close" id="closeGalleryPreview">&times;</button>
        <img id="galleryPreviewImg" src="" alt="">
        <div class="gallery-preview-copy">
            <h3 id="galleryPreviewTitle">Villa Eusebio</h3>
            <p id="galleryPreviewDescription"></p>
        </div>
    </div>
</div>
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
    const previewModal = document.getElementById('galleryPreviewModal');
    const previewImg = document.getElementById('galleryPreviewImg');
    const previewTitle = document.getElementById('galleryPreviewTitle');
    const previewDescription = document.getElementById('galleryPreviewDescription');
    const previewClose = document.getElementById('closeGalleryPreview');
    const searchInput = document.getElementById('gallerySearchInput');
    const emptyState = document.getElementById('galleryEmptyState');
    const addImagesBtn = document.getElementById('galleryAddImagesBtn');
    const uploadForm = document.getElementById('galleryUploadForm');
    const cards = Array.from(document.querySelectorAll('.gallery-admin-item'));
    let activeFilter = 'all';

    function openEditModal(card) {
        editId.value = card.dataset.imageId || '';
        editCaption.value = card.dataset.caption || '';
        editDescription.value = card.dataset.description || '';
        if (editPreview) editPreview.src = card.dataset.imageSrc || '';
        modal.classList.add('show');
    }

    function openPreviewModal(card) {
        previewImg.src = card.dataset.imageSrc || '';
        previewImg.alt = card.dataset.caption || 'Villa Eusebio';
        previewTitle.textContent = card.dataset.caption || 'Villa Eusebio';
        previewDescription.textContent = card.dataset.description || 'Villa Eusebio gallery image';
        previewModal.classList.remove('closing');
        previewModal.classList.add('show');
        previewModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePreviewModal() {
        previewModal.classList.add('closing');
        setTimeout(function() {
            previewModal.classList.remove('show', 'closing');
            previewModal.setAttribute('aria-hidden', 'true');
            previewImg.src = '';
            if (!modal.classList.contains('show')) document.body.style.overflow = '';
        }, 260);
    }

    function applyGalleryFilters() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        let visibleCount = 0;

        cards.forEach(function(card) {
            const isArchived = card.dataset.archived === '1';
            const matchesTab =
                (activeFilter === 'all' && !isArchived) ||
                (activeFilter === 'homepage' && !isArchived && card.dataset.homepage === '1') ||
                (activeFilter === 'archived' && isArchived);
            const text = ((card.dataset.caption || '') + ' ' + (card.dataset.description || '')).toLowerCase();
            const matchesSearch = !query || text.includes(query);
            const isVisible = matchesTab && matchesSearch;

            card.style.display = isVisible ? 'flex' : 'none';
            if (isVisible) visibleCount++;
        });

        if (emptyState) emptyState.style.display = visibleCount ? 'none' : 'block';
    }

    cards.forEach(function(card) {
        card.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            if (card.dataset.archived === '1') {
                openPreviewModal(card);
                return;
            }
            openEditModal(card);
        });

        card.addEventListener('click', function(e) {
            if (e.target.closest('button, form, a, input, textarea')) return;
            openPreviewModal(card);
        });

        const previewButton = card.querySelector('.gallery-preview-trigger');
        if (previewButton) {
            previewButton.addEventListener('click', function(e) {
                e.stopPropagation();
                openPreviewModal(card);
            });
        }

        const editButton = card.querySelector('.gallery-edit-btn');
        if (editButton) {
            editButton.addEventListener('click', function(e) {
                e.stopPropagation();
                openEditModal(card);
            });
        }

        card.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });

    document.querySelectorAll('.close-gallery-edit').forEach(function(btn) {
        btn.addEventListener('click', function() { modal.classList.remove('show'); });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.classList.remove('show');
    });

    document.querySelectorAll('[data-gallery-filter]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            activeFilter = btn.dataset.galleryFilter || 'all';
            document.querySelectorAll('[data-gallery-filter]').forEach(function(item) {
                item.classList.toggle('active', item === btn);
            });
            applyGalleryFilters();
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyGalleryFilters);

    if (previewClose) previewClose.addEventListener('click', closePreviewModal);
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === previewModal) closePreviewModal();
        });
    }

    if (addImagesBtn && uploadForm) {
        addImagesBtn.addEventListener('click', function() {
            uploadForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            uploadForm.classList.add('settings-card-pulse');
            setTimeout(function() { uploadForm.classList.remove('settings-card-pulse'); }, 900);
            const fileInput = uploadForm.querySelector('input[type="file"]');
            if (fileInput) fileInput.focus();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && previewModal && previewModal.classList.contains('show')) {
            closePreviewModal();
        }
    });

    applyGalleryFilters();
});
</script>







