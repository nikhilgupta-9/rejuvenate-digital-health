-- ============================================================
-- DROP DEAD/UNUSED TABLES
--
-- Found via a full audit comparing production's live schema dump against
-- this codebase (grepped every .php file for every table name). These
-- three tables are not referenced anywhere in the application code, are
-- empty on both local dev and production, and nothing else has a foreign
-- key pointing into them:
--
--   - address_list       — superseded by `addresses` / `user_addresses`,
--                          which are the tables actually used by the
--                          address-book features.
--   - blog_tags          — a tagging feature that was never wired up;
--                          blogs.php/admin blog screens don't reference it.
--   - user_admin_profiles — superseded by `admin_user`'s own profile
--                          columns; nothing reads or writes this table.
--
-- Safe to drop: 0 rows in production, 0 rows locally, 0 references in
-- the PHP codebase, no incoming foreign keys.
-- ============================================================

DROP TABLE IF EXISTS `address_list`;
DROP TABLE IF EXISTS `blog_tags`;
DROP TABLE IF EXISTS `user_admin_profiles`;
