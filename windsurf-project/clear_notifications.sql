-- Clear all notifications from the database
DELETE FROM notifications;

-- Reset auto-increment if needed
ALTER TABLE notifications AUTO_INCREMENT = 1;
