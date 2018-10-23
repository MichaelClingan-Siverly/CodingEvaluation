/* I haven't done SQL in a couple years. Had to use the manual pages quite a bit: https://dev.mysql.com/doc/refman/8.0/en/ */

CREATE SCHEMA `eval_schema`;

/* assumption: I don't need to update 'Active', as requests are invalid if a user is inactive, and an
active user sending requests is clearly not yet inactive.
*/

/* name and ID are closely related, but I feel the name is easier to read when reading the statistics*/

CREATE TABLE `eval_schema`.`customer` (
`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`name` VARCHAR(255) NOT NULL,
`active` BIT NOT NULL DEFAULT 1,
PRIMARY KEY (`id`),
UNIQUE KEY (`name`)
);

/* I decided not to put the blacklists together because (for example) one ip may have many user IDs. This would make the table much larger than two separate ones (n * m instead of n + m)*/

CREATE TABLE `eval_schema`.`ip_blacklist` (
`ip` int(11) unsigned NOT NULL,
PRIMARY KEY (`ip`)
);

CREATE TABLE `eval_schema`.`user_id_blacklist` (
`user_id` varchar(255) NOT NULL,
PRIMARY KEY (`user_id`)
);

CREATE TABLE `eval_schema`.`hourly_stats` (
`customer_id` INT(11) UNSIGNED NOT NULL,
`time` TIMESTAMP NOT NULL,
`request_count` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
`invalid_count` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY (`customer_id`, `time`),
FOREIGN KEY (`customer_id`)
REFERENCES `eval_schema`.`customer` (`id`)
ON DELETE CASCADE
ON UPDATE NO ACTION
);