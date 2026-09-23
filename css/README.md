# CSS Organization

All project CSS now lives directly inside this `css` folder. The files are grouped by page or feature so you can quickly find what to edit.

## Entry Files

- `../style.css` loads desktop/default styles.
- `../responsive-fixes.css` loads mobile, tablet, zoom, and final sizing fixes.

## Main CSS Files

- `theme.css` - colors, variables, reset, body, fonts, scrollbars, shared helpers
- `components.css` - reusable buttons, modals, tooltips, and small UI pieces
- `customer-layout.css` - customer header, navigation, section titles, footer
- `homepage.css` - homepage hero, amenities section, visual stories preview, homepage sections
- `customer-calendar.css` - customer booking calendar, booking summary, validation, status pages
- `gallery.css` - gallery page, gallery cards, image viewer/effects
- `amenities.css` - amenities page
- `reviews.css` - reviews page, review cards, review form
- `contact.css` - contact page, map/location design, contact cards
- `chatbot.css` - automated customer chatbot

## Admin CSS Files

- `admin-login.css` - owner/admin login page
- `admin-layout.css` - admin shell, sidebar, popups, shared admin layout
- `admin-calendar.css` - admin booking calendar
- `admin-reservations-sales.css` - reservation table, sales record table, archive table
- `admin-dashboard.css` - modern admin dashboard cards, details panel, statistics
- `admin-announcements.css` - announcement page, homepage announcement bubble, announcement modal
- `admin-archive.css` - archive hub cards, archived rows, archive right-click menu
- `admin-settings.css` - settings page, undo button, gallery image admin editor
- `admin-buttons.css` - admin-only box-shaped buttons

## Responsive CSS Files

- `responsive-base.css` - global responsive stabilization
- `responsive-customer.css` - customer page responsive fixes
- `responsive-chatbot-payment.css` - chatbot/payment responsive sizing
- `responsive-admin.css` - admin responsive fixes
- `responsive-final-fixes.css` - latest responsive overrides

## Editing Rule

Edit the most specific file first. For example, gallery changes go in `gallery.css`, admin reservation table changes go in `admin-reservations-sales.css`, and mobile-only issues go in the matching `responsive-*.css` file.
