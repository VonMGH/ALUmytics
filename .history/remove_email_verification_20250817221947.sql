-- Script to remove email verification columns from the users table
-- Run this after importing the updated alumytics.sql file

USE `alumytics`;

-- If you've already imported the old database, run these commands to remove the columns:
-- ALTER TABLE users DROP COLUMN otp_code;
-- ALTER TABLE users DROP COLUMN otp_expires_at;
-- ALTER TABLE users DROP COLUMN email_verified;

-- Update all existing users to have onboarded = 0 if they are new alumni accounts
-- (this will redirect them to UpdateAccount.php on first login)
UPDATE users SET onboarded = 0 WHERE role = 'alumni' AND onboarded IS NULL;

SELECT 'Email verification system removed successfully!' as status;
