CREATE TABLE `channels` (
	`channel_id` BIGINT NOT NULL,
    `owner_id` BIGINT NOT NULL,
    `linked_chat_id` BIGINT,
    `username` varchar(32),
    `display_name` varchar(32),
    `admins` JSON,
    `settings` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`channel_id`)
);
