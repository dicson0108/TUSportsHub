-- Taylor's University SportsHub Seed Data
USE `tu_sportshub`;

-- Clean existing data
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `refund_records`;
TRUNCATE TABLE `registrations`;
TRUNCATE TABLE `sessions`;
TRUNCATE TABLE `club_managers`;
TRUNCATE TABLE `clubs`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Seed Users (Password for all: Password123!)
-- Hash: $2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `phone`) VALUES
(1, 'SportsHub Admin', 'admin@taylors.edu.my', '$2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO', 'admin', 'active', '+60123456700'),
(2, 'Alex Wong', 'alex@sd.taylors.edu.my', '$2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO', 'student', 'active', '+60123456701'),
(3, 'Liam Tan', 'liam@sd.taylors.edu.my', '$2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO', 'student', 'active', '+60123456702'),
(4, 'Chloe Lee', 'chloe@sd.taylors.edu.my', '$2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO', 'student', 'active', '+60123456703'),
(5, 'Danial Hakim', 'danial@sd.taylors.edu.my', '$2y$10$OCr7hFX/AMZBn.PsFEehs.86WDY0A8U85s1hlaSZlLLiFUIKY42VO', 'student', 'active', '+60123456704');

-- Seed Clubs
INSERT INTO `clubs` (`id`, `name`, `slug`, `description`, `sport_type`, `bank_name`, `account_holder`, `account_number`, `status`) VALUES
(1, "Taylor's Badminton Club", 'taylors-badminton-club', "The official Taylor's University Badminton Club organizing weekly sparring sessions, footwork clinics, and campus tournaments.", 'Badminton', 'Maybank', "Taylor's Badminton Club", '514012345678', 'active'),
(2, "Taylor's Basketball Club", 'taylors-basketball-club', "Taylor's University Basketball Club connecting campus hoopers for regular open runs, league matches, and skill development.", 'Basketball', 'CIMB Bank', "Taylor's Basketball Club", '800987654321', 'active');

-- Appoint Club Manager (Alex Wong manages Badminton Club)
INSERT INTO `club_managers` (`id`, `club_id`, `user_id`, `appointed_by`) VALUES
(1, 1, 2, 1);

-- Seed Sessions
INSERT INTO `sessions` (`id`, `club_id`, `title`, `description`, `sport_type`, `venue`, `session_date`, `start_time`, `end_time`, `price`, `capacity`, `status`, `created_by`) VALUES
(1, 1, 'Sunday Open Play & Social Smash', 'Casual doubles and singles open play across 3 indoor courts. Shuttles provided. All skill levels welcome.', 'Badminton', "Taylor's Sports Complex Court 1-3", '2026-11-15', '14:00:00', '16:00:00', 0.00, 16, 'published', 2),
(2, 1, 'Weekly Badminton Intensive & Footwork Clinic', 'Structured 2-hour coaching session focusing on shadow footwork drills, net play, and match strategy with tournament seniors.', 'Badminton', 'Multi-Purpose Hall Court A', '2026-11-20', '18:00:00', '20:00:00', 15.00, 12, 'published', 2),
(3, 1, 'Doubles Mini-Tournament (Semester Cup)', 'Internal club tournament with group stages and knockout playoffs. Trophies and grip bundles for top 3 pairs.', 'Badminton', "Taylor's Sports Complex Court 1-4", '2026-12-05', '09:00:00', '13:00:00', 25.00, 24, 'draft', 2),
(4, 2, "Friday Night 3v3 Half-Court Run", 'Weekly 3v3 basketball runs with king-of-the-court rotation. Bring both light and dark jerseys.', 'Basketball', 'Outdoor Basketball Court 2', '2026-11-22', '19:00:00', '21:00:00', 10.00, 18, 'published', 1);

-- Seed Registrations
-- Liam registered for free session -> confirmed
INSERT INTO `registrations` (`id`, `session_id`, `user_id`, `status`, `price_snapshot`, `payment_instructions_snapshot`) VALUES
(1, 1, 3, 'confirmed', 0.00, 'Free session. No payment required.');

-- Chloe registered for paid session -> awaiting review
INSERT INTO `registrations` (`id`, `session_id`, `user_id`, `status`, `price_snapshot`, `payment_instructions_snapshot`, `payment_deadline`, `receipt_uploaded_at`, `receipt_path`) VALUES
(2, 2, 4, 'awaiting_approval', 15.00, 'Maybank 514012345678 (Taylor\'s Badminton Club)', '2026-11-05 18:00:00', '2026-11-04 12:30:00', 'assets/uploads/receipts/sample_chloe_receipt.png');

-- Danial registered for paid session -> awaiting payment
INSERT INTO `registrations` (`id`, `session_id`, `user_id`, `status`, `price_snapshot`, `payment_instructions_snapshot`, `payment_deadline`) VALUES
(3, 2, 5, 'awaiting_payment', 15.00, 'Maybank 514012345678 (Taylor\'s Badminton Club)', '2026-11-05 18:00:00');
