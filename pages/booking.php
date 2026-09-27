<?php include "../includes/header.php"; ?>

<?php
$timeType = $_GET['time_type'] ?? '';
$checkIn = $_GET['check_in_date'] ?? '';
$checkOut = $_GET['check_out_date'] ?? '';
$bookingsPaused = isset($conn) && $conn instanceof mysqli ? ve_bookings_paused($conn) : false;
$bookingPauseMessage = isset($conn) && $conn instanceof mysqli ? ve_booking_pause_message($conn) : 'Bookings are temporarily closed. Please check again later or contact Villa Eusebio for assistance.';

function getStayLabel($type) {
    if ($type === 'day') return 'Day Tour';
    if ($type === 'overnight') return 'Overnight Stay';
    if ($type === '22hour') return '22-Hour Stay';
    return 'Not Selected';
}

function getStayTime($type) {
    if ($type === 'day') return '9:00 AM - 5:00 PM';
    if ($type === 'overnight') return '7:00 PM - 7:00 AM (Next Day)';
    if ($type === '22hour') return '9:00 AM - 7:00 AM (Next Day)';
    return '--';
}

function getBasePrice($type) {
    if ($type === 'day') return 7000;
    if ($type === 'overnight') return 10000;
    if ($type === '22hour') return 13000;
    return 0;
}

$stayLabel = getStayLabel($timeType);
$stayTime = getStayTime($timeType);
$basePrice = getBasePrice($timeType);

if ($bookingsPaused):
?>

<div class="booking-wrapper booking-closed-wrapper">
    <div class="booking-closed-card">
        <span class="booking-closed-kicker">No bookings for now</span>
        <h1>Bookings are temporarily closed</h1>
        <p><?php echo htmlspecialchars($bookingPauseMessage); ?></p>
        <a class="back-link" href="../pages/appointment.php">Back to Calendar</a>
    </div>
</div>

<?php
exit;
endif;
?>

<style>
.booking-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(6px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.booking-modal.show {
    display: flex;
}

.booking-modal-box {
    background: #F5F3EF;
    width: 100%;
    max-width: 900px;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 20px 80px rgba(0,0,0,0.25);
    border: 1px solid rgba(0,0,0,0.05);
}

.booking-modal-title {
    text-align: center;
    font-size: 28px;
    margin-bottom: 5px;
}

.booking-modal-subtext {
    text-align: center;
    font-size: 13px;
    color: #777;
    margin-bottom: 25px;
}

.booking-modal-card {
    background: white;
    border-radius: 12px;
    padding: 18px;
    border: 1px solid #eee;
}

.booking-modal-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 14px;
    border-bottom: 1px solid #eee;
}

.booking-modal-actions {
    margin-top: 25px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

.booking-modal-btn.cancel {
    background: #ddd;
    padding: 10px 25px;
    border-radius: 20px;
}

.booking-modal-btn.confirm {
    background: #333;
    color: white;
    padding: 10px 25px;
    border-radius: 20px;
}


.booking-modal-warning {
    margin-top: 22px;
    padding: 16px 18px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f7efe3, #efe4d3);
    border: 1px solid #e3d2b8;
    color: #5d4331;
    font-size: 14px;
    line-height: 1.7;
}


.extra-fee-highlight {
    color: #c62828;
    font-weight: 700;
}

.additional-fee-note {
    margin-top: 8px;
    color: #c62828;
    font-size: 13px;
    font-weight: 600;
}

.final-confirm-box {
    max-width: 680px;
}

.final-confirm-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 22px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #eadcc9;
}

.final-confirm-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: #f3e4cf;
    color: #6b4c34;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.final-confirm-card h3 {
    margin: 0 0 8px;
    color: #3b2c22;
}

.final-confirm-card p {
    margin: 0;
    color: #5b4a3b;
    line-height: 1.7;
}

