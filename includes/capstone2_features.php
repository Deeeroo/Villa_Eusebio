<?php
function ve_column_exists(mysqli $conn, string $table, string $column): bool {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function ve_column_type(mysqli $conn, string $table, string $column): string {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    if (!$result) return '';
    $row = mysqli_fetch_assoc($result);
    return strtolower((string)($row['Type'] ?? ''));
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
        source_ip VARCHAR(45) NULL,
        source_user_agent VARCHAR(255) NULL,
        source_page VARCHAR(255) NULL,
        request_method VARCHAR(12) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (ve_table_exists($conn, 'settings_logs')) {
        if (!ve_column_exists($conn, 'settings_logs', 'source_ip')) {
            @mysqli_query($conn, "ALTER TABLE settings_logs ADD COLUMN source_ip VARCHAR(45) NULL AFTER action_note");
        }
        if (!ve_column_exists($conn, 'settings_logs', 'source_user_agent')) {
            @mysqli_query($conn, "ALTER TABLE settings_logs ADD COLUMN source_user_agent VARCHAR(255) NULL AFTER source_ip");
        }
        if (!ve_column_exists($conn, 'settings_logs', 'source_page')) {
            @mysqli_query($conn, "ALTER TABLE settings_logs ADD COLUMN source_page VARCHAR(255) NULL AFTER source_user_agent");
        }
        if (!ve_column_exists($conn, 'settings_logs', 'request_method')) {
            @mysqli_query($conn, "ALTER TABLE settings_logs ADD COLUMN request_method VARCHAR(12) NULL AFTER source_page");
        }
        if (strpos(ve_column_type($conn, 'settings_logs', 'created_at'), '(6)') === false) {
            @mysqli_query($conn, "ALTER TABLE settings_logs MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)");
        }
    }

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
        duration_type VARCHAR(30) NOT NULL DEFAULT 'never',
        expires_at DATETIME NULL,
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
    if (ve_table_exists($conn, 'announcements') && !ve_column_exists($conn, 'announcements', 'duration_type')) {
        @mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN duration_type VARCHAR(30) NOT NULL DEFAULT 'never' AFTER is_active");
    }
    if (ve_table_exists($conn, 'announcements') && !ve_column_exists($conn, 'announcements', 'expires_at')) {
        @mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL AFTER duration_type");
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
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-08.jpeg', 'Poolside Villa', 'A warm poolside view of Villa Eusebio.', 1],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-03.jpeg', 'Night Swimming Pool', 'Evening pool ambience with tropical greenery.', 1],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-04.jpeg', 'Living Lounge', 'A cozy lounge area for relaxing between swims.', 1],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-05.jpeg', 'Sunset Swimming Pool', 'Golden-hour light across the private pool.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-02.jpeg', 'Poolside Lounge at Night', 'An intimate outdoor corner beside the pool.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-06.jpeg', 'Kitchen Area', 'A clean kitchen space for guest use.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-09.jpeg', 'Kitchen and Pantry', 'Kitchen, refrigerator, and water dispenser area.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-10.jpeg', 'Dining Table', 'Long dining setup for group meals.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-07.jpeg', 'Dining Room', 'Indoor dining area with warm resort styling.', 0],
        ['assets/gallery/customer-20260926/villa-eusebio-gallery-01.jpeg', 'Lounge Interior', 'Comfortable seating with natural accents.', 0],
    ];

    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_images (image_path, caption, description, show_on_home)
        SELECT ?, ?, ?, ? FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM gallery_images WHERE image_path = ? LIMIT 1)");
    if (!$stmt) return;

    foreach ($images as $image) {
        mysqli_stmt_bind_param($stmt, 'sssis', $image[0], $image[1], $image[2], $image[3], $image[0]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    @mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('gallery_seeded_v1', '1')
        ON DUPLICATE KEY UPDATE setting_value = '1'");
}

function ve_audit_text(string $value, int $maxLength): string {
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if (strlen($value) <= $maxLength) {
        return $value;
    }
    return substr($value, 0, $maxLength);
}

function ve_audit_log(mysqli $conn, string $area, string $note): void {
    ve_ensure_capstone2_schema($conn);

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $sourceIp = ve_audit_text((string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown'), 45);
    $userAgent = ve_audit_text((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 255);
    $sourcePage = ve_audit_text((string)($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? 'Unknown'), 255);
    $requestMethod = ve_audit_text((string)($_SERVER['REQUEST_METHOD'] ?? 'Unknown'), 12);

    $stmt = mysqli_prepare($conn, "INSERT INTO settings_logs
        (admin_id, setting_area, action_note, source_ip, source_user_agent, source_page, request_method, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(6))");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issssss', $adminId, $area, $note, $sourceIp, $userAgent, $sourcePage, $requestMethod);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return;
    }

    $fallback = mysqli_prepare($conn, "INSERT INTO settings_logs (admin_id, setting_area, action_note) VALUES (?, ?, ?)");
    if ($fallback) {
        mysqli_stmt_bind_param($fallback, 'iss', $adminId, $area, $note);
        mysqli_stmt_execute($fallback);
        mysqli_stmt_close($fallback);
    }
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

function ve_bookings_paused(mysqli $conn): bool {
    return ve_setting($conn, 'bookings_paused', '0') === '1';
}

function ve_booking_pause_message(mysqli $conn): string {
    $message = trim(ve_setting($conn, 'bookings_pause_note', ''));
    if ($message !== '') {
        return $message;
    }
    return 'Bookings are temporarily closed. Please check again later or contact Villa Eusebio for assistance.';
}
?>
