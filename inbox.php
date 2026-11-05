<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$page_title = "Inbox (Preview)";
require_once __DIR__ . '/includes/header.php';
?>

<div class="inbox-page-container">
    <!-- Static Prototype Notice Banner -->
    <div class="inbox-notice-banner">
        <svg class="inbox-notice-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <div>
            <div class="inbox-notice-title-row">
                <strong class="inbox-notice-title">Inbox Static Preview</strong>
                <span class="inbox-notice-tag">Prototype Only</span>
            </div>
            <p class="inbox-notice-text">
                Per Taylor's SportsHub MVP specification, message delivery and in-app chat are deferred. This page serves as a read-only concept preview demonstrating future notification possibilities. Real status updates are authoritatively reflected on your <a href="<?= app_url('/my-activities.php') ?>" class="inbox-notice-link">My Activities</a> page.
            </p>
        </div>
    </div>

    <div class="inbox-header-row">
        <h1 class="inbox-page-title">
            Sample Messages Preview
        </h1>
        <span class="inbox-count-badge">3 Synthetic Examples</span>
    </div>

    <!-- Sample Messages List -->
    <div class="inbox-messages-list">
        <!-- Sample 1 -->
        <article class="inbox-message-card">
            <div class="inbox-message-header">
                <div class="inbox-message-badge-wrap">
                    <span class="inbox-badge-green">Registration Update</span>
                    <strong class="inbox-message-subject">Confirmed: Sunday Open Play & Social Smash</strong>
                </div>
                <span class="inbox-message-time">Sample · 2 hours ago</span>
            </div>
            <p class="inbox-message-body">
                Hi <?= e($user['name']) ?>, your spot for Sunday Open Play with Taylor's Badminton Club is confirmed! Remember to bring non-marking court shoes. Shuttles are provided on Court 1-3.
            </p>
            <div class="inbox-message-meta-row">
                <span class="inbox-meta-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Sports Complex Court 1-3</span>
                </span>
                <span class="inbox-meta-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    <span>15 Nov 2026, 2:00 PM</span>
                </span>
            </div>
        </article>

        <!-- Sample 2 -->
        <article class="inbox-message-card">
            <div class="inbox-message-header">
                <div class="inbox-message-badge-wrap">
                    <span class="inbox-badge-red">Announcement</span>
                    <strong class="inbox-message-subject">Venue Relocation Notice: Court Maintenance</strong>
                </div>
                <span class="inbox-message-time">Sample · Yesterday</span>
            </div>
            <p class="inbox-message-body">
                Attention all participants: Due to scheduled floor resurfacing at the Multi-Purpose Hall, Friday's coaching session has been shifted to Sports Complex Hall B. Time and date remain unchanged.
            </p>
            <div class="inbox-message-meta-row">
                <span class="inbox-meta-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Sender: Taylor's Badminton Club Committee</span>
                </span>
            </div>
        </article>

        <!-- Sample 3 -->
        <article class="inbox-message-card">
            <div class="inbox-message-header">
                <div class="inbox-message-badge-wrap">
                    <span class="inbox-badge-blue">Payment Review</span>
                    <strong class="inbox-message-subject">Payment Receipt Verified</strong>
                </div>
                <span class="inbox-message-time">Sample · 3 days ago</span>
            </div>
            <p class="inbox-message-body">
                Your DuitNow transfer of RM 15.00 has been verified by the club manager. Your registration record has been updated to Confirmed.
            </p>
            <div class="inbox-message-meta-row">
                <span class="inbox-meta-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                    <span>Transaction Ref: TNX-90184 · Taylor's Badminton Club</span>
                </span>
            </div>
        </article>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
