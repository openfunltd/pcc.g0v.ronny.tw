<?php

// 一次性回填 entity 表的 dept_name / award_amount / budget_amount 欄位與 entity_winner 表：
// 從既有 entity_data 原始 JSON 用 EntityData::extractStat() 抽出統計用欄位。
//
// import.php 現在匯入時會直接寫這些欄位，這支是用來補齊過去的歷史資料。
// 以日期為單位處理（新到舊），每個日期一個 transaction，中斷後可以用參數從中斷的日期接著跑。
// 執行前要先用 postgres 身分跑過 stat-schema.sql 建立欄位與資料表。
//
// 用法：php backfill-stat.php [從哪天開始往前(YYYYMMDD，預設最新)] [到哪天為止(YYYYMMDD，預設最舊)]

include(__DIR__ . '/../init.inc.php');
ini_set('memory_limit', '2G');

$db = Entity::getDb();

$from = intval($_SERVER['argv'][1] ?? 0) ?: 99999999;
$to = intval($_SERVER['argv'][2] ?? 0);

$dates = array();
$res = $db->query("SELECT DISTINCT date FROM entity WHERE date <= {$from} AND date >= {$to} ORDER BY date DESC");
while ($row = $res->fetch_assoc()) {
    $dates[] = intval($row['date']);
}
error_log("dates to process: " . count($dates));

$quote = function($value, $type) use ($db) {
    return is_null($value) ? "NULL::{$type}" : $db->quoteWithColumn('', strval($value)) . "::{$type}";
};

foreach ($dates as $i => $date) {
    touch('heartbeat');
    $entity_terms = array();
    $winner_terms = array();
    $res = $db->query("SELECT filename, data FROM entity_data WHERE date = {$date}");
    while ($row = $res->fetch_assoc()) {
        $stat = EntityData::extractStat(json_decode($row['data']));
        $entity_terms[] = sprintf("(%s, %s, %s, %s)",
            $quote($row['filename'], 'varchar'),
            $quote($stat['dept_name'], 'text'),
            $quote($stat['award_amount'], 'bigint'),
            $quote($stat['budget_amount'], 'bigint')
        );
        foreach ($stat['winners'] as $winner) {
            $winner_terms[] = sprintf("(%d, %s, %s, %s, %s)",
                $date,
                $quote($row['filename'], 'varchar'),
                $quote($winner['company_name'], 'text'),
                $quote($winner['company_id'], 'varchar'),
                $quote($winner['amount'], 'bigint')
            );
        }
    }

    $db->query("BEGIN");
    if (count($entity_terms)) {
        $db->query("UPDATE entity SET dept_name = v.dept_name, award_amount = v.award_amount, budget_amount = v.budget_amount
            FROM (VALUES " . implode(',', $entity_terms) . ") AS v (filename, dept_name, award_amount, budget_amount)
            WHERE entity.date = {$date} AND entity.filename = v.filename");
    }
    $db->query("DELETE FROM entity_winner WHERE date = {$date}");
    if (count($winner_terms)) {
        $db->query("INSERT INTO entity_winner (date, filename, company_name, company_id, amount) VALUES " . implode(',', $winner_terms) . " ON CONFLICT DO NOTHING");
    }
    $db->query("COMMIT");

    error_log(sprintf("%d (%d/%d) records: %d, winners: %d", $date, $i + 1, count($dates), count($entity_terms), count($winner_terms)));
}

// 重新寫入的得標廠商統編會回到解析時的樣子，跑完再整批用同名廠商補一次
EntityWinner::fillCompanyId();
error_log("done");
