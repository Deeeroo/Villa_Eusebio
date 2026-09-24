<?php
function ve_column_exists(mysqli $conn, string $table, string $column): bool {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function ve_table_exists(mysqli $conn, string $table): bool {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$tableEsc}'");
    return $result && mysqli_num_rows($result) > 0;
}

function ve_ensure_capstone2_schema(mysqli $conn): void {
    if (ve_table_exists($conn, 'bookings')) {
        if (!ve_column_exists($conn, 'bookings', 'archived')) {
            @mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
        if (!ve_column_exists($conn, 'bookings', 'rejection_reason')) {
            @mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN rejection_reason TEXT NULL AFTER archived");
        }
        if (!ve_column_exists($conn, 'bookings', 'archived_at')) {
            @mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN archived_at DATETIME NULL AFTER rejection_reason");
        }
    }

    if (ve_table_exists($conn, 'payments')) {
        if (!ve_column_exists($conn, 'payments', 'ocr_text')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_text LONGTEXT NULL AFTER proof_of_payment");
        }
        if (!ve_column_exists($conn, 'payments', 'ocr_reference')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_reference VARCHAR(120) NULL AFTER ocr_text");
        }
        if (!ve_column_exists($conn, 'payments', 'ocr_amount')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_amount DECIMAL(10,2) NULL AFTER ocr_reference");
        }
        if (!ve_column_exists($conn, 'payments', 'ocr_status')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_status VARCHAR(40) NOT NULL DEFAULT 'not_scanned' AFTER ocr_amount");
        }
        if (!ve_column_exists($conn, 'payments', 'ocr_notes')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_notes TEXT NULL AFTER ocr_status");
        }
        if (!ve_column_exists($conn, 'payments', 'ocr_scanned_at')) {
            @mysqli_query($conn, "ALTER TABLE payments ADD COLUMN ocr_scanned_at DATETIME NULL AFTER ocr_notes");
        }
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_blocks (
        block_id INT AUTO_INCREMENT PRIMARY KEY,
        blocked_date DATE NOT NULL,
        stay_type VARCHAR(30) NOT NULL DEFAULT 'day',
        reason TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (ve_table_exists($conn, 'admin_blocks')) {
        @mysqli_query($conn, "ALTER TABLE admin_blocks MODIFY stay_type VARCHAR(30) NOT NULL DEFAULT 'day'");
        @mysqli_query($conn, "ALTER TABLE admin_blocks MODIFY reason TEXT NOT NULL");
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        setting_area VARCHAR(80) NOT NULL,
        action_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings_snapshots (
        snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        snapshot_data LONGTEXT NOT NULL,
        action_note VARCHAR(160) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS email_subscribers (
        subscriber_id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcements (
        announcement_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        message TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        archived_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (ve_table_exists($conn, 'announcements') && !ve_column_exists($conn, 'announcements', 'archived_at')) {
        @mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN archived_at DATETIME NULL AFTER is_active");
    }
    if (ve_table_exists($conn, 'announcements') && !ve_column_exists($conn, 'announcements', 'image_path')) {
        @mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER message");
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS gallery_images (
        image_id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) NULL,
        description TEXT NULL,
        show_on_home TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (ve_table_exists($conn, 'gallery_images')) {
        if (!ve_column_exists($conn, 'gallery_images', 'description')) {
            @mysqli_query($conn, "ALTER TABLE gallery_images ADD COLUMN description TEXT NULL AFTER caption");
        }
        if (!ve_column_exists($conn, 'gallery_images', 'show_on_home')) {
            @mysqli_query($conn, "ALTER TABLE gallery_images ADD COLUMN show_on_home TINYINT(1) NOT NULL DEFAULT 0 AFTER caption");
        }
        if (!ve_column_exists($conn, 'gallery_images', 'archived_at')) {
            @mysqli_query($conn, "ALTER TABLE gallery_images ADD COLUMN archived_at DATETIME NULL AFTER show_on_home");
        }
        ve_seed_gallery_images($conn);
    }

}

function ve_seed_gallery_images(mysqli $conn): void {
    static $seeded = false;
    if ($seeded) return;
    $seeded = true;

    $done = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'gallery_seeded_v1' LIMIT 1");
    if ($done && mysqli_num_rows($done) > 0) return;

    $images = [
        ['assets/pool2.jpg', 'Swimming Pool Area', 1],
        ['assets/morning1.jpg', 'Main Villa Exterior', 1],
        ['assets/kitchen.jpg', 'Kitchen', 1],
        ['assets/table.jpg', 'Room Interior', 0],
        ['assets/night1.jpg', 'Night View', 0],
        ['assets/nightpool2.jpg', 'Night Swimming Pool', 0],
        ['assets/room1.jpg', 'Room', 0],
        ['assets/room2.jpg', 'Room', 0],
        ['assets/table2.jpg', 'Dining Table', 0],
        ['assets/bed2.jpg', 'Room', 0],
        ['assets/bed4.jpg', 'Bed', 0],
        ['assets/nightpool1.jpg', 'Night Swimming Pool', 0],
    ];

    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_images (image_path, caption, show_on_home)
        SELECT ?, ?, ? FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM gallery_images WHERE image_path = ? LIMIT 1)");
    if (!$stmt) return;

    foreach ($images as $image) {
        mysqli_stmt_bind_param($stmt, 'ssis', $image[0], $image[1], $image[2], $image[0]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    @mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('gallery_seeded_v1', '1')
        ON DUPLICATE KEY UPDATE setting_value = '1'");
}

function ve_setting(mysqli $conn, string $key, string $default = ''): string {
    ve_ensure_capstone2_schema($conn);
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ? (string)$row['setting_value'] : $default;
}
?>
