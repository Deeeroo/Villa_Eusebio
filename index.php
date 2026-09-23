<?php
include "includes/header.php";
include "includes/db.php";
require_once "includes/capstone2_features.php";
ve_ensure_capstone2_schema($conn);
$homeBio = ve_setting($conn, 'bio_text', 'Discover understated luxury nestled in the tranquil hills of Antipolo. Where nature meets refined comfort.');
$homeAddress = ve_setting($conn, 'contact_address', 'Antipolo, Rizal');
$homeGalleryImages = [];
$galleryHomeResult = mysqli_query($conn, "SELECT image_path, caption, description FROM gallery_images WHERE show_on_home = 1 AND archived_at IS NULL ORDER BY image_id ASC LIMIT 3");
if ($galleryHomeResult) {
    while ($galleryHomeRow = mysqli_fetch_assoc($galleryHomeResult)) {
        $homeGalleryImages[] = $galleryHomeRow;
    }
}
$activeAnnouncement = null;
$announcementResult = mysqli_query($conn, "SELECT title, message, image_path, updated_at FROM announcements WHERE is_active = 1 AND archived_at IS NULL ORDER BY updated_at DESC, announcement_id DESC LIMIT 1");
if ($announcementResult) {
    $activeAnnouncement = mysqli_fetch_assoc($announcementResult);
}
?>

<section class="hero">
    <div class="video-wrapper">
        <video id="heroVideo" autoplay muted loop playsinline preload="auto" class="hero-video">
            <source src="/capstone_system/assets/bgvid.mp4" type="video/mp4">
        </video>

        <button id="muteBtn" class="mute-btn">&#128263;</button>
    </div>

    <div class="hero-overlay"></div>

    <div class="container hero-content">
        <div class="hero-yearline">
            <span>Est.</span>
            <i></i>
            <span>2024</span>
        </div>

        <h1 class="font-script">Villa Eusebio</h1>
        <h2 class="hero-sub">A Nature-Inspired Sanctuary</h2>

        <p class="hero-text">
            <?php echo nl2br(htmlspecialchars($homeBio)); ?>
        </p>

        <div class="hero-buttons">
            <a href="pages/appointment.php" class="btn">Book now</a>
            <a href="#amenities" class="btn outline">Explore</a>
        </div>

        <div class="hero-est">Est. 2024</div>
    </div>
</section>

<?php if ($activeAnnouncement): ?>
<button type="button" class="announcement-bubble" id="announcementBubble" aria-label="Open announcement">
    <span>Announcement</span>
    <strong>1</strong>
</button>
<div class="announcement-modal" id="announcementModal" aria-hidden="true">
    <div class="announcement-modal-card">
        <button type="button" class="announcement-close" id="announcementClose">&times;</button>
        <span class="announcement-kicker">Villa Eusebio Notice</span>
        <?php if (!empty($activeAnnouncement['image_path'])): ?>
            <img class="announcement-modal-image" src="<?php echo htmlspecialchars($activeAnnouncement['image_path']); ?>" alt="Announcement image">
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($activeAnnouncement['title']); ?></h3>
        <p><?php echo nl2br(htmlspecialchars($activeAnnouncement['message'])); ?></p>
        <small>Updated <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($activeAnnouncement['updated_at']))); ?></small>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const bubble = document.getElementById('announcementBubble');
    const modal = document.getElementById('announcementModal');
    const close = document.getElementById('announcementClose');
    if (!bubble || !modal || !close) return;
    bubble.addEventListener('click', function() {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    });
    close.addEventListener('click', function() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) close.click();
    });
});
</script>
<?php endif; ?>

<script>
const video = document.getElementById("heroVideo");
const btn = document.getElementById("muteBtn");

if (video && btn) {
    btn.addEventListener("click", () => {
        video.muted = !video.muted;
        btn.textContent = video.muted ? String.fromCodePoint(0x1F507) : String.fromCodePoint(0x1F50A);
    });
}
</script>

<section class="find-us">
    <div class="container">
        <div class="section-header">
            <div class="section-mark">
                <span></span><i></i><span></span>
            </div>
            <h2 class="font-script">Find Us</h2>
            <p><?php echo htmlspecialchars($homeAddress); ?></p>
        </div>

        <div class="map-card">
            <div class="hero-image-container location-map-frame">
                <img src="assets/villagglmaps.png" alt="Villa Eusebio Google Maps location">
            </div>

            <div class="map-label">
                <span class="map-kicker">Our Location</span>
                <h3>Villa Eusebio</h3>
                <p class="map-address"><?php echo htmlspecialchars($homeAddress); ?></p>
                <div class="map-hours">
                    <strong>Open Daily</strong>
                    <span>9:00 AM - 9:00 PM</span>
                </div>
                <a href="https://maps.app.goo.gl/wYK2PfKVFhQC1YBn7" target="_blank" rel="noopener noreferrer" class="map-open-btn">Open in Google Maps</a>
            </div>

            <div class="location-guide-row">
                <div class="location-guide-item">
                    <span class="guide-icon">&#128663;</span>
                    <div><strong>By Car</strong><p>Approximately 45-60 minutes from Metro Manila via Marcos Hwy or Sumulong Highway.</p></div>
                </div>
                <div class="location-guide-item">
                    <span class="guide-icon">&#128652;</span>
                    <div><strong>By Commute</strong><p>Take LRT 2 to Antipolo Station, then ride a jeep or tricycle to Brookside Hills.</p></div>
                </div>
                <div class="location-guide-item">
                    <span class="guide-icon">&#10087;</span>
                    <div><strong>Scenic and Serene</strong><p>Enjoy a peaceful escape surrounded by nature, fresh air, and gentle views.</p></div>
                </div>
            </div>
        </div>

        <div class="location-script">&#128205; We can't wait to welcome you.</div>
    </div>
