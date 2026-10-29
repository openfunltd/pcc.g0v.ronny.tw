-- listbyunit 的 dept_name 篩選、金額欄位與 /api/unitstat 需要的資料結構。
-- entity 表擁有者是 postgres，pcc-api 帳號不能改結構，要用 postgres 身分執行：
--   psql "$DATABASE_URL 換成 postgres 帳號" -f stat-schema.sql
-- 欄位都允許 NULL、沒有預設值，ADD COLUMN 只改 metadata 不會重寫整張表；
-- 但仍需要短暫的獨佔鎖，lock_timeout 避免等 import.php 的 transaction 時卡住後面的 API 查詢。
-- 執行完再跑 php backfill-stat.php 回填歷史資料。

SET lock_timeout = '10s';

ALTER TABLE entity
    ADD COLUMN IF NOT EXISTS dept_name text,
    ADD COLUMN IF NOT EXISTS award_amount bigint,
    ADD COLUMN IF NOT EXISTS budget_amount bigint;

CREATE TABLE IF NOT EXISTS entity_winner (
    date int NOT NULL,
    filename varchar(16) NOT NULL,
    company_name text NOT NULL,
    company_id varchar(64),
    amount bigint,
    PRIMARY KEY (date, filename, company_name)
);
ALTER TABLE entity_winner OWNER TO postgres;
GRANT SELECT, INSERT, UPDATE, DELETE ON entity_winner TO "pcc-api";
