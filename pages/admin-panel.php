<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../pages/owner.php");
    exit;
}

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

$pendingCount = 0;
$approvedCount = 0;
$totalRevenue = 0;
$monthlyRevenue = 0;
$monthlyReservations = 0;
$rescheduledCount = 0;

$currentMonth = date('Y-m');
$totalReservations = count($appointments);
$recentAppointments = array_slice($appointments, 0, 5);
$latestReservation = $appointments[0] ?? null;
$activeAnnouncement = null;
$announcementResult = mysqli_query($conn, "SELECT title, message, updated_at FROM announcements WHERE is_active = 1 AND archived_at IS NULL ORDER BY updated_at DESC, announcement_id DESC LIMIT 1");
if ($announcementResult) {
    $activeAnnouncement = mysqli_fetch_assoc($announcementResult);
}

foreach ($appointments as $appointment) {
    $status = $appointment['status'] ?? '';
    $month = date('Y-m', strtotime($appointment['created_at']));

    if ($status === 'pending') $pendingCount++;
    if ($status === 'approved') {
        $approvedCount++;
        $totalRevenue += getBasePrice($appointment['time_type']);
    }

    if ($status === 'rescheduled') $rescheduledCount++;

    if ($month === $currentMonth) {
        $monthlyReservations++;
        if ($status === 'approved') {
            $monthlyRevenue += getBasePrice($appointment['time_type']);
        }
    }
}
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
        <strong>Owner</strong>
        <button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button>
        <a href="../api/logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<?php if ($pendingCount > 0): ?>
<div class="admin-notice-bar" onclick="goToNewReservations()">
    You have <?php echo $pendingCount; ?> new booking request<?php echo $pendingCount > 1 ? 's' : ''; ?> - Click to view
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

    <div class="admin-stats-grid">

        <div class="admin-stat-card total-card">
            <div class="stat-label">Total Reservations</div>
            <div class="stat-value"><?php echo $totalReservations; ?></div>
            <div class="stat-trend">All booking records</div>
        </div>

        <div class="admin-stat-card pending-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $pendingCount; ?></div>
            <div class="stat-trend">Needs approval</div>
        </div>

        <div class="admin-stat-card confirmed-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value"><?php echo $approvedCount; ?></div>
            <div class="stat-trend"><?php echo $totalReservations ? round(($approvedCount / $totalReservations) * 100) : 0; ?>% of total</div>
        </div>

        <div class="admin-stat-card revenue-card">
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-trend"><?php echo $monthlyReservations; ?> reservations this month</div>
            <div class="stat-value">&#8369;<?php echo number_format($monthlyRevenue); ?></div>
        </div>

    </div>

    <div class="dashboard-workspace-grid">
    <div class="dashboard-primary-column">
    <div class="admin-calendar-wrapper">
    <div class="calendar-admin-head">
        <div>
            <h2>Booking Calendar</h2>
            <p class="calendar-helper-text">Right-click any day to block a date and add the reason why it is unavailable.</p>
        </div>
        <button type="button" class="calendar-block-help-btn" id="calendarBlockHelp">Right-click a day to block</button>
    </div>
    <div id="adminCalendar"></div>
</div>

