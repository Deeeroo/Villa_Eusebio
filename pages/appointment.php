<?php
include "../includes/header.php";
$initialBookingsPaused = isset($conn) && $conn instanceof mysqli ? ve_bookings_paused($conn) : false;
$initialBookingPauseMessage = isset($conn) && $conn instanceof mysqli ? ve_booking_pause_message($conn) : 'Bookings are temporarily closed. Please check again later or contact Villa Eusebio for assistance.';
?>

<div class="booking-wrapper">
    <div class="booking-pause-notice" id="bookingPauseNotice" <?php echo $initialBookingsPaused ? '' : 'hidden'; ?>>
        <strong>No bookings for now</strong>
        <span id="bookingPauseMessage"><?php echo htmlspecialchars($initialBookingPauseMessage); ?></span>
    </div>

    <div class="calendar-panel">
        <h2 class="font-script">Select Date</h2>

        <div class="calendar-header">
            <button type="button" id="prevMonth">&lt;</button>
            <h3 id="currentMonth">MARCH 2026</h3>
            <button type="button" id="nextMonth">&gt;</button>
        </div>

        <div class="calendar-grid" id="calendarGrid"></div>

        <div class="calendar-info-note">Extra guests above 30 have an added fee of <strong class="extra-head-fee-red">₱150 per head</strong>. Each room can fit around <strong>10 to 15 people</strong>.</div>

        <div class="calendar-legend">
            <div class="legend-item"><span class="box available"></span> Available</div>
            <div class="legend-item"><span class="box booked"></span> Booked</div>
            <div class="legend-item"><span class="box selected"></span> Selected</div>
        </div>
    </div>

    <div class="details-panel">
        <h2 class="font-script">Reservation Details</h2>

        <form action="../pages/booking.php" method="GET" id="reservationForm">
            <div class="stay-options">
                <label class="option-card">
                    <input type="radio" name="time_type" value="day" required>
                    <div class="info">
                        <span class="title">DAY TOUR</span>
                        <span class="time">9:00 AM - 5:00 PM</span>
                    </div>
                    <span class="price">₱7,000</span>
                </label>

                <label class="option-card">
                    <input type="radio" name="time_type" value="overnight">
                    <div class="info">
                        <span class="title">OVERNIGHT STAY</span>
                        <span class="time">7:00 PM - 7:00 AM (Next Day)</span>
                    </div>
                    <span class="price">₱10,000</span>
                </label>

                <label class="option-card">
                    <input type="radio" name="time_type" value="22hour">
                    <div class="info">
                        <span class="title">22-HOUR STAY</span>
                        <span class="time">9:00 AM - 7:00 AM (Next Day)</span>
                    </div>
                    <span class="price">₱13,000</span>
                </label>
            </div>

            <div class="booking-summary">
                <div class="summary-line">
                    <span>CHECK-IN</span>
                    <span id="displayCheckIn">Select a date</span>
                </div>

                <div class="summary-line border-bottom">
                    <span>CHECK-OUT</span>
                    <span id="displayCheckOut">--</span>
                </div>
            </div>

            <input type="hidden" name="check_in_date" id="inputCheckIn">
            <input type="hidden" name="check_out_date" id="inputCheckOut">

            <button type="submit" class="btn-complete">COMPLETE RESERVATION</button>
        </form>
    </div>
</div>

<script>
let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();

let bookedDates = {};
let bookedMeta = {};
let bookingsPaused = <?php echo $initialBookingsPaused ? 'true' : 'false'; ?>;
let bookingPauseMessage = <?php echo json_encode($initialBookingPauseMessage); ?>;
const slotLabels = { day: 'Day Tour', overnight: 'Overnight Stay', '22hour': '22-Hour Stay', whole: 'Whole Day Blocked', blocked: 'Blocked' };
const slotTimes = { day: '9:00 AM - 5:00 PM', overnight: '7:00 PM - 7:00 AM', '22hour': '9:00 AM - 7:00 AM', whole: 'Whole day unavailable', blocked: 'Unavailable' };
const stayStartMinutes = { day: 9 * 60, overnight: 19 * 60, '22hour': 9 * 60 };
let selectedCheckIn = '';
let selectedCheckOut = '';