.cash-payment-reminder {
    display: none;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 14px;
    margin-top: 18px;
    padding: 18px;
    border-radius: 18px;
    border: 2px solid #d4af37;
    background:
        linear-gradient(135deg, rgba(255, 249, 220, .98), rgba(255, 244, 202, .96)),
        #fff8df;
    color: #4f3a12;
    box-shadow: 0 18px 40px rgba(143, 104, 11, .16);
}

.cash-payment-reminder.show {
    display: grid;
}

.cash-reminder-icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    background: #2f5d34;
    color: #fff8df;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: 900;
    box-shadow: 0 10px 24px rgba(47, 93, 52, .22);
}

.cash-reminder-content h3 {
    margin: 0 0 8px;
    color: #2f3f2a;
    font-size: 20px;
}

.cash-reminder-content p {
    margin: 0;
    color: #60491b;
    line-height: 1.65;
}

.cash-reminder-highlight {
    display: inline-flex;
    align-items: center;
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(212, 175, 55, .55);
    color: #9a6100;
    font-weight: 900;
}

.cash-reminder-points {
    margin: 12px 0 0;
    padding-left: 18px;
    color: #4f3a12;
    line-height: 1.6;
    font-size: 14px;
}

.cash-reminder-points strong {
    color: #2f5d34;
}

.final-cash-reminder {
    margin-bottom: 18px;
}

@media (max-width: 768px) {
    .booking-modal-box {
        padding: 24px 18px;
    }

    .booking-modal-grid {
        grid-template-columns: 1fr;
    }

    .booking-modal-title {
        font-size: 28px;
        padding-right: 30px;
    }

    .cash-payment-reminder {
        grid-template-columns: 1fr;
    }

    .cash-reminder-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        font-size: 24px;
    }
}
</style>

<div class="booking-wrapper">
    <div class="details-panel">
        <h2 class="font-script">Booking Summary</h2>

        <div class="booking-summary">
            <div class="summary-line">
                <span>STAY TYPE</span>
                <span><?php echo htmlspecialchars($stayLabel); ?></span>
            </div>

            <div class="summary-line">
                <span>TIME DURATION</span>
                <span><?php echo htmlspecialchars($stayTime); ?></span>
            </div>

            <div class="summary-line">
                <span>CHECK-IN</span>
                <span><?php echo htmlspecialchars($checkIn ?: '--'); ?></span>
            </div>

            <div class="summary-line">
                <span>CHECK-OUT</span>
                <span><?php echo htmlspecialchars($checkOut ?: '--'); ?></span>
            </div>

            <div class="summary-line">
                <span>BASE PRICE</span>
                <span>₱<?php echo number_format($basePrice); ?></span>
            </div>

            <div class="summary-line">
                <span>RESERVATION FEE</span>
                <span>₱2,000</span>
            </div>

            <div class="summary-line border-bottom">
                <span>REMAINING BALANCE</span>
                <span>₱<?php echo number_format(max(0, $basePrice - 2000)); ?></span>
            </div>
        </div>

        <div class="booking-fee-note" style="margin-top:14px;padding:12px 14px;background:#fbf7f0;border:1px solid #eadfcf;border-radius:12px;color:#5f4a37;font-size:14px;line-height:1.6;">
            A <strong>₱2,000 reservation fee</strong> is required to reserve your selected date. The remaining balance will be settled after approval.<div class="additional-fee-note">Guests above 30 will be charged an additional ₱150 per person.</div><div class="room-capacity-note">Room guide: each room can fit around 10 to 15 people.</div>
        </div>
    </div>

    <div class="details-panel">
        <h2 class="font-script">Guest Information</h2>

        <form action="../api/submit_booking.php" method="POST" enctype="multipart/form-data" class="booking-form" id="finalBookingForm">

            <input type="hidden" name="time_type" value="<?php echo htmlspecialchars($timeType); ?>">
            <input type="hidden" name="check_in_date" value="<?php echo htmlspecialchars($checkIn); ?>">
            <input type="hidden" name="check_out_date" value="<?php echo htmlspecialchars($checkOut); ?>">

            <div class="form-group">
                <label for="guest_name">Full Name</label>
                <input
                    type="text"
                    id="guest_name"
                    name="guest_name"
                    pattern="[A-Za-z ]+"
                    title="Use letters and spaces only."
                    autocomplete="name"
                    required
                >
                <small class="field-help">Letters and spaces only.</small>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="example@email.com"
                    pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
                    required
                >
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input 
                    type="text" 
                    id="mobile" 
                    name="mobile" 
                    pattern="^(09\d{9}|\+639\d{9})$"
                    placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                    required
                >
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" required>
            </div>

            <div class="form-group" style="position: relative;">
    <label for="guests">Number of Guests</label>

    <input
        type="number"
        id="guests"
        name="guests"
        list="guest-options"
        min="1"
        max="50"
        placeholder="Enter number of guests"
        required
    >

    <div id="guestPopup" style="
        display:none;
        position:absolute;
        top: 100%;
        left: 0;
        margin-top:6px;
        background:#fff;
        border:1px solid #ccc;
        padding:8px 12px;
        font-size:12px;
        border-radius:6px;
        box-shadow:0 6px 18px rgba(0,0,0,0.15);
        color:#333;
        z-index:999;
        white-space: nowrap;
    ">
        ⚠️ Above 30 will cost additional ₱150 per extra guest
    </div>

    <datalist id="guest-options">
        <option value="5">
        <option value="10">
        <option value="15">
        <option value="20">
        <option value="30">
    </datalist>