<div class="dashboard-panel recent-reservations-panel">
    <div class="dashboard-panel-head">
        <div>
            <h2>Recent Reservations</h2>
            <p class="calendar-helper-text">Latest booking activity</p>
        </div>
        <a href="reservation.php" class="panel-link-btn">View All</a>
    </div>
    <div class="recent-table-wrap">
        <table class="recent-reservations-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Guest</th>
                    <th>Stay</th>
                    <th>Check-in</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAppointments as $recent): ?>
                <tr>
                    <td>#<?php echo (int)$recent['id']; ?></td>
                    <td><?php echo htmlspecialchars($recent['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars(formatStayType($recent['time_type'])); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($recent['check_in_date']))); ?></td>
                    <td><span class="status <?php echo htmlspecialchars($recent['status']); ?>"><?php echo htmlspecialchars(ucfirst($recent['status'])); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<aside class="dashboard-side-column">
    <div class="dashboard-panel dashboard-announcement-card">
        <div class="dashboard-panel-head">
            <div>
                <h2>Announcement</h2>
                <p class="calendar-helper-text">Homepage notice</p>
            </div>
            <a href="announcements.php" class="panel-link-btn">Manage</a>
        </div>
        <?php if ($activeAnnouncement): ?>
            <span class="announcement-status-dot">Active</span>
            <h3><?php echo htmlspecialchars($activeAnnouncement['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($activeAnnouncement['message'])); ?></p>
            <small>Updated <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($activeAnnouncement['updated_at']))); ?></small>
        <?php else: ?>
            <span class="announcement-status-dot muted">No active notice</span>
            <h3>No announcement yet</h3>
            <p>Create a customer-facing announcement and it will appear as a notification bubble on the homepage.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-panel reservation-details-card">
        <div class="dashboard-panel-head">
            <div>
                <h2>Reservation Details</h2>
                <p class="calendar-helper-text">Latest booking</p>
            </div>
        </div>
        <?php if ($latestReservation): ?>
            <div class="detail-mini-row"><span>Reservation ID</span><strong>#<?php echo (int)$latestReservation['id']; ?></strong></div>
            <div class="detail-mini-row"><span>Guest</span><strong><?php echo htmlspecialchars($latestReservation['guest_name']); ?></strong></div>
            <div class="detail-mini-row"><span>Stay Type</span><strong><?php echo htmlspecialchars(formatStayType($latestReservation['time_type'])); ?></strong></div>
            <div class="detail-mini-row"><span>Check-in</span><strong><?php echo htmlspecialchars(date('M d, Y', strtotime($latestReservation['check_in_date']))); ?></strong></div>
            <div class="detail-mini-row"><span>Status</span><strong><span class="status <?php echo htmlspecialchars($latestReservation['status']); ?>"><?php echo htmlspecialchars(ucfirst($latestReservation['status'])); ?></span></strong></div>
            <a href="reservation.php?highlight=booking&id=<?php echo (int)$latestReservation['id']; ?>" class="panel-link-btn reservation-detail-link">Go to Reservation</a>
        <?php else: ?>
            <p class="muted-text">No reservations yet.</p>
        <?php endif; ?>
    </div>
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
        });
    }

    function applySplitDayColors() {
        clearDayCellStyles();
        const eventMap = {};

        calendar.getEvents().forEach(function(event) {
            const start = event.start;
            if (!start) return;
            const dateKey = start.toLocaleDateString('en-CA');
            eventMap[dateKey] = event.extendedProps || {};
        });

        calendarEl.querySelectorAll('.fc-daygrid-day').forEach(function(cell) {
            const dateKey = cell.getAttribute('data-date');
            const props = eventMap[dateKey];
            if (!props) return;

            if (props.type === 'blocked') {
                cell.style.background = '#9ca3af';
            } else if (props.type === '22hour') {
                cell.style.background = '#d4af37';
            } else if (Array.isArray(props.slots) && props.slots.includes('day') && props.slots.includes('overnight')) {
                cell.style.background = 'linear-gradient(to bottom, #1e3a8a 0 50%, #f59e0b 50% 100%)';
            } else if (props.type === 'overnight') {
                cell.style.background = '#1e3a8a';
            } else if (props.type === 'day') {
                cell.style.background = '#f59e0b';
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
        cell.style.background = background === undefined ? '#9ca3af' : background;
        cell.classList.add('ve-optimistic-pending');
        return function() {
            cell.style.background = previousBackground;
            cell.className = previousClass;
        };
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
            .then(successCallback)
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

<div id="notifModal" class="notif-modal">

    <div class="notif-card">

        <span class="close-btn" onclick="closeNotifModal()">&times;</span>

        <h2>New Booking</h2>

        <div id="notifContent"></div>

        <div class="notif-actions">
            <button onclick="prevBooking()">← Prev</button>
            <button onclick="nextBooking()">Next →</button>
        </div>

    </div>

</div>

<script>
let currentIndex = 0;

function openNotifModal() {
    if (pendingBookings.length === 0) return;

    document.getElementById('notifModal').style.display = 'flex';
    currentIndex = 0;
    showBooking();
}

function closeNotifModal() {
    document.getElementById('notifModal').style.display = 'none';
}

function nextBooking() {
    if (currentIndex < pendingBookings.length - 1) {
        currentIndex++;
        showBooking();
    }
}

function prevBooking() {
    if (currentIndex > 0) {
        currentIndex--;
        showBooking();
    }
}
</script>

<script>
function formatStay(type) {
    if (type === 'day') return 'Day Tour (9:00 AM – 5:00 PM)';
    if (type === 'overnight') return 'Overnight Stay (7:00 PM – 7:00 AM)';
    if (type === '22hour') return '22-Hour Stay (9:00 AM – 7:00 AM)';
    return type;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatFullDate(dateStr) {
    const date = new Date(dateStr);

    return date.toLocaleDateString('en-US', {
        weekday: 'long',   
        month: 'long',    
        day: 'numeric',   
        year: 'numeric'   
    });
}

function showBooking() {
    if (pendingBookings.length === 0) return;

    let b = pendingBookings[currentIndex];

    document.getElementById('notifContent').innerHTML = `
        <div class="notif-detail">
            <strong>${b.guest_name}</strong>
            <p>${b.email}</p>
            <p>${b.mobile}</p>
        </div>

        <div class="notif-detail">
            <p><strong>Stay Type:</strong> ${formatStay(b.time_type)}</p>
            <p><strong>Check-in:</strong> ${formatFullDate(b.check_in_date)}</p>
            <p><strong>Check-out:</strong> ${formatFullDate(b.check_out_date)}</p>
            <p><strong>Guests:</strong> ${b.guests}</p>
        </div>

        <div class="notif-detail">
            <p><strong>Submitted:</strong> ${formatDate(b.created_at)}</p>
        </div>
    `;
}
</script>








