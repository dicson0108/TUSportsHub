<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$page_title = "Inbox (Preview)";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 860px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Static Prototype Notice Banner -->
    <div style="background: #fdf2f4; border: 1px solid #fecdd3; border-radius: 18px; padding: 1.5rem; margin-bottom: 2.5rem; display: flex; align-items: flex-start; gap: 1rem;">
        <span style="font-size: 1.75rem; line-height: 1;">ℹ️</span>
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <strong style="color: #c8102e; font-size: 1rem;">Inbox Static Preview</strong>
                <span style="background: #c8102e; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 980px; text-transform: uppercase;">Prototype Only</span>
            </div>
            <p style="color: #6e6e73; font-size: 0.9rem; line-height: 1.5; margin: 0;">
                Per Taylor's SportsHub MVP specification, message delivery and in-app chat are deferred. This page serves as a read-only concept preview demonstrating future notification possibilities. Real status updates are authoritatively reflected on your <a href="<?= app_url('/my-activities.php') ?>" style="color: #c8102e; font-weight: 600;">My Activities</a> page.
            </p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.85rem; font-weight: 800; color: #1d1d1f; margin: 0; letter-spacing: -0.02em;">
            Sample Messages Preview
        </h1>
        <span style="font-size: 0.85rem; color: #86868b;">3 Synthetic Examples</span>
    </div>

    <!-- Sample Messages List -->
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <!-- Sample 1 -->
        <div style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 18px rgba(0,0,0,0.03); padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: #eef8f1; color: #248a3d; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 980px;">Registration Update</span>
                    <strong style="color: #1d1d1f; font-size: 1.05rem;">Confirmed: Sunday Open Play & Social Smash</strong>
                </div>
                <span style="font-size: 0.8rem; color: #86868b;">Sample · 2 hours ago</span>
            </div>
            <p style="color: #424245; font-size: 0.92rem; line-height: 1.5; margin: 0 0 1rem;">
                Hi <?= e($user['name']) ?>, your spot for Sunday Open Play with Taylor's Badminton Club is confirmed! Remember to bring non-marking court shoes. Shuttles are provided on Court 1-3.
            </p>
            <div style="font-size: 0.82rem; color: #86868b; display: flex; gap: 1rem;">
                <span>📍 Sports Complex Court 1-3</span>
                <span>📅 15 Nov 2026, 2:00 PM</span>
            </div>
        </div>

        <!-- Sample 2 -->
        <div style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 18px rgba(0,0,0,0.03); padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: #fff2f2; color: #c81e27; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 980px;">Announcement</span>
                    <strong style="color: #1d1d1f; font-size: 1.05rem;">Venue Relocation Notice: Court Maintenance</strong>
                </div>
                <span style="font-size: 0.8rem; color: #86868b;">Sample · Yesterday</span>
            </div>
            <p style="color: #424245; font-size: 0.92rem; line-height: 1.5; margin: 0 0 1rem;">
                Attention all participants: Due to scheduled floor resurfacing at the Multi-Purpose Hall, Friday's coaching session has been shifted to Sports Complex Hall B. Time and date remain unchanged.
            </p>
            <div style="font-size: 0.82rem; color: #86868b;">
                Sender: Taylor's Badminton Club Committee
            </div>
        </div>

        <!-- Sample 3 -->
        <div style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 18px rgba(0,0,0,0.03); padding: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: #f0f7ff; color: #0071e3; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 980px;">Payment Review</span>
                    <strong style="color: #1d1d1f; font-size: 1.05rem;">Payment Receipt Verified</strong>
                </div>
                <span style="font-size: 0.8rem; color: #86868b;">Sample · 3 days ago</span>
            </div>
            <p style="color: #424245; font-size: 0.92rem; line-height: 1.5; margin: 0 0 1rem;">
                Your DuitNow transfer of RM 15.00 has been verified by the club manager. Your registration record has been updated to Confirmed.
            </p>
            <div style="font-size: 0.82rem; color: #86868b;">
                Transaction Ref: TNX-90184 · Taylor's Badminton Club
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
