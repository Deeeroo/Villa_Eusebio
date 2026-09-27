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

$rows = ve_fetch_all_bookings($conn, "bs.booking_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservation Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #2f2a24; margin: 28px; }
        .report-head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #6b8e6b; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand img { width: 58px; height: 58px; object-fit: cover; border-radius: 50%; }
        h1 { margin: 0; color: #2e4b2a; font-size: 24px; }
        p { margin: 4px 0; color: #6d624f; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ddd3c2; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eef5e8; color: #2e4b2a; }
        .status { font-weight: 700; text-transform: capitalize; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .toolbar button,
        .toolbar a { background: #3f6b37; color: #fff; border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #6d624f; }
        @media print { .toolbar { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="toolbar"><a href="reservation.php">Back to Reservations</a><button onclick="window.print()">Export / Save as PDF</button></div>
    <header class="report-head">
        <div class="brand">
            <img src="../assets/icon.jpg" alt="Villa Eusebio">
            <div>
                <h1>Reservation Report</h1>
                <p>Villa Eusebio Private Resort</p>
            </div>
        </div>
        <p>Generated <?php echo date('M d, Y h:i A'); ?></p>
    </header>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Guest</th>
                <th>Contact</th>
                <th>Stay Type</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Guests</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td>#<?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['guest_name']); ?><br><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                <td><?php echo htmlspecialchars(exportStayType($row['time_type'])); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['check_in_date']))); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['check_out_date']))); ?></td>
                <td><?php echo (int)$row['guests']; ?></td>
                <td><?php echo htmlspecialchars(strtoupper($row['payment_method'])); ?></td>
                <td class="status"><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['special_requests'] ?: 'None'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });</script>
</body>
</html>



