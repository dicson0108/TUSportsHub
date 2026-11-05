-- Additive upgrade; safe to rerun after interrupted setup. Legacy reviewers remain unknown.
CREATE TABLE IF NOT EXISTS payment_receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  receipt_path VARCHAR(255) NOT NULL,
  submitted_at DATETIME NULL,
  decision ENUM('awaiting_review', 'approved', 'rejected', 'legacy') NOT NULL DEFAULT 'awaiting_review',
  rejection_reason VARCHAR(255) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  UNIQUE KEY unique_registration_receipt (registration_id, receipt_path),
  CONSTRAINT fk_receipt_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_receipts (registration_id, receipt_path, submitted_at, decision, rejection_reason)
SELECT r.id, r.receipt_path, r.receipt_uploaded_at,
  CASE WHEN r.status = 'awaiting_approval' THEN 'awaiting_review'
       WHEN r.rejection_reason IS NOT NULL THEN 'rejected'
       WHEN r.status IN ('confirmed', 'cancellation_pending') THEN 'approved'
       ELSE 'legacy' END,
  r.rejection_reason
FROM registrations r
LEFT JOIN payment_receipts p ON p.registration_id=r.id AND p.receipt_path=r.receipt_path
WHERE r.receipt_path IS NOT NULL AND r.receipt_path <> '' AND p.id IS NULL;