const monthNames = [
    'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE',
    'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'
];

const calendarGrid = document.getElementById('calendarGrid');
const currentMonthText = document.getElementById('currentMonth');
const prevMonthBtn = document.getElementById('prevMonth');
const nextMonthBtn = document.getElementById('nextMonth');
const displayCheckIn = document.getElementById('displayCheckIn');
const displayCheckOut = document.getElementById('displayCheckOut');
const inputCheckIn = document.getElementById('inputCheckIn');
const inputCheckOut = document.getElementById('inputCheckOut');
const reservationForm = document.getElementById('reservationForm');
const bookingWrapper = document.querySelector('.booking-wrapper');
const bookingPauseNotice = document.getElementById('bookingPauseNotice');
const bookingPauseMessageEl = document.getElementById('bookingPauseMessage');
const completeReservationBtn = reservationForm.querySelector('.btn-complete');

function clearSelectedDate() {
    selectedCheckIn = '';
    selectedCheckOut = '';
    inputCheckIn.value = '';
    inputCheckOut.value = '';
    displayCheckIn.textContent = 'Select a date';
    displayCheckOut.textContent = '--';
}

function setBookingPauseUi() {
    if (bookingWrapper) bookingWrapper.classList.toggle('booking-paused', bookingsPaused);
    if (bookingPauseNotice) bookingPauseNotice.hidden = !bookingsPaused;
    if (bookingPauseMessageEl) bookingPauseMessageEl.textContent = bookingPauseMessage;
    document.querySelectorAll('input[name="time_type"]').forEach(function(radio) {
        radio.disabled = bookingsPaused;
        if (bookingsPaused) radio.checked = false;
    });
    if (completeReservationBtn) {
        completeReservationBtn.disabled = bookingsPaused;
        completeReservationBtn.textContent = bookingsPaused ? 'BOOKINGS TEMPORARILY CLOSED' : 'COMPLETE RESERVATION';
    }
    if (bookingsPaused) clearSelectedDate();
}

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function prettyDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function getSelectedTimeType() {
    const selected = document.querySelector('input[name="time_type"]:checked');
    return selected ? selected.value : '';
}

function getNextDay(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    date.setDate(date.getDate() + 1);
    return formatDate(date);
}

function isPastDate(dateString) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const checkDate = new Date(dateString + 'T00:00:00');
    return checkDate < today;
}

function isToday(dateString) {
    return dateString === formatDate(new Date());
}

function hasStayStartPassedToday(dateString, timeType) {
    if (!isToday(dateString) || !stayStartMinutes.hasOwnProperty(timeType)) return false;
    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    return nowMinutes >= stayStartMinutes[timeType];
}

function getSameDayUnavailableMessage(timeType) {
    if (timeType === 'day') return 'Day Tour can no longer be booked today because its 9:00 AM start time has passed.';
    if (timeType === 'overnight') return 'Overnight Stay can no longer be booked today because its 7:00 PM start time has passed.';
    if (timeType === '22hour') return '22-Hour Stay can no longer be booked today because its 9:00 AM start time has passed.';
    return 'Selected stay type can no longer be booked today.';
}

function getSlotsForDate(dateString) {
    return bookedDates[dateString] || [];
}

function getMetaStayType(item) {
    return item.booking_time_type || item.slot || '';
}

function metaItemAffectsStayType(item, selectedType) {
    if (!selectedType) return true;

    const itemType = getMetaStayType(item);
    const itemSlot = item.slot || itemType;

    if (itemType === '22hour' || itemType === 'whole' || itemSlot === 'whole' || itemType === 'blocked') {
        return true;
    }

    if (selectedType === 'day') {
        return itemSlot === 'day' || itemType === 'day';
    }

    if (selectedType === 'overnight') {
        return itemSlot === 'overnight' || itemType === 'overnight';
    }

    if (selectedType === '22hour') {
        return itemSlot === 'day' || itemSlot === 'overnight' || itemType === 'day' || itemType === 'overnight';
    }

    return false;
}

