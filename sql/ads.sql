CREATE TABLE `ads` (
	`ad_id` BIGINT NOT NULL,
    `owner_id` BIGINT NOT NULL,
    `message_id` INT NOT NULL,
    `destinations` JSON NOT NULL,
    `settings` JSON NOT NULL,
    `display_name` varchar(32),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`ad_id`)
);
