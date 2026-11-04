<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$current_user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$managed_clubs = $current_user ? get_user_managed_clubs($current_user['id']) : [];
$is_any_manager = !empty($managed_clubs) || ($current_user && $current_user['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . " · Taylor's SportsHub" : "Taylor's SportsHub · Student Sports & Club Sessions" ?></title>
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/style.css">
    <style>
        /* Apple-inspired fluid Navigation Bar */
        .site-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            transition: background 0.2s ease;
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-link {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #c8102e;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .brand-link span { color: #1d1d1f; font-weight: 600; }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .nav-item {
            text-decoration: none;
            color: #424245;
            font-size: 0.92rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        .nav-item:hover, .nav-item.active {
            color: #c8102e;
        }
        .nav-auth-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .btn-nav-login {
            text-decoration: none;
            color: #1d1d1f;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.45rem 0.95rem;
            border-radius: 980px;
            border: 1px solid #d2d2d7;
            background: #fff;
            transition: all 0.15s ease;
        }
        .btn-nav-login:hover { background: #f5f5f7; }
        .btn-nav-signup {
            text-decoration: none;
            color: #fff;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.45rem 1rem;
            border-radius: 980px;
            background: #c8102e;
            transition: background 0.15s ease;
        }
        .btn-nav-signup:hover { background: #a60d26; }
        .user-pill {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 980px;
            background: #f5f5f7;
            border: 1px solid #e5e5ea;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 500;
            color: #1d1d1f;
        }
        .user-avatar-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #c8102e;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            border: 1px solid rgba(0,0,0,0.08);
            width: 220px;
            padding: 0.5rem;
            z-index: 1001;
        }
        .user-pill:hover .user-dropdown-menu, .user-pill:focus-within .user-dropdown-menu {
            display: block;
        }
        .user-dropdown-header {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #f0f0f2;
            font-size: 0.82rem;
            color: #86868b;
        }
        .user-dropdown-header strong {
            display: block;
            color: #1d1d1f;
            font-size: 0.9rem;
            margin-bottom: 0.1rem;
        }
        .dropdown-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.75rem;
            color: #1d1d1f;
            text-decoration: none;
            font-size: 0.88rem;
            border-radius: 8px;
            transition: background 0.1s ease;
        }
        .dropdown-link:hover { background: #f5f5f7; }
        .dropdown-link.manager-link { color: #c8102e; font-weight: 600; }
        .dropdown-link.logout-btn {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }
        .dropdown-link.logout-btn:hover { background: #fff2f2; color: #c81e27; }
        
        /* Mobile menu button */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #1d1d1f;
        }
        @media (max-width: 820px) {
            .nav-links { display: none; }
            .mobile-menu-toggle { display: block; }
        }
    </style>
</head>
<body>

<header class="site-nav">
    <div class="nav-container">
        <a href="<?= APP_BASE_PATH ?>/index.php" class="brand-link" aria-label="Taylor's SportsHub home">
            <strong>Taylor's</strong> <span>SportsHub</span>
        </a>

        <nav class="nav-links" aria-label="Main Navigation">
            <a href="<?= APP_BASE_PATH ?>/index.php" class="nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= APP_BASE_PATH ?>/sessions.php" class="nav-item <?= in_array($current_page, ['sessions.php', 'session-detail.php']) ? 'active' : '' ?>">Explore Sessions</a>
            <a href="<?= APP_BASE_PATH ?>/clubs.php" class="nav-item <?= in_array($current_page, ['clubs.php', 'club.php']) ? 'active' : '' ?>">Clubs</a>
            <?php if ($current_user): ?>
                <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="nav-item <?= in_array($current_page, ['my-activities.php', 'payment.php', 'cancel-registration.php']) ? 'active' : '' ?>">My Activities</a>
                <a href="<?= APP_BASE_PATH ?>/inbox.php" class="nav-item <?= $current_page === 'inbox.php' ? 'active' : '' ?>">Inbox <span style="font-size: 0.75rem; background: #f0f0f2; color: #6e6e73; padding: 2px 6px; border-radius: 980px;">Preview</span></a>
                <?php if ($is_any_manager): ?>
                    <a href="<?= APP_BASE_PATH ?>/manager/sessions.php" class="nav-item <?= str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/manager/') ? 'active' : '' ?>" style="color: #c8102e; font-weight: 600;">Club Manager</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="nav-auth-actions">
            <?php if ($current_user): ?>
                <div class="user-pill" tabindex="0">
                    <div class="user-avatar-badge"><?= get_initials($current_user['name']) ?></div>
                    <span><?= e($current_user['name']) ?></span>
                    <span style="font-size: 0.75rem; color: #86868b;">▾</span>

                    <div class="user-dropdown-menu">
                        <div class="user-dropdown-header">
                            <strong><?= e($current_user['name']) ?></strong>
                            <span><?= e($current_user['email']) ?></span>
                        </div>
                        <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="dropdown-link">My Activities</a>
                        <a href="<?= APP_BASE_PATH ?>/inbox.php" class="dropdown-link">Inbox (Preview)</a>
                        <?php if ($is_any_manager): ?>
                            <a href="<?= APP_BASE_PATH ?>/manager/sessions.php" class="dropdown-link manager-link">Manager Workbench ↗</a>
                        <?php endif; ?>
                        <div style="height: 1px; background: #f0f0f2; margin: 0.35rem 0;"></div>
                        <form method="POST" action="<?= APP_BASE_PATH ?>/logout.php" style="margin: 0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-link logout-btn">Sign out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-nav-login">Sign in</a>
                <a href="<?= APP_BASE_PATH ?>/register.php" class="btn-nav-signup">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main id="main-content">