</div>

            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" required>
                    <option value="" disabled selected>-- Select Payment Method --</option>
                    <option value="gcash">GCash</option>
                    <option value="bdo">BDO Bank Transfer</option>
                    <option value="unionbank">UnionBank</option>
                    <option value="cash">Cash Payment</option>
                </select>

                <div id="paymentPreview" class="payment-preview">
                    <h4 id="paymentPreviewTitle" class="payment-preview-title">Payment Preview</h4>
                    <p id="paymentPreviewNote" class="payment-preview-note"></p>
                    <img id="paymentPreviewImage" src="" alt="Selected payment QR or instruction image">
                </div>
            </div>

            <div class="form-group">
                <label for="proof_of_payment" id="proofLabel">Upload Payment Proof</label>
                <input type="file" id="proof_of_payment" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                <small id="proofHelpText" class="payment-proof-help">Upload a clear screenshot, QR payment receipt, or transfer confirmation.</small>
                <div id="proofPreview" class="proof-preview"></div>
            </div>

            <div class="form-group">
                <label for="special_requests">Notes</label>
                <textarea id="special_requests" name="special_requests" rows="4" placeholder="Enter notes or requests here..."></textarea>
                <small class="field-help">Room capacity guide: each room can fit around 10 to 15 people.</small>
            </div>

            <button type="submit" class="btn-complete">SUBMIT BOOKING REQUEST</button>
        </form>
    </div>
</div>

