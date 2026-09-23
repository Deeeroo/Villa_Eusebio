<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: owner.php");
    exit;
}

include "../includes/header.php";
include "../includes/db.php";
include "../includes/booking_repository.php";

function formatStayType($type) {
    if ($type === 'day') return 'Day Tour';
    if ($type === 'overnight') return 'Overnight Stay';
    if ($type === '22hour') return '22-Hour Stay';
    return ucfirst($type);
}


function stayTypeClass($type) {
    if ($type === 'day') return 'stay-day';
    if ($type === 'overnight') return 'stay-overnight';
    if ($type === '22hour') return 'stay-22hour';
    return 'stay-default';
}

function paymentMethodClass($method) {
    if ($method === 'gcash') return 'payment-gcash';
    if ($method === 'bdo') return 'payment-bdo';
    if ($method === 'unionbank') return 'payment-unionbank';
    if ($method === 'cash') return 'payment-cash';
    return 'payment-default';
}

function formatPaymentMethod($method) {
    $map = [
        'gcash' => 'GCash',
        'bdo' => 'BDO Bank Transfer',
        'unionbank' => 'UnionBank',
        'cash' => 'Cash Payment',
    ];
    return $map[$method] ?? strtoupper($method);
}


function getCheckInTime($type) {
    if ($type === 'day' || $type === '22hour') return '9:00 AM';
    if ($type === 'overnight') return '7:00 PM';
    return '-';
}

function getCheckOutTime($type) {
    if ($type === 'day') return '5:00 PM';
    if ($type === 'overnight' || $type === '22hour') return '7:00 AM';
    return '-';
}

$rows = ve_fetch_all_bookings($conn, "bs.booking_id DESC");
$today = date('Y-m-d');
$approvedCount = 0;
$rejectedCount = 0;
$incomingCount = 0;
foreach ($rows as $countRow) {
    $status = strtolower($countRow['status'] ?? '');
    if ($status === 'approved') $approvedCount++;
    if ($status === 'rejected') $rejectedCount++;
    if ($status !== 'rejected' && ($countRow['check_in_date'] ?? '') >= $today) $incomingCount++;
}
?>