function dateHasCheckoutDayBlock(dateString) {
    const metaItems = bookedMeta[dateString] || [];
    return metaItems.some(function(item) {
        const itemType = getMetaStayType(item);
        return item.is_blocked && (itemType === '22hour' || itemType === 'whole' || item.slot === '22hour' || item.slot === 'whole');
    });
}

function hasCheckoutDayBlock(dateString, timeType) {
    if (timeType !== 'overnight' && timeType !== '22hour') return false;
    return dateHasCheckoutDayBlock(getNextDay(dateString));
}

function getCheckoutDayBlockMessage(dateString) {
    return 'Blocked: Check-out date is unavailable (' + prettyDate(getNextDay(dateString)) + ')';
}

function isDateUnavailable(dateString, timeType) {
    if (bookingsPaused) return true;
    if (isPastDate(dateString)) return true;
    if (hasStayStartPassedToday(dateString, timeType)) return true;
    if (hasCheckoutDayBlock(dateString, timeType)) return true;

    const slots = getSlotsForDate(dateString);

    if (timeType === 'day') {
        return slots.includes('day');
    }

    if (timeType === 'overnight') {
        return slots.includes('overnight');
    }

    if (timeType === '22hour') {
        return slots.includes('day') || slots.includes('overnight');
    }

    return false;
}

function renderCalendar() {
    currentMonthText.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    calendarGrid.innerHTML = '';

    const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

    dayNames.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const selectedType = getSelectedTimeType();

    for (let i = 0; i < firstDay; i++) {
        const blank = document.createElement('div');
        blank.className = 'calendar-day empty';
        calendarGrid.appendChild(blank);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateObj = new Date(currentYear, currentMonth, day);
        const dateString = formatDate(dateObj);

        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.textContent = day;

        const metaItems = bookedMeta[dateString] || [];
        const visibleMetaItems = selectedType
            ? metaItems.filter(function(item) { return metaItemAffectsStayType(item, selectedType); })
            : metaItems;
        let hasAdminBlock = false;
        if (visibleMetaItems.length > 0) {
            hasAdminBlock = visibleMetaItems.some(function(item) { return item.is_blocked; });
            const infoLines = visibleMetaItems.map(function(item) {
                if (item.is_blocked) {
                    const t = getMetaStayType(item) || 'blocked';
                    return 'Blocked: ' + (slotLabels[t] || 'Unavailable') + ' - ' + (item.block_reason || 'No reason provided');
                }
                const t = getMetaStayType(item);
                return 'Booked: ' + (slotLabels[t] || t) + ' (' + (slotTimes[t] || 'time unavailable') + ')';
            });
            dayCell.title = infoLines.join('\n');
        }

        const past = isPastDate(dateString);
        const unavailable = bookingsPaused || (selectedType ? isDateUnavailable(dateString, selectedType) : past);

        if (past) {
            dayCell.classList.add('past');
        }

        if (bookingsPaused && !past) {
            dayCell.classList.add('booking-paused-day');
            dayCell.title = bookingPauseMessage;
        }

        if (hasAdminBlock && !past) {
            dayCell.classList.add('admin-blocked-day');
        }

        if (selectedType && hasCheckoutDayBlock(dateString, selectedType) && !past) {
            dayCell.classList.add('admin-blocked-day');
            dayCell.title = (dayCell.title ? dayCell.title + '\n' : '') + getCheckoutDayBlockMessage(dateString);
        }

        if (selectedType && hasStayStartPassedToday(dateString, selectedType)) {
            dayCell.title = (dayCell.title ? dayCell.title + '\n' : '') + getSameDayUnavailableMessage(selectedType);
        }

        if (unavailable) {
            dayCell.classList.add('booked');
        } else {
            dayCell.addEventListener('click', () => handleDateSelect(dateString));
        }

        if (dateString === selectedCheckIn || dateString === selectedCheckOut) {
            dayCell.classList.add('selected');
        }

        calendarGrid.appendChild(dayCell);
    }
}