</section>

<section id="amenities" class="amenities">
    <div class="container">
        <div class="section-header">
            <div class="section-mark">
                <span></span><i></i><span></span>
            </div>
            <h2 class="font-script">Thoughtful Amenities</h2>
            <p>Every detail curated for your comfort and tranquility</p>
        </div>

        <div class="grid amenities-grid">
            <div class="amenity-card">
                <span>🎤</span>
                <p>Karaoke</p>
                <small>Entertainment system available</small>
            </div>

            <div class="amenity-card">
                <span>🚗</span>
                <p>Parking Slot</p>
                <small>Free dedicated parking space</small>
            </div>

            <div class="amenity-card">
                <span>🔥</span>
                <p>Gas Stove/Char-Grill</p>
                <small>Outdoor cooking facilities</small>
            </div>

            <div class="amenity-card">
                <span>📶</span>
                <p>WiFi Connection</p>
                <small>High-speed internet access</small>
            </div>

            <div class="amenity-card">
                <span>🚿</span>
                <p>Outdoor Shower</p>
                <small>Refreshing outdoor facilities</small>
            </div>

            <div class="amenity-card">
                <span>🍽️</span>
                <p>Kitchen Wares</p>
                <small>Fully equipped kitchen</small>
            </div>

            <div class="amenity-card">
                <span>🐶</span>
                <p>Pet-friendly</p>
                <small>Bring your furry friends</small>
            </div>

            <div class="amenity-card">
                <span>🧊</span>
                <p>Chiller</p>
                <small>Keep your drinks cold</small>
            </div>

            <div class="amenity-card">
                <span>💧</span>
                <p>Water Dispenser</p>
                <small>Hot and cold water</small>
            </div>

            <div class="amenity-card suraya-highlight">
                <span>🏠</span>
                <p>Suraya Room</p>
                <small>Special accommodation space</small>
            </div>
        </div>

        <div class="section-footer-text">Curated for comfort</div>
    </div>
</section>

<section id="gallery" class="gallery-section">
    <div class="container">
        <div class="section-header">
            <div class="section-mark">
                <span></span><i></i><span></span>
            </div>
            <h2 class="font-script">Visual Stories</h2>
            <p>Glimpses of serenity and refined comfort</p>
        </div>

        <div class="gallery-grid">
            <?php foreach ($homeGalleryImages as $galleryHomeImage):
                $homeGalleryPath = ltrim($galleryHomeImage['image_path'], '/');
                $homeGalleryCaption = $galleryHomeImage['caption'] ?: 'Villa Eusebio';
                $homeGalleryDescription = trim($galleryHomeImage['description'] ?? '') ?: 'Relax - Unwind - Enjoy';
            ?>
            <div class="polaroid" onclick='openGalleryModal(<?php echo json_encode($homeGalleryPath); ?>, <?php echo json_encode($homeGalleryCaption); ?>)'>
                <img src="<?php echo htmlspecialchars($homeGalleryPath); ?>" alt="<?php echo htmlspecialchars($homeGalleryCaption); ?>">
                <span class="gallery-card-emblem">&#10087;</span>
                <div class="gallery-card-copy">
                    <p><?php echo htmlspecialchars($homeGalleryCaption); ?></p>
                    <small><?php echo htmlspecialchars($homeGalleryDescription); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="gallery-action">
            <a href="pages/gallery.php" class="btn">View Full Gallery</a>
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
<div id="cameraFlash"></div>
<script>
function openGalleryModal(imageSrc, imageTitle) {

    const modal = document.getElementById('galleryModal');
    const flash = document.getElementById('cameraFlash');
    const content = modal.querySelector('.gallery-modal-content');

    // RESET
    modal.classList.remove('show');
    content.style.animation = 'none';
    void content.offsetWidth;
    content.style.animation = '';

    document.getElementById('galleryModalImg').src = imageSrc;
    document.getElementById('galleryModalTitle').textContent = imageTitle;

    modal.style.display = 'flex';

    // FLASH SAFE CHECK
    if (flash) {
        flash.classList.add('flash-active');

        setTimeout(() => {
            flash.classList.remove('flash-active');

            setTimeout(() => {
                modal.classList.add('show');
            }, 200);

        }, 500);

    } else {
        // fallback if flash missing
        modal.classList.add('show');
    }
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

<?php include "includes/footer.php"; ?>
