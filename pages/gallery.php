<?php
include "../includes/header.php";
include "../includes/db.php";
require_once "../includes/capstone2_features.php";
ve_ensure_capstone2_schema($conn);
$uploadedGalleryImages = [];
$galleryResult = mysqli_query($conn, "SELECT image_path, caption, description FROM gallery_images WHERE archived_at IS NULL ORDER BY show_on_home DESC, image_id ASC");
if ($galleryResult) {
    while ($galleryRow = mysqli_fetch_assoc($galleryResult)) {
        $uploadedGalleryImages[] = $galleryRow;
    }
}
?>

<section class="page-section">
    <div class="container">

        <div class="section-heading">
            <div class="section-mark">
                <span></span><i></i><span></span>
            </div>
            <h2 class="font-script">Visual Stories</h2>
            <p>Glimpses of serenity and refined comfort</p>
        </div>

        <div class="gallery-grid">
            <div id="cameraFlash"></div>
            <?php foreach ($uploadedGalleryImages as $galleryImage):
                $uploadedPath = '../' . ltrim($galleryImage['image_path'], '/');
                $uploadedCaption = $galleryImage['caption'] ?: 'Villa Eusebio';
                $uploadedDescription = trim($galleryImage['description'] ?? '') ?: 'Relax - Unwind - Enjoy';
            ?>
            <div class="polaroid" onclick='openGalleryModal(<?php echo json_encode($uploadedPath); ?>, <?php echo json_encode($uploadedCaption); ?>)'>
                <img src="<?php echo htmlspecialchars($uploadedPath); ?>" alt="<?php echo htmlspecialchars($uploadedCaption); ?>">
                <span class="gallery-card-emblem">&#10087;</span>
                <div class="gallery-card-copy">
                    <p><?php echo htmlspecialchars($uploadedCaption); ?></p>
                    <small><?php echo htmlspecialchars($uploadedDescription); ?></small>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

    </div>
</section>

<div id="galleryModal" class="gallery-modal" onclick="closeGalleryModal(event)">
    <div class="gallery-modal-content">
        <button type="button" class="gallery-close" onclick="closeGalleryModalDirect()">&times;</button>
        <img id="galleryModalImg" src="" alt="">
        <p id="galleryModalTitle"></p>
    </div>
</div>

<script>
function openGalleryModal(imageSrc, imageTitle) {

    const modal = document.getElementById('galleryModal');
    const flash = document.getElementById('cameraFlash');
    const content = modal.querySelector('.gallery-modal-content');

    // RESET FIRST (IMPORTANT)
    modal.classList.remove('show');
    content.style.animation = 'none';
    void content.offsetWidth; // FORCE REFLOW (VERY IMPORTANT)
    content.style.animation = '';

    document.getElementById('galleryModalImg').src = imageSrc;
    document.getElementById('galleryModalTitle').textContent = imageTitle;

    modal.style.display = 'flex';

    // FLASH
    flash.classList.add('flash-active');

    setTimeout(() => {
        flash.classList.remove('flash-active');

        setTimeout(() => {
            modal.classList.add('show'); // animation triggers again
        }, 200);

    }, 500);
}

function closeGalleryModal(event) {
    if (event.target.id === 'galleryModal') {
        closeGalleryModalDirect();
    }
}

function closeGalleryModalDirect() {
    const modal = document.getElementById('galleryModal');

    // ADD closing class
    modal.classList.add('closing');

    // WAIT FOR ANIMATION
    setTimeout(() => {
        modal.classList.remove('show');
        modal.classList.remove('closing');
        modal.style.display = 'none';
    }, 500); // match animation duration
}
</script>


<?php include "../includes/footer.php"; ?>



