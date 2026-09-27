<?php
require_once "../includes/admin_auth.php";
admin_require_login(true);

include "../includes/header.php";
include "../includes/db.php";
include "../includes/booking_repository.php";
ve_ensure_capstone2_schema($conn);
$today = date('Y-m-d');

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

function getStayPrice($type) {
    if ($type === 'day') return 7000;
    if ($type === 'overnight') return 10000;
    if ($type === '22hour') return 13000;
    return 0;
}

$rows = [];
$totalApprovedRevenue = 0;
$totalPaidRevenue = 0;
$approvedBookingsCount = 0;
foreach (ve_fetch_all_bookings($conn, "bs.booking_id DESC") as $row) {
    $baseStayValue = getStayPrice($row['time_type']);
    $guestsCount = isset($row['guests']) ? (int)$row['guests'] : 0;
    $extraGuests = max($guestsCount - 30, 0);
    $additionalGuestFee = $extraGuests * 150;
    $row['additional_guest_fee'] = $additionalGuestFee;
    $row['stay_value'] = $baseStayValue + $additionalGuestFee;
    $row['reservation_fee_amount'] = isset($row['reservation_fee_amount']) ? (float)$row['reservation_fee_amount'] : 2000;
    $row['reservation_fee_status'] = $row['reservation_fee_status'] ?? 'unpaid';
    $row['payment_status'] = $row['payment_status'] ?? 'unpaid';
    $row['remaining_balance'] = max($row['stay_value'] - $row['reservation_fee_amount'], 0);
    if (($row['status'] ?? '') === 'approved') {
        $approvedBookingsCount++;
        $totalApprovedRevenue += $row['stay_value'];
    }
    if (($row['payment_status'] ?? '') === 'paid') {
        $totalPaidRevenue += $row['remaining_balance'] + (($row['reservation_fee_status'] ?? '') === 'paid' ? $row['reservation_fee_amount'] : 0);
    } elseif (($row['reservation_fee_status'] ?? '') === 'paid') {
        $totalPaidRevenue += $row['reservation_fee_amount'];
    }
    $rows[] = $row;
}
?>

