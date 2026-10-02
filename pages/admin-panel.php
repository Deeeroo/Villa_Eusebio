<?php
require_once "../includes/admin_auth.php";
admin_require_login(true);

include "../includes/header.php";
include "../includes/db.php";
include "../includes/booking_repository.php";
ve_ensure_capstone2_schema($conn);

$appointments = ve_fetch_all_bookings($conn, "bs.created_at DESC");

function formatStayType($type) {
    if ($type === 'day') return 'Day Tour';
    if ($type === 'overnight') return 'Overnight Stay';
    if ($type === '22hour') return '22-Hour Stay';
    return $type;
}

function getBasePrice($type) {
    if ($type === 'day') return 7000;
    if ($type === 'overnight') return 10000;
    if ($type === '22hour') return 13000;
    return 0;
}

function stayTypeColorClass($type) {
    if ($type === 'day') return 'next-booking-day';
    if ($type === 'overnight') return 'next-booking-overnight';
    if ($type === '22hour') return 'next-booking-22hour';
    if ($type === 'full') return 'next-booking-full';
    return 'next-booking-default';
}

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$cancelledCount = 0;
$bookingValueThisMonth = 0;
$totalSubscribers = 0;

$currentMonth = date('Y-m');
$totalReservations = count($appointments);
$todayDate = new DateTimeImmutable('today');
$todayString = $todayDate->format('Y-m-d');
$upcomingStayDate = null;
$upcomingStays = [];
$activeAnnouncement = null;
$announcementResult = mysqli_query($conn, "SELECT title, message, updated_at, expires_at FROM announcements WHERE is_active = 1 AND archived_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY updated_at DESC, announcement_id DESC LIMIT 1");
if ($announcementResult) {
    $activeAnnouncement = mysqli_fetch_assoc($announcementResult);
}

$analyticsMonths = [];
$analyticsSeries = [
    'labels' => [],
    'bookings' => [],
    'revenue' => [],
];
$monthCursor = new DateTimeImmutable('first day of this month 00:00:00');
for ($i = 5; $i >= 0; $i--) {
    $monthDate = $monthCursor->modify("-{$i} months");
    $monthKey = $monthDate->format('Y-m');
    $analyticsMonths[$monthKey] = [
        'label' => $monthDate->format('M'),
        'bookings' => 0,
        'revenue' => 0.0,
    ];
}

$totalSubscriberResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM email_subscribers");
if ($totalSubscriberResult && $totalSubscriberRow = mysqli_fetch_assoc($totalSubscriberResult)) {
    $totalSubscribers = (int)($totalSubscriberRow['total'] ?? 0);
}

$subscriberNotificationRows = [];
$subscriberNotificationResult = mysqli_query($conn, "SELECT subscriber_id, email, subscribed_at FROM email_subscribers ORDER BY subscribed_at DESC, subscriber_id DESC LIMIT 8");
while ($subscriberNotificationResult && ($subscriberRow = mysqli_fetch_assoc($subscriberNotificationResult))) {
    $subscriberNotificationRows[] = $subscriberRow;
}

foreach ($appointments as $appointment) {
    $status = $appointment['status'] ?? '';
    $month = date('Y-m', strtotime($appointment['created_at']));
    $checkInMonth = !empty($appointment['check_in_date']) ? date('Y-m', strtotime($appointment['check_in_date'])) : '';
    $stayValue = isset($appointment['total_stay_value']) ? (float)$appointment['total_stay_value'] : 0.0;
    if ($stayValue <= 0) {
        $stayValue = getBasePrice($appointment['time_type']);
    }
    $reservationFeePaid = strtolower($appointment['reservation_fee_status'] ?? 'unpaid') === 'paid';
    $balancePaid = strtolower($appointment['payment_status'] ?? 'unpaid') === 'paid';
    $isCompletedPaidBooking = $status === 'approved' && $reservationFeePaid && $balancePaid;

    if ($status === 'pending') $pendingCount++;
    if ($status === 'rejected') $rejectedCount++;
    if ($status === 'cancelled') $cancelledCount++;
    if ($status === 'approved') {
        $approvedCount++;
        if ($isCompletedPaidBooking && $checkInMonth === $currentMonth) {
            $bookingValueThisMonth += $stayValue;
        }

        $checkInDate = $appointment['check_in_date'] ?? '';
        if ($checkInDate >= $todayString) {
            if ($upcomingStayDate === null || $checkInDate < $upcomingStayDate) {
                $upcomingStayDate = $checkInDate;
                $upcomingStays = [$appointment];
            } elseif ($checkInDate === $upcomingStayDate) {
                $upcomingStays[] = $appointment;
            }
        }
    }

    if (isset($analyticsMonths[$month])) {
        $analyticsMonths[$month]['bookings']++;
        if ($isCompletedPaidBooking) {
            $analyticsMonths[$month]['revenue'] += $stayValue;
        }
    }
}

foreach ($analyticsMonths as $monthData) {
    $analyticsSeries['labels'][] = $monthData['label'];
    $analyticsSeries['bookings'][] = (int)$monthData['bookings'];
    $analyticsSeries['revenue'][] = (float)$monthData['revenue'];
}

function dashboardChartMax(array $values): int {
    $maxValue = max(array_map('intval', $values ?: [0]));
    if ($maxValue <= 4) return 4;
    if ($maxValue <= 10) return (int)(ceil($maxValue / 2) * 2);
    if ($maxValue <= 100000) return (int)(ceil($maxValue / 10000) * 10000);
    return (int)(ceil($maxValue / 25000) * 25000);
}

function dashboardAxisValues(int $maxValue): array {
    return [
        $maxValue,
        (int)($maxValue * .75),
        (int)($maxValue * .5),
        (int)($maxValue * .25),
        0,
    ];
}

$dashboardBookingChartMax = dashboardChartMax($analyticsSeries['bookings']);
$dashboardRevenueChartMax = dashboardChartMax($analyticsSeries['revenue']);
$dashboardBookingAxisValues = dashboardAxisValues($dashboardBookingChartMax);
$dashboardRevenueAxisValues = dashboardAxisValues($dashboardRevenueChartMax);
$dashboardMonthLabel = date('F Y');
$dashboardStatusTotal = max($pendingCount + $approvedCount + $rejectedCount + $cancelledCount, 1);
$dashboardStatusRows = [
    ['key' => 'approved', 'label' => 'Approved', 'count' => $approvedCount, 'class' => 'approved'],
    ['key' => 'pending', 'label' => 'Pending', 'count' => $pendingCount, 'class' => 'pending'],
    ['key' => 'rejected', 'label' => 'Rejected', 'count' => $rejectedCount, 'class' => 'rejected'],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $cancelledCount, 'class' => 'cancelled'],
];

