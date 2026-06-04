-- ============================================================
-- TuskPress — Schema MySQL
-- Mantém compatibilidade 100% com o schema original do WordPress
-- para permitir migração de sites existentes.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- wp_users
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_users` (
  `ID`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login`          varchar(60)  NOT NULL DEFAULT '',
  `user_pass`           varchar(255) NOT NULL DEFAULT '',
  `user_nicename`       varchar(50)  NOT NULL DEFAULT '',
  `user_email`          varchar(100) NOT NULL DEFAULT '',
  `user_url`            varchar(100) NOT NULL DEFAULT '',
  `user_registered`     datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) NOT NULL DEFAULT '',
  `user_status`         int(11)      NOT NULL DEFAULT 0,
  `display_name`        varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename`  (`user_nicename`),
  KEY `user_email`     (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_usermeta
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_usermeta` (
  `umeta_id`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key`   varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`umeta_id`),
  KEY `meta_key` (`meta_key`(191)),
  KEY `user_id`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_posts
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_posts` (
  `ID`                    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_author`           bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `post_date`             datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt`         datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content`          longtext     NOT NULL,
  `post_title`            text         NOT NULL,
  `post_excerpt`          text         NOT NULL,
  `post_status`           varchar(20)  NOT NULL DEFAULT 'publish',
  `comment_status`        varchar(20)  NOT NULL DEFAULT 'open',
  `ping_status`           varchar(20)  NOT NULL DEFAULT 'open',
  `post_password`         varchar(255) NOT NULL DEFAULT '',
  `post_name`             varchar(200) NOT NULL DEFAULT '',
  `to_ping`               text         NOT NULL,
  `pinged`                text         NOT NULL,
  `post_modified`         datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt`     datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext     NOT NULL,
  `post_parent`           bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `guid`                  varchar(255) NOT NULL DEFAULT '',
  `menu_order`            int(11)      NOT NULL DEFAULT 0,
  `post_type`             varchar(20)  NOT NULL DEFAULT 'post',
  `post_mime_type`        varchar(100) NOT NULL DEFAULT '',
  `comment_count`         bigint(20)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `post_name`        (`post_name`(191)),
  KEY `type_status_date` (`post_type`, `post_status`, `post_date`, `ID`),
  KEY `post_parent`      (`post_parent`),
  KEY `post_author`      (`post_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_postmeta
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_postmeta` (
  `meta_id`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`    bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key`   varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`meta_id`),
  KEY `meta_key` (`meta_key`(191)),
  KEY `post_id`  (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_terms
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_terms` (
  `term_id`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       varchar(200) NOT NULL DEFAULT '',
  `slug`       varchar(200) NOT NULL DEFAULT '',
  `term_group` bigint(10)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_term_taxonomy
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_term_taxonomy` (
  `term_taxonomy_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id`          bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `taxonomy`         varchar(32)  NOT NULL DEFAULT '',
  `description`      longtext     NOT NULL,
  `parent`           bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `count`            bigint(20)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`, `taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_term_relationships
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_term_relationships` (
  `object_id`        bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_taxonomy_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_order`       int(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`object_id`, `term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_options
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_options` (
  `option_id`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_name`  varchar(191) NOT NULL DEFAULT '',
  `option_value` longtext     NOT NULL,
  `autoload`     varchar(20)  NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_comments
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_comments` (
  `comment_ID`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_post_ID`      bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `comment_author`       tinytext     NOT NULL,
  `comment_author_email` varchar(100) NOT NULL DEFAULT '',
  `comment_author_url`   varchar(200) NOT NULL DEFAULT '',
  `comment_author_IP`    varchar(100) NOT NULL DEFAULT '',
  `comment_date`         datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt`     datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content`      text         NOT NULL,
  `comment_karma`        int(11)      NOT NULL DEFAULT 0,
  `comment_approved`     varchar(20)  NOT NULL DEFAULT '1',
  `comment_agent`        varchar(255) NOT NULL DEFAULT '',
  `comment_type`         varchar(20)  NOT NULL DEFAULT 'comment',
  `comment_parent`       bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `user_id`              bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID`     (`comment_post_ID`),
  KEY `comment_approved_date_gmt` (`comment_approved`, `comment_date_gmt`),
  KEY `comment_date_gmt`    (`comment_date_gmt`),
  KEY `comment_parent`      (`comment_parent`),
  KEY `comment_author_email` (`comment_author_email`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- wp_commentmeta
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_commentmeta` (
  `meta_id`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key`   varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key`   (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Dados iniciais obrigatórios
-- ============================================================

-- Default administrator user (password: admin — CHANGE after installation)
INSERT IGNORE INTO `wp_users`
  (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `display_name`)
VALUES
  (1, 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@example.com', '', NOW(), 'Administrador');

-- Administrator user roles
INSERT IGNORE INTO `wp_usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES
  (1, 'wp_capabilities', 'a:1:{s:13:"administrator";b:1;}'),
  (1, 'wp_user_level',   '10'),
  (1, 'nickname',        'admin'),
  (1, 'first_name',      'Admin'),
  (1, 'last_name',       '');

-- Opções padrão do site
INSERT IGNORE INTO `wp_options` (`option_name`, `option_value`, `autoload`) VALUES
  ('siteurl',               'http://localhost:8000',   'yes'),
  ('home',                  'http://localhost:8000',   'yes'),
  ('blogname',              'TuskPress',               'yes'),
  ('blogdescription',       'Motor moderno, interface familiar', 'yes'),
  ('admin_email',           'admin@example.com',       'yes'),
  ('posts_per_page',        '10',                      'yes'),
  ('date_format',           'd/m/Y',                   'yes'),
  ('time_format',           'H:i',                     'yes'),
  ('timezone_string',       'America/Sao_Paulo',       'yes'),
  ('permalink_structure',   '/%postname%/',            'yes'),
  ('template',              'twentytwentyfour',        'yes'),
  ('stylesheet',            'twentytwentyfour',        'yes'),
  ('active_plugins',        'a:0:{}',                  'yes'),
  ('wp_user_roles',         'a:5:{s:13:"administrator";a:2:{s:4:"name";s:13:"Administrator";s:12:"capabilities";a:1:{s:13:"administrator";b:1;}}s:6:"editor";a:2:{s:4:"name";s:6:"Editor";s:12:"capabilities";a:1:{s:6:"editor";b:1;}}s:6:"author";a:2:{s:4:"name";s:6:"Author";s:12:"capabilities";a:1:{s:6:"author";b:1;}}s:11:"contributor";a:2:{s:4:"name";s:11:"Contributor";s:12:"capabilities";a:1:{s:11:"contributor";b:1;}}s:10:"subscriber";a:2:{s:4:"name";s:10:"Subscriber";s:12:"capabilities";a:1:{s:10:"subscriber";b:1;}}}', 'yes'),
  ('tuskpress_version',     '1.0.0',                   'yes'),
  ('tuskpress_db_version',  '1.0.0',                   'yes');

-- Post de exemplo (Hello World)
INSERT IGNORE INTO `wp_posts`
  (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_name`, `post_modified`, `post_modified_gmt`, `post_type`, `guid`)
VALUES
  (1, 1, NOW(), UTC_TIMESTAMP(), '<p>Welcome to TuskPress! This is your first post. Edit or delete it and start creating content!</p>', 'Hello World!', '', 'publish', 'open', 'open', 'hello-world', NOW(), UTC_TIMESTAMP(), 'post', 'http://localhost:8000/?p=1'),
  (2, 1, NOW(), UTC_TIMESTAMP(), '<p>This is a sample page. Edit or delete it and start creating your content!</p>', 'Sample Page', '', 'publish', 'closed', 'closed', 'sample-page', NOW(), UTC_TIMESTAMP(), 'page', 'http://localhost:8000/?page_id=2');

-- Default category "Uncategorized"
INSERT IGNORE INTO `wp_terms`       (`term_id`, `name`,          `slug`,           `term_group`) VALUES (1, 'Uncategorized', 'uncategorized', 0);
INSERT IGNORE INTO `wp_term_taxonomy` (`term_taxonomy_id`, `term_id`, `taxonomy`,    `description`, `parent`, `count`) VALUES (1, 1, 'category', '', 0, 1);
INSERT IGNORE INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES (1, 1, 0);