<!-- CUSTOM CONFIRMATION MODAL -->
<div id="bookingModal" class="booking-modal">
    <div class="booking-modal-box">
        <button type="button" class="booking-modal-close" id="closeBookingModal">&times;</button>

        <h2 class="font-script booking-modal-title">Booking Request Confirmation</h2>
        <p class="booking-modal-subtext">Please review your details carefully. Once confirmed, your booking request will be submitted for admin approval.</p>

        <div class="booking-modal-grid">
            <div class="booking-modal-card">
                <h3>Booking Summary</h3>

                <div class="booking-modal-row">
                    <span>Stay Type</span>
                    <span id="modalStayType"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Time Duration</span>
                    <span id="modalStayTime"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Check-in</span>
                    <span id="modalCheckIn"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Check-out</span>
                    <span id="modalCheckOut"></span>
                </div>

                <div class="booking-modal-row total">
                    <span>Base Price</span>
                    <span id="modalBasePrice"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Reservation Fee</span>
                    <span id="modalReservationFee">₱2,000</span>
                </div>

                <div class="booking-modal-row additional-fee-row" id="additionalFeeRow" style="display:none;">
                    <span>Additional Guest Fee</span>
                    <span id="modalAdditionalFee" class="extra-fee-highlight"></span>
                </div>

                <div class="booking-modal-row total">
                    <span>Remaining Balance</span>
                    <span id="modalRemainingBalance"></span>
                </div>
            </div>

            <div class="booking-modal-card">
                <h3>Guest Information</h3>

                <div class="booking-modal-row">
                    <span>Full Name</span>
                    <span id="modalGuestName"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Email</span>
                    <span id="modalEmail"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Mobile Number</span>
                    <span id="modalMobile"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Address</span>
                    <span id="modalAddress"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Number of Guests</span>
                    <span id="modalGuests"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Payment Method</span>
                    <span id="modalPaymentMethod"></span>
                </div>

                <div class="booking-modal-row">
                    <span>Payment Proof</span>
                    <span id="modalProofText"></span>
                </div>

                <div class="booking-modal-proof-wrap" id="modalProofPreviewWrap" style="display:none;">
                    <img id="modalProofPreview" src="" alt="Payment proof preview">
                </div>

                <div class="cash-payment-reminder" id="cashBookingReminder">
                    <div class="cash-reminder-icon">₱</div>
                    <div class="cash-reminder-content">
                        <h3>Cash Reservation Reminder</h3>
                        <p>Cash payment bookings still need a reservation fee before the date can be secured.</p>
                        <span class="cash-reminder-highlight">Minimum payment required: ₱2,000</span>
                        <ul class="cash-reminder-points">
                            <li>Upload a valid ID with this booking request.</li>
                            <li>Coordinate with the owner/admin to settle at least <strong>₱2,000</strong>.</li>
                            <li>Your selected date is confirmed only after admin verification.</li>
                        </ul>
                    </div>
                </div>

                <div class="booking-modal-row">
                    <span>Notes</span>
                    <span id="modalSpecialRequests"></span>
                </div>
            </div>
        </div>

        <div class="booking-modal-warning">Warning: Once this booking request is submitted and confirmed, it will not be eligible for cancellation or refund.</div>

        <div class="booking-modal-actions">
            <button type="button" class="booking-modal-btn cancel" id="cancelBookingBtn">Cancel</button>
            <button type="button" class="booking-modal-btn confirm" id="confirmBookingBtn">Yes, Submit Booking</button>
        </div>
    </div>
</div>

<div id="finalConfirmModal" class="booking-modal final-confirm-modal">
    <div class="booking-modal-box final-confirm-box">
        <h2 class="font-script booking-modal-title">Final Confirmation</h2>
        <p class="booking-modal-subtext">Please review this reminder before completing your booking request.</p>
        <div class="final-confirm-card">
            <div class="final-confirm-icon">⚠</div>
            <div>
                <h3>Important Notice</h3>
                <p>Please confirm: once booked, this reservation will not be eligible for cancellation or refund. Continue only if all of your details are final and correct.</p>
            </div>
        </div>
        <div class="cash-payment-reminder final-cash-reminder" id="cashFinalReminder">
            <div class="cash-reminder-icon">₱</div>
            <div class="cash-reminder-content">
                <h3>Please prepare the reservation fee</h3>
                <p>Because you selected cash payment, the resort must still receive and verify at least <strong>₱2,000</strong> before your reservation date is approved.</p>
                <span class="cash-reminder-highlight">Do not leave the payment unsettled.</span>
            </div>
        </div>
        <div class="booking-modal-actions">
            <button type="button" class="booking-modal-btn cancel" id="cancelFinalConfirmBtn">Go Back</button>
            <button type="button" class="booking-modal-btn confirm" id="submitFinalBookingBtn">I Understand, Submit</button>
        </div>
    </div>
</div>