$notificationItems = [];
foreach ($appointments as $appointment) {
    if (($appointment['status'] ?? '') !== 'pending') {
        continue;
    }
    $bookingDedupeKey = strtolower(implode('|', [
        'booking',
        trim((string)($appointment['guest_name'] ?? '')),
        trim((string)($appointment['email'] ?? '')),
        trim((string)($appointment['mobile'] ?? '')),
        trim((string)($appointment['time_type'] ?? '')),
        trim((string)($appointment['check_in_date'] ?? '')),
        trim((string)($appointment['check_out_date'] ?? '')),
        trim((string)($appointment['guests'] ?? '')),
    ]));
    $notificationItems[] = [
        'uid' => 'booking-' . (int)$appointment['id'] . '-' . md5((string)($appointment['created_at'] ?? '')),
        'dedupe_key' => $bookingDedupeKey,
        'type' => 'booking',
        'title' => 'New booking request',
        'summary' => ($appointment['guest_name'] ?? 'Guest') . ' requested ' . formatStayType($appointment['time_type'] ?? ''),
        'time' => strtotime($appointment['created_at'] ?? 'now') ?: time(),
        'time_label' => date('M d, Y h:i A', strtotime($appointment['created_at'] ?? 'now')),
        'url' => 'reservation.php?highlight=booking&id=' . (int)$appointment['id'],
        'details' => [
            'Guest' => $appointment['guest_name'] ?? 'Guest',
            'Email' => $appointment['email'] ?? '',
            'Mobile' => $appointment['mobile'] ?? '',
            'Stay Type' => formatStayType($appointment['time_type'] ?? ''),
            'Check-in' => date('M d, Y', strtotime($appointment['check_in_date'] ?? 'now')),
            'Check-out' => date('M d, Y', strtotime($appointment['check_out_date'] ?? 'now')),
            'Guests' => (string)($appointment['guests'] ?? ''),
        ],
    ];
}

foreach ($subscriberNotificationRows as $subscriber) {
    $email = $subscriber['email'] ?? '';
    $notificationItems[] = [
        'uid' => 'subscriber-' . (int)($subscriber['subscriber_id'] ?? 0) . '-' . md5((string)($subscriber['subscribed_at'] ?? '') . $email),
        'dedupe_key' => 'subscriber|' . strtolower(trim($email)),
        'type' => 'subscriber',
        'title' => 'New subscriber',
        'summary' => $email . ' joined the email list',
        'time' => strtotime($subscriber['subscribed_at'] ?? 'now') ?: time(),
        'time_label' => date('M d, Y h:i A', strtotime($subscriber['subscribed_at'] ?? 'now')),
        'url' => 'subscribers.php?q=' . urlencode($email),
        'details' => [
            'Email' => $email,
            'Subscribed' => date('M d, Y h:i A', strtotime($subscriber['subscribed_at'] ?? 'now')),
        ],
    ];
}

$seenNotificationKeys = [];
$uniqueNotificationItems = [];
foreach ($notificationItems as $notificationItem) {
    $dedupeKey = $notificationItem['dedupe_key'] ?? $notificationItem['uid'] ?? '';
    if ($dedupeKey !== '' && isset($seenNotificationKeys[$dedupeKey])) {
        continue;
    }
    if ($dedupeKey !== '') {
        $seenNotificationKeys[$dedupeKey] = true;
    }
    unset($notificationItem['dedupe_key']);
    $uniqueNotificationItems[] = $notificationItem;
}
$notificationItems = $uniqueNotificationItems;

usort($notificationItems, function($a, $b) {
    return ($b['time'] ?? 0) <=> ($a['time'] ?? 0);
});
$notificationItems = array_slice($notificationItems, 0, 10);
$notificationCount = count($notificationItems);
?>

<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link active"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link"><span class="nav-icon">S</span><span>Sales Record</span></a>
    <a href="announcements.php" class="nav-link"><span class="nav-icon">A</span><span>Announcement</span></a>
    <a href="subscribers.php" class="nav-link"><span class="nav-icon">EM</span><span>Subscribers</span></a>
    <a href="archive.php" class="nav-link"><span class="nav-icon">AR</span><span>Archive</span></a>
    <a href="settings.php" class="nav-link"><span class="nav-icon">ST</span><span>Settings</span></a>
</div>

<div class="admin-dashboard">

<div class="admin-topbar">
    <div class="admin-brand">

        <button id="menuToggle" class="menu-btn">Menu</button>

        <div>
            <h1>Villa Eusebio Admin</h1>
            <p>Admin Dashboard</p>
        </div>

    </div>

    <div class="admin-userbar">
        <a href="settings.php" class="admin-avatar-link" aria-label="Open settings" title="Open settings"></a>
        <div class="admin-notification-center" id="adminNotificationCenter">
            <button type="button" class="admin-notification-toggle" id="adminNotificationToggle" aria-expanded="false">
                <img src="../assets/menu-notification.svg" alt="" aria-hidden="true">
                Notifications
                <span id="adminNotificationBadge" hidden>0</span>
            </button>
            <div class="admin-notification-menu" id="adminNotificationMenu" aria-hidden="true">
                <div class="admin-notification-menu-head">
                    <strong>Notifications</strong>
                    <small><?php echo $notificationCount > 0 ? (int)$notificationCount . ' recent update' . ($notificationCount === 1 ? '' : 's') : 'All clear'; ?></small>
                </div>
                <?php if ($notificationItems): ?>
                    <?php foreach ($notificationItems as $index => $item): ?>
                        <button type="button" class="admin-notification-item" data-notification-index="<?php echo (int)$index; ?>">
                            <span class="notification-type-pill <?php echo htmlspecialchars($item['type']); ?>"><?php echo htmlspecialchars($item['type'] === 'booking' ? 'Booking' : 'Subscriber'); ?></span>
                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                            <em><?php echo htmlspecialchars($item['summary']); ?></em>
                            <small><?php echo htmlspecialchars($item['time_label']); ?></small>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="admin-notification-empty">No new bookings or subscribers yet.</div>
                <?php endif; ?>
            </div>
        </div>
        <strong>Owner</strong>
        <button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button>
        <a href="../api/logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<?php if ($pendingCount > 0): ?>
