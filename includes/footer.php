<?php
// footer settings values
if (!isset($conn)) {
    $dbPath = __DIR__ . '/db.php';
    if (file_exists($dbPath)) include $dbPath;
}
require_once __DIR__ . '/capstone2_features.php';
if (isset($conn)) {
    ve_ensure_capstone2_schema($conn);
    $footerPhone = ve_setting($conn, 'contact_phone', '+63 912 345 6789');
    $footerEmail = ve_setting($conn, 'contact_email', 'info@villaeusebio.com');
    $footerAddress = ve_setting($conn, 'contact_address', 'Lot 6, Darvin Street, Brookside Hills Subdivision Area 15 Blk 4, Antipolo 1800 Rizal');
    $footerBio = ve_setting($conn, 'bio_text', 'A nature-inspired sanctuary where understated luxury meets tranquil comfort. Creating cherished memories in the hills of Antipolo.');
    $footerFacebook = ve_setting($conn, 'facebook_link', 'https://www.facebook.com/villa.eusebioresort');
    $footerInstagram = ve_setting($conn, 'instagram_link', 'https://www.instagram.com/villaeusebioprivateresort/');
} else {
    $footerPhone = '+63 912 345 6789';
    $footerEmail = 'info@villaeusebio.com';
    $footerAddress = 'Lot 6, Darvin Street, Brookside Hills Subdivision Area 15 Blk 4, Antipolo 1800 Rizal';
    $footerBio = 'A nature-inspired sanctuary where understated luxury meets tranquil comfort. Creating cherished memories in the hills of Antipolo.';
    $footerFacebook = 'https://www.facebook.com/villa.eusebioresort';
    $footerInstagram = 'https://www.instagram.com/villaeusebioprivateresort/';
}
?>
<footer class="site-footer-custom">
    <div class="footer-overlay"></div>

    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-col footer-brand-col">
                <div class="footer-brand">
                    <div class="footer-brand-icon"></div>
                    <div>
                        <h3>Villa Eusebio</h3>
                        <p>Antipolo Sanctuary</p>
                    </div>
                </div>

                <p class="footer-description">
                    <?php echo nl2br(htmlspecialchars($footerBio)); ?>
                </p>

                <div class="footer-est">Est. 2024</div>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="/capstone_system/pages/appointment.php">Calendar</a></li>
                    <li><a href="/capstone_system/index.php#amenities">Amenities</a></li>
                    <li><a href="/capstone_system/pages/gallery.php">Gallery</a></li>
                    <li><a href="/capstone_system/pages/reviews.php">Reviews</a></li>
                    <li><a href="/capstone_system/pages/contact.php">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact Us</h4>
                <div class="footer-contact-list">
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">&#9742;</span>
                        <span><?php echo htmlspecialchars($footerPhone); ?></span>
                    </div>

                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">&#9993;</span>
                        <span><?php echo htmlspecialchars($footerEmail); ?></span>
                    </div>

                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">&#9906;</span>
                        <span><?php echo htmlspecialchars($footerAddress); ?></span>
                    </div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Connect</h4>

                <div class="footer-socials">
                    <a href="<?php echo htmlspecialchars($footerFacebook); ?>" class="footer-social" target="_blank" rel="noopener noreferrer">f</a>

                    <a href="<?php echo htmlspecialchars($footerInstagram); ?>" class="footer-social" target="_blank" rel="noopener noreferrer">&#9678;</a>
                </div>

                <p class="footer-connect-text">Share your moments with us</p>
                <div class="footer-hashtag">#VillaEusebio</div>
                <button type="button" class="footer-assist-btn" onclick="openChatbot()">
                    <span>&#9743;</span>
                    <strong>Need Assistance?</strong>
                    <em>Ask a question</em>
                </button>
            </div>
        </div>

        <div class="footer-loop">
            <div class="footer-loop-copy">
                <span>&#10087;</span>
                <div>
                    <strong>Stay in the loop</strong>
                    <p>Get updates, special offers, and resort news.</p>
                </div>
            </div>
            <form class="footer-subscribe" action="/capstone_system/api/subscribe.php" method="POST" onsubmit="handleFooterSubscribe(event, this);">
                <div class="footer-subscribe-control">
                    <input type="email" name="email" placeholder="Your email address" required>
                    <button type="submit">Subscribe</button>
                </div>
                <small class="footer-subscribe-message" aria-live="polite"></small>
            </form>
            <div class="footer-service-strip">
                <div><span>&#10087;</span><strong>Nature Inspired</strong><small>Surrounded by lush greenery</small></div>
                <div><span>&#10003;</span><strong>Safe &amp; Secure</strong><small>Your comfort and safety, our priority</small></div>
                <div><span>&#9743;</span><strong>Personalized Service</strong><small>Genuine Filipino hospitality</small></div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2026 Villa Eusebio. All rights reserved.</p>
            <div class="footer-legal-links">
                <a href="/capstone_system/pages/privacy-policy.php">Privacy Policy</a>
                <a href="/capstone_system/pages/terms-of-use.php">Terms of Use</a>
            </div>
            <small>Crafted with care for your comfort.</small>
        </div>
    </div>
