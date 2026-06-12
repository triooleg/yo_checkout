# Promo GIF Tooltip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a configurable UIkit tooltip with a GIF preview for the green promo discount badge on product cards.

**Architecture:** Store the GIF URL in the existing plugin settings, expose it through `YOCheckout`, and enhance the existing frontend promo badge creation code to add a small green help button with `uk-tooltip` HTML content. Keep promo discount logic unchanged.

**Tech Stack:** WordPress plugin PHP settings, WordPress Media Library, UIkit tooltip utility, existing `assets/yo-checkout.js` and `assets/yo-checkout.css`.

---

### Task 1: Admin Setting

**Files:**
- Modify: `yoleotard-checkout-invoice.php`

- [ ] Add a `promo_tooltip_gif_url` default setting.
- [ ] Sanitize the setting as a URL with the existing settings flow.
- [ ] Add a field in the Promo tab with a Media Library picker button.
- [ ] Enqueue `wp_enqueue_media()` and a tiny inline admin script only on the plugin admin screen.

### Task 2: Frontend Tooltip

**Files:**
- Modify: `yoleotard-checkout-invoice.php`
- Modify: `assets/yo-checkout.js`
- Modify: `assets/yo-checkout.css`

- [ ] Localize `promoTooltipGifUrl` into `YOCheckout`.
- [ ] When promo is enabled and a GIF URL exists, add a small green promo help button next to each `.yo-promo-badge`.
- [ ] Use UIkit tooltip attributes with HTML content containing the GIF image.
- [ ] Support hover and tap by using a focusable button element.
- [ ] Keep the original green discount badge text and placement behavior intact.

### Task 3: Docs, Verification, Archive

**Files:**
- Modify: `CHANGELOG.txt`
- Modify: `DEVELOPMENT_LOG.md`
- Modify: `PLUGIN_MAP.md`
- Modify: `KNOWN_ISSUES.md`

- [ ] Document v4.0.60 as a test candidate.
- [ ] Run PHP syntax checks for the main file and all files under `includes/`.
- [ ] Run `node --check assets/yo-checkout.js`.
- [ ] Run `git diff --check`.
- [ ] Build `plugin-archives/yoleotard-checkout-invoice.zip` using Python `zipfile` with forward-slash archive names.
- [ ] Verify the ZIP has one top-level `yoleotard-checkout-invoice/` folder, no backslash entries, and real `assets/` and `includes/` directories after extraction.