<div id="guestLimitModal" class="booking-modal">
    <div class="booking-modal-box final-confirm-box">
        <h2 class="font-script booking-modal-title">Guest Limit Notice</h2>
        <p class="booking-modal-subtext">Villa Eusebio has a maximum guest limit.</p>
        <div class="final-confirm-card">
            <div class="final-confirm-icon">!</div>
            <div>
                <h3>50 guests maximum only</h3>
                <p>More than 50 people is not possible for the resort capacity. Please enter 50 guests or below only.</p>
            </div>
        </div>
        <div class="booking-modal-actions">
            <button type="button" class="booking-modal-btn confirm" id="closeGuestLimitModal">OK</button>
        </div>
    </div>
</div>

<script>
const finalBookingForm = document.getElementById("finalBookingForm");
const bookingModal = document.getElementById("bookingModal");
const closeBookingModal = document.getElementById("closeBookingModal");
const cancelBookingBtn = document.getElementById("cancelBookingBtn");
const confirmBookingBtn = document.getElementById("confirmBookingBtn");
const finalConfirmModal = document.getElementById("finalConfirmModal");
const cancelFinalConfirmBtn = document.getElementById("cancelFinalConfirmBtn");
const submitFinalBookingBtn = document.getElementById("submitFinalBookingBtn");
const guestLimitModal = document.getElementById("guestLimitModal");
const closeGuestLimitModal = document.getElementById("closeGuestLimitModal");

const paymentPreview = document.getElementById("paymentPreview");
const paymentPreviewTitle = document.getElementById("paymentPreviewTitle");
const paymentPreviewNote = document.getElementById("paymentPreviewNote");
const paymentPreviewImage = document.getElementById("paymentPreviewImage");
const paymentMethodSelect = document.getElementById("payment_method");
const proofInput = document.getElementById("proof_of_payment");
const proofPreview = document.getElementById("proofPreview");
const proofLabel = document.getElementById("proofLabel");
const proofHelpText = document.getElementById("proofHelpText");
const guestNameInput = document.getElementById("guest_name");
const cashBookingReminder = document.getElementById("cashBookingReminder");
const cashFinalReminder = document.getElementById("cashFinalReminder");
const guestNamePattern = /^[A-Za-z]+(?: [A-Za-z]+)*$/;

const paymentMeta = {
    gcash: {
        label: "GCash",
        note: "Scan this QR image to pay through GCash.",
        image: "../assets/gcashqrcode.jpg"
    },
    bdo: {
        label: "BDO Bank Transfer",
        note: "Scan this QR image or replace it with your official BDO payment QR.",
        image: "../assets/bdoqrcode.jpg"
    },
    unionbank: {
        label: "UnionBank",
        note: "Scan this QR image or replace it with your official UnionBank payment QR.",
        image: "../assets/ubqrcode.jpg"
    },
    cash: {
        label: "Cash Payment",
        note: "Cash payment still requires at least ₱2,000 reservation fee. Upload a valid ID, then coordinate with the owner/admin for verification.",
        image: "../assets/cash.png"
    }
};

function updatePaymentPreview() {
    const selected = paymentMethodSelect.value;
    const config = paymentMeta[selected];

    if (!config) {
        paymentPreview.classList.remove("show");
        paymentPreviewImage.src = "";
        return;
    }

    paymentPreviewTitle.textContent = config.label;
    paymentPreviewNote.textContent = config.note;
    paymentPreviewImage.src = config.image;
    paymentPreviewImage.alt = config.label + " image";
    paymentPreview.classList.add("show");
}

function updateProofRequirement() {
    const selected = paymentMethodSelect.value;
    const isCash = selected === 'cash';
    proofInput.required = true;
    proofLabel.textContent = isCash ? 'Upload Valid ID' : 'Upload Payment Proof';
    proofHelpText.textContent = isCash
        ? 'Cash payment requires a valid legal ID before you can submit your booking request.'
        : 'Upload a clear screenshot, QR payment receipt, or transfer confirmation before submitting.';
}