<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link active"><span class="nav-icon">R</span><span>Reservation</span></a>
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
            <h1>Villa Eusebio</h1>
            <p>Admin Dashboard</p>
        </div>
    </div>

    <div class="admin-userbar">
        <strong>Owner</strong>
        <button type="button" class="refresh-btn" onclick="window.location.reload();">Refresh</button>
        <a href="../api/logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="main-content">

    <?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
    <div class="admin-alert error-alert"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>
    <div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <div class="admin-page-heading">
        <div>
            <h2>Reservation Management</h2>
            <p class="reservation-helper-text">Click a reservation row to view complete details and approve or reject the booking.</p>
        </div>
        <a href="export_reservations_pdf.php" target="_blank" class="admin-export-btn">Export PDF</a>
    </div>

    <div class="reservation-summary-grid">
        <div class="reservation-summary-card approved"><span>Approved</span><strong><?php echo $approvedCount; ?></strong></div>
        <div class="reservation-summary-card rejected"><span>Rejected</span><strong><?php echo $rejectedCount; ?></strong></div>
        <div class="reservation-summary-card incoming"><span>Incoming</span><strong><?php echo $incomingCount; ?></strong></div>
    </div>

    <div class="filter-dropdown-wrap">
        <button type="button" class="filter-toggle-btn" id="reservationFilterToggle">Filters</button>
        <div class="reservation-filters smart-filters filter-panel" id="reservationFilterPanel">
            <input type="text" id="reservationSearch" placeholder="Search name or ID">
            <select id="reservationStayFilter"><option value="all">All stay types</option><option value="day">Day Tour</option><option value="overnight">Overnight</option><option value="22hour">22-Hour</option></select>
            <select id="reservationStatusFilter"><option value="all">All status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
            <select id="reservationPaymentFilter"><option value="all">All payment types</option><option value="gcash">GCash</option><option value="bdo">BDO</option><option value="unionbank">UnionBank</option><option value="cash">Cash</option></select>
            <button type="button" class="filter-btn active" id="clearReservationFilters">Clear</button>
        </div>
    </div>

    <div class="table-container">
        <table class="reservation-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact No</th>
                    <th>Type</th>
                    <th>Reservation Date</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody id="reservationTable">
            <?php foreach ($rows as $row):
                $isPastBooking = !empty($row['check_out_date']) && $row['check_out_date'] < $today;
            ?>
                <tr
                    class="reservation-row <?php echo $isPastBooking ? 'booking-completed-row' : ''; ?>"
                    data-id="<?php echo (int)$row['id']; ?>"
                    data-is_past="<?php echo $isPastBooking ? '1' : '0'; ?>"
                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                    data-guest_name="<?php echo htmlspecialchars($row['guest_name']); ?>"
                    data-email="<?php echo htmlspecialchars($row['email']); ?>"
                    data-mobile="<?php echo htmlspecialchars($row['mobile']); ?>"
                    data-address="<?php echo htmlspecialchars($row['address']); ?>"
                    data-time_type="<?php echo htmlspecialchars(formatStayType($row['time_type'])); ?>"
                    data-time_type_raw="<?php echo htmlspecialchars($row['time_type']); ?>"
                    data-stay_class="<?php echo htmlspecialchars(stayTypeClass($row['time_type'])); ?>"
                    data-guests="<?php echo htmlspecialchars($row['guests']); ?>"
                    data-payment_method="<?php echo htmlspecialchars(formatPaymentMethod($row['payment_method'])); ?>"
                    data-payment_method_raw="<?php echo htmlspecialchars($row['payment_method']); ?>"
                    data-payment_class="<?php echo htmlspecialchars(paymentMethodClass($row['payment_method'])); ?>"
                    data-check_in_date="<?php echo htmlspecialchars(date('F d, Y', strtotime($row['check_in_date']))); ?>"
                    data-check_in_raw="<?php echo htmlspecialchars($row['check_in_date']); ?>"
                    data-check_in_time="<?php echo htmlspecialchars(getCheckInTime($row['time_type'])); ?>"
                    data-check_out_date="<?php echo htmlspecialchars(date('F d, Y', strtotime($row['check_out_date']))); ?>"
                    data-check_out_raw="<?php echo htmlspecialchars($row['check_out_date']); ?>"
                    data-check_out_time="<?php echo htmlspecialchars(getCheckOutTime($row['time_type'])); ?>"
                    data-created_at="<?php echo htmlspecialchars(date('F d, Y h:i A', strtotime($row['created_at']))); ?>"
                    data-special_requests="<?php echo htmlspecialchars($row['special_requests'] ?: 'None'); ?>"
                    data-rejection_reason="<?php echo htmlspecialchars($row['rejection_reason'] ?? ''); ?>"
                    data-proof_of_payment="<?php echo htmlspecialchars($row['proof_of_payment'] ?? ''); ?>"
                >
                    <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                    <td><span class="stay-badge <?php echo htmlspecialchars(stayTypeClass($row['time_type'])); ?>"><?php echo htmlspecialchars(formatStayType($row['time_type'])); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    <td class="reservation-checkin-cell"><?php echo date('M d, Y', strtotime($row['check_in_date'])); ?><br><small><?php echo htmlspecialchars(getCheckInTime($row['time_type'])); ?></small></td>
                    <td class="reservation-checkout-cell"><?php echo date('M d, Y', strtotime($row['check_out_date'])); ?><br><small><?php echo htmlspecialchars(getCheckOutTime($row['time_type'])); ?></small></td>
                    <td class="reservation-guests-cell <?php echo ((int)$row['guests'] > 30) ? 'guest-count-warning' : ''; ?>"><?php echo (int)$row['guests']; ?></td>
                    <td><span class="status <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span></td>
                    <td><span class="payment-badge <?php echo htmlspecialchars(paymentMethodClass($row['payment_method'])); ?>"><?php echo htmlspecialchars(formatPaymentMethod($row['payment_method'])); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div id="reservationModal" class="modal reservation-modal">
    <div class="modal-content reservation-modal-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Villa Eusebio</p>
                <h3>Reservation Details</h3>
            </div>
            <span class="close-modal">&times;</span>
        </div>

        <div class="modal-body" id="modalDetails"></div>

        <div class="modal-footer" id="modalActions"></div>
    </div>
</div>


<div id="statusConfirmModal" class="modal reservation-modal">
    <div class="modal-content reservation-confirm-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Booking Action</p>
                <h3 id="confirmTitle">Confirm booking action</h3>
            </div>
            <span class="close-confirm-modal">&times;</span>
        </div>
        <div class="modal-body">
            <p id="confirmMessage">Are you sure you want to continue?</p>
            <form method="POST" action="../api/update_status.php" id="statusConfirmForm" class="reject-reason-form">
                <input type="hidden" name="id" id="confirmBookingId">
                <input type="hidden" name="status" id="confirmBookingStatus">
                <input type="hidden" name="redirect" value="reservation">
                <div id="rejectReasonBox" class="reject-reason-box" style="display:none;">
                    <label>Reason for rejection</label>
                    <select name="reject_reason">
                        <option value="No work">No work</option>
                        <option value="Out">Out</option>
                        <option value="Technical difficulties">Technical difficulties</option>
                    </select>
                    <textarea name="reject_note" rows="3" placeholder="Custom note (optional)"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer reservation-confirm-actions">
            <button type="button" class="modal-btn btn-cancel-action" id="cancelStatusAction">Cancel</button>
            <button type="submit" form="statusConfirmForm" class="modal-btn btn-approve" id="confirmStatusAction">Continue</button>
        </div>
    </div>