<div class="admin-notice-bar dashboard-review-alert" onclick="goToNewReservations()">
    <span class="dashboard-review-icon" aria-hidden="true">!</span>
    <div>
        <strong><?php echo $pendingCount; ?> booking request<?php echo $pendingCount > 1 ? 's' : ''; ?> need review.</strong>
        <small>Review and approve pending reservations to keep your calendar up to date.</small>
    </div>
    <button type="button">Review requests -></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
<div style="margin: 16px 0; padding: 12px 14px; border: 1px solid #e2b3b3; background: #fff2f2; color: #8a1f1f; border-radius: 10px;">
    <?php echo htmlspecialchars($_GET['error']); ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>
<div style="margin: 16px 0; padding: 12px 14px; border: 1px solid #b8d8b8; background: #f2fff2; color: #1f6b1f; border-radius: 10px;">
    <?php echo htmlspecialchars($_GET['success']); ?>
</div>
<?php endif; ?>

    <div class="admin-stats-grid dashboard-metrics-grid">
        <article class="admin-stat-card dashboard-metric-card total-card">
            <span class="dashboard-card-icon dashboard-icon-calendar" aria-hidden="true"></span>
            <div class="stat-label">Total Reservations</div>
            <div class="stat-value"><?php echo $totalReservations; ?></div>
            <div class="stat-trend">All time</div>
        </article>

        <article class="admin-stat-card dashboard-metric-card pending-card">
            <span class="dashboard-card-icon dashboard-icon-clock" aria-hidden="true"></span>
            <div class="stat-label">Pending Approval</div>
            <div class="stat-value"><?php echo $pendingCount; ?></div>
            <div class="stat-trend">All time</div>
        </article>

        <article class="admin-stat-card dashboard-metric-card confirmed-card">
            <span class="dashboard-card-icon dashboard-icon-check" aria-hidden="true"></span>
            <div class="stat-label">Approved Bookings</div>
            <div class="stat-value"><?php echo $approvedCount; ?></div>
            <div class="stat-trend">All time</div>
        </article>

        <article class="admin-stat-card dashboard-metric-card revenue-card">
            <span class="dashboard-card-icon dashboard-icon-peso" aria-hidden="true"></span>
            <div class="stat-label">Completed Booking Value</div>
            <div class="stat-value">&#8369;<?php echo number_format($bookingValueThisMonth); ?></div>
            <div class="stat-trend"><?php echo htmlspecialchars($dashboardMonthLabel); ?></div>
        </article>
    </div>

    <div class="dashboard-home-grid">
        <div class="dashboard-primary-column">
            <section class="admin-calendar-wrapper dashboard-calendar-panel">
                <div class="calendar-admin-head dashboard-calendar-head">
                    <div>
                        <h2>Booking Calendar</h2>
                        <div class="dashboard-calendar-legend" aria-label="Calendar legend">
                            <span class="legend-day">Day tour</span>
                            <span class="legend-overnight">Overnight</span>
                            <span class="legend-22hour">22-hour stay</span>
                            <span class="legend-blocked">Blocked</span>
                        </div>
                    </div>
                </div>
                <div id="adminCalendar"></div>
            </section>

            <section class="dashboard-panel dashboard-activity-panel" aria-label="Booking activity">
                <div class="dashboard-panel-head dashboard-activity-head">
                    <div>
                        <h2>Booking Activity</h2>
                        <p class="calendar-helper-text">Monthly bookings and fully paid booking value.</p>
                    </div>
                    <div class="dashboard-chart-toggle" aria-label="Chart view">
                        <button type="button" class="active" data-dashboard-chart="bookings">Bookings</button>
                        <button type="button" data-dashboard-chart="value">Completed Value</button>
                    </div>
                </div>

                <svg class="dashboard-bar-chart active" data-dashboard-chart-view="bookings" viewBox="0 0 720 230" role="img" aria-label="Monthly bookings">
                    <?php foreach ($dashboardBookingAxisValues as $axisIndex => $axisValue):
                        $axisY = 28 + ((128 / 4) * $axisIndex);
                    ?>
                        <line x1="64" y1="<?php echo htmlspecialchars((string)round($axisY, 2)); ?>" x2="684" y2="<?php echo htmlspecialchars((string)round($axisY, 2)); ?>"></line>
                        <text class="axis-label" x="44" y="<?php echo htmlspecialchars((string)round($axisY + 4, 2)); ?>"><?php echo htmlspecialchars((string)(int)$axisValue); ?></text>
                    <?php endforeach; ?>
                    <?php
                    $dashboardLabels = $analyticsSeries['labels'];
                    $dashboardCount = max(count($dashboardLabels), 1);
                    $dashboardSlot = 620 / $dashboardCount;
                    $dashboardBase = 156;
                    $dashboardHeight = 128;
                    foreach ($dashboardLabels as $index => $label):
                        $value = $analyticsSeries['bookings'][$index] ?? 0;
                        $barHeight = ($value / $dashboardBookingChartMax) * $dashboardHeight;
                        $barWidth = min(44, max(20, $dashboardSlot * .42));
                        $x = 64 + ($dashboardSlot * $index) + (($dashboardSlot - $barWidth) / 2);
                        $y = $dashboardBase - $barHeight;
                    ?>
                        <rect class="bar-bookings" x="<?php echo htmlspecialchars((string)round($x, 2)); ?>" y="<?php echo htmlspecialchars((string)round($y, 2)); ?>" width="<?php echo htmlspecialchars((string)round($barWidth, 2)); ?>" height="<?php echo htmlspecialchars((string)max(1, round($barHeight, 2))); ?>"></rect>
                        <?php if ((int)$value > 0): ?>
                            <text class="value-label" x="<?php echo htmlspecialchars((string)round($x + ($barWidth / 2), 2)); ?>" y="<?php echo htmlspecialchars((string)round($y - 8, 2)); ?>"><?php echo htmlspecialchars((string)(int)$value); ?></text>
                        <?php endif; ?>
                        <text class="month-label" x="<?php echo htmlspecialchars((string)round(64 + ($dashboardSlot * $index) + ($dashboardSlot / 2), 2)); ?>" y="196"><?php echo htmlspecialchars($label); ?></text>
                    <?php endforeach; ?>
                </svg>

                <svg class="dashboard-bar-chart" data-dashboard-chart-view="value" viewBox="0 0 720 230" role="img" aria-label="Monthly completed booking value">
                    <?php foreach ($dashboardRevenueAxisValues as $axisIndex => $axisValue):
                        $axisY = 28 + ((128 / 4) * $axisIndex);
                    ?>
                        <line x1="76" y1="<?php echo htmlspecialchars((string)round($axisY, 2)); ?>" x2="684" y2="<?php echo htmlspecialchars((string)round($axisY, 2)); ?>"></line>
                        <text class="axis-label" x="58" y="<?php echo htmlspecialchars((string)round($axisY + 4, 2)); ?>">&#8369;<?php echo htmlspecialchars(number_format($axisValue)); ?></text>
                    <?php endforeach; ?>
                    <?php
                    $valueSlot = 608 / $dashboardCount;
                    foreach ($dashboardLabels as $index => $label):
                        $value = $analyticsSeries['revenue'][$index] ?? 0;
                        $barHeight = ($value / $dashboardRevenueChartMax) * $dashboardHeight;
                        $barWidth = min(44, max(20, $valueSlot * .42));
                        $x = 76 + ($valueSlot * $index) + (($valueSlot - $barWidth) / 2);
                        $y = $dashboardBase - $barHeight;
                    ?>
                        <rect class="bar-value" x="<?php echo htmlspecialchars((string)round($x, 2)); ?>" y="<?php echo htmlspecialchars((string)round($y, 2)); ?>" width="<?php echo htmlspecialchars((string)round($barWidth, 2)); ?>" height="<?php echo htmlspecialchars((string)max(1, round($barHeight, 2))); ?>"></rect>
                        <text class="month-label" x="<?php echo htmlspecialchars((string)round(76 + ($valueSlot * $index) + ($valueSlot / 2), 2)); ?>" y="196"><?php echo htmlspecialchars($label); ?></text>
                    <?php endforeach; ?>
                </svg>
            </section>
        </div>

        <aside class="dashboard-side-column">
            <section class="dashboard-panel dashboard-upcoming-card">
                <div class="dashboard-panel-head">
                    <h2>Upcoming Stay</h2>
                    <a href="reservation.php" class="panel-link-btn">View all</a>
                </div>
                <?php if ($upcomingStays):
                    usort($upcomingStays, function($a, $b) {
                        $order = ['day' => 1, '22hour' => 2, 'overnight' => 3];
                        return ($order[$a['time_type'] ?? ''] ?? 9) <=> ($order[$b['time_type'] ?? ''] ?? 9);
                    });
                    $upcomingDate = new DateTimeImmutable(($upcomingStayDate ?? $todayString) . ' 00:00:00');
                    $upcomingDays = (int)$todayDate->diff($upcomingDate)->format('%a');
                    $upcomingDayLabel = $upcomingDays === 0 ? 'Today' : 'In ' . $upcomingDays . ' day' . ($upcomingDays === 1 ? '' : 's');
                ?>
                    <div class="upcoming-stay-layout">
                        <div class="upcoming-date-box">
                            <span><?php echo htmlspecialchars(strtoupper($upcomingDate->format('M'))); ?></span>
                            <strong><?php echo htmlspecialchars($upcomingDate->format('j')); ?></strong>
                            <small><?php echo htmlspecialchars($upcomingDate->format('Y')); ?></small>
                        </div>
                        <div class="upcoming-stay-info">
                            <span class="upcoming-days-pill"><?php echo htmlspecialchars($upcomingDayLabel); ?></span>
                            <div class="upcoming-stay-list">
                                <?php foreach ($upcomingStays as $upcomingStay):
                                    $upcomingType = $upcomingStay['time_type'] ?? '';
                                    $upcomingStayValue = (float)($upcomingStay['total_stay_value'] ?: getBasePrice($upcomingType));
                                ?>
                                    <a class="upcoming-stay-item" href="reservation.php?highlight=booking&id=<?php echo (int)$upcomingStay['id']; ?>">
                                        <h3><?php echo htmlspecialchars($upcomingStay['guest_name'] ?? 'Guest'); ?></h3>
                                        <b class="next-booking-stay-pill <?php echo htmlspecialchars(stayTypeColorClass($upcomingType)); ?>"><?php echo htmlspecialchars(formatStayType($upcomingType)); ?></b>
                                        <p><?php if ($upcomingStayValue > 0): ?>Booking value &#8369;<?php echo number_format($upcomingStayValue); ?><?php else: ?>Approved booking<?php endif; ?></p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <a href="reservation.php?date=<?php echo htmlspecialchars($upcomingDate->format('Y-m-d')); ?>" class="dashboard-primary-link">View reservations</a>
                <?php else: ?>
                    <p class="muted-text">No upcoming approved stays.</p>
                    <a href="reservation.php" class="dashboard-primary-link">Open reservations</a>
                <?php endif; ?>
            </section>

            <section class="dashboard-panel dashboard-status-card">
                <div class="dashboard-panel-head">
                    <h2>Booking Status</h2>
                    <strong>Total <?php echo (int)($pendingCount + $approvedCount + $rejectedCount + $cancelledCount); ?></strong>
                </div>
                <div class="dashboard-status-stack" aria-hidden="true">
                    <?php foreach ($dashboardStatusRows as $statusRow):
                        $percent = ($statusRow['count'] / $dashboardStatusTotal) * 100;
                    ?>
                        <span class="<?php echo htmlspecialchars($statusRow['class']); ?>" style="width: <?php echo htmlspecialchars((string)round($percent, 2)); ?>%;"></span>
                    <?php endforeach; ?>
                </div>
                <div class="dashboard-status-list">
                    <?php foreach ($dashboardStatusRows as $statusRow):
                        $percent = $dashboardStatusTotal > 0 ? round(($statusRow['count'] / $dashboardStatusTotal) * 100) : 0;
                    ?>
                        <div>
                            <span class="status-dot <?php echo htmlspecialchars($statusRow['class']); ?>"></span>
                            <strong><?php echo htmlspecialchars($statusRow['label']); ?></strong>
                            <b><?php echo (int)$statusRow['count']; ?></b>
                            <em><?php echo (int)$percent; ?>%</em>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="dashboard-panel dashboard-announcement-card">
                <div class="dashboard-panel-head">
                    <h2>Announcement</h2>
                    <a href="announcements.php" class="panel-link-btn">Manage announcement</a>
                </div>
                <div class="dashboard-announcement-body">
                    <span class="dashboard-mini-icon" aria-hidden="true"></span>
                    <div>
                        <?php if ($activeAnnouncement): ?>
                            <?php
                            $announcementPreview = trim(strip_tags($activeAnnouncement['message'] ?? ''));
                            if (strlen($announcementPreview) > 90) {
                                $announcementPreview = substr($announcementPreview, 0, 87) . '...';
                            }
                            ?>
                            <h3><?php echo htmlspecialchars($activeAnnouncement['title']); ?></h3>
                            <p><?php echo htmlspecialchars($announcementPreview); ?></p>
                        <?php else: ?>
                            <h3>No active announcement</h3>
                            <p>Create an announcement to inform your guests.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="dashboard-subscriber-link" href="subscribers.php">
                    <span class="dashboard-mini-icon subscriber" aria-hidden="true"></span>
                    <strong>Subscribers <b><?php echo (int)$totalSubscribers; ?></b></strong>
                    <em>View subscribers</em>
                </a>
            </section>
        </aside>
    </div>
