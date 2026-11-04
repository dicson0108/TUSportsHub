</main>

<footer style="background: #ffffff; border-top: 1px solid #e5e5ea; padding: 3rem 1.5rem 2rem; margin-top: 4rem;">
    <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #f0f0f2;">
        <div>
            <div style="color: #c8102e; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">
                Taylor's <span style="color: #1d1d1f;">SportsHub</span>
            </div>
            <p style="font-size: 0.88rem; color: #6e6e73; line-height: 1.5; margin: 0;">
                Connect with university sports clubs, participate in regular sessions, and stay active across Taylor's Lakeside Campus.
            </p>
        </div>
        <div>
            <h4 style="font-size: 0.9rem; font-weight: 600; color: #1d1d1f; margin: 0 0 0.75rem;">Explore</h4>
            <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.88rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <li><a href="<?= APP_BASE_PATH ?>/sessions.php" style="color: #6e6e73; text-decoration: none;">Upcoming Sessions</a></li>
                <li><a href="<?= APP_BASE_PATH ?>/clubs.php" style="color: #6e6e73; text-decoration: none;">Sports Clubs</a></li>
                <li><a href="<?= APP_BASE_PATH ?>/inbox.php" style="color: #6e6e73; text-decoration: none;">Inbox Preview</a></li>
            </ul>
        </div>
        <div>
            <h4 style="font-size: 0.9rem; font-weight: 600; color: #1d1d1f; margin: 0 0 0.75rem;">Community</h4>
            <p style="font-size: 0.88rem; color: #6e6e73; line-height: 1.5; margin: 0;">
                Sessions are organized by appointed club managers. Taylor's students can register using their active @sd.taylors.edu.my credentials.
            </p>
        </div>
    </div>
    <div style="max-width: 1200px; margin: 1.5rem auto 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; font-size: 0.82rem; color: #86868b;">
        <div>© <?= date('Y') ?> Taylor's University SportsHub. All rights reserved.</div>
        <div style="display: flex; gap: 1rem;">
            <span>Malaysia Time (MYT)</span>
            <span>·</span>
            <span>Subang Jaya, Selangor</span>
        </div>
    </div>
</footer>

</body>
</html>