</div>

<div id="rescheduleModal" class="modal reservation-modal">
    <div class="modal-content reservation-confirm-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Booking Schedule</p>
                <h3>Reschedule reservation</h3>
            </div>
            <span class="close-reschedule-modal">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="../api/reschedule_booking.php" id="rescheduleForm" class="reject-reason-form">
                <input type="hidden" name="id" id="rescheduleBookingId">
                <input type="hidden" name="redirect" value="reservation">
                <input type="hidden" name="check_in_date" id="rescheduleDateInput" required>
                <div class="reservation-detail-grid">
                    <div class="reservation-detail-item"><span>Guest</span><strong id="rescheduleGuest">-</strong></div>
                    <div class="reservation-detail-item"><span>Current Stay Type</span><strong id="rescheduleStayType">-</strong></div>
                </div>
                <label>New stay type</label>
                <select name="time_type" id="rescheduleStayTypeSelect" required>
                    <option value="day">Day Tour</option>
                    <option value="overnight">Overnight Stay</option>
                    <option value="22hour">22-Hour Stay</option>
                </select>
                <div class="reschedule-calendar-card">
                    <div class="reschedule-calendar-toolbar">
                        <button type="button" id="reschedulePrevMonth" aria-label="Previous month">&lsaquo;</button>
                        <strong id="rescheduleCurrentMonth">Month Year</strong>
                        <button type="button" id="rescheduleNextMonth" aria-label="Next month">&rsaquo;</button>
                    </div>
                    <div class="reschedule-calendar-legend">
                        <span><i class="legend-open"></i>Available</span>
                        <span><i class="legend-booked"></i>Booked</span>
                        <span><i class="legend-selected"></i>Selected</span>
                    </div>
                    <div class="reschedule-calendar-grid" id="rescheduleCalendarGrid"></div>
                </div>
                <p class="settings-note" id="reschedulePreview">Choose a new date to preview the checkout date.</p>
            </form>
        </div>
        <div class="modal-footer reservation-confirm-actions">
            <button type="button" class="modal-btn btn-cancel-action close-reschedule-btn">Cancel</button>
            <button type="submit" form="rescheduleForm" class="modal-btn btn-approve">Save Reschedule</button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const dashboard = document.querySelector(".admin-dashboard");
    const detailModal = document.getElementById('reservationModal');
    const confirmModal = document.getElementById('statusConfirmModal');
    const rescheduleModal = document.getElementById('rescheduleModal');
    const statusConfirmForm = document.getElementById('statusConfirmForm');
    const closeDetailModal = detailModal.querySelector('.close-modal');
    const closeConfirmModal = confirmModal.querySelector('.close-confirm-modal');
    const closeRescheduleModal = rescheduleModal.querySelector('.close-reschedule-modal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmButton = document.getElementById('confirmStatusAction');
    const confirmBookingId = document.getElementById('confirmBookingId');
    const confirmBookingStatus = document.getElementById('confirmBookingStatus');
    const rejectReasonBox = document.getElementById('rejectReasonBox');
    const cancelStatusAction = document.getElementById('cancelStatusAction');
    const rescheduleBookingId = document.getElementById('rescheduleBookingId');
    const rescheduleForm = document.getElementById('rescheduleForm');
    const rescheduleDateInput = document.getElementById('rescheduleDateInput');
    const reschedulePreview = document.getElementById('reschedulePreview');
    const rescheduleGuest = document.getElementById('rescheduleGuest');
    const rescheduleStayType = document.getElementById('rescheduleStayType');
    const rescheduleStayTypeSelect = document.getElementById('rescheduleStayTypeSelect');
    const rescheduleCalendarGrid = document.getElementById('rescheduleCalendarGrid');
    const rescheduleCurrentMonth = document.getElementById('rescheduleCurrentMonth');
    const reschedulePrevMonth = document.getElementById('reschedulePrevMonth');
    const rescheduleNextMonth = document.getElementById('rescheduleNextMonth');
    const rescheduleMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let rescheduleBookedDates = {};
    let rescheduleBookedMeta = {};
    let rescheduleCalendarMonth = new Date().getMonth();
    let rescheduleCalendarYear = new Date().getFullYear();
    let contextBookingId = '';
    let pendingExternalSync = false;

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

    function openModal(modal) {
        modal.style.display = 'flex';
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        if (pendingExternalSync) {
            window.location.reload();
        }
    }

    if (window.VillaAsync) {
        window.VillaAsync.onSync(function() {
            if (document.body.classList.contains('modal-open')) {
                pendingExternalSync = true;
                return;
            }
            window.location.reload();
        }, { externalOnly: true });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildDetailItem(label, value) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong>' + escapeHtml(value || '—') + '</strong></div>';
    }

    function buildBadgeDetailItem(label, value, badgeClass) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong><span class="detail-badge ' + escapeHtml(badgeClass || '') + '">' + escapeHtml(value || '—') + '</span></strong></div>';
    }

    function buildStatusDetailItem(label, value) {
        const raw = String(value || '').toLowerCase();
        const cls = raw === 'approved' ? 'status-approved-text' : (raw === 'rejected' ? 'status-rejected-text' : 'status-pending-text');
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong class="' + cls + '">' + escapeHtml(value || '—') + '</strong></div>';
    }

    function buildSpecialRequestItem(label, value) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong class="special-request-text">' + escapeHtml(value || 'N/A') + '</strong></div>';
    }


    function getProofUrl(proofValue) {
        if (!proofValue) return '';
        let cleaned = String(proofValue).trim().replace(/\\/g, '/');
        cleaned = cleaned.replace(/^\.\.\//, '');
        cleaned = cleaned.replace(/^\//, '');
        if (cleaned.indexOf('uploads/') === 0) {
            const parts = cleaned.split('/').map(function (part) { return encodeURIComponent(part); });
            return '../' + parts.join('/').replace('uploads%2F', 'uploads/');
        }
        return '../uploads/' + encodeURIComponent(cleaned.split('/').pop());
    }

    function openConfirmModal(id, action) {
        const actionLabel = action === 'approved' ? 'approve' : 'reject';
        confirmTitle.textContent = action === 'approved' ? 'Approve this booking?' : 'Reject this booking?';
        confirmMessage.textContent = 'Are you sure you want to ' + actionLabel + ' this booking request?';
        confirmBookingId.value = id;
        confirmBookingStatus.value = action;
        rejectReasonBox.style.display = action === 'rejected' ? 'block' : 'none';
        confirmButton.textContent = action === 'approved' ? 'Yes, approve' : 'Yes, reject';
        confirmButton.className = 'modal-btn ' + (action === 'approved' ? 'btn-approve' : 'btn-reject');
        openModal(confirmModal);
    }

    function findReservationRow(id) {
        return document.querySelector('.reservation-row[data-id="' + String(id || '').replace(/"/g, '') + '"]');
    }

    function statusLabel(status) {
        const value = String(status || 'pending');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function getStayLabel(type) {
        if (type === 'day') return 'Day Tour';
        if (type === 'overnight') return 'Overnight Stay';
        if (type === '22hour') return '22-Hour Stay';
        return type || '-';
    }

    function getStayClass(type) {
        if (type === 'day') return 'stay-day';
        if (type === 'overnight') return 'stay-overnight';
        if (type === '22hour') return 'stay-22hour';
        return 'stay-default';
    }

    function getCheckInTime(type) {
        if (type === 'day' || type === '22hour') return '9:00 AM';
        if (type === 'overnight') return '7:00 PM';
        return '-';
    }

    function getCheckOutTime(type) {
        if (type === 'day') return '5:00 PM';
        if (type === 'overnight' || type === '22hour') return '7:00 AM';
        return '-';
    }

    function formatDateShort(value) {
        if (!value) return '-';
        const date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function optimisticReservationStatus(id, status) {
        const row = findReservationRow(id);
        if (!row) return null;
        const badge = row.querySelector('.status');
        const previous = {
            rowClass: row.className,
            status: row.dataset.status || '',
            rejectionReason: row.dataset.rejection_reason || '',
            badgeClass: badge ? badge.className : '',
            badgeText: badge ? badge.textContent : ''
        };

        row.dataset.status = status;
        if (status === 'approved') row.dataset.rejection_reason = '';
        if (badge) {
            badge.className = 'status ' + status;
            badge.textContent = statusLabel(status);
        }
        row.classList.add('ve-optimistic-pending');
        applyReservationFilters();

        return function() {
            row.className = previous.rowClass;
            row.dataset.status = previous.status;
            row.dataset.rejection_reason = previous.rejectionReason;
            if (badge) {
                badge.className = previous.badgeClass;
                badge.textContent = previous.badgeText;
            }
            applyReservationFilters();
        };
    }

    function optimisticReschedule(row) {
        if (!row) return null;
        const checkIn = rescheduleDateInput.value;
        const timeType = rescheduleStayTypeSelect.value;
        const checkOut = calculateCheckoutDate(checkIn, timeType);
        const stayBadge = row.querySelector('.stay-badge');
        const checkInCell = row.querySelector('.reservation-checkin-cell');
        const checkOutCell = row.querySelector('.reservation-checkout-cell');
        const previous = {
            rowClass: row.className,
            timeType: row.dataset.time_type || '',
            timeTypeRaw: row.dataset.time_type_raw || '',
            stayClass: row.dataset.stay_class || '',
            checkInDate: row.dataset.check_in_date || '',
            checkInRaw: row.dataset.check_in_raw || '',
            checkInTime: row.dataset.check_in_time || '',
            checkOutDate: row.dataset.check_out_date || '',
            checkOutRaw: row.dataset.check_out_raw || '',
            checkOutTime: row.dataset.check_out_time || '',
            stayBadgeClass: stayBadge ? stayBadge.className : '',
            stayBadgeText: stayBadge ? stayBadge.textContent : '',
            checkInHtml: checkInCell ? checkInCell.innerHTML : '',
            checkOutHtml: checkOutCell ? checkOutCell.innerHTML : ''
        };

        row.dataset.time_type = getStayLabel(timeType);
        row.dataset.time_type_raw = timeType;
        row.dataset.stay_class = getStayClass(timeType);
        row.dataset.check_in_date = formatDateForPreview(checkIn);
        row.dataset.check_in_raw = checkIn;
        row.dataset.check_in_time = getCheckInTime(timeType);
        row.dataset.check_out_date = formatDateForPreview(checkOut);
        row.dataset.check_out_raw = checkOut;
        row.dataset.check_out_time = getCheckOutTime(timeType);
        if (stayBadge) {
            stayBadge.className = 'stay-badge ' + getStayClass(timeType);
            stayBadge.textContent = getStayLabel(timeType);
        }
        if (checkInCell) checkInCell.innerHTML = escapeHtml(formatDateShort(checkIn)) + '<br><small>' + escapeHtml(getCheckInTime(timeType)) + '</small>';
        if (checkOutCell) checkOutCell.innerHTML = escapeHtml(formatDateShort(checkOut)) + '<br><small>' + escapeHtml(getCheckOutTime(timeType)) + '</small>';
        row.classList.add('ve-optimistic-pending');
        applyReservationFilters();

        return function() {
            row.className = previous.rowClass;
            row.dataset.time_type = previous.timeType;
            row.dataset.time_type_raw = previous.timeTypeRaw;
            row.dataset.stay_class = previous.stayClass;
            row.dataset.check_in_date = previous.checkInDate;
            row.dataset.check_in_raw = previous.checkInRaw;
            row.dataset.check_in_time = previous.checkInTime;
            row.dataset.check_out_date = previous.checkOutDate;
            row.dataset.check_out_raw = previous.checkOutRaw;
            row.dataset.check_out_time = previous.checkOutTime;
            if (stayBadge) {
                stayBadge.className = previous.stayBadgeClass;
                stayBadge.textContent = previous.stayBadgeText;
            }
            if (checkInCell) checkInCell.innerHTML = previous.checkInHtml;
            if (checkOutCell) checkOutCell.innerHTML = previous.checkOutHtml;
            applyReservationFilters();
        };
    }

    function applyTemporaryHighlight(row) {
        row.classList.add('reservation-new-highlight');
        setTimeout(function () {
            row.classList.remove('reservation-new-highlight');
        }, 1800);
    }

    const highlightIdsRaw = sessionStorage.getItem('reservationHighlightIds');
    if (highlightIdsRaw) {
        try {
            const highlightIds = JSON.parse(highlightIdsRaw);
            const rows = document.querySelectorAll('.reservation-row');
            let firstMatch = null;

            rows.forEach(function (row) {
                if (highlightIds.includes(String(row.dataset.id))) {
                    applyTemporaryHighlight(row);
                    if (!firstMatch) firstMatch = row;
                }
            });

            if (firstMatch) {
                firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (e) {}

        sessionStorage.removeItem('reservationHighlightIds');
    }

    const highlightDate = sessionStorage.getItem('reservationHighlightDate');
    if (highlightDate) {
        const rows = document.querySelectorAll('.reservation-row');
        let firstDateMatch = null;

        rows.forEach(function (row) {
            const checkInRaw = row.dataset.check_in_raw || '';
            const checkOutRaw = row.dataset.check_out_raw || '';
            if (highlightDate >= checkInRaw && highlightDate <= checkOutRaw) {
                applyTemporaryHighlight(row);
                if (!firstDateMatch) firstDateMatch = row;
            }
        });

        if (firstDateMatch) {
            firstDateMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        sessionStorage.removeItem('reservationHighlightDate');
    }

    const pageParams = new URLSearchParams(window.location.search);
    const highlightBookingId = pageParams.get('id');
    if (pageParams.get('highlight') === 'booking' && highlightBookingId) {
        const bookingRow = document.querySelector('.reservation-row[data-id="' + highlightBookingId.replace(/"/g, '') + '"]');
        if (bookingRow) {
            applyTemporaryHighlight(bookingRow);
            bookingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    document.querySelectorAll('.reservation-row').forEach(row => {
        row.addEventListener('click', function (event) {
            if (event.target.closest('.reservation-inline-action')) return;

            const data = this.dataset;
            const modalDetails = document.getElementById('modalDetails');
            const modalActions = document.getElementById('modalActions');

            modalDetails.innerHTML =
                '<div class="reservation-detail-grid">' +
                buildDetailItem('Guest Name', data.guest_name) +
                buildDetailItem('Email Address', data.email) +
                buildDetailItem('Mobile Number', data.mobile) +
                buildDetailItem('Address', data.address) +
                buildBadgeDetailItem('Stay Type', data.time_type, 'stay-badge ' + data.stay_class) +
                buildDetailItem('Number of Guests', data.guests) +
                buildBadgeDetailItem('Payment Method', data.payment_method, 'payment-badge ' + data.payment_class) +
                buildDetailItem('Reservation Date', data.created_at) +
                buildDetailItem('Check-in', data.check_in_date + ' • ' + data.check_in_time) +
                buildDetailItem('Check-out', data.check_out_date + ' • ' + data.check_out_time) +
                buildStatusDetailItem('Status', data.status) +
                buildSpecialRequestItem('Notes', data.special_requests) +
                (data.rejection_reason ? buildSpecialRequestItem('Rejection Reason', data.rejection_reason) : '') +
                buildDetailItem('Payment Proof File', data.proof_of_payment || 'No payment proof uploaded') +
                '</div>';

            if (data.proof_of_payment) {
                const proofExt = data.proof_of_payment.split('.').pop().toLowerCase();
                const proofUrl = getProofUrl(data.proof_of_payment);
                if (['jpg', 'jpeg', 'png', 'webp'].includes(proofExt)) {
                    modalDetails.innerHTML += '<div class="reservation-proof-preview"><p>Uploaded Payment Proof</p><a href="' + proofUrl + '" target="_blank"><img src="' + proofUrl + '" alt="Payment proof"></a><a href="' + proofUrl + '" target="_blank">Open payment proof file</a></div>';
                } else {
                    modalDetails.innerHTML += '<div class="reservation-proof-preview"><p>Uploaded Payment Proof</p><a href="' + proofUrl + '" target="_blank">Open payment proof file</a></div>';
                }
            }

            if (data.status === 'pending') {
                modalActions.innerHTML =
                    '<button type="button" class="modal-btn btn-approve reservation-inline-action" data-action="approved" data-id="' + data.id + '">Approve</button>' +
                    '<button type="button" class="modal-btn btn-reject reservation-inline-action" data-action="rejected" data-id="' + data.id + '">Reject</button>';
            } else {
                modalActions.innerHTML = '<button type="button" class="modal-btn btn-cancel-action reservation-inline-action" id="closeDetailsOnly">Close</button>';
            }

            openModal(detailModal);
        });

    });


    document.addEventListener('click', function (event) {
        const actionBtn = event.target.closest('.reservation-inline-action');
        if (actionBtn && actionBtn.dataset.id) {
            closeModal(detailModal);
            openConfirmModal(actionBtn.dataset.id, actionBtn.dataset.action);
            return;
        }

        if (event.target.id === 'closeDetailsOnly') {
            closeModal(detailModal);
        }
    });

    if (statusConfirmForm) {
        statusConfirmForm.addEventListener('submit', function(event) {
            if (!window.VillaAsync) return;
            event.preventDefault();
            const row = findReservationRow(confirmBookingId.value);
            closeModal(confirmModal);
            window.VillaAsync.submitOptimisticForm(statusConfirmForm, {
                target: row,
                errorMessage: 'Booking status could not be updated.',
                onMutate: function() {
                    return optimisticReservationStatus(confirmBookingId.value, confirmBookingStatus.value);
                }
            });
        });
    }

    const reservationFilterToggle = document.getElementById('reservationFilterToggle');
    const reservationFilterPanel = document.getElementById('reservationFilterPanel');
    if (reservationFilterToggle && reservationFilterPanel) {
        reservationFilterToggle.addEventListener('click', function() {
            reservationFilterPanel.classList.toggle('show');
            reservationFilterToggle.classList.toggle('active');
        });
    }

    function applyReservationFilters() {
        const search = (document.getElementById('reservationSearch').value || '').toLowerCase();
        const stay = document.getElementById('reservationStayFilter').value;
        const status = document.getElementById('reservationStatusFilter').value;
        const payment = document.getElementById('reservationPaymentFilter').value;
        document.querySelectorAll('#reservationTable tr').forEach(row => {
            const matchesSearch = !search || (row.dataset.guest_name || '').toLowerCase().includes(search) || String(row.dataset.id || '').includes(search);
            const matchesStay = stay === 'all' || row.dataset.time_type_raw === stay;
            const matchesStatus = status === 'all' || row.dataset.status === status;
            const matchesPayment = payment === 'all' || row.dataset.payment_method_raw === payment;
            row.style.display = (matchesSearch && matchesStay && matchesStatus && matchesPayment) ? '' : 'none';
        });
    }
    ['reservationSearch','reservationStayFilter','reservationStatusFilter','reservationPaymentFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applyReservationFilters);
        if (el) el.addEventListener('change', applyReservationFilters);
    });
    document.getElementById('clearReservationFilters').addEventListener('click', function(){
        document.getElementById('reservationSearch').value = '';
        document.getElementById('reservationStayFilter').value = 'all';
        document.getElementById('reservationStatusFilter').value = 'all';
        document.getElementById('reservationPaymentFilter').value = 'all';
        applyReservationFilters();
    });

    function calculateCheckoutDate(checkIn, timeType) {
        if (!checkIn) return '';
        const date = new Date(checkIn + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return '';
        if (timeType === 'overnight' || timeType === '22hour') {
            date.setDate(date.getDate() + 1);
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function formatDateForPreview(value) {
        if (!value) return '-';
        const date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function updateReschedulePreview() {
        const timeType = rescheduleStayTypeSelect ? rescheduleStayTypeSelect.value : '';
        const checkout = calculateCheckoutDate(rescheduleDateInput.value, timeType);
        reschedulePreview.textContent = checkout
            ? 'New schedule: ' + formatDateForPreview(rescheduleDateInput.value) + ' to ' + formatDateForPreview(checkout)
            : 'Choose an available date from the calendar.';
    }

    function isReschedulePastDate(dateString) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const date = new Date(dateString + 'T00:00:00');
        return date < today;
    }

    function getRescheduleSlots(dateString) {
        return rescheduleBookedDates[dateString] || [];
    }

    function isRescheduleUnavailable(dateString) {
        const type = rescheduleStayTypeSelect ? rescheduleStayTypeSelect.value : '';
        const slots = getRescheduleSlots(dateString);
        if (isReschedulePastDate(dateString)) return true;
        if (type === 'day') return slots.includes('day');
        if (type === 'overnight') return slots.includes('overnight');
        if (type === '22hour') return slots.includes('day') || slots.includes('overnight');
        return true;
    }

    function renderRescheduleCalendar() {
        if (!rescheduleCalendarGrid || !rescheduleCurrentMonth) return;
        rescheduleCurrentMonth.textContent = rescheduleMonthNames[rescheduleCalendarMonth] + ' ' + rescheduleCalendarYear;
        rescheduleCalendarGrid.innerHTML = '';

        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(function(day) {
            const header = document.createElement('div');
            header.className = 'reschedule-calendar-day-header';
            header.textContent = day;
            rescheduleCalendarGrid.appendChild(header);
        });

        const firstDay = new Date(rescheduleCalendarYear, rescheduleCalendarMonth, 1).getDay();
        const daysInMonth = new Date(rescheduleCalendarYear, rescheduleCalendarMonth + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement('div');
            blank.className = 'reschedule-calendar-day empty';
            rescheduleCalendarGrid.appendChild(blank);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateObj = new Date(rescheduleCalendarYear, rescheduleCalendarMonth, day);
            const dateString = formatDate(dateObj);
            const cell = document.createElement('div');
            cell.className = 'reschedule-calendar-day';
            cell.innerHTML = '<span>' + day + '</span>';
            cell.dataset.date = dateString;

            const unavailable = isRescheduleUnavailable(dateString);
            const meta = rescheduleBookedMeta[dateString] || [];
            if (meta.length) {
                cell.title = meta.map(function(item) {
                    if (item.is_blocked) return 'Blocked: ' + (item.block_reason || 'Admin blocked date');
                    return 'Booked: ' + (item.guest_name || 'Guest') + ' - ' + (item.booking_time_type || item.slot || '');
                }).join('\n');
            }
            if (isReschedulePastDate(dateString)) cell.classList.add('past');
            if (unavailable) {
                cell.classList.add('booked');
            } else {
                cell.addEventListener('click', function() {
                    rescheduleDateInput.value = dateString;
                    updateReschedulePreview();
                    renderRescheduleCalendar();
                });
            }
            if (dateString === rescheduleDateInput.value) {
                cell.classList.add('selected');
            }
            rescheduleCalendarGrid.appendChild(cell);
        }
    }

    function loadRescheduleAvailability(bookingId) {
        rescheduleBookedDates = {};
        rescheduleBookedMeta = {};
        const availabilityUrl = '../api/get_booked_dates.php?exclude_booking_id=' + encodeURIComponent(bookingId);
        if (window.VillaAsync) {
            window.VillaAsync.renderCalendarSkeleton(rescheduleCalendarGrid, 42);
        }
        const availabilityRequest = window.VillaAsync
            ? window.VillaAsync.cachedJson(availabilityUrl, {}, { ttl: 5000, cacheKey: 'rescheduleAvailability:' + bookingId, force: true })
            : fetch(availabilityUrl).then(response => response.ok ? response.json() : null);
        return availabilityRequest
            .then(data => {
                if (data) {
                    rescheduleBookedDates = data.dates || {};
                    rescheduleBookedMeta = data.meta || {};
                }
            })
            .catch(() => {})
            .finally(function() {
                rescheduleCalendarGrid.classList.remove('is-loading');
                renderRescheduleCalendar();
            });
    }

    function openRescheduleModal(row) {
        if (!row) return;
        contextBookingId = row.dataset.id;
        rescheduleBookingId.value = row.dataset.id;
        rescheduleGuest.textContent = row.dataset.guest_name || '-';
        rescheduleStayType.textContent = row.dataset.time_type || '-';
        rescheduleStayTypeSelect.value = row.dataset.time_type_raw || 'day';
        rescheduleDateInput.value = row.dataset.check_in_raw || '';
        const currentDate = rescheduleDateInput.value ? new Date(rescheduleDateInput.value + 'T00:00:00') : new Date();
        if (!Number.isNaN(currentDate.getTime())) {
            rescheduleCalendarMonth = currentDate.getMonth();
            rescheduleCalendarYear = currentDate.getFullYear();
        }
        updateReschedulePreview();
        openModal(rescheduleModal);
        loadRescheduleAvailability(row.dataset.id);
    }

    rescheduleStayTypeSelect.addEventListener('change', function() {
        if (rescheduleDateInput.value && isRescheduleUnavailable(rescheduleDateInput.value)) {
            rescheduleDateInput.value = '';
        }
        updateReschedulePreview();
        renderRescheduleCalendar();
    });
    reschedulePrevMonth.addEventListener('click', function() {
        rescheduleCalendarMonth--;
        if (rescheduleCalendarMonth < 0) {
            rescheduleCalendarMonth = 11;
            rescheduleCalendarYear--;
        }
        renderRescheduleCalendar();
    });
    rescheduleNextMonth.addEventListener('click', function() {
        rescheduleCalendarMonth++;
        if (rescheduleCalendarMonth > 11) {
            rescheduleCalendarMonth = 0;
            rescheduleCalendarYear++;
        }
        renderRescheduleCalendar();
    });
    rescheduleForm.addEventListener('submit', function(event) {
        if (!rescheduleDateInput.value) {
            event.preventDefault();
            reschedulePreview.textContent = 'Please choose an available date from the calendar.';
            return;
        }
        if (isRescheduleUnavailable(rescheduleDateInput.value)) {
            event.preventDefault();
            reschedulePreview.textContent = 'That date is no longer available for the selected stay type.';
            return;
        }
        if (window.VillaAsync) {
            event.preventDefault();
            const row = findReservationRow(rescheduleBookingId.value);
            closeModal(rescheduleModal);
            window.VillaAsync.submitOptimisticForm(rescheduleForm, {
                target: row,
                errorMessage: 'Reservation could not be rescheduled.',
                onMutate: function() {
                    return optimisticReschedule(row);
                }
            });
        }
    });

    const archiveMenu = document.createElement('div');
    archiveMenu.className = 'archive-context-menu';
    archiveMenu.innerHTML =
        '<button type="button" data-action="reschedule">Reschedule</button>' +
        '<button type="button" data-action="archive" class="danger-context-action">Move to Archive</button>';
    document.body.appendChild(archiveMenu);
    let archiveTargetId = '';
    let contextRow = null;
    document.querySelectorAll('.reservation-row').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            archiveTargetId = row.dataset.id;
            contextBookingId = row.dataset.id;
            contextRow = row;
            archiveMenu.style.left = e.pageX + 'px';
            archiveMenu.style.top = e.pageY + 'px';
            archiveMenu.classList.add('show');
        });
    });
    archiveMenu.addEventListener('click', function(event){
        const actionButton = event.target.closest('button[data-action]');
        if (!actionButton) return;
        const action = actionButton.dataset.action;
        archiveMenu.classList.remove('show');
        if (action === 'reschedule') {
            openRescheduleModal(contextRow);
            return;
        }
        if (!archiveTargetId) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/archive_booking.php';
        form.innerHTML = '<input name="id" value="' + archiveTargetId + '"><input name="redirect" value="reservation">';
        document.body.appendChild(form);
        if (window.VillaAsync) {
            window.VillaAsync.submitOptimisticForm(form, {
                target: contextRow || findReservationRow(archiveTargetId),
                effect: 'remove',
                errorMessage: 'Booking could not be moved to archive.'
            });
        } else {
            form.submit();
        }
    });
    document.addEventListener('click', function(){ archiveMenu.classList.remove('show'); });

    closeDetailModal.addEventListener('click', () => closeModal(detailModal));
    closeConfirmModal.addEventListener('click', () => closeModal(confirmModal));
    closeRescheduleModal.addEventListener('click', () => closeModal(rescheduleModal));
    document.querySelectorAll('.close-reschedule-btn').forEach(btn => {
        btn.addEventListener('click', () => closeModal(rescheduleModal));
    });
    cancelStatusAction.addEventListener('click', () => closeModal(confirmModal));

    window.addEventListener('click', function (event) {
        if (event.target === detailModal) closeModal(detailModal);
        if (event.target === confirmModal) closeModal(confirmModal);
        if (event.target === rescheduleModal) closeModal(rescheduleModal);
    });
});
</script>







