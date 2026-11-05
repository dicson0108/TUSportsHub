<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$current_user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$is_manager_route = str_contains($script_name, '/manager/');
$managed_clubs = $current_user ? get_user_managed_clubs($current_user['id']) : [];
$is_any_manager = !empty($managed_clubs) || ($current_user && $current_user['role'] === 'admin');

$css_map = [
    'index.php' => 'home.css',
    'sessions.php' => 'sessions.css',
    'session-detail.php' => 'session-detail.css',
    'clubs.php' => 'clubs.css',
    'club.php' => 'club.css',
    'my-activities.php' => 'my-activities.css',
    'payment.php' => 'payment.css',
    'cancel-registration.php' => 'payment.css',
    'upload-receipt.php' => 'payment.css',
    'evidence.php' => 'payment.css',
    'profile.php' => 'profile.css',
    'inbox.php' => 'inbox.css',
    'login.php' => 'auth.css',
    'register.php' => 'auth.css',
    'privacy.php' => 'legal.css',
    'terms.php' => 'legal.css',
    'participants.php' => 'participants.css',
    '403.php' => 'errors.css',
    '404.php' => 'errors.css',
    '500.php' => 'errors.css',
];
$page_css = $css_map[$current_page] ?? ($is_manager_route ? 'manager.css' : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . " · Taylor's SportsHub" : "Taylor's SportsHub · Student Sports & Club Sessions" ?></title>
    <link rel="icon" type="image/x-icon" href="<?= APP_BASE_PATH ?>/favicon.ico">
    <link rel="icon" type="image/webp" href="<?= APP_BASE_PATH ?>/assets/images/logo/logo.webp">
    <link rel="apple-touch-icon" href="<?= APP_BASE_PATH ?>/assets/images/logo/logo.webp">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/style.css">
    <?php if ($page_css && file_exists(__DIR__ . '/../assets/css/' . $page_css)): ?>
        <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/<?= e($page_css) ?>">
    <?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">
    <div class="header-inner">
        <a href="<?= APP_BASE_PATH ?>/index.php" class="brand-group" aria-label="Taylor's SportsHub Home">
            <?= get_site_logo_html() ?>
            <div class="brand-text">
                <span class="brand-title">Taylor's</span>
                <span class="brand-subtitle">SPORTS HUB</span>
            </div>
        </a>

        <nav class="nav-links" aria-label="Main Navigation">
            <a href="<?= APP_BASE_PATH ?>/index.php" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= APP_BASE_PATH ?>/sessions.php" class="nav-link <?= (!$is_manager_route && in_array($current_page, ['sessions.php', 'session-detail.php'])) ? 'active' : '' ?>">Explore Sessions</a>
            <a href="<?= APP_BASE_PATH ?>/clubs.php" class="nav-link <?= in_array($current_page, ['clubs.php', 'club.php']) ? 'active' : '' ?>">Clubs</a>
            <?php if ($current_user): ?>
                <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="nav-link <?= in_array($current_page, ['my-activities.php', 'payment.php', 'cancel-registration.php']) ? 'active' : '' ?>">My Activities</a>
            <?php endif; ?>
        </nav>

        <div class="nav-actions">
            <?php if ($current_user): ?>
                <?php if ($is_any_manager): ?>
                    <a href="<?= APP_BASE_PATH ?>/manager/sessions.php" class="btn-manage-club">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Manager workspace</span>
                    </a>
                <?php endif; ?>

                <a href="<?= APP_BASE_PATH ?>/inbox.php" class="nav-inbox-btn" title="Inbox sample preview" aria-label="Inbox sample preview">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </a>

                <div class="user-menu-pill" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false" aria-label="User menu for <?= e($current_user['name']) ?>">
                    <div class="avatar-initials"><?= get_initials($current_user['name']) ?></div>
                    <span class="user-name-text"><?= e($current_user['name']) ?></span>
                    <svg class="dropdown-arrow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>

                    <div class="user-dropdown" role="menu">
                        <div class="user-dropdown-header">
                            <strong><?= e($current_user['name']) ?></strong>
                            <span><?= e($current_user['email']) ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="<?= APP_BASE_PATH ?>/profile.php" class="dropdown-item" role="menuitem">My Profile</a>
                        <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="dropdown-item" role="menuitem">My Activities</a>
                        <a href="<?= APP_BASE_PATH ?>/inbox.php" class="dropdown-item" role="menuitem">Inbox (Preview)</a>

                        <div class="dropdown-divider"></div>
                        <form method="POST" action="<?= APP_BASE_PATH ?>/logout.php" style="margin: 0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger" role="menuitem" style="width:100%; border:0; background:none; font:inherit; text-align:left; cursor:pointer;">Sign out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-link">Log in</a>
                <a href="<?= APP_BASE_PATH ?>/register.php" class="btn-primary">Sign up</a>
            <?php endif; ?>

            <!-- Mobile Hamburger Toggle Button -->
            <button type="button" class="mobile-menu-btn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-navigation">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="mobile-drawer-backdrop" aria-hidden="true"></div>
<aside class="mobile-drawer" id="mobile-navigation" role="dialog" aria-modal="true" aria-label="Navigation" inert>
    <div class="mobile-drawer-header">
        <a href="<?= APP_BASE_PATH ?>/index.php" class="brand-group" aria-label="Taylor's SportsHub Home">
            <?= get_site_logo_html() ?>
            <div class="brand-text">
                <span class="brand-title">Taylor's</span>
                <span class="brand-subtitle">SPORTS HUB</span>
            </div>
        </a>
        <button type="button" class="mobile-drawer-close" aria-label="Close navigation menu">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="mobile-drawer-nav">
        <a href="<?= APP_BASE_PATH ?>/index.php" class="mobile-nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
        <a href="<?= APP_BASE_PATH ?>/sessions.php" class="mobile-nav-link <?= (!$is_manager_route && in_array($current_page, ['sessions.php', 'session-detail.php'])) ? 'active' : '' ?>">Explore Sessions</a>
        <a href="<?= APP_BASE_PATH ?>/clubs.php" class="mobile-nav-link <?= in_array($current_page, ['clubs.php', 'club.php']) ? 'active' : '' ?>">Sports Clubs</a>
        <?php if ($current_user): ?>
            <a href="<?= APP_BASE_PATH ?>/profile.php" class="mobile-nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">My Profile</a>
            <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="mobile-nav-link <?= in_array($current_page, ['my-activities.php', 'payment.php', 'cancel-registration.php']) ? 'active' : '' ?>">My Activities</a>
            <a href="<?= APP_BASE_PATH ?>/inbox.php" class="mobile-nav-link <?= $current_page === 'inbox.php' ? 'active' : '' ?>">Inbox (Preview)</a>
            <?php if ($is_any_manager): ?>
                <a href="<?= APP_BASE_PATH ?>/manager/sessions.php" class="mobile-nav-link manager-nav-link">Manager Workspace ↗</a>
            <?php endif; ?>
            <div class="mobile-drawer-footer">
                <div class="mobile-drawer-user-info">
                    Signed in as <strong><?= e($current_user['name']) ?></strong>
                </div>
                <form method="POST" action="<?= APP_BASE_PATH ?>/logout.php" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-secondary btn-full btn-danger-text">Log out</button>
                </form>
            </div>
        <?php else: ?>
            <div class="mobile-drawer-auth">
                <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-secondary btn-full">Log in</a>
                <a href="<?= APP_BASE_PATH ?>/register.php" class="btn-primary btn-full">Register</a>
            </div>
        <?php endif; ?>
    </nav>
</aside>

<main id="main-content" tabindex="-1">
