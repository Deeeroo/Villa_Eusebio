<?php
function ve_review_relative_label(string $dateString): string {
    $timezone = new DateTimeZone('Asia/Manila');
    $reviewDate = new DateTimeImmutable($dateString, $timezone);
    $today = new DateTimeImmutable('today', $timezone);

    if ($reviewDate > $today) {
        return 'Today';
    }

    $diff = $reviewDate->diff($today);
    if ($diff->y >= 1) {
        return $diff->y . ' year' . ($diff->y === 1 ? '' : 's') . ' ago';
    }
    if ($diff->m >= 1) {
        return $diff->m . ' month' . ($diff->m === 1 ? '' : 's') . ' ago';
    }
    if ($diff->d >= 7) {
        $weeks = (int)floor($diff->d / 7);
        return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
    }
    if ($diff->d >= 1) {
        return $diff->d . ' day' . ($diff->d === 1 ? '' : 's') . ' ago';
    }
    return 'Today';
}

$reviews = [
    [
        'name' => 'Roleto Monteclaro Jr.',
        'text' => 'My Family liked it. Sobrang lapit sa kabihasnan. Walang tolls at di masakit sa gas. Will definitely come back.',
        'date' => '2026-03-04'
    ],
    [
        'name' => 'yeehnard buco',
        'text' => 'ANG GANDA pag gabi na may iba ibang kulay',
        'date' => '2025-07-04'
    ],
    [
        'name' => 'Matt Peralta',
        'text' => 'Edited review - still a great experience overall.',
        'date' => '2026-05-04'
    ],
    [
        'name' => 'Jhen Nares',
        'text' => 'Great place and nice atmosphere.',
        'date' => '2025-06-04'
    ],
    [
        'name' => 'Kenneth Quesada',
        'text' => 'Clean space and relaxing stay for family outings.',
        'date' => '2023-06-04'
    ],
    [
        'name' => 'Lawrence Bernardino',
        'text' => 'Nice place for gatherings and overnight stays.',
        'date' => '2022-06-04'
    ],
    [
        'name' => 'Gilbert Guevarra',
        'text' => 'Good service and peaceful location.',
        'date' => '2022-06-04'
    ],
    [
        'name' => 'Rommel DF',
        'text' => 'Good experience overall.',
        'date' => '2024-06-04'
    ],
];

include __DIR__ . "/../includes/header.php";
?>

<section class="page-section reviews-page-simple">
    <div class="container">
        <div class="section-header">
            <div class="section-mark">
                <span></span><i></i><span></span>
            </div>
            <h2 class="font-script">Guest Reviews</h2>
            <p>Simple feedback from guests who stayed at Villa Eusebio</p>
            <div class="review-rating-summary"><strong>Overall Rating: 5.0 / 5</strong><span>&#9733;&#9733;&#9733;&#9733;&#9733;</span><small>Based on <?php echo count($reviews); ?> guest reviews</small></div>
        </div>

        <div class="reviews-grid reviews-grid-simple">
            <?php foreach ($reviews as $review): ?>
            <div class="review-card review-card-static">
                <div class="review-header">
                    <strong><?php echo htmlspecialchars($review['name']); ?></strong>
                    <span>&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </div>
                <p><?php echo htmlspecialchars($review['text']); ?></p>
                <small title="<?php echo htmlspecialchars(date('F d, Y', strtotime($review['date']))); ?>"><?php echo htmlspecialchars(ve_review_relative_label($review['date'])); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