function updateProofPreview() {
    const file = proofInput.files && proofInput.files[0] ? proofInput.files[0] : null;
    if (!file) {
        proofPreview.innerHTML = '';
        return;
    }

    const extension = file.name.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp'].includes(extension)) {
        const reader = new FileReader();
        reader.onload = function(e) {
            proofPreview.innerHTML = '<div class="proof-preview-card"><span>' + file.name + '</span><img src="' + e.target.result + '" alt="Payment proof preview"></div>';
        };
        reader.readAsDataURL(file);
    } else {
        proofPreview.innerHTML = '<div class="proof-preview-card proof-file-card"><span>' + file.name + '</span><p>PDF file selected and ready to upload.</p></div>';
    }
}

paymentMethodSelect.addEventListener("change", function() {
    updatePaymentPreview();
    updateProofRequirement();
});
proofInput.addEventListener('change', updateProofPreview);
updateProofRequirement();

guestNameInput.addEventListener("input", function() {
    this.value = this.value.replace(/[^A-Za-z ]/g, "").replace(/\s{2,}/g, " ");
});

finalBookingForm.addEventListener("submit", function(e) {
    e.preventDefault();

    const guestName = document.getElementById("guest_name").value.trim();
    const email = document.getElementById("email").value.trim();
    const mobile = document.getElementById("mobile").value.trim();
    const address = document.getElementById("address").value.trim();
    const guests = parseInt(document.getElementById("guests").value);
    const paymentMethod = document.getElementById("payment_method").value;
    const specialRequests = document.getElementById("special_requests").value.trim();
    const proofFile = proofInput.files && proofInput.files[0] ? proofInput.files[0] : null;

    if (!guestNamePattern.test(guestName)) {
        alert("Full name must contain letters and spaces only.");
        document.getElementById("guest_name").focus();
        return;
    }

    if (guests < 1 || isNaN(guests)) {
        alert("Please enter valid number of guests.");
        return;
    }

    if (guests > 50) {
        guestLimitModal.classList.add("show");
        document.body.style.overflow = "hidden";
        return;
    }

    if (!proofFile) {
        alert(paymentMethod === 'cash'
            ? 'Please upload a valid ID before submitting this cash booking request.'
            : 'Please upload your payment proof before submitting this booking request.');
        return;
    }

    const stayType = "<?php echo htmlspecialchars($stayLabel, ENT_QUOTES); ?>";
    const stayTime = "<?php echo htmlspecialchars($stayTime, ENT_QUOTES); ?>";
    const checkIn = "<?php echo htmlspecialchars($checkIn, ENT_QUOTES); ?>";
    const checkOut = "<?php echo htmlspecialchars($checkOut, ENT_QUOTES); ?>";
    const basePriceNumber = <?php echo (int)$basePrice; ?>;
    const reservationFeeNumber = 2000;
    const extraGuestCount = guests > 30 ? (guests - 30) : 0;
    const additionalGuestFee = extraGuestCount * 150;
    const remainingBalanceNumber = Math.max((basePriceNumber + additionalGuestFee) - reservationFeeNumber, 0);
    const basePrice = "₱" + basePriceNumber.toLocaleString();

    document.getElementById("modalStayType").textContent = stayType;
    document.getElementById("modalStayTime").textContent = stayTime;
    document.getElementById("modalCheckIn").textContent = checkIn;
    document.getElementById("modalCheckOut").textContent = checkOut;
    document.getElementById("modalBasePrice").textContent = basePrice;
    document.getElementById("modalReservationFee").textContent = "₱" + reservationFeeNumber.toLocaleString();
    document.getElementById("modalRemainingBalance").textContent = "₱" + remainingBalanceNumber.toLocaleString();
    const additionalFeeRow = document.getElementById("additionalFeeRow");
    const modalAdditionalFee = document.getElementById("modalAdditionalFee");
    if (additionalGuestFee > 0) {
        modalAdditionalFee.textContent = "₱" + additionalGuestFee.toLocaleString() + " (" + extraGuestCount + " extra guest" + (extraGuestCount > 1 ? "s" : "") + ")";
        additionalFeeRow.style.display = "flex";
    } else {
        modalAdditionalFee.textContent = "₱0";
        additionalFeeRow.style.display = "none";
    }

    document.getElementById("modalGuestName").textContent = guestName;
    document.getElementById("modalEmail").textContent = email;
    document.getElementById("modalMobile").textContent = mobile;
    document.getElementById("modalAddress").textContent = address;
    document.getElementById("modalGuests").textContent = guests;
    document.getElementById("modalPaymentMethod").textContent = paymentMeta[paymentMethod] ? paymentMeta[paymentMethod].label : paymentMethod;
    document.getElementById("modalProofText").textContent = proofFile ? proofFile.name : (paymentMethod === "cash" ? "No valid ID uploaded" : "No payment proof uploaded");
    const isCashPayment = paymentMethod === "cash";
    cashBookingReminder.classList.toggle("show", isCashPayment);
    cashFinalReminder.classList.toggle("show", isCashPayment);
    const modalProofPreviewWrap = document.getElementById("modalProofPreviewWrap");
    const modalProofPreview = document.getElementById("modalProofPreview");
    if (proofFile && proofFile.type && proofFile.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = function(e) {
            modalProofPreview.src = e.target.result;
            modalProofPreviewWrap.style.display = "block";
        };
        reader.readAsDataURL(proofFile);
    } else {
        modalProofPreview.src = "";
        modalProofPreviewWrap.style.display = "none";
    }
    document.getElementById("modalSpecialRequests").textContent = specialRequests || "None";

    bookingModal.classList.add("show");
    document.body.style.overflow = "hidden";
});