</footer>

<div id="chatbotToggle" class="chatbot-toggle" role="button" tabindex="0" aria-label="Open chat assistant">
    <span aria-hidden="true"></span>
</div>

<div id="chatbotBackdrop" class="chatbot-backdrop" onclick="closeChatbot()"></div>

<div id="chatbotBox" class="chatbot-box">
    <div class="chatbot-header">
        <div class="chatbot-header-left">
            <div class="chatbot-avatar"></div>
            <div class="chatbot-brand">
                <h3>Villa Eusebio</h3>
                <p>CONCIERGE SERVICE</p>
                <small>We're here to help make your stay unforgettable.</small>
            </div>
        </div>

        <div class="chatbot-header-actions">
            <button type="button" onclick="closeChatbot()">&times;</button>
        </div>
    </div>

    <div id="chatbotBody" class="chatbot-body">
        <div id="chatbotMessages" class="chatbot-messages">
            <div class="chat-message bot">
                <div class="chat-bubble">
                    Hello! Welcome to Villa Eusebio.<br>
                    How can I help you today?
                    <small><?php echo date("g:i A"); ?></small>
                </div>
            </div>
        </div>

        <div class="chat-quick-wrap">
            <div class="chat-quick-title"><span></span><button type="button" class="quick-minimize-btn" onclick="toggleQuickQuestions()">+</button></div>
            <div class="chat-quick-grid quick-hidden" id="chatQuickGrid">
                <button type="button" onclick="sendQuickReply('Booking procedure')"><span>&#10003;</span> Booking steps</button>
                <button type="button" onclick="sendQuickReply('Check availability')"><span>&#128197;</span> Check availability</button>
                <button type="button" onclick="sendQuickReply('Rates and packages')"><span>&#9671;</span> Rates</button>
                <button type="button" onclick="sendQuickReply('Payment methods')"><span>&#8369;</span> Payment</button>
                <button type="button" onclick="sendQuickReply('Amenities')"><span>&#9962;</span> Amenities</button>
                <button type="button" onclick="sendQuickReply('House rules')"><span>&#8505;</span> House rules</button>
            </div>
        </div>

        <div class="chatbot-contact-box">
            <strong>Other ways to reach us</strong>
            <p><span>&#9742;</span><?php echo htmlspecialchars($footerPhone); ?></p>
            <p><span>&#128172;</span>Facebook @VillaEusebio</p>
        </div>
        <div class="chatbot-input-wrap">
            <input type="text" id="chatbotInput" placeholder="Type your message..." onkeypress="handleChatKey(event)">
            <button type="button" id="chatbotSendButton" aria-label="Send message">&#10148;</button>
        </div>
        <div class="chatbot-secure-note"><span>&#128274;</span> Your conversations are private and secure.</div>
    </div>
