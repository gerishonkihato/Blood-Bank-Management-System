-- Populate blood inventory with sample data
-- Run this SQL in phpMyAdmin to add realistic blood unit counts

INSERT INTO inventory (bloodType, unitsAvailable, lastUpdated) VALUES
('A+', 25, NOW()),
('A-', 12, NOW()),
('B+', 18, NOW()),
('B-', 8, NOW()),
('AB+', 6, NOW()),
('AB-', 4, NOW()),
('O+', 40, NOW()),
('O-', 20, NOW());

-- Optional: Add sample donors and blood units if needed
-- First, uncomment and run the inventory insert above
-- Then add donors:

INSERT INTO donors (donorId, userId, encryptedName, encryptedPhone, bloodGroup, rhFactor, status) VALUES
('DNR-001', 'donor-001', 'John Doe (encrypted)', '0712345678 (encrypted)', 'O', '+', 'ACTIVE'),
('DNR-002', 'donor-002', 'Jane Smith (encrypted)', '0787654321 (encrypted)', 'A', '+', 'ACTIVE');

INSERT INTO blood_units (unitId, donorId, collectionDate, status) VALUES
('UNIT-001', 'DNR-001', DATE_SUB(NOW(), INTERVAL 5 DAY), 'AVAILABLE'),
('UNIT-002', 'DNR-001', DATE_SUB(NOW(), INTERVAL 10 DAY), 'AVAILABLE'),
('UNIT-003', 'DNR-002', DATE_SUB(NOW(), INTERVAL 3 DAY), 'AVAILABLE');

-- Insert sample test donor and recipient users
INSERT INTO users (userId, username, passwordHash, role) VALUES
('donor-001', 'donor1', '$2y$10$x3eGh0q2kTKLw1CKTL2zD.W/L0H7/BBZ/qipem4HJjdK2aUegTae6', 'DONOR'),
('donor-002', 'donor2', '$2y$10$x3eGh0q2kTKLw1CKTL2zD.W/L0H7/BBZ/qipem4HJjdK2aUegTae6', 'DONOR'),
('recipient-001', 'recipient1', '$2y$10$x3eGh0q2kTKLw1CKTL2zD.W/L0H7/BBZ/qipem4HJjdK2aUegTae6', 'RECIPIENT');

INSERT INTO recipients (recipientId, userId, encryptedName, encryptedPhone, hospitalName, status) VALUES
('RCP-001', 'recipient-001', 'Robert Johnson (encrypted)', '0712121212 (encrypted)', 'City General Hospital', 'ACTIVE');