function handleDateSelect(dateString) {
    if (bookingsPaused) {
        showPopup(bookingPauseMessage);
        return;
    }

    const timeType = getSelectedTimeType();

    if (!timeType) {
        showPopup('Please select a stay type first.');
        return;
    }

    if (isPastDate(dateString)) {
        showPopup('Past dates are not allowed.');
        return;
    }

    if (hasStayStartPassedToday(dateString, timeType)) {
        showPopup(getSameDayUnavailableMessage(timeType));
        return;
    }

    if (isDateUnavailable(dateString, timeType)) {
        showPopup('Selected schedule is unavailable.');
        return;
    }

    selectedCheckIn = dateString;
    selectedCheckOut = timeType === 'day' ? dateString : getNextDay(dateString);

    inputCheckIn.value = selectedCheckIn;
    inputCheckOut.value = selectedCheckOut;
    displayCheckIn.textContent = prettyDate(selectedCheckIn);
    displayCheckOut.textContent = prettyDate(selectedCheckOut);

    renderCalendar();
}

prevMonthBtn.addEventListener('click', () => {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    renderCalendar();
});

nextMonthBtn.addEventListener('click', () => {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    renderCalendar();
});

document.querySelectorAll('input[name="time_type"]').forEach(radio => {
    radio.addEventListener('change', () => {
        clearSelectedDate();
        renderCalendar();
    });
});

if (window.VillaAsync) {
    window.VillaAsync.renderCalendarSkeleton(calendarGrid, 42);
}

function applyBookedDateData(data) {
    if (!data) return;
    bookedDates = data.dates || {};
    bookedMeta = data.meta || {};
    const system = data.system || {};
    if (Object.prototype.hasOwnProperty.call(system, 'bookings_paused')) {
        bookingsPaused = system.bookings_paused === true || system.bookings_paused === '1';
    }
    if (system.booking_pause_message) {
        bookingPauseMessage = system.booking_pause_message;
    }
    setBookingPauseUi();
    renderCalendar();
}

function refreshBookedDates(force) {
    const bookedDateRequest = window.VillaAsync
        ? window.VillaAsync.cachedJson('../api/get_booked_dates.php', {}, {
            ttl: 15000,
            cacheKey: 'bookedDates:public',
            force: !!force,
            revalidate: true,
            onUpdate: applyBookedDateData
        })
        : fetch('../api/get_booked_dates.php').then(response => response.json());

    return bookedDateRequest
        .then(applyBookedDateData)
        .catch(error => {
            console.error('Error fetching booked dates:', error);
            bookedDates = {};
            renderCalendar();
        })
        .finally(() => {
            calendarGrid.classList.remove('is-loading');
        });
}

setBookingPauseUi();
refreshBookedDates(bookingsPaused);
setInterval(function() {
    if (document.visibilityState === 'visible') {
        refreshBookedDates(true);
    }
}, 10000);

if (window.VillaAsync) {
    window.VillaAsync.onSync(function() {
        refreshBookedDates(true);
    });
}

reservationForm.addEventListener('submit', function(e) {
    if (bookingsPaused) {
        e.preventDefault();
        showPopup(bookingPauseMessage);
        return;
    }

    if (!inputCheckIn.value || !inputCheckOut.value) {
        e.preventDefault();
        showPopup('Please select your stay type and date first.');
        return;
    }

    const timeType = getSelectedTimeType();
    if (hasStayStartPassedToday(inputCheckIn.value, timeType)) {
        e.preventDefault();
        showPopup(getSameDayUnavailableMessage(timeType));
    }
});

function showPopup(message) {
    document.getElementById('popupMessage').innerText = message;
    document.getElementById('customPopup').style.display = 'flex';
}

function closePopup() {
    document.getElementById('customPopup').style.display = 'none';
}
</script>

<div id="customPopup" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
    z-index:9999;
">
    <div style="
        background:#F5F3EF;
        padding:30px;
        border-radius:10px;
        text-align:center;
        width:300px;
        box-shadow:0 10px 30px rgba(0,0,0,0.2);
    ">
        <p id="popupMessage"></p>
        <button onclick="closePopup()" style="
            padding:10px 20px;
            border:1px solid #6B8E6B;
            background:none;
            color:#6B8E6B;
            cursor:pointer;
        ">OK</button>
    </div>
</div>
