-- Premade QR migration (MariaDB/MySQL)
-- Goal: let premade printed QRs behave like normal visitors (unique records),
-- and keep destination purpose per entry.

START TRANSACTION;

-- 1) Mark premade-generated visitor rows explicitly.
ALTER TABLE `tbl_visitor`
    ADD COLUMN IF NOT EXISTS `is_premade` tinyint(1) NOT NULL DEFAULT 0 AFTER `contact_no`;

ALTER TABLE `tbl_visitor`
    ADD COLUMN IF NOT EXISTS `premade_batch_id` varchar(64) DEFAULT NULL AFTER `is_premade`;

-- 2) Keep purpose snapshot inside destination rows (so old records do not change
-- when visitor purpose is edited later).
ALTER TABLE `tbl_visitor_destination`
    ADD COLUMN IF NOT EXISTS `purpose_snapshot` varchar(255) NOT NULL DEFAULT '' AFTER `destination_name`;

-- 3) Backfill missing snapshot values for existing rows.
UPDATE `tbl_visitor_destination` vd
INNER JOIN `tbl_visitor` v ON v.tbl_visitor_id = vd.tbl_visitor_id
SET vd.purpose_snapshot = v.purpose
WHERE TRIM(COALESCE(vd.purpose_snapshot, '')) = '';

COMMIT;

