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

<div id="chatbotToggle" class="chatbot-toggle" onclick="openChatbot()">
    <span>&#128172;</span>
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
                <button type="button" onclick="sendQuickReply('Check availability')"><span>&#128197;</span> Check availability</button>
                <button type="button" onclick="sendQuickReply('Pricing information')"><span>&#9671;</span> Pricing information</button>
                <button type="button" onclick="sendQuickReply('Amenities')"><span>&#9962;</span> Amenities</button>
                <button type="button" onclick="sendQuickReply('Contact details')"><span>&#9906;</span> Contact details</button>
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
    bubble.innerHTML = text + '<small>' + getCurrentTime() + '</small>';
    wrapper.appendChild(bubble);
    messages.appendChild(wrapper);
    scrollChatToBottom();
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

function getBotReply(message) {
    const msg = message.toLowerCase();
    if (msg.includes('price') || msg.includes('pricing') || msg.includes('rate') || msg.includes('cost')) {
        return 'Our rates are:<br>&bull; Day Tour (9AM-5PM): PHP 7,000<br>&bull; Overnight Stay (7PM-7AM): PHP 10,000<br>&bull; 22-Hour Stay (9AM-7AM): PHP 13,000';
    }
    if (msg.includes('availability') || msg.includes('available') || msg.includes('book') || msg.includes('reservation')) {
        return 'You can check available dates on our <a href="/capstone_system/pages/appointment.php" style="color:#6B8E6B; text-decoration:underline;">Calendar page</a>.<br><br>Open the calendar, choose your stay type, then select an available date to continue booking.';
    }
    if (msg.includes('amenities') || msg.includes('karaoke') || msg.includes('wifi') || msg.includes('pool')) {
        return 'We offer Karaoke, Parking Slot, Gas Stove/Char-Grill, Wifi Connection, Outdoor Shower, Kitchen Wares, Pet-friendly, Chiller, Water Dispenser, and Suraya Room.';
    }
    if (msg.includes('contact') || msg.includes('phone') || msg.includes('email') || msg.includes('number')) {
        return <?php echo json_encode('You can contact us at:<br>Phone: ' . $footerPhone . '<br>Email: ' . $footerEmail); ?>;
    }
    if (msg.includes('location') || msg.includes('address') || msg.includes('where') || msg.includes('map')) {
        return <?php echo json_encode('We are located at ' . $footerAddress); ?>;
    }
    if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey')) {
        return 'Hello! I can help you with pricing, availability, amenities, contact details, location, and booking steps.';
    }
    if (msg.includes('thank')) {
        return 'You are welcome! Let me know if you need anything else.';
    }
    return 'I can help you with availability, pricing, amenities, contact details, location, and booking steps. Try typing a keyword like pricing, booking, amenities, contact, or location.';
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
    }
});
</script>

</body>
</html>



