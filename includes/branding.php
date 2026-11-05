<?php
// Database-independent branding for normal pages and error recovery.
function get_site_logo_html(): string {
    static $logo_html = null;
    if ($logo_html !== null) {
        return $logo_html;
    }
    $logo_path = '/assets/images/logo/logo.webp';
    if (file_exists(__DIR__ . '/..' . $logo_path)) {
        $logo_html = '<img src="' . htmlspecialchars(app_url($logo_path), ENT_QUOTES, 'UTF-8') . '" alt="Taylor\'s University" class="brand-logo-img">';
    } else {
        $logo_html = '<div class="brand-logo-icon" aria-hidden="true">t</div>';
    }
    return $logo_html;
}

