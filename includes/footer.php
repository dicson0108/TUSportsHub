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
                <h4 class="footer-col-title">Campus Sports</h4>
                <ul class="footer-links-list">
                    <li><a href="<?= APP_BASE_PATH ?>/sessions.php?sport=Badminton" class="footer-link">Badminton Club</a></li>
                    <li><a href="<?= APP_BASE_PATH ?>/sessions.php?sport=Basketball" class="footer-link">Basketball Club</a></li>
                    <li><a href="mailto:sportshub@taylors.edu.my" class="footer-link">Contact Sports Complex</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <span>© <?= date('Y') ?> Taylor's University SportsHub. All rights reserved.</span>
            <div style="display:flex; gap:20px; align-items:center;">
                <span style="color:var(--text-muted)">Subang Jaya, Selangor</span>
                <span style="color:var(--text-muted)">·</span>
                <span style="color:var(--text-muted)">Malaysia Time (MYT)</span>
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
