<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    $dbPath = __DIR__ . '/db.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
    }
}
require_once __DIR__ . '/capstone2_features.php';
$siteIconPath = 'assets/icon.jpg';
if (isset($conn) && $conn instanceof mysqli) {
    $siteIconPath = ve_setting($conn, 'site_icon', 'assets/icon.jpg');
}
$siteIconHref = preg_match('/^https?:\/\//i', $siteIconPath)
    ? $siteIconPath
    : '/capstone_system/' . ltrim($siteIconPath, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa Eusebio</title>

    
    <link rel="icon" href="<?php echo htmlspecialchars($siteIconHref); ?>">
    
    <link rel="stylesheet" href="/capstone_system/style.css?v=20260923-admin-subscribers2">
    <link rel="stylesheet" href="/capstone_system/responsive-fixes.css?v=20260923-admin-archive-block1">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet'>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script src="/capstone_system/js/async-ui.js?v=20260920-async1"></script>
</head>
<body class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['admin-panel.php', 'reservation.php', 'sales.php', 'admin.php', 'archive.php', 'archive_reservation.php', 'archive_sales_record.php', 'archive_announcement.php', 'archive_settings.php', 'settings.php', 'announcements.php', 'subscribers.php'], true) ? 'admin-shell-page' : ''; ?>">

<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$hideMainNavPages = ['admin-panel.php', 'reservation.php', 'sales.php', 'admin.php', 'archive.php', 'archive_reservation.php', 'archive_sales_record.php', 'archive_announcement.php', 'archive_settings.php', 'settings.php', 'announcements.php', 'subscribers.php'];
$isHomePage = in_array($currentPage, ['index.php', ''], true);
?>

<?php if (!in_array($currentPage, $hideMainNavPages, true)): ?>
<nav class="main-nav">
    <div class="nav-logo">
        <a href="/capstone_system/index.php">
            <div class="logo-main">Villa Eusebio</div>
            <div class="logo-sub">Antipolo Sanctuary</div>
        </a>
    </div>

    <div class="nav-links">
        <a href="/capstone_system/index.php" data-nav="home" class="<?php echo $isHomePage ? 'active' : ''; ?>">Home</a>
        <a href="/capstone_system/pages/appointment.php" data-nav="calendar" class="<?php echo $currentPage === 'appointment.php' ? 'active' : ''; ?>">Calendar</a>
        <a href="/capstone_system/index.php#amenities" data-nav="amenities">Amenities</a>
        <a href="/capstone_system/index.php#gallery" data-nav="gallery">Gallery</a>
        <a href="/capstone_system/pages/reviews.php" data-nav="reviews" class="<?php echo $currentPage === 'reviews.php' ? 'active' : ''; ?>">Reviews</a>
        <a href="/capstone_system/pages/contact.php" data-nav="contact" class="<?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact</a>
    </div>