<div id="adminTooltip" class="admin-tooltip"></div>

<div id="calendarContextMenu" class="calendar-context-menu">
    <button type="button" id="openBlockDateModal">Block this date</button>
    <button type="button" id="openEditBlockDateModal" class="blocked-only-action">Edit blocked date</button>
    <button type="button" id="showBlockedInfo" class="blocked-only-action">Show block reason</button>
    <button type="button" id="removeBlockedDate" class="blocked-only-action danger-context-action">Remove blocked date</button>
</div>

<div id="blockDateModal" class="modal reservation-modal">
    <div class="modal-content block-date-modal-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Admin Calendar</p>
                <h3 id="blockModalTitle">Block Unavailable Date</h3>
            </div>
            <button type="button" class="close-block-modal">&times;</button>
        </div>
        <form method="POST" action="../api/block_date.php" class="block-date-form" data-ve-managed="true">
            <input type="hidden" name="action" id="blockFormAction" value="create">
            <input type="hidden" name="block_id" id="blockIdValue" value="0">
            <input type="hidden" name="blocked_date" id="blockDateValue">
            <div class="block-date-selected">
                <span>Selected date</span>
                <strong id="blockDateText">-</strong>
            </div>
            <label>Blocked schedule</label>
            <select name="stay_type" id="blockStayType" required>
                <option value="day">Day Tour only</option>
                <option value="overnight">Overnight only</option>
                <option value="22hour">22-Hour Stay</option>
                <option value="whole">Whole Day Blocked</option>
            </select>
            <label>Reason why unavailable</label>
            <textarea name="reason" id="blockReason" rows="4" placeholder="Example: Maintenance, private event, staff unavailable..." required></textarea>
            <div class="modal-footer reservation-confirm-actions">
                <button type="button" class="modal-btn btn-cancel-action close-block-modal-btn">Cancel</button>
                <button type="submit" class="modal-btn btn-reject" id="blockSubmitBtn">Block Date</button>
            </div>
        </form>
    </div>