</div>

<script>
let chatbotMinimized = false;
let chatbotCloseTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    const sendButton = document.getElementById('chatbotSendButton');
    if (sendButton) {
        sendButton.addEventListener('click', function(event) {
            event.preventDefault();
            sendChatMessage();
        });
    }
});

function handleFooterSubscribe(event, form) {
    event.preventDefault();
    const message = form.querySelector('.footer-subscribe-message');
    const button = form.querySelector('button');
    const formData = new FormData(form);

    if (message) {
        message.innerHTML = window.VillaAsync ? window.VillaAsync.skeletonMarkup(1) : 'Saving...';
        message.className = 'footer-subscribe-message';
    }
    if (button) button.disabled = true;

    const subscribeRequest = window.VillaAsync
        ? window.VillaAsync.postFormJson(form)
        : fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'fetch'
            }
        }).then(response => response.json());

    subscribeRequest
        .then(data => {
            form.classList.toggle('subscribed', !!data.ok);
            if (message) {
                message.className = 'footer-subscribe-message';
                message.textContent = data.message || (data.ok ? 'Subscribed successfully.' : 'Please try again.');
                message.classList.toggle('is-error', !data.ok);
            }
            if (data.ok) form.reset();
        })
        .catch(() => {
            if (message) {
                message.textContent = 'Please try again.';
                message.classList.add('is-error');
            }
        })
        .finally(() => {
            if (button) button.disabled = false;
        });
}

function openChatbot() {
    const chatbotBox = document.getElementById('chatbotBox');
    const chatbotBody = document.getElementById('chatbotBody');
    const chatbotBackdrop = document.getElementById('chatbotBackdrop');
    const chatbotToggle = document.getElementById('chatbotToggle');
    if (chatbotCloseTimer) {
        window.clearTimeout(chatbotCloseTimer);
        chatbotCloseTimer = null;
    }
    chatbotBox.classList.remove('closing');
    chatbotBox.style.display = 'flex';
    chatbotBox.classList.remove('minimized');
    requestAnimationFrame(() => chatbotBox.classList.add('open'));
    chatbotBody.style.display = 'flex';
    chatbotMinimized = false;
    chatbotBackdrop.style.display = 'block';
    requestAnimationFrame(() => chatbotBackdrop.classList.add('show'));
    chatbotToggle.classList.add('is-hidden');
    scrollChatToBottom();
}

function closeChatbot() {
    const chatbotBox = document.getElementById('chatbotBox');
    const chatbotBackdrop = document.getElementById('chatbotBackdrop');
    const chatbotToggle = document.getElementById('chatbotToggle');
    chatbotBox.classList.remove('open');
    chatbotBox.classList.add('closing');
    chatbotBackdrop.classList.remove('show');
    chatbotCloseTimer = window.setTimeout(function() {
        chatbotBox.style.display = 'none';
        chatbotBox.classList.remove('closing');
        chatbotBackdrop.style.display = 'none';
        chatbotToggle.classList.remove('is-hidden');
        chatbotCloseTimer = null;
    }, 220);
}

function minimizeChatbot() {
    const chatbotBox = document.getElementById('chatbotBox');
    const chatbotBody = document.getElementById('chatbotBody');
    if (chatbotMinimized) {
        chatbotBox.classList.remove('minimized');
        chatbotBody.style.display = 'flex';
        chatbotMinimized = false;
        scrollChatToBottom();
    } else {
        chatbotBox.classList.add('minimized');
        chatbotBody.style.display = 'none';
        chatbotMinimized = true;
    }
}

function toggleQuickQuestions() {
    const grid = document.getElementById('chatQuickGrid');
    const btn = document.querySelector('.quick-minimize-btn');
    if (!grid) return;
    grid.classList.toggle('quick-hidden');
    if (btn) btn.textContent = grid.classList.contains('quick-hidden') ? '+' : '-';
}