</nav>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.body.classList.add("page-loaded");

    if (document.body.classList.contains('admin-shell-page')) {
        const applyAdminSidebarBadges = function(data) {
            if (!data) return;
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            const badgeMap = [
                { selector: 'a[href*="reservation.php"]', count: Number(data.reservation_pending || 0) }
            ];

            badgeMap.forEach(item => {
                const link = sidebar.querySelector(item.selector);
                if (!link) return;
                link.classList.toggle('has-sidebar-alert', item.count > 0);
                link.dataset.alertCount = item.count > 0 ? String(item.count) : '';
            });
        };

        const updateAdminSidebarBadges = function() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            const badgeRequest = window.VillaAsync
                ? window.VillaAsync.cachedJson('/capstone_system/api/admin_badges.php', {}, {
                    ttl: 5000,
                    cacheKey: 'adminBadges',
                    force: true,
                    onUpdate: applyAdminSidebarBadges
                })
                : fetch('/capstone_system/api/admin_badges.php').then(response => response.ok ? response.json() : null);

            badgeRequest
                .then(applyAdminSidebarBadges)
                .catch(() => {});
        };

        updateAdminSidebarBadges();
        setInterval(updateAdminSidebarBadges, 5000);
        if (window.VillaAsync) {
            window.VillaAsync.onSync(updateAdminSidebarBadges);
        }
    }

    if (!document.body.classList.contains('admin-shell-page')) {
        const publicNav = document.querySelector('.main-nav');
        let lastScrollY = window.scrollY;
        let navTicking = false;

        const updatePublicNav = function() {
            if (!publicNav) return;
            const currentY = window.scrollY;
            if (currentY > 120 && currentY > lastScrollY + 6) {
                publicNav.classList.add('nav-hidden');
            } else if (currentY < lastScrollY - 6 || currentY < 80) {
                publicNav.classList.remove('nav-hidden');
            }
            lastScrollY = Math.max(currentY, 0);
            navTicking = false;
        };

        window.addEventListener('scroll', function() {
            if (!navTicking) {
                window.requestAnimationFrame(updatePublicNav);
                navTicking = true;
            }
        }, { passive: true });

        const revealSelectors = [
            '.hero-content',
            '.find-us .section-header',
            '.map-card',
            '.location-guide-item',
            '.amenities .section-header',
            '.amenity-card',
            '.gallery-section .section-header',
            '.gallery-section .polaroid',
            '.gallery-action',
            '.page-section .section-heading',
            '.reviews-page-simple .section-header',
            '.review-card-static',
            '.contact-page .section-mark',
            '.contact-title',
            '.contact-subtitle',
            '.contact-card',
            '.contact-social-section',
            '.legal-page .section-mark',
            '.legal-kicker',
            '.legal-page h1',
            '.legal-intro',
            '.legal-card'
        ];
        const revealItems = Array.from(document.querySelectorAll(revealSelectors.join(',')));
        revealItems.forEach(function(item, index) {
            item.classList.add('js-reveal');
            item.style.setProperty('--reveal-delay', Math.min((index % 8) * 0.09, 0.54) + 's');
        });

        const revealOnce = function(item) {
            item.classList.add('revealed-once');
        };

        if (!('IntersectionObserver' in window)) {
            revealItems.forEach(revealOnce);
        } else {
            const revealObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (!entry.isIntersecting) return;
                    revealOnce(entry.target);
                    revealObserver.unobserve(entry.target);
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
            revealItems.forEach(function(item) {
                revealObserver.observe(item);
            });
        }
    }

    const pendingTarget = sessionStorage.getItem("pendingScrollTarget");
    const currentPath = window.location.pathname;
    const isIndexPage = currentPath.endsWith('/index.php') || currentPath.endsWith('/capstone_system/') || currentPath === '/capstone_system';
    const targetFromHash = window.location.hash ? window.location.hash.substring(1) : '';
    const finalTarget = isIndexPage ? (pendingTarget || targetFromHash) : '';

    if (finalTarget) {
        const scrollToSection = function() {
            const targetEl = document.getElementById(finalTarget);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                sessionStorage.removeItem('pendingScrollTarget');
            }
        };
        setTimeout(scrollToSection, 120);
    }

    const navLinks = document.querySelectorAll('.nav-links a[data-nav]');
    const setActiveNav = function(key) {
        navLinks.forEach(link => link.classList.toggle('active', link.dataset.nav === key));
    };
    if (navLinks.length) {
        const path = window.location.pathname;
        const hash = window.location.hash.replace('#', '');
        if (path.includes('/pages/appointment.php')) setActiveNav('calendar');
        else if (path.includes('/pages/gallery.php')) setActiveNav('gallery');
        else if (path.includes('/pages/reviews.php')) setActiveNav('reviews');
        else if (path.includes('/pages/contact.php')) setActiveNav('contact');
        else if (hash === 'amenities' || hash === 'gallery') setActiveNav(hash);
        else setActiveNav('home');

        if (isIndexPage) {
            const sectionKeys = ['amenities', 'gallery'];
            const updateActiveFromScroll = function() {
                const y = window.scrollY + 180;
                let activeKey = 'home';
                sectionKeys.forEach(key => {
                    const section = document.getElementById(key);
                    if (section && y >= section.offsetTop) activeKey = key;
                });
                setActiveNav(activeKey);
            };
            window.addEventListener('scroll', updateActiveFromScroll, { passive: true });
            updateActiveFromScroll();
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                const target = this.dataset.nav || 'home';
                setActiveNav(target);
            });
        });
    }
});

function startPageExit(callback) {
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        callback();
        return;
    }
    document.body.classList.add('page-exiting');
    window.setTimeout(callback, 140);
}

document.addEventListener("click", function(e) {
        const link = e.target.closest("a");
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const href = link.getAttribute("href");
        if (!href || link.hasAttribute('download') || link.dataset.noTransition === 'true') return;
        const target = link.getAttribute('target');
        if (target && target !== '_self') return;
        let linkUrl;
        try {
            linkUrl = new URL(href, window.location.href);
        } catch (error) {
            return;
        }
        if (linkUrl.origin !== window.location.origin || ['mailto:', 'tel:'].includes(linkUrl.protocol)) return;

        if (href.includes('/index.php#')) {
            e.preventDefault();
            const targetId = href.split('#')[1] || '';
            const currentPath = window.location.pathname;
            const isIndexPage = currentPath.endsWith('/index.php') || currentPath.endsWith('/capstone_system/') || currentPath === '/capstone_system';

            if (isIndexPage) {
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                sessionStorage.setItem('pendingScrollTarget', targetId);
                startPageExit(function() {
                    window.location = '/capstone_system/index.php#' + targetId;
                });
            }
            return;
        }

        if (href.startsWith("#")) {
            e.preventDefault();
            const targetId = href.substring(1);
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }

        const samePageHash = linkUrl.pathname === window.location.pathname &&
            linkUrl.search === window.location.search &&
            linkUrl.hash;
        if (samePageHash || linkUrl.href === window.location.href) return;

        e.preventDefault();
        startPageExit(function() {
            window.location.href = linkUrl.href;
        });
});

window.addEventListener('pageshow', function() {
    document.body.classList.remove('page-exiting');
    document.body.classList.add('page-loaded');
});
</script>

