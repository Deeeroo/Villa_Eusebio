<?php
require_once '../includes/admin_auth.php';
admin_require_login(true);
include '../includes/db.php';
include '../includes/booking_repository.php';

function exportStayType($type) {
    if ($type === 'day') return 'Day Tour';
    if ($type === 'overnight') return 'Overnight Stay';
    if ($type === '22hour') return '22-Hour Stay';
    return ucfirst((string)$type);
}
function exportStayPrice($type) {
    if ($type === 'day') return 7000;
    if ($type === 'overnight') return 10000;
    if ($type === '22hour') return 13000;
    return 0;
}

$rows = [];
$total = 0;
foreach (ve_fetch_all_bookings($conn, "bs.booking_id DESC") as $row) {
    $isCancelled = strtolower($row['status'] ?? '') === 'cancelled';
    $guests = (int)($row['guests'] ?? 0);
    $additional = $isCancelled ? 0 : max($guests - 30, 0) * 150;
    $row['stay_value'] = $isCancelled ? 0 : exportStayPrice($row['time_type']) + $additional;
    $row['reservation_fee_amount'] = isset($row['reservation_fee_amount']) ? (float)$row['reservation_fee_amount'] : 2000;
    $row['remaining_balance'] = $isCancelled ? 0 : max($row['stay_value'] - $row['reservation_fee_amount'], 0);
    if (($row['status'] ?? '') === 'approved') $total += $row['stay_value'];
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #2f2a24; margin: 28px; }
        .report-head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #6b8e6b; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand img { width: 58px; height: 58px; object-fit: cover; border-radius: 50%; }
        h1 { margin: 0; color: #2e4b2a; font-size: 24px; }
        p { margin: 4px 0; color: #6d624f; }
        .summary { display: flex; gap: 12px; margin-bottom: 16px; }
        .summary div { flex: 1; background: #f5f1e8; border: 1px solid #ddd3c2; padding: 12px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ddd3c2; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eef5e8; color: #2e4b2a; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .toolbar button,
        .toolbar a { background: #3f6b37; color: #fff; border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #6d624f; }
        @media print { .toolbar { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="toolbar"><a href="sales.php">Back to Sales Record</a><button onclick="window.print()">Export / Save as PDF</button></div>
    <header class="report-head">
        <div class="brand">
            <img src="../assets/icon.jpg" alt="Villa Eusebio">
            <div>
                <h1>Sales Report</h1>
                <p>Villa Eusebio Private Resort</p>
            </div>
        </div>
        <p>Generated <?php echo date('M d, Y h:i A'); ?></p>
    </header>
    <section class="summary">
        <div><strong>Total Approved Value</strong><br>&#8369;<?php echo number_format($total); ?></div>
        <div><strong>Total Records</strong><br><?php echo count($rows); ?></div>
    </section>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Guest</th>
                <th>Stay</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Status</th>
                <th>Stay Value</th>
                <th>Reservation Fee</th>
                <th>Balance</th>
                <th>Payment Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td>#<?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                <td><?php echo htmlspecialchars(exportStayType($row['time_type'])); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['check_in_date']))); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['check_out_date']))); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                <td>&#8369;<?php echo number_format($row['stay_value']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($row['reservation_fee_status'] ?? 'unpaid')); ?> (&#8369;<?php echo number_format($row['reservation_fee_amount']); ?>)</td>
                <td>&#8369;<?php echo number_format($row['remaining_balance']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($row['payment_status'] ?? 'unpaid')); ?></td>
                <td><?php echo htmlspecialchars($row['special_requests'] ?: 'None'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });</script>
</body>
</html>



