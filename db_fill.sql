/* I'll probably copy values from the suggested tables to initialize them */

INSERT INTO `eval_schema`.`customer` VALUES
(1,'Big News Media Corp',1),
(2,'Online Mega Store',1),
(3,'Nachoroo Delivery',0),
(4,'Euro Telecom Group',1);

INSERT INTO `eval_schema`.`ip_blacklist` VALUES
(0),
(2130706433),
(4294967295);

INSERT INTO `eval_schema`.`user_id_blacklist` VALUES
('A6-Indexer'),
('Googlebot-News'),
('Googlebot');