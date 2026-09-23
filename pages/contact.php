<?php
include "../includes/header.php";
include "../includes/db.php";
require_once "../includes/capstone2_features.php";
ve_ensure_capstone2_schema($conn);
$contactPhone = ve_setting($conn, 'contact_phone', '+63 912 345 6789');
$contactEmail = ve_setting($conn, 'contact_email', 'info@villaeusebio.com');
$contactAddress = ve_setting($conn, 'contact_address', 'Lot 6, Darwin Street, Brookside Hills Subdivision Area 15 Blk 4, Antipolo, 1800 Rizal');
$bioText = ve_setting($conn, 'bio_text', 'A nature-inspired sanctuary where understated luxury meets tranquil comfort. Creating cherished memories in the hills of Antipolo.');
$facebookLink = ve_setting($conn, 'facebook_link', 'https://www.facebook.com/villa.eusebioresort');
$instagramLink = ve_setting($conn, 'instagram_link', 'https://www.instagram.com/villaeusebioprivateresort/');
?>

<section class="contact-page">
    <div class="contact-container">
        <div class="section-mark contact-mark">
            <span></span><i></i><span></span>
        </div>

        <h1 class="contact-title">Get in Touch</h1>
        <p class="contact-subtitle">We're here to help make your experience exceptional</p>

        <div class="contact-cards">
            <div class="contact-card brand-card">
                <div class="brand-top">
                    <div class="brand-icon">◦</div>
                    <div>
                        <h2 class="brand-title">Villa Eusebio</h2>
                    </div>
                </div>

                <p class="brand-description">
                    <?php echo nl2br(htmlspecialchars($bioText)); ?>
                </p>

                <div class="brand-est">Est. 2024</div>
            </div>

            <div class="contact-card info-card">
                <h3 class="info-heading">CONTACT INFORMATION</h3>

                <div class="info-item">
                    <div class="info-icon">☎</div>
                    <div>
                        <span class="info-label">PHONE</span>
                        <p><?php echo htmlspecialchars($contactPhone); ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">✉</div>
                    <div>
                        <span class="info-label">EMAIL</span>
                        <p><?php echo htmlspecialchars($contactEmail); ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">⌂</div>
                    <div>
                        <span class="info-label">ADDRESS</span>
                        <p><?php echo nl2br(htmlspecialchars($contactAddress)); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="contact-social-section">
            <h4>CONNECT WITH US</h4>

            <div class="contact-socials">
                <a href="<?php echo htmlspecialchars($facebookLink); ?>" class="contact-social" target="_blank" rel="noopener noreferrer">f</a>
                <a href="<?php echo htmlspecialchars($instagramLink); ?>" class="contact-social" target="_blank" rel="noopener noreferrer">◎</a>
            </div>

            <p class="contact-share-text">Share your moments with us</p>
            <div class="contact-hashtag">#VillaEusebio</div>
        </div>
    </div>
</section>



