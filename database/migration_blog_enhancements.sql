-- ============================================================
-- BLOG ENHANCEMENTS
-- Adds category/tags/excerpt/SEO fields to the pre-existing
-- `blogs` table (base schema, not created by any migration) and
-- enforces slug uniqueness so the public blog listing/detail
-- pages (blog.php / blog-details.php) can rely on `slug_url`
-- resolving to exactly one row.
-- ============================================================

ALTER TABLE `blogs`
    ADD COLUMN IF NOT EXISTS `excerpt` VARCHAR(300) DEFAULT NULL AFTER `content`,
    ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `author`,
    ADD COLUMN IF NOT EXISTS `tags` VARCHAR(255) DEFAULT NULL AFTER `category`,
    ADD COLUMN IF NOT EXISTS `meta_title` VARCHAR(255) DEFAULT NULL AFTER `tags`,
    ADD COLUMN IF NOT EXISTS `meta_description` VARCHAR(300) DEFAULT NULL AFTER `meta_title`;

ALTER TABLE `blogs`
    ADD INDEX IF NOT EXISTS `idx_blogs_status_created` (`status`, `created_at`),
    ADD INDEX IF NOT EXISTS `idx_blogs_category` (`category`);

-- slug_url must be unique for fetch_blog_detail()'s LIMIT 1 lookup to be
-- unambiguous; NULLs remain allowed (multiple NULLs don't collide).
ALTER TABLE `blogs`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_blogs_slug_url` (`slug_url`);
