-- ============================================================
-- DEPARTMENT DESCRIPTION
-- Adds a rich, visitor-facing description field to sub_categories
-- (medical departments), separate from meta_desc which stays as
-- the short SEO <meta description> tag only.
-- ============================================================

ALTER TABLE `sub_categories`
    ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `categories`;