</div>

</div>

</div>


<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var calendarEl = document.getElementById('adminCalendar');
    const tooltip = document.getElementById('adminTooltip');
    const blockDateForm = document.querySelector('.block-date-form');

    function getTimeRange(type) {
        if (type === 'day') {
            return { checkin: '9:00 AM', checkout: '5:00 PM' };
        }
        if (type === 'overnight') {
            return { checkin: '7:00 PM', checkout: '7:00 AM' };
        }
        if (type === '22hour') {
            return { checkin: '9:00 AM', checkout: '7:00 AM' };
        }
        if (type === 'whole' || type === 'blocked') {
            return { checkin: 'Whole day', checkout: 'Unavailable' };
        }
        return { checkin: '-', checkout: '-' };
    }

    function getBadge(type) {
        if (type === 'day') return '<span class="tooltip-badge badge-day">Day Tour</span>';
        if (type === 'overnight') return '<span class="tooltip-badge badge-overnight">Overnight</span>';
        if (type === '22hour') return '<span class="tooltip-badge badge-22hour">22-Hour</span>';
        if (type === 'whole' || type === 'blocked') return '<span class="tooltip-badge badge-blocked">Admin Blocked</span>';
        return '';
    }

    function formatCalendarDate(dateValue) {
        if (!dateValue) return '-';
        const date = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return dateValue;
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function statusText(status) {
        return String(status || 'unpaid').toLowerCase() === 'paid' ? 'Paid' : 'Unpaid';
    }

    function statusClass(status) {
        return String(status || 'unpaid').toLowerCase() === 'paid' ? 'status-paid-text' : 'status-unpaid-text';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(match) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[match];
        });
    }

    function bookingSortRank(type) {
        if (type === 'day') return 1;
        if (type === 'overnight') return 2;
        if (type === '22hour') return 3;
        if (type === 'whole' || type === 'blocked') return 4;
        return 9;
    }

    function getTooltipType(source) {
        return source.booking_time_type || source.type || source.slot || '';
    }

    function uniqueTooltipDetails(details) {
        const seen = new Set();
        return (details || []).filter(function(detail) {
            const key = detail.is_blocked
                ? 'block-' + (detail.block_id || detail.check_in_date || '')
                : 'booking-' + (detail.booking_id || detail.guest_name || '') + '-' + getTooltipType(detail);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        }).sort(function(a, b) {
            return bookingSortRank(getTooltipType(a)) - bookingSortRank(getTooltipType(b));
        });
    }

    function buildPaymentInfo(source) {
        const reservationFeeStatus = statusText(source.reservation_fee_status);
        const balanceStatus = statusText(source.payment_status);
        return `
            <div class="tooltip-row tooltip-payment-row">Reservation Fee: <span class="${statusClass(source.reservation_fee_status)}">${reservationFeeStatus}</span></div>
            <div class="tooltip-row tooltip-payment-row">Remaining Balance: <span class="${statusClass(source.payment_status)}">${balanceStatus}</span></div>
        `;
    }

    function buildBookingTooltip(source) {
        const type = getTooltipType(source);
        const time = getTimeRange(type);
        if (source.is_blocked) {
            return `
                <div class="tooltip-booking-item tooltip-blocked-item">
                    <div class="tooltip-title">Admin Blocked Date</div>
                    ${getBadge(type)}
                    <div class="tooltip-row"><strong>Reason:</strong> ${escapeHtml(source.block_reason || 'Admin blocked date')}</div>
                </div>
            `;
        }

        return `
            <div class="tooltip-booking-item">
                <div class="tooltip-title">${escapeHtml(source.guest_name || 'Booked')}</div>
                ${getBadge(type)}
                <div class="tooltip-row">Check In: ${time.checkin}</div>
                <div class="tooltip-row">Check Out: ${time.checkout}</div>
                ${buildPaymentInfo(source)}
            </div>
        `;
    }


    function clearDayCellStyles() {
        calendarEl.querySelectorAll('.fc-daygrid-day').forEach(function(cell) {
            cell.style.background = '';
            cell.classList.remove(
                'admin-blocked-day',
                'admin-day-tour-day',
                'admin-overnight-day',
                'admin-full-day',
                'admin-22hour-day'
            );
            cell.removeAttribute('data-admin-day-label');
        });
    }

    function applySplitDayColors() {
        clearDayCellStyles();
        const eventMap = {};

        calendar.getEvents().forEach(function(event) {
            const start = event.start;
            if (!start) return;
            if (event.extendedProps && event.extendedProps.isCalendarChip) return;
            const dateKey = start.toLocaleDateString('en-CA');
            eventMap[dateKey] = event.extendedProps || {};
        });

        calendarEl.querySelectorAll('.fc-daygrid-day').forEach(function(cell) {
            const dateKey = cell.getAttribute('data-date');
            const props = eventMap[dateKey];
            if (!props) return;

            if (props.type === 'blocked') {
                cell.classList.add('admin-blocked-day');
                cell.dataset.adminDayLabel = 'Blocked';
            } else if (props.type === '22hour') {
                cell.classList.add('admin-22hour-day');
            } else if (Array.isArray(props.slots) && props.slots.includes('day') && props.slots.includes('overnight')) {
                cell.classList.add('admin-full-day');
            } else if (props.type === 'overnight') {
                cell.classList.add('admin-overnight-day');
            } else if (props.type === 'day') {
                cell.classList.add('admin-day-tour-day');
            }
        });
    }

    function findCalendarCell(dateValue) {
        if (!dateValue) return null;
        return calendarEl.querySelector('.fc-daygrid-day[data-date="' + String(dateValue).replace(/"/g, '') + '"]');
    }

    function optimisticBlockCell(dateValue, background) {
        const cell = findCalendarCell(dateValue);
        if (!cell) return null;
        const previousBackground = cell.style.background;
        const previousClass = cell.className;
        const previousLabel = cell.dataset.adminDayLabel || '';
        if (background === undefined) {
            cell.style.background = '';
            cell.classList.add('admin-blocked-day');
            cell.dataset.adminDayLabel = 'Saving';
        } else {
            cell.style.background = background;
        }
        cell.classList.add('ve-optimistic-pending');
        return function() {
            cell.style.background = previousBackground;
            cell.className = previousClass;
            if (previousLabel) {
                cell.dataset.adminDayLabel = previousLabel;
            } else {
                cell.removeAttribute('data-admin-day-label');
            }
        };
    }

    function calendarStayLabel(type) {
        if (type === 'day') return 'Day tour';
        if (type === 'overnight') return 'Overnight';
        if (type === '22hour') return '22-hour stay';
        if (type === 'whole' || type === 'blocked') return 'Blocked';
        if (type === 'full') return 'Booked';
        return 'Booked';
    }

    function calendarFirstName(name) {
        const first = String(name || '').trim().split(/\s+/)[0] || '';
        return first.length > 14 ? first.slice(0, 13) + '...' : first;
    }

    function dashboardCalendarEvents(events) {
        const output = [];
        (events || []).forEach(function(event) {
            const aggregate = Object.assign({}, event, {
                display: 'background',
                classNames: ['dashboard-calendar-bg-event']
            });
            output.push(aggregate);

            const props = event.extendedProps || {};
            const details = uniqueTooltipDetails(props.details || []);
            const visibleDetails = details.length ? details.slice(0, 3) : [props];
            visibleDetails.forEach(function(detail) {
                const type = getTooltipType(detail) || props.type || '';
                const isBlocked = !!detail.is_blocked || type === 'whole' || type === 'blocked';
                const guest = isBlocked ? '' : calendarFirstName(detail.guest_name || props.guest_name);
                const title = calendarStayLabel(type) + (guest ? ' ' + guest : '');
                output.push(Object.assign({}, event, {
                    title: title,
                    display: 'block',
                    backgroundColor: 'transparent',
                    borderColor: 'transparent',
                    classNames: ['dashboard-calendar-chip', 'dashboard-calendar-' + (type || 'default')],
                    extendedProps: Object.assign({}, props, {
                        isCalendarChip: true,
                        chipType: type,
                        details: props.details || [],
                        display_detail: detail
                    })
                }));
            });
        });
        return output;
    }

    function loadCalendarEvents(fetchInfo, successCallback, failureCallback) {
        const eventsUrl = '../api/get_booked_dates.php?mode=events';
        calendarEl.classList.add('is-loading');
        const eventRequest = window.VillaAsync
            ? window.VillaAsync.cachedJson(eventsUrl, {}, {
                ttl: 5000,
                cacheKey: 'adminCalendarEvents',
                force: true
            })
            : fetch(eventsUrl).then(response => response.json());

        eventRequest
            .then(function(events) {
                successCallback(dashboardCalendarEvents(events));
            })
            .catch(failureCallback)
            .finally(function() {
                calendarEl.classList.remove('is-loading');
            });
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',
        height: 'auto',
        contentHeight: 'auto',
        expandRows: false,
        fixedWeekCount: false,

        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: ''
        },

        events: loadCalendarEvents,

        loading: function(isLoading) {
            calendarEl.classList.toggle('is-loading', isLoading);
        },

        dayCellDidMount: function() {
            setTimeout(applySplitDayColors, 30);
        },

        datesSet: function() {
            setTimeout(applySplitDayColors, 30);
        },

        eventsSet: function() {
            setTimeout(applySplitDayColors, 30);
        },

        

        eventMouseEnter: function(info) {

            const props = info.event.extendedProps;
            const tooltipDetails = uniqueTooltipDetails(props.details || []);
            const tooltipHtml = tooltipDetails.length
                ? tooltipDetails.map(buildBookingTooltip).join('')
                : buildBookingTooltip(props);

            tooltip.innerHTML = tooltipHtml;
            tooltip.classList.add('show');
            tooltip.style.left = (info.jsEvent.pageX + 12) + 'px';
            tooltip.style.top = (info.jsEvent.pageY + 12) + 'px';
            return;
        },

        eventMouseLeave: function() {
            tooltip.classList.remove('show');
        },

        eventMouseMove: function(info) {
            tooltip.style.left = (info.jsEvent.pageX + 12) + 'px';
            tooltip.style.top = (info.jsEvent.pageY + 12) + 'px';
        },

        eventClick: function(info) {
            const dateKey = info.event.start ? info.event.start.toLocaleDateString('en-CA') : '';
            if (!dateKey) return;
            sessionStorage.setItem('reservationHighlightDate', dateKey);
            window.location.href = 'reservation.php?highlight=date';
        },

        eventDidMount: function(info) {
            info.el.style.opacity = "0.9";
            info.el.style.cursor = 'pointer';
        }

    });

    calendar.render();

    if (window.VillaAsync) {
        window.VillaAsync.onSync(function(detail) {
            if (detail.external) {
                window.location.reload();
                return;
            }
            calendar.refetchEvents();
        });
    }

    setInterval(function() {
        if (document.visibilityState === 'visible') {
            calendar.refetchEvents();
        }
    }, 10000);

    let selectedBlockDate = '';
    let selectedBlockDetail = null;
    let selectedApprovedBookingDetail = null;
    const contextMenu = document.getElementById('calendarContextMenu');
    const blockModal = document.getElementById('blockDateModal');
    const blockDateValue = document.getElementById('blockDateValue');
    const blockDateText = document.getElementById('blockDateText');
    const blockIdValue = document.getElementById('blockIdValue');
    const blockFormAction = document.getElementById('blockFormAction');
    const blockStayType = document.getElementById('blockStayType');
    const blockReason = document.getElementById('blockReason');
    const blockModalTitle = document.getElementById('blockModalTitle');
    const blockSubmitBtn = document.getElementById('blockSubmitBtn');

    function getDetailsForDate(dateKey) {
        const matching = calendar.getEvents().find(function(event) {
            const startKey = event.start ? event.start.toLocaleDateString('en-CA') : '';
            return startKey === dateKey;
        });
        return matching && matching.extendedProps ? (matching.extendedProps.details || []) : [];
    }

    function getBlockDetailForDate(dateKey) {
        const details = getDetailsForDate(dateKey);
        return details.find(function(item) { return item.is_blocked; }) || null;
    }

    function getApprovedBookingDetailForDate(dateKey) {
        const details = getDetailsForDate(dateKey);
        return details.find(function(item) { return !item.is_blocked && item.booking_id; }) || null;
    }

    function updateContextMenuForDate(dateKey) {
        selectedBlockDetail = getBlockDetailForDate(dateKey);
        selectedApprovedBookingDetail = getApprovedBookingDetailForDate(dateKey);
        const openBlockButton = document.getElementById('openBlockDateModal');
        document.querySelectorAll('.blocked-only-action').forEach(function(btn) {
            btn.style.display = selectedBlockDetail ? 'block' : 'none';
        });
        openBlockButton.style.display = selectedBlockDetail ? 'none' : 'block';
        openBlockButton.disabled = !!selectedApprovedBookingDetail;
        openBlockButton.textContent = selectedApprovedBookingDetail ? 'Approved date cannot be blocked' : 'Block this date';
    }

    function openBlockModal(dateKey, mode = 'create') {
        selectedBlockDate = dateKey;
        const detail = mode === 'edit' ? selectedBlockDetail : null;
        blockFormAction.value = mode === 'edit' ? 'update' : 'create';
        blockIdValue.value = detail && detail.block_id ? detail.block_id : 0;
        blockDateValue.value = dateKey;
        blockDateText.textContent = formatCalendarDate(dateKey);
        blockStayType.value = detail ? (detail.booking_time_type || detail.slot || 'day') : 'day';
        blockReason.value = detail ? (detail.block_reason || '') : '';
        blockModalTitle.textContent = mode === 'edit' ? 'Edit Blocked Date' : 'Block Unavailable Date';
        blockSubmitBtn.textContent = mode === 'edit' ? 'Save Changes' : 'Block Date';
        contextMenu.classList.remove('show');
        blockModal.classList.add('show');
    }

    function attachRightClickToDays() {
        calendarEl.querySelectorAll('.fc-daygrid-day').forEach(function(cell) {
            if (cell.dataset.contextReady === '1') return;
            cell.dataset.contextReady = '1';
            cell.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                selectedBlockDate = cell.getAttribute('data-date');
                contextMenu.style.left = e.pageX + 'px';
                contextMenu.style.top = e.pageY + 'px';
                updateContextMenuForDate(selectedBlockDate);
                contextMenu.classList.add('show');
            });
        });
    }

    setTimeout(attachRightClickToDays, 300);
    calendar.on('datesSet', function() { setTimeout(attachRightClickToDays, 200); });
    calendar.on('eventsSet', function() { setTimeout(attachRightClickToDays, 200); });

    calendarEl.addEventListener('contextmenu', function(e) {
        const dayCell = e.target.closest('.fc-daygrid-day');
        if (!dayCell) return;
        e.preventDefault();
        selectedBlockDate = dayCell.getAttribute('data-date');
        if (!selectedBlockDate) return;
        contextMenu.style.left = e.pageX + 'px';
        contextMenu.style.top = e.pageY + 'px';
        updateContextMenuForDate(selectedBlockDate);
        contextMenu.classList.add('show');
    });

    document.getElementById('openBlockDateModal').addEventListener('click', function() {
        if (selectedApprovedBookingDetail) {
            alert('This date already has an approved reservation and cannot be blocked.');
            contextMenu.classList.remove('show');
            return;
        }
        if (selectedBlockDate) openBlockModal(selectedBlockDate, 'create');
    });

    document.getElementById('openEditBlockDateModal').addEventListener('click', function() {
        if (selectedBlockDate && selectedBlockDetail) openBlockModal(selectedBlockDate, 'edit');
    });

    document.getElementById('showBlockedInfo').addEventListener('click', function() {
        if (!selectedBlockDetail) return;
        alert('Blocked date: ' + formatCalendarDate(selectedBlockDate) + '\nReason: ' + (selectedBlockDetail.block_reason || 'No reason provided'));
        contextMenu.classList.remove('show');
    });

    document.getElementById('removeBlockedDate').addEventListener('click', function() {
        if (!selectedBlockDetail || !selectedBlockDetail.block_id) return;
        if (!confirm('Remove this blocked date?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/block_date.php';
        form.dataset.veManaged = 'true';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="block_id" value="' + selectedBlockDetail.block_id + '">';
        document.body.appendChild(form);
        if (window.VillaAsync) window.VillaAsync.ensureCsrf(form);
        form.submit();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#calendarContextMenu')) contextMenu.classList.remove('show');
    });

    document.querySelectorAll('.close-block-modal, .close-block-modal-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { blockModal.classList.remove('show'); });
    });

    blockModal.addEventListener('click', function(e) {
        if (e.target === blockModal) blockModal.classList.remove('show');
    });
});
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const dashboard = document.querySelector(".admin-dashboard");

    if (localStorage.getItem("sidebar") === "collapsed") {
        sidebar.classList.add("active");
        dashboard.classList.add("shift");
    }

    if (btn) {
        btn.addEventListener("click", function () {
            sidebar.classList.toggle("active");
            dashboard.classList.toggle("shift");

            if (sidebar.classList.contains("active")) {
                localStorage.setItem("sidebar", "collapsed");
            } else {
                localStorage.setItem("sidebar", "expanded");
            }
        });
    }
});
</script>

