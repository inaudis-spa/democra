-- OTP validity per election, in minutes; default 10, allowed 10-1440.
ALTER TABLE elections ADD COLUMN otp_ttl_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10;