function closeModal() {
    bookingModal.classList.remove("show");
    finalConfirmModal.classList.remove("show");
    document.body.style.overflow = "";
}

closeBookingModal.addEventListener("click", closeModal);
cancelBookingBtn.addEventListener("click", closeModal);

bookingModal.addEventListener("click", function(e) {
    if (e.target === bookingModal) {
        closeModal();
    }
});

confirmBookingBtn.addEventListener("click", function() {
    finalConfirmModal.classList.add("show");
    bookingModal.classList.remove("show");
    document.body.style.overflow = "hidden";
});

cancelFinalConfirmBtn.addEventListener("click", function() {
    finalConfirmModal.classList.remove("show");
    bookingModal.classList.add("show");
});

finalConfirmModal.addEventListener("click", function(e) {
    if (e.target === finalConfirmModal) {
        finalConfirmModal.classList.remove("show");
        bookingModal.classList.add("show");
    }
});

submitFinalBookingBtn.addEventListener("click", function() {
    finalBookingForm.submit();
});

const guestInput = document.getElementById("guests");
const guestPopup = document.getElementById("guestPopup");

guestInput.addEventListener("input", function() {
    let val = parseInt(this.value);

    if (!isNaN(val) && val > 50) {
        this.value = 50;
        val = 50;
        guestLimitModal.classList.add("show");
        document.body.style.overflow = "hidden";
    }

    if (!isNaN(val) && val > 30) {
        guestPopup.style.display = "block";
    } else {
        guestPopup.style.display = "none";
    }
});

closeGuestLimitModal.addEventListener("click", function() {
    guestLimitModal.classList.remove("show");
    if (!bookingModal.classList.contains("show") && !finalConfirmModal.classList.contains("show")) {
        document.body.style.overflow = "";
    }
});

guestLimitModal.addEventListener("click", function(e) {
    if (e.target === guestLimitModal) {
        guestLimitModal.classList.remove("show");
        if (!bookingModal.classList.contains("show") && !finalConfirmModal.classList.contains("show")) {
            document.body.style.overflow = "";
        }
    }
});
</script>


