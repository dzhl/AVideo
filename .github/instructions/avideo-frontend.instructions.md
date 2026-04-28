---
name: "AVideo Frontend"
description: "Use when writing or reviewing JavaScript, HTML, or CSS in AVideo. Covers Bootstrap 5 usage, jQuery patterns, Video.js integration, AJAX conventions, modal/toast/alert patterns, and frontend code reuse."
applyTo: "{**/*.js,**/*.html,view/**,plugin/**/view/**,plugin/**/page/**}"
---

# AVideo Frontend Guidelines

## Core Principle: Reuse Before Writing

Before adding any frontend code, search for existing utilities:
- `view/js/script.js` — global modal, toast, alert, player, session utilities
- `view/js/ajaxLoad.js` — AJAX loading patterns
- `view/js/form2JSON.js` — form serialization
- `view/js/session.js` — session management
- `view/js/navbarLogged.js` — logged-in navbar utilities

Do not introduce new frontend frameworks (React, Vue, Angular, etc.) unless explicitly requested.

---

## Framework Stack

| Library | Version | Purpose |
|---|---|---|
| Bootstrap | 5.3.8 | Layout, components, utilities |
| jQuery | 4.0.0 | DOM manipulation, AJAX |
| Video.js | 8.23.7 | Video player |
| HLS.js | 1.6.16 | HLS streaming |
| Socket.io | 4.8.3 | Real-time communication |
| Chart.js | 4.5.1 | Analytics charts |
| TinyMCE | 8.4.0 | Rich text editing |
| Select2 | — | Enhanced select dropdowns |
| Flatpickr | 4.6.13 | Date/time pickers |
| jQuery UI | 1.14.2 | Drag/sort, UI widgets |
| Intro.js | 8.3.2 | Onboarding tutorials |
| Croppie | 2.6.5 | Image cropping |

These are already loaded. Do not `npm install` additional packages without explicit approval.

---

## Loading Indicators

Always wrap async operations with the global `modal` object:

```javascript
modal.showPleaseWait();       // show full-screen spinner

// ... async work ...

modal.hidePleaseWait();       // hide spinner
```

Never implement a custom loading spinner — use `modal.showPleaseWait()`.

---

## User Feedback (Toasts & Alerts)

Use the global AVideo feedback functions — do not use `alert()` or custom notification code:

```javascript
// Success notification (green toast, auto-dismiss)
avideoToastSuccess('Video saved successfully.');

// Error notification (red toast, auto-dismiss)
avideoToastError('Failed to save. Please try again.');

// Error alert modal (requires user to dismiss)
avideoAlertError('You do not have permission to perform this action.');
```

---

## AJAX Pattern

Standard pattern using jQuery AJAX with `modal` feedback:

```javascript
modal.showPleaseWait();
$.ajax({
    type: 'POST',
    url: webSiteRootURL + 'objects/myAction.json.php',
    data: {
        action: 'save',
        videos_id: videoId,
        value: myValue
    },
    success: function (response) {
        if (response.error) {
            avideoAlertError(response.msg);
        } else {
            avideoToastSuccess(response.msg || 'Done');
        }
    },
    error: function () {
        avideoToastError('Request failed. Please check your connection.');
    },
    complete: function () {
        modal.hidePleaseWait();
    }
});
```

Global variables available in page context (set by `view/js/script.js`):
- `webSiteRootURL` — site URL with trailing slash
- `_serverTime` — server Unix timestamp
- `timezone` — user timezone string
- `player` — Video.js player instance
- `mediaId` — current media ID
- `isDebuging` — debug mode flag
- `userLang` — browser language

---

## Bootstrap 5 Conventions

- Use Bootstrap 5.3 utility classes and components — do not write custom CSS for layout/spacing unless unavoidable
- Use `btn btn-primary`, `btn btn-secondary`, `btn btn-danger` etc. for buttons
- Use `modal`, `offcanvas`, `toast`, `alert` Bootstrap components for UI patterns
- Use `d-none` / `d-flex` / `d-block` for visibility toggles — not inline `style="display:none"`
- Use `col-md-*`, `col-sm-*`, `col-12` responsive grid — do not use fixed pixel widths
- Use Bootstrap form classes: `form-control`, `form-select`, `form-check`
- Dark theme support: respect existing theme variable patterns; do not hardcode colors

---

## jQuery Patterns

```javascript
// Document ready
$(document).ready(function () {
    // initialization
});

// Event delegation (for dynamically added elements)
$(document).on('click', '.my-button', function () {
    var id = $(this).data('id');
    // ...
});

// Serialize form data
var formData = $('#myForm').serialize();

// Show/hide with Bootstrap class toggle
$('#myElement').toggleClass('d-none');
```

Use jQuery 4.0 API. Do not use deprecated jQuery methods (e.g., `$.parseJSON`, `$.type`, `.live()`, `.die()`).

---

## Video.js

Use the existing `player` global — do not initialize a second Video.js instance on pages where one already exists:

```javascript
// Access the existing player
if (typeof player !== 'undefined' && player) {
    player.play();
    player.pause();
    player.currentTime(0);
}
```

For plugin-added video features, use Video.js plugin API:
```javascript
videojs.registerPlugin('myPluginFeature', function (options) {
    // this = player instance
});
```

---

## Modals

Use Bootstrap 5 modal markup — do not use jQuery UI dialogs or custom modal implementations:

```html
<div class="modal fade" id="myModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Title</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- content -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
```

Open/close modals programmatically:
```javascript
var modal = new bootstrap.Modal(document.getElementById('myModal'));
modal.show();
modal.hide();
```

---

## Forms

- Use `form2JSON.js` utilities for serializing forms to JSON before AJAX submission
- Validate required fields client-side with Bootstrap's validation classes (`is-invalid`, `invalid-feedback`)
- Always validate server-side as well — client validation is UX only
- Use `<input type="hidden">` for passing IDs or tokens that should not be visible

---

## Internationalization

AVideo uses server-side locale files in `locale/`. For frontend strings:
- Do not hardcode English-only strings in JavaScript if they are user-facing
- Prefer passing translated strings from PHP into JS via data attributes or inline variables
- Check `locale/` before adding new translation keys

---

## CSS / Styling

- Prefer Bootstrap 5 utility classes over custom CSS
- Add custom CSS only in the correct plugin or view CSS file — do not embed `<style>` blocks inline in PHP views
- Use CSS custom properties (`--var`) where theming is needed
- Avoid `!important` unless overriding a third-party library

---

## Do

- Use `modal.showPleaseWait()` / `modal.hidePleaseWait()` for all async operations
- Use `avideoToastSuccess()`, `avideoToastError()`, `avideoAlertError()` for user messages
- Use Bootstrap 5 components — modals, toasts, dropdowns
- Use `webSiteRootURL` for AJAX endpoint URLs
- Use jQuery 4.0 patterns (no deprecated methods)
- Reuse existing JS utilities from `view/js/` before writing new ones

## Do Not

- Use `alert()`, `confirm()`, or `prompt()` natively
- Introduce React, Vue, or Angular
- Write custom CSS for things Bootstrap utilities already handle
- Hardcode absolute URLs — use `webSiteRootURL`
- Use jQuery deprecated methods (`.live()`, `.die()`, `$.parseJSON`)
- Initialize a new Video.js player on a page that already has one
- Import CDN resources — all libraries are bundled locally