<script>
const pendingBookings = <?php
    $pending = array_filter($appointments, function($a) {
        return $a['status'] === 'pending';
    });
    echo json_encode(array_values($pending));
?>;

function goToNewReservations() {
    if (!Array.isArray(pendingBookings) || pendingBookings.length === 0) return;
    const pendingIds = pendingBookings.map(function(item) { return String(item.id); });
    sessionStorage.setItem('reservationHighlightIds', JSON.stringify(pendingIds));
    window.location.href = 'reservation.php?highlight=new';
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartButtons = document.querySelectorAll('[data-dashboard-chart]');
    const chartViews = document.querySelectorAll('[data-dashboard-chart-view]');
    chartButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const target = button.dataset.dashboardChart || 'bookings';
            chartButtons.forEach(function(item) {
                item.classList.toggle('active', item === button);
            });
            chartViews.forEach(function(view) {
                view.classList.toggle('active', view.dataset.dashboardChartView === target);
            });
        });
    });
});
</script>

<div id="notificationDetailModal" class="notif-modal admin-notification-detail-modal" aria-hidden="true">
    <div class="notif-card admin-notification-detail-card">
        <button type="button" class="close-btn admin-notification-close" id="notificationDetailClose">&times;</button>
        <span class="notification-type-pill" id="notificationDetailType">Update</span>
        <h2 id="notificationDetailTitle">Notification</h2>
        <p id="notificationDetailSummary" class="notification-detail-summary"></p>
        <div id="notificationDetailRows" class="notification-detail-rows"></div>
        <div class="notif-actions">
            <a href="#" class="panel-link-btn" id="notificationDetailLink">Open</a>
        </div>
    </div>