function handleChatKey(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        sendChatMessage();
    }
}

function scrollChatToBottom() {
    const messages = document.getElementById('chatbotMessages');
    setTimeout(() => { messages.scrollTop = messages.scrollHeight; }, 50);
}

function getCurrentTime() {
    const now = new Date();
    return now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function addMessage(sender, text) {
    const messages = document.getElementById('chatbotMessages');
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message ' + sender;
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    const safeText = sender === 'user' ? escapeChatHtml(text) : text;
    bubble.innerHTML = safeText + '<small>' + getCurrentTime() + '</small>';
    wrapper.appendChild(bubble);
    messages.appendChild(wrapper);
    scrollChatToBottom();
}

function escapeChatHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text || '');
    return div.innerHTML;
}

function showTypingIndicator() {
    const messages = document.getElementById('chatbotMessages');
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message bot';
    wrapper.id = 'typingIndicator';
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble typing-bubble ve-placeholder-bubble';
    bubble.innerHTML = window.VillaAsync ? window.VillaAsync.skeletonMarkup(3, 'chat-typing-skeleton') : '<span class="ve-skeleton-bars"><span class="ve-skeleton-line"></span><span class="ve-skeleton-line"></span></span>';
    wrapper.appendChild(bubble);
    messages.appendChild(wrapper);
    scrollChatToBottom();
}

function removeTypingIndicator() {
    const typing = document.getElementById('typingIndicator');
    if (typing) typing.remove();
}

const villaChatbotInfo = {
    phone: <?php echo json_encode(htmlspecialchars($footerPhone, ENT_QUOTES, 'UTF-8')); ?>,
    email: <?php echo json_encode(htmlspecialchars($footerEmail, ENT_QUOTES, 'UTF-8')); ?>,
    address: <?php echo json_encode(htmlspecialchars($footerAddress, ENT_QUOTES, 'UTF-8')); ?>
};

const botLinkStyle = 'color:#6B8E6B; text-decoration:underline; font-weight:700;';

function botLink(href, label) {
    return '<a href="' + href + '" style="' + botLinkStyle + '">' + label + '</a>';
}