<style>
.guest-count-warning { color:#c62828; font-weight:700; }
.balance-cell { font-weight:700; color:#3d3d3d; }
.sales-pay-btn small, .sales-paid-lock small { display:block; font-size:11px; line-height:1.2; margin-top:2px; }
.sales-rejected-label { display:inline-flex; align-items:center; justify-content:center; min-width:88px; padding:8px 12px; border-radius:8px; color:#b91c1c; font-weight:800; background:#fff1f1; border:1px solid #f3b5b5; }
.sales-pay-btn.disabled-payment { background:#c9c3b8; cursor:not-allowed; opacity:.75; }
.status-paid-text { color:#15803d; font-weight:800; }
.status-unpaid-text { color:#dc2626; font-weight:800; }
</style>

<div id="sidebar" class="sidebar">
    <a href="../index.php" class="sidebar-title sidebar-brand-link">Villa Eusebio</a>
    <a href="admin-panel.php" class="nav-link"><span class="nav-icon">D</span><span>Dashboard</span></a>
    <a href="reservation.php" class="nav-link"><span class="nav-icon">R</span><span>Reservation</span></a>
    <a href="sales.php" class="nav-link active"><span class="nav-icon">S</span><span>Sales Record</span></a>
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
    <?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>
    <div class="admin-alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
    <div class="admin-alert error-alert"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <div class="admin-page-heading">
        <div>
            <h2>Sales Record</h2>
            <p class="reservation-helper-text">Click a sales row to view complete payment details and uploaded proof.</p>
        </div>
        <a href="export_sales_pdf.php" target="_blank" class="admin-export-btn">Export PDF</a>
    </div>

    <div class="sales-summary-grid">
        <div class="admin-stat-card revenue-card">
            <div class="stat-label">Approved Booking Earnings</div>
            <div class="stat-value">₱<?php echo number_format($totalApprovedRevenue); ?></div>
        </div>
        <div class="admin-stat-card confirmed-card">
            <div class="stat-label">Approved Bookings</div>
            <div class="stat-value"><?php echo $approvedBookingsCount; ?></div>
        </div>
        <div class="admin-stat-card">
            <div class="stat-label">Paid Amount</div>
            <div class="stat-value">₱<?php echo number_format($totalPaidRevenue); ?></div>
        </div>
    </div>

    <div class="filter-dropdown-wrap">
        <div class="reservation-filters smart-filters filter-panel always-visible-filter-panel" id="salesFilterPanel">
            <div class="filter-search-wrap">
                <input type="text" id="salesSearch" placeholder="Search name or ID">
                <button type="button" class="filter-search-clear" id="clearSalesSearch" aria-label="Clear sales search">&times;</button>
            </div>
            <select id="salesStayFilter"><option value="all">All stay types</option><option value="day">Day Tour</option><option value="overnight">Overnight</option><option value="22hour">22-Hour</option></select>
            <select id="salesStatusFilter"><option value="all">All booking status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
            <select id="salesResFeeFilter"><option value="all">All reservation fee</option><option value="paid">Reservation Fee Paid</option><option value="unpaid">Reservation Fee Unpaid</option></select>
            <select id="salesPaymentStatusFilter"><option value="all">All payment status</option><option value="paid">Balance Paid</option><option value="unpaid">Balance Unpaid</option></select>
            <select id="salesPaymentTypeFilter"><option value="all">All payment types</option><option value="gcash">GCash</option><option value="bdo">BDO</option><option value="unionbank">UnionBank</option><option value="cash">Cash</option></select>
        </div>
    </div>

    <div class="table-container">
        <table class="sales-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Guest Name</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Guests</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Stay Value</th>
                    <th>Res. Fee</th>
                    <th>Balance</th>
                    <th>Balance Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $isPastBooking = !empty($row['check_out_date']) && $row['check_out_date'] < $today;
            ?>
                <tr class="sales-row <?php echo $isPastBooking ? 'booking-completed-row' : ''; ?>"
                    data-id="<?php echo (int)$row['id']; ?>"
                    data-is_past="<?php echo $isPastBooking ? '1' : '0'; ?>"
                    data-guest_name="<?php echo htmlspecialchars($row['guest_name']); ?>"
                    data-email="<?php echo htmlspecialchars($row['email']); ?>"
                    data-mobile="<?php echo htmlspecialchars($row['mobile']); ?>"
                    data-address="<?php echo htmlspecialchars($row['address']); ?>"
                    data-check_in_date="<?php echo htmlspecialchars(date('F d, Y', strtotime($row['check_in_date']))); ?>"
                    data-check_out_date="<?php echo htmlspecialchars(date('F d, Y', strtotime($row['check_out_date']))); ?>"
                    data-guests="<?php echo htmlspecialchars($row['guests']); ?>"
                    data-payment_method="<?php echo htmlspecialchars(formatPaymentMethod($row['payment_method'])); ?>"
                    data-payment_method_raw="<?php echo htmlspecialchars($row['payment_method']); ?>"
                    data-payment_class="<?php echo htmlspecialchars(paymentMethodClass($row['payment_method'])); ?>"
                    data-special_requests="<?php echo htmlspecialchars($row['special_requests'] ?: 'None'); ?>"
                    data-time_type="<?php echo htmlspecialchars(formatStayType($row['time_type'])); ?>"
                    data-time_type_raw="<?php echo htmlspecialchars($row['time_type']); ?>"
                    data-stay_class="<?php echo htmlspecialchars(stayTypeClass($row['time_type'])); ?>"
                    data-status="<?php echo htmlspecialchars(ucfirst($row['status'])); ?>"
                    data-status_raw="<?php echo htmlspecialchars(strtolower($row['status'])); ?>"
                    data-payment_status="<?php echo htmlspecialchars(ucfirst($row['payment_status'] ?: 'unpaid')); ?>"
                    data-payment_status_raw="<?php echo htmlspecialchars(strtolower($row['payment_status'] ?: 'unpaid')); ?>"
                    data-base_stay_value="₱<?php echo number_format($baseStayValue); ?>"
                    data-stay_value="₱<?php echo number_format($row['stay_value']); ?>"
                    data-reservation_fee_amount="₱<?php echo number_format((float)$row['reservation_fee_amount']); ?>"
                    data-reservation_fee_status="<?php echo htmlspecialchars(ucfirst($row['reservation_fee_status'] ?: 'unpaid')); ?>"
                    data-reservation_fee_status_raw="<?php echo htmlspecialchars(strtolower($row['reservation_fee_status'] ?: 'unpaid')); ?>"
                    data-additional_guest_fee="₱<?php echo number_format($row['additional_guest_fee']); ?>"
                    data-remaining_balance="₱<?php echo number_format($row['remaining_balance']); ?>"
                    data-proof_of_payment="<?php echo htmlspecialchars($row['proof_of_payment'] ?? ''); ?>"
                    data-ocr_status="<?php echo htmlspecialchars($row['ocr_status'] ?? 'not_scanned'); ?>"
                    data-ocr_text="<?php echo htmlspecialchars($row['ocr_text'] ?? ''); ?>"
                    data-ocr_reference="<?php echo htmlspecialchars($row['ocr_reference'] ?? ''); ?>"
                    data-ocr_amount="<?php echo htmlspecialchars(isset($row['ocr_amount']) && $row['ocr_amount'] !== null ? number_format((float)$row['ocr_amount'], 2, '.', '') : ''); ?>"
                    data-ocr_notes="<?php echo htmlspecialchars($row['ocr_notes'] ?? ''); ?>"
                    data-ocr_scanned_at="<?php echo htmlspecialchars(!empty($row['ocr_scanned_at']) ? date('F d, Y h:i A', strtotime($row['ocr_scanned_at'])) : ''); ?>"
                >
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($row['check_in_date']))); ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($row['check_out_date']))); ?></td>
                    <td class="<?php echo ((int)$row['guests'] > 30) ? 'guest-count-warning' : ''; ?>"><?php echo htmlspecialchars($row['guests']); ?></td>
                    <td><span class="stay-badge <?php echo htmlspecialchars(stayTypeClass($row['time_type'])); ?>"><?php echo htmlspecialchars(formatStayType($row['time_type'])); ?></span></td>
                    <td><span class="status <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span></td>
                    <td class="stay-value-cell">₱<?php echo number_format($row['stay_value']); ?></td>
                    <td>
                        <?php if (($row['status'] ?? '') === 'rejected'): ?>
                            <span class="sales-rejected-label">Rejected</span>
                        <?php elseif (($row['reservation_fee_status'] ?? '') === 'paid'): ?>
                            <button type="button" class="sales-paid-lock" disabled>Paid<br><small>₱<?php echo number_format((float)$row['reservation_fee_amount']); ?></small></button>
                        <?php else: ?>
                            <form method="POST" action="../api/toggle_reservation_fee.php" style="margin:0;" class="reservation-fee-form">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" class="sales-pay-btn">Unpaid<br><small>₱<?php echo number_format((float)$row['reservation_fee_amount']); ?></small></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="balance-cell">₱<?php echo number_format($row['remaining_balance']); ?></td>
                    <td>
                        <?php if (($row['status'] ?? '') === 'rejected'): ?>
                            <span class="sales-rejected-label">Rejected</span>
                        <?php elseif (($row['payment_status'] ?? '') === 'paid'): ?>
                            <button type="button" class="sales-paid-lock" disabled>Paid</button>
                        <?php elseif (($row['reservation_fee_status'] ?? '') !== 'paid'): ?>
                            <button type="button" class="sales-pay-btn disabled-payment" disabled title="Pay the reservation fee first">Unpaid</button>
                        <?php else: ?>
                            <button type="button" class="sales-pay-btn" data-pay-id="<?php echo (int)$row['id']; ?>">Unpaid</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div id="salesDetailsModal" class="modal reservation-modal">
    <div class="modal-content reservation-modal-content sales-modal-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Villa Eusebio</p>
                <h3>Sales Record Details</h3>
                <p class="sales-modal-subtitle" id="salesModalSubtitle"></p>
            </div>
            <span class="close-sales-modal">&times;</span>
        </div>
        <div class="modal-body" id="salesModalDetails"></div>
        <div class="modal-footer" id="salesModalActions"></div>
    </div>
</div>

<div id="salesConfirmModal" class="modal reservation-modal">
    <div class="modal-content reservation-confirm-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Payment Confirmation</p>
                <h3>Confirm paid?</h3>
            </div>
            <span class="close-sales-confirm-modal">&times;</span>
        </div>
        <div class="modal-body">
            <p>This booking payment will be marked as paid and the action will be locked.</p>
        </div>
        <div class="modal-footer reservation-confirm-actions">
            <button type="button" class="modal-btn btn-cancel-action" id="cancelSalesConfirm">Cancel</button>
            <form method="POST" action="../api/toggle_payment.php" id="salesConfirmForm" style="margin:0;">
                <input type="hidden" name="id" id="salesConfirmId" value="">
                <button type="submit" class="modal-btn btn-approve">Yes, mark as paid</button>
            </form>
        </div>
    </div>
</div>

<div id="reservationFeeConfirmModal" class="modal reservation-modal">
    <div class="modal-content reservation-confirm-content">
        <div class="modal-header reservation-modal-header">
            <div>
                <p class="reservation-modal-kicker">Reservation Fee Confirmation</p>
                <h3>Confirm reservation fee paid?</h3>
            </div>
            <span class="close-resfee-confirm-modal">&times;</span>
        </div>
        <div class="modal-body">
            <p>The ₱2,000 reservation fee will be marked as paid and this action will be locked.</p>
        </div>
        <div class="modal-footer reservation-confirm-actions">
            <button type="button" class="modal-btn btn-cancel-action" id="cancelResFeeConfirm">Cancel</button>
            <form method="POST" action="../api/toggle_reservation_fee.php" id="resFeeConfirmForm" style="margin:0;">
                <input type="hidden" name="id" id="resFeeConfirmId" value="">
                <button type="submit" class="modal-btn btn-approve">Yes, mark reservation fee paid</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const dashboard = document.querySelector(".admin-dashboard");
    const salesModal = document.getElementById('salesDetailsModal');
    const confirmModal = document.getElementById('salesConfirmModal');
    const resFeeConfirmModal = document.getElementById('reservationFeeConfirmModal');
    const salesDetails = document.getElementById('salesModalDetails');
    const salesActions = document.getElementById('salesModalActions');
    const salesSubtitle = document.getElementById('salesModalSubtitle');
    const salesConfirmForm = document.getElementById('salesConfirmForm');
    const resFeeConfirmForm = document.getElementById('resFeeConfirmForm');
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
        return String(value || '—')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildDetailItem(label, value) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function buildBadgeDetailItem(label, value, badgeClass) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong><span class="detail-badge ' + escapeHtml(badgeClass || '') + '">' + escapeHtml(value || '—') + '</span></strong></div>';
    }

    function buildBookingStatusDetailItem(label, value) {
        const raw = String(value || '').toLowerCase();
        const cls = raw === 'approved' ? 'status-approved-text' : (raw === 'rejected' ? 'status-rejected-text' : 'status-pending-text');
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong class="' + cls + '">' + escapeHtml(value || '—') + '</strong></div>';
    }

    function buildSpecialRequestItem(label, value) {
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong class="special-request-text">' + escapeHtml(value || 'N/A') + '</strong></div>';
    }

    function buildStatusDetailItem(label, value) {
        const rawValue = String(value || 'unpaid').toLowerCase();
        const statusClass = rawValue === 'paid' ? 'status-paid-text' : 'status-unpaid-text';
        const displayValue = rawValue === 'paid' ? 'Paid' : 'Unpaid';
        return '<div class="reservation-detail-item"><span>' + label + '</span><strong class="' + statusClass + '">' + displayValue + '</strong></div>';
    }

    function formatOcrStatus(status) {
        const labels = {
            scanned: 'Scanned',
            no_text: 'No text detected',
            unavailable: 'OCR unavailable',
            unsupported_file: 'Unsupported file',
            failed: 'Scan failed',
            not_applicable: 'Not applicable',
            not_scanned: 'Not scanned'
        };
        return labels[String(status || 'not_scanned').toLowerCase()] || 'Not scanned';
    }

    function buildOcrPanel(data) {
        const status = String(data.ocr_status || 'not_scanned').toLowerCase();
        const amount = data.ocr_amount ? 'PHP ' + Number(data.ocr_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Not detected';
        const reference = data.ocr_reference || 'Not detected';
        const scannedAt = data.ocr_scanned_at || 'Not available';
        const notes = data.ocr_notes || 'No OCR notes available.';
        const rawText = data.ocr_text || '';

        return '<div class="ocr-review-card ocr-status-' + escapeHtml(status) + '">' +
            '<div class="ocr-review-header"><span>OCR Payment Verification</span><strong>' + escapeHtml(formatOcrStatus(status)) + '</strong></div>' +
            '<div class="ocr-review-grid">' +
            '<div><span>Detected Amount</span><strong>' + escapeHtml(amount) + '</strong></div>' +
            '<div><span>Reference Number</span><strong>' + escapeHtml(reference) + '</strong></div>' +
            '<div><span>Scanned At</span><strong>' + escapeHtml(scannedAt) + '</strong></div>' +
            '</div>' +
            '<p>' + escapeHtml(notes) + '</p>' +
            (rawText ? '<details><summary>View OCR extracted text</summary><pre>' + escapeHtml(rawText) + '</pre></details>' : '') +
            '</div>';
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

    function buildSalesInfoRow(label, value) {
        return '<div class="sales-info-row"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value || '—') + '</strong></div>';
    }

    function buildSalesMiniMetric(label, value) {
        return '<div class="sales-mini-metric"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value || '—') + '</strong></div>';
    }

    function buildSalesStatusPill(status, type) {
        const raw = String(status || 'unpaid').toLowerCase();
        const label = raw === 'paid' ? 'Paid' : (raw === 'approved' ? 'Approved' : (raw === 'rejected' ? 'Rejected' : (raw === 'pending' ? 'Pending' : 'Unpaid')));
        return '<span class="sales-status-pill sales-status-' + escapeHtml(raw) + ' sales-status-kind-' + escapeHtml(type || 'payment') + '">' + label + '</span>';
    }

    function buildSalesProof(data) {
        if (!data.proof_of_payment) {
            return '<div class="sales-proof-card is-empty"><span>No uploaded proof</span></div>';
        }

        const proofUrl = getProofUrl(data.proof_of_payment);
        const proofExt = data.proof_of_payment.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'webp'].includes(proofExt);
        const fileLabel = proofExt ? proofExt.toUpperCase() + ' file' : 'Uploaded file';
        const preview = isImage
            ? '<a class="sales-proof-preview" href="' + proofUrl + '" target="_blank"><img src="' + proofUrl + '" alt="Uploaded proof"></a>'
            : '<a class="sales-proof-preview file-preview" href="' + proofUrl + '" target="_blank">File</a>';

        return '<div class="sales-proof-card">' +
            preview +
            '<div class="sales-proof-meta">' +
                '<strong>' + escapeHtml(data.proof_of_payment) + '</strong>' +
                '<span>' + escapeHtml(fileLabel) + '</span>' +
                '<a href="' + proofUrl + '" target="_blank">' + (isImage ? 'View full image' : 'Open uploaded file') + '</a>' +
            '</div>' +
        '</div>';
    }

    function buildSalesRecordDetails(data) {
        const balancePaid = String(data.payment_status_raw || data.payment_status || '').toLowerCase() === 'paid';
        const reservationFeePaid = String(data.reservation_fee_status_raw || data.reservation_fee_status || '').toLowerCase() === 'paid';
        const bookingStatusRaw = String(data.status_raw || data.status || '').toLowerCase();

        return '<div class="sales-record-modern">' +
            '<div class="sales-record-layout">' +
                '<div class="sales-record-main">' +
                    '<section class="sales-record-section">' +
                        '<h4>Guest Information</h4>' +
                        buildSalesInfoRow('Guest name', data.guest_name) +
                        buildSalesInfoRow('Email address', data.email) +
                        buildSalesInfoRow('Mobile number', data.mobile) +
                        buildSalesInfoRow('Address', data.address) +
                    '</section>' +
                    '<section class="sales-record-section">' +
                        '<h4>Stay Details</h4>' +
                        '<div class="sales-stay-grid">' +
                            buildSalesMiniMetric('Check-in date', data.check_in_date) +
                            buildSalesMiniMetric('Check-out date', data.check_out_date) +
                            buildSalesMiniMetric('Guests', data.guests) +
                            '<div class="sales-mini-metric"><span>Stay type</span><strong><span class="stay-badge ' + escapeHtml(data.stay_class || '') + '">' + escapeHtml(data.time_type || '—') + '</span></strong></div>' +
                            '<div class="sales-mini-metric"><span>Booking status</span><strong>' + buildSalesStatusPill(bookingStatusRaw, 'booking') + '</strong></div>' +
                        '</div>' +
                    '</section>' +
                    '<section class="sales-record-section">' +
                        '<h4>Notes</h4>' +
                        '<p class="sales-notes-text">' + escapeHtml(data.special_requests || 'No notes') + '</p>' +
                    '</section>' +
                    '<section class="sales-record-section">' +
                        '<h4>Uploaded Proof</h4>' +
                        buildSalesProof(data) +
                    '</section>' +
                '</div>' +
                '<aside class="sales-payment-summary-card">' +
                    '<h4>Payment Summary</h4>' +
                    '<div class="sales-payment-top">' +
                        '<div><span>Payment status</span>' + buildSalesStatusPill(data.payment_status_raw, 'payment') + '</div>' +
                        '<div><span>Payment method</span><strong><span class="payment-badge ' + escapeHtml(data.payment_class || '') + '">' + escapeHtml(data.payment_method || '—') + '</span></strong></div>' +
                    '</div>' +
                    '<div class="sales-payment-lines">' +
                        '<div><span>Stay value</span><strong>' + escapeHtml(data.base_stay_value || data.stay_value) + '</strong></div>' +
                        '<div><span>Additional guest fee</span><strong>' + escapeHtml(data.additional_guest_fee || '₱0') + '</strong></div>' +
                        '<div class="sales-payment-total"><span>Total amount</span><strong>' + escapeHtml(data.stay_value) + '</strong></div>' +
                        '<div><span>Reservation fee paid</span><strong>' + buildSalesStatusPill(data.reservation_fee_status_raw, 'reservation') + ' <em>−' + escapeHtml(data.reservation_fee_amount) + '</em></strong></div>' +
                    '</div>' +
                    '<div class="sales-remaining-block ' + (balancePaid ? 'is-paid' : 'is-unpaid') + '">' +
                        '<span>Remaining balance</span>' +
                        '<strong>' + escapeHtml(balancePaid ? '₱0' : data.remaining_balance) + '</strong>' +
                        '<small>' + (reservationFeePaid ? 'Reservation fee has been deducted.' : 'Pay the reservation fee before marking balance paid.') + '</small>' +
                    '</div>' +
                '</aside>' +
            '</div>' +
        '</div>';
    }

    function findSalesRow(id) {
        return document.querySelector('.sales-row[data-id="' + String(id || '').replace(/"/g, '') + '"]');
    }

    function optimisticReservationFeePaid(row) {
        if (!row) return null;
        const resFeeCell = row.cells[8];
        const balanceStatusCell = row.cells[10];
        const previous = {
            rowClass: row.className,
            reservationFeeStatus: row.dataset.reservation_fee_status || '',
            reservationFeeStatusRaw: row.dataset.reservation_fee_status_raw || '',
            resFeeHtml: resFeeCell ? resFeeCell.innerHTML : '',
            balanceStatusHtml: balanceStatusCell ? balanceStatusCell.innerHTML : ''
        };
        row.dataset.reservation_fee_status = 'Paid';
        row.dataset.reservation_fee_status_raw = 'paid';
        if (resFeeCell) {
            resFeeCell.innerHTML = '<button type="button" class="sales-paid-lock" disabled>Paid<br><small>' + escapeHtml(row.dataset.reservation_fee_amount || '') + '</small></button>';
        }
        if (balanceStatusCell && String(row.dataset.payment_status_raw || '').toLowerCase() !== 'paid') {
            balanceStatusCell.innerHTML = '<button type="button" class="sales-pay-btn" data-pay-id="' + escapeHtml(row.dataset.id || '') + '">Unpaid</button>';
        }
        row.classList.add('ve-optimistic-pending');
        applySalesFilters();

        return function() {
            row.className = previous.rowClass;
            row.dataset.reservation_fee_status = previous.reservationFeeStatus;
            row.dataset.reservation_fee_status_raw = previous.reservationFeeStatusRaw;
            if (resFeeCell) resFeeCell.innerHTML = previous.resFeeHtml;
            if (balanceStatusCell) balanceStatusCell.innerHTML = previous.balanceStatusHtml;
            applySalesFilters();
        };
    }

    function optimisticBalancePaid(row) {
        if (!row) return null;
        const balanceStatusCell = row.cells[10];
        const previous = {
            rowClass: row.className,
            paymentStatus: row.dataset.payment_status || '',
            paymentStatusRaw: row.dataset.payment_status_raw || '',
            balanceStatusHtml: balanceStatusCell ? balanceStatusCell.innerHTML : ''
        };
        row.dataset.payment_status = 'Paid';
        row.dataset.payment_status_raw = 'paid';
        if (balanceStatusCell) {
            balanceStatusCell.innerHTML = '<button type="button" class="sales-paid-lock" disabled>Paid</button>';
        }
        row.classList.add('ve-optimistic-pending');
        applySalesFilters();

        return function() {
            row.className = previous.rowClass;
            row.dataset.payment_status = previous.paymentStatus;
            row.dataset.payment_status_raw = previous.paymentStatusRaw;
            if (balanceStatusCell) balanceStatusCell.innerHTML = previous.balanceStatusHtml;
            applySalesFilters();
        };
    }

    document.querySelectorAll('.sales-row').forEach(function(row) {
        row.addEventListener('click', function(event) {
            if (event.target.closest('.sales-pay-btn') || event.target.closest('.sales-paid-lock')) return;

            const data = row.dataset;
            if (salesSubtitle) {
                salesSubtitle.textContent = (data.guest_name || 'Guest') + ' · ' + (data.time_type || 'Stay');
            }
            salesDetails.innerHTML = buildSalesRecordDetails(data);
            salesDetails.innerHTML += buildOcrPanel(data);

            if (String(data.status || '').toLowerCase() === 'rejected') {
                salesActions.innerHTML = '<button type="button" class="modal-btn btn-cancel-action close-sales-only">Close</button>';
            } else if (String(data.reservation_fee_status_raw || data.reservation_fee_status || '').toLowerCase() !== 'paid') {
                salesActions.innerHTML =
                    '<button type="button" class="modal-btn btn-approve resfee-pay-btn" data-resfee-id="' + data.id + '">Mark Reservation Fee as Paid</button>' +
                    '<button type="button" class="modal-btn btn-cancel-action" disabled>Balance locked until reservation fee is paid</button>' +
                    '<button type="button" class="modal-btn btn-cancel-action close-sales-only">Close</button>';
            } else if (String(data.payment_status_raw || data.payment_status || '').toLowerCase() === 'paid') {
                salesActions.innerHTML = '<button type="button" class="modal-btn btn-cancel-action close-sales-only">Close</button>';
            } else {
                salesActions.innerHTML =
                    '<button type="button" class="modal-btn btn-approve sales-pay-btn" data-pay-id="' + data.id + '">Mark Balance as Paid</button>' +
                    '<button type="button" class="modal-btn btn-cancel-action close-sales-only">Close</button>';
            }

            openModal(salesModal);
        });
    });

    document.addEventListener('click', function(event) {
        const resFeeBtn = event.target.closest('.resfee-pay-btn');
        if (resFeeBtn && resFeeBtn.dataset.resfeeId) {
            document.getElementById('resFeeConfirmId').value = resFeeBtn.dataset.resfeeId;
            closeModal(salesModal);
            openModal(resFeeConfirmModal);
            return;
        }

        const payBtn = event.target.closest('.sales-pay-btn');
        if (payBtn && payBtn.dataset.payId) {
            document.getElementById('salesConfirmId').value = payBtn.dataset.payId;
            closeModal(salesModal);
            openModal(confirmModal);
            return;
        }

        if (event.target.closest('.close-sales-only')) {
            closeModal(salesModal);
        }
    });


    document.querySelectorAll('.reservation-fee-form').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const idInput = form.querySelector('input[name="id"]');
            document.getElementById('resFeeConfirmId').value = idInput ? idInput.value : '';
            openModal(resFeeConfirmModal);
        });
    });

    if (salesConfirmForm) {
        salesConfirmForm.addEventListener('submit', function(event) {
            if (!window.VillaAsync) return;
            event.preventDefault();
            const id = document.getElementById('salesConfirmId').value;
            const row = findSalesRow(id);
            closeModal(confirmModal);
            window.VillaAsync.submitOptimisticForm(salesConfirmForm, {
                target: row,
                errorMessage: 'Payment status could not be updated.',
                onMutate: function() {
                    return optimisticBalancePaid(row);
                }
            });
        });
    }

    if (resFeeConfirmForm) {
        resFeeConfirmForm.addEventListener('submit', function(event) {
            if (!window.VillaAsync) return;
            event.preventDefault();
            const id = document.getElementById('resFeeConfirmId').value;
            const row = findSalesRow(id);
            closeModal(resFeeConfirmModal);
            window.VillaAsync.submitOptimisticForm(resFeeConfirmForm, {
                target: row,
                errorMessage: 'Reservation fee could not be updated.',
                onMutate: function() {
                    return optimisticReservationFeePaid(row);
                }
            });
        });
    }


    function applySalesFilters() {
        const search = (document.getElementById('salesSearch').value || '').toLowerCase();
        const stay = document.getElementById('salesStayFilter').value;
        const status = document.getElementById('salesStatusFilter').value;
        const resFee = document.getElementById('salesResFeeFilter').value;
        const payStatus = document.getElementById('salesPaymentStatusFilter').value;
        const payType = document.getElementById('salesPaymentTypeFilter').value;
        document.querySelectorAll('.sales-row').forEach(row => {
            const matchesSearch = !search || (row.dataset.guest_name || '').toLowerCase().includes(search) || String(row.dataset.id || '').includes(search);
            const matchesStay = stay === 'all' || row.dataset.time_type_raw === stay;
            const matchesStatus = status === 'all' || row.dataset.status_raw === status;
            const matchesResFee = resFee === 'all' || row.dataset.reservation_fee_status_raw === resFee;
            const matchesPayStatus = payStatus === 'all' || row.dataset.payment_status_raw === payStatus;
            const matchesPayType = payType === 'all' || row.dataset.payment_method_raw === payType;
            row.style.display = (matchesSearch && matchesStay && matchesStatus && matchesResFee && matchesPayStatus && matchesPayType) ? '' : 'none';
        });
    }
    ['salesSearch','salesStayFilter','salesStatusFilter','salesResFeeFilter','salesPaymentStatusFilter','salesPaymentTypeFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applySalesFilters);
        if (el) el.addEventListener('change', applySalesFilters);
    });
    document.getElementById('clearSalesSearch').addEventListener('click', function(){
        const searchInput = document.getElementById('salesSearch');
        searchInput.value = '';
        searchInput.focus();
        applySalesFilters();
    });

    const archiveMenu = document.createElement('div');
    archiveMenu.className = 'archive-context-menu';
    archiveMenu.innerHTML = '<button type="button">Move to Archive</button>';
    document.body.appendChild(archiveMenu);
    let archiveTargetId = '';
    document.querySelectorAll('.sales-row').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            archiveTargetId = row.dataset.id;
            archiveMenu.style.left = e.pageX + 'px';
            archiveMenu.style.top = e.pageY + 'px';
            archiveMenu.classList.add('show');
        });
    });
    archiveMenu.querySelector('button').addEventListener('click', function(){
        if (!archiveTargetId) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/archive_booking.php';
        form.innerHTML = '<input name="id" value="' + archiveTargetId + '"><input name="redirect" value="sales">';
        document.body.appendChild(form);
        if (window.VillaAsync) {
            window.VillaAsync.submitOptimisticForm(form, {
                target: findSalesRow(archiveTargetId),
                effect: 'remove',
                errorMessage: 'Booking could not be moved to archive.'
            });
        } else {
            form.submit();
        }
    });
    document.addEventListener('click', function(){ archiveMenu.classList.remove('show'); });

    document.querySelector('.close-sales-modal').addEventListener('click', function(){ closeModal(salesModal); });
    document.querySelector('.close-sales-confirm-modal').addEventListener('click', function(){ closeModal(confirmModal); });
    document.getElementById('cancelSalesConfirm').addEventListener('click', function(){ closeModal(confirmModal); });
    document.querySelector('.close-resfee-confirm-modal').addEventListener('click', function(){ closeModal(resFeeConfirmModal); });
    document.getElementById('cancelResFeeConfirm').addEventListener('click', function(){ closeModal(resFeeConfirmModal); });

    window.addEventListener('click', function(event) {
        if (event.target === salesModal) closeModal(salesModal);
        if (event.target === confirmModal) closeModal(confirmModal);
        if (event.target === resFeeConfirmModal) closeModal(resFeeConfirmModal);
    });
});
</script>