</div>

<script>
const adminNotifications = <?php echo json_encode($notificationItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const center = document.getElementById('adminNotificationCenter');
    const toggle = document.getElementById('adminNotificationToggle');
    const menu = document.getElementById('adminNotificationMenu');
    const modal = document.getElementById('notificationDetailModal');
    const closeBtn = document.getElementById('notificationDetailClose');
    const detailType = document.getElementById('notificationDetailType');
    const detailTitle = document.getElementById('notificationDetailTitle');
    const detailSummary = document.getElementById('notificationDetailSummary');
    const detailRows = document.getElementById('notificationDetailRows');
    const detailLink = document.getElementById('notificationDetailLink');
    const badge = document.getElementById('adminNotificationBadge');
    const seenStorageKey = 'villaEusebioSeenAdminNotificationsV1';

    function escapeText(value) {
        return String(value || '').replace(/[&<>"']/g, function(match) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[match];
        });
    }

    function notificationUid(item) {
        return String(item && item.uid ? item.uid : '');
    }

    function readSeenNotifications() {
        try {
            const stored = JSON.parse(localStorage.getItem(seenStorageKey) || '[]');
            return Array.isArray(stored) ? stored : [];
        } catch (error) {
            return [];
        }
    }

    function writeSeenNotifications(ids) {
        try {
            localStorage.setItem(seenStorageKey, JSON.stringify(ids.slice(0, 80)));
        } catch (error) {
            return;
        }
    }

    function currentNotificationIds() {
        return adminNotifications.map(notificationUid).filter(Boolean);
    }

    function updateNotificationBadge() {
        if (!badge) return;
        const seen = new Set(readSeenNotifications());
        const unreadCount = currentNotificationIds().filter(function(id) {
            return !seen.has(id);
        }).length;
        badge.textContent = String(unreadCount);
        badge.hidden = unreadCount === 0;
    }

    function markCurrentNotificationsSeen() {
        const merged = new Set(readSeenNotifications());
        currentNotificationIds().forEach(function(id) {
            merged.add(id);
        });
        writeSeenNotifications(Array.from(merged));
        updateNotificationBadge();
    }

    function closeMenu() {
        if (!menu || !toggle) return;
        menu.classList.remove('show');
        menu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function openNotification(index) {
        const item = adminNotifications[index];
        if (!item || !modal) return;
        detailType.textContent = item.type === 'booking' ? 'Booking' : 'Subscriber';
        detailType.className = 'notification-type-pill ' + (item.type || 'update');
        detailTitle.textContent = item.title || 'Notification';
        detailSummary.textContent = item.summary || '';
        detailLink.href = item.url || '#';
        detailLink.textContent = item.type === 'booking' ? 'Open Reservation' : 'Open Subscriber';
        const rows = item.details || {};
        detailRows.innerHTML = Object.keys(rows).map(function(key) {
            return '<div class="notification-detail-row"><span>' + escapeText(key) + '</span><strong>' + escapeText(rows[key]) + '</strong></div>';
        }).join('');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        closeMenu();
    }

    if (toggle && menu) {
        toggle.addEventListener('click', function(event) {
            event.stopPropagation();
            const willOpen = !menu.classList.contains('show');
            menu.classList.toggle('show', willOpen);
            menu.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) markCurrentNotificationsSeen();
        });
    }

    document.querySelectorAll('.admin-notification-item').forEach(function(item) {
        item.addEventListener('click', function() {
            openNotification(Number(this.dataset.notificationIndex || 0));
        });
    });

    document.addEventListener('click', function(event) {
        if (center && !center.contains(event.target)) closeMenu();
    });

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) closeModal();
        });
    }

    updateNotificationBadge();
});
</script>

