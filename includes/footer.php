</main>

<footer class="site-footer">
    <div class="app-container">
        <div class="footer-top-grid">
            <div>
                <a href="<?= APP_BASE_PATH ?>/index.php" class="brand-group" style="margin-bottom: 16px;">
                    <?= get_site_logo_html() ?>
                    <div class="brand-text">
                        <span class="brand-title">Taylor's</span>
                        <span class="brand-subtitle">SPORTS HUB</span>
                    </div>
                </a>
                <p style="font-size: 13.5px; color: var(--text-secondary); max-width: 320px; line-height: 1.5;">
                    Your campus arena. Connect with university sports clubs, participate in competitive drills and social open play.
                </p>
            </div>

            <div>
                <h4 class="footer-col-title">Discover</h4>
                <ul class="footer-links-list">
                    <li><a href="<?= APP_BASE_PATH ?>/sessions.php" class="footer-link">Upcoming sessions</a></li>
                    <li><a href="<?= APP_BASE_PATH ?>/clubs.php" class="footer-link">Sports clubs</a></li>
                    <li><a href="<?= APP_BASE_PATH ?>/my-activities.php" class="footer-link">My activities</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-col-title">Legal & Campus</h4>
                <ul class="footer-links-list">
                    <li><a href="<?= APP_BASE_PATH ?>/privacy.php" class="footer-link">Privacy policy (PDPA)</a></li>
                    <li><a href="<?= APP_BASE_PATH ?>/terms.php" class="footer-link">Terms of use</a></li>
                    <li><span class="footer-link" style="color: var(--text-muted, #86868b);">Lakeside Sports Centre</span></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <span>© <?= date('Y') ?> Taylor's University. All rights reserved.</span>
            <div style="display:flex; gap:20px; align-items:center;">
                <a href="<?= APP_BASE_PATH ?>/privacy.php" class="footer-link">Privacy policy</a>
                <a href="<?= APP_BASE_PATH ?>/terms.php" class="footer-link">Terms of use</a>
                <span style="color:var(--text-muted)">Lakeside Campus, Malaysia</span>
            </div>
        </div>
    </div>
</footer>

<!-- Floating Back To Top Button -->
<button type="button" id="back-to-top" class="back-to-top-btn" aria-label="Back to top" title="Back to top">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 15l-6-6-6 6"/>
    </svg>
</button>

<script src="<?= APP_BASE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