function normalizeBotText(message) {
    return String(message || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function escapeBotRegex(text) {
    return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function botMessageHasAny(message, terms) {
    return terms.some(function(term) {
        if (term.length <= 3 && !term.includes(' ') && !term.includes('-')) {
            return new RegExp('(^|\\s)' + escapeBotRegex(term) + '(?=\\s|$)').test(message);
        }
        return message.includes(term);
    });
}

function getBotReply(message) {
    const msg = normalizeBotText(message);
    const calendarLink = botLink('/capstone_system/pages/appointment.php', 'Calendar page');
    const contactLink = botLink('/capstone_system/pages/contact.php', 'Contact page');
    const galleryLink = botLink('/capstone_system/pages/gallery.php', 'Gallery page');
    const reviewsLink = botLink('/capstone_system/pages/reviews.php', 'Reviews page');

    if (msg === '') {
        return 'You can ask me about booking steps, available dates, rates, payment methods, amenities, guest count, house rules, location, or contact details.';
    }

    if (botMessageHasAny(msg, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'kamusta'])) {
        return 'Hello! Welcome to Villa Eusebio. I can help you check dates, understand the booking process, view rates, payment options, amenities, house rules, and contact details.';
    }

    if (botMessageHasAny(msg, ['thank', 'thanks', 'salamat', 'appreciate'])) {
        return 'You are welcome! I am happy to help with anything about Villa Eusebio or your reservation.';
    }

    if (botMessageHasAny(msg, ['bye', 'goodbye', 'see you'])) {
        return 'Thank you for visiting Villa Eusebio online. We hope to welcome you soon!';
    }

    if (botMessageHasAny(msg, ['staff', 'owner', 'agent', 'human', 'person', 'talk to someone', 'message you', 'call you'])) {
        return 'For direct assistance, you may contact us here:<br>&bull; Phone: ' + villaChatbotInfo.phone + '<br>&bull; Email: ' + villaChatbotInfo.email + '<br><br>You can also use the ' + contactLink + ' for more contact details.';
    }

    if (botMessageHasAny(msg, ['procedure', 'process', 'steps', 'how to book', 'how can i book', 'how do i book', 'book online', 'make a booking', 'reserve a date', 'reservation process'])) {
        return 'Here is the booking procedure:<br>1. Go to the ' + calendarLink + '.<br>2. Choose your stay type: Day Tour, Overnight Stay, or 22-Hour Stay.<br>3. Select an available date.<br>4. Fill in your guest information and special requests.<br>5. Choose a payment method and upload the required proof or valid ID.<br>6. Submit the booking request and wait for admin approval.';
    }

    if (botMessageHasAny(msg, ['today', 'same day', 'same-day', 'book today', 'walk in', 'walk-in', 'right now'])) {
        return 'Same-day booking depends on the current time:<br>&bull; Day Tour starts at 9:00 AM, so it cannot be booked today after 9:00 AM.<br>&bull; Overnight Stay starts at 7:00 PM, so it cannot be booked today after 7:00 PM.<br>&bull; 22-Hour Stay starts at 9:00 AM, so it cannot be booked today after 9:00 AM.<br><br>The calendar will automatically disable same-day options once their start time has passed.';
    }

    if (botMessageHasAny(msg, ['price', 'pricing', 'rate', 'rates', 'cost', 'how much', 'package', 'packages', 'fee'])) {
        return 'Our rates are:<br>&bull; Day Tour: PHP 7,000 (9:00 AM - 5:00 PM)<br>&bull; Overnight Stay: PHP 10,000 (7:00 PM - 7:00 AM next day)<br>&bull; 22-Hour Stay: PHP 13,000 (9:00 AM - 7:00 AM next day)<br><br>A PHP 2,000 reservation fee is required. Guests above 30 have an additional PHP 150 per extra guest.';
    }

    if (botMessageHasAny(msg, ['payment', 'pay', 'gcash', 'bdo', 'unionbank', 'bank', 'cash', 'proof', 'receipt', 'valid id', 'reservation fee', 'down payment', 'downpayment'])) {
        return 'Available payment methods are GCash, BDO Bank Transfer, UnionBank, and Cash Payment.<br><br>A PHP 2,000 reservation fee is required to reserve the selected date. For online payments, upload payment proof. For cash payment, upload a valid legal ID before submitting the booking request.';
    }

    if (botMessageHasAny(msg, ['guest', 'guests', 'pax', 'head', 'heads', 'capacity', 'people', 'person', 'room', 'extra'])) {
        return 'Guest guide:<br>&bull; Each room can fit around 10 to 15 people.<br>&bull; The resort booking limit is up to 50 guests.<br>&bull; Guests above 30 have an added fee of PHP 150 per extra guest.';
    }

    if (botMessageHasAny(msg, ['check in', 'check-in', 'checkout', 'check out', 'time', 'duration', 'day tour', 'overnight', '22 hour', '22-hour'])) {
        return 'Stay schedules:<br>&bull; Day Tour: 9:00 AM - 5:00 PM<br>&bull; Overnight Stay: 7:00 PM - 7:00 AM next day<br>&bull; 22-Hour Stay: 9:00 AM - 7:00 AM next day';
    }

    if (botMessageHasAny(msg, ['available', 'availability', 'calendar', 'date', 'dates', 'schedule', 'slot', 'vacant', 'book', 'booking', 'reservation'])) {
        return 'You can check available dates on our ' + calendarLink + '.<br><br>Choose your stay type first, then the calendar will show which dates are available, booked, blocked, or no longer available for same-day booking.';
    }

    if (botMessageHasAny(msg, ['cancel', 'cancellation', 'refund', 'refundable', 'reschedule', 'change date', 'move date'])) {
        return 'Please review your details carefully before submitting. The booking page warns that once a booking request is submitted and confirmed, it is not eligible for cancellation or refund. For date changes or special cases, contact the resort directly so the admin can assist you.';
    }

    if (botMessageHasAny(msg, ['rules', 'policy', 'policies', 'allowed', 'not allowed', 'reminder', 'note'])) {
        return 'Important reminders:<br>&bull; Bookings are subject to admin approval.<br>&bull; A PHP 2,000 reservation fee is required.<br>&bull; Once confirmed, bookings are not eligible for cancellation or refund.<br>&bull; Use the special request field if you need to tell the owner about add-ons or other concerns.';
    }

    if (botMessageHasAny(msg, ['special request', 'request', 'add on', 'add-on', 'addons', 'notes', 'pillow', 'blanket'])) {
        return 'You can type special requests in the Notes or Special Requests field on the booking form. The admin will see it together with your reservation details.';
    }

    if (botMessageHasAny(msg, ['amenities', 'amenity', 'karaoke', 'wifi', 'wi-fi', 'pool', 'parking', 'kitchen', 'stove', 'grill', 'pet', 'pets', 'chiller', 'shower', 'water dispenser'])) {
        return 'Amenities include Karaoke, parking slot, gas stove or char-grill, WiFi connection, outdoor shower, kitchen wares, pet-friendly accommodation, chiller, water dispenser, and Suraya Room.';
    }

    if (botMessageHasAny(msg, ['location', 'address', 'where', 'map', 'direction', 'directions', 'antipolo'])) {
        return 'Villa Eusebio is located at ' + villaChatbotInfo.address + '.<br><br>You can also visit the ' + contactLink + ' for map and contact details.';
    }

    if (botMessageHasAny(msg, ['contact', 'phone', 'email', 'number', 'facebook', 'fb', 'instagram', 'ig', 'social'])) {
        return 'You can contact Villa Eusebio here:<br>&bull; Phone: ' + villaChatbotInfo.phone + '<br>&bull; Email: ' + villaChatbotInfo.email + '<br>&bull; Facebook: @VillaEusebio<br><br>More details are available on the ' + contactLink + '.';
    }

    if (botMessageHasAny(msg, ['review', 'reviews', 'rating', 'ratings', 'feedback'])) {
        return 'You can read guest feedback on our ' + reviewsLink + '. Reviews help future guests know what to expect before booking.';
    }

    if (botMessageHasAny(msg, ['photo', 'photos', 'picture', 'pictures', 'gallery', 'image', 'images'])) {
        return 'You can view resort photos on our ' + galleryLink + '. It is a good place to check the rooms, amenities, and overall resort feel before booking.';
    }

    return 'I can help with booking procedures, available dates, rates, payment methods, same-day booking rules, guest capacity, amenities, house rules, location, contact details, gallery, and reviews. Try asking something like "How do I book?", "How much is overnight?", or "Can I book today?"';
}

function sendChatMessage() {
    const input = document.getElementById('chatbotInput');
    const text = input.value.trim();
    if (text === '') return;
    addMessage('user', text);
    input.value = '';
    input.focus();
    showTypingIndicator();
    setTimeout(() => {
        removeTypingIndicator();
        addMessage('bot', getBotReply(text));
    }, 700);
}

function sendQuickReply(text) {
    addMessage('user', text);
    showTypingIndicator();
    setTimeout(() => {
        removeTypingIndicator();
        addMessage('bot', getBotReply(text));
    }, 700);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeChatbot();
});

window.addEventListener('load', scrollChatToBottom);
window.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('chatbotToggle');
    if (toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            openChatbot();
        });
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openChatbot();
            }
        });
    }
});
</script>

</body>
</html>



