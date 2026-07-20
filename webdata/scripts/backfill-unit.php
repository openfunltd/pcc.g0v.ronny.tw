<?php

// 一次性回填 unit 表：找出 entity 表裡出現過、但 unit 表沒有對應名稱的機關代碼，
// 從該機關代碼底下隨便一筆 entity_data 的原始 JSON 反查機關名稱，補寫進 unit 表。
//
// import.php 現在每次匯入時也會做同樣的事，但只對「當次處理到的日期」有效；
// 這支是用來一次補齊過去所有歷史資料留下的缺漏。
//
// 用法：php backfill-unit.php

include(__DIR__ . '/../init.inc.php');
ini_set('memory_limit', '2G');

// 從一筆 entity_data 原始 JSON 裡，找出「值剛好等於 $oid」的「XXX機關代碼」欄位，
// 取同樣前綴的「XXX機關名稱」欄位當作機關名稱。這樣不用重複 import.php 裡一大串
// 依公告類型判斷欄位名稱的邏輯，同一個 category 前綴的代碼/名稱欄位是成對出現的。
function extract_name_for_oid($data, $oid)
{
    $suffix_map = array(
        '招標機關代碼' => '招標機關名稱',
        '機關代碼' => '機關名稱',
    );
    foreach ($data as $k => $v) {
        if (!is_scalar($v) or (string) $v !== (string) $oid) {
            continue;
        }
        foreach ($suffix_map as $code_suffix => $name_suffix) {
            $len = mb_strlen($code_suffix, 'UTF-8');
            if (mb_substr($k, -$len, null, 'UTF-8') !== $code_suffix) {
                continue;
            }
            $prefix = mb_substr($k, 0, mb_strlen($k, 'UTF-8') - $len, 'UTF-8');
            $name_key = $prefix . $name_suffix;
            if (property_exists($data, $name_key) and is_scalar($data->{$name_key}) and trim($data->{$name_key}) !== '') {
                return trim($data->{$name_key});
            }
        }
    }
    return null;
}

$db = Entity::getDb();

$missing_oids = array();
$res = $db->query("SELECT DISTINCT e.oid FROM entity e LEFT JOIN unit u ON u.oid = e.oid WHERE u.oid IS NULL AND e.oid IS NOT NULL");
while ($row = $res->fetch_assoc()) {
    $missing_oids[] = $row['oid'];
}
error_log("missing oid count: " . count($missing_oids));

$total_filled = 0;
$total_unresolved = 0;
$chunk_size = 200;

for ($i = 0; $i < count($missing_oids); $i += $chunk_size) {
    $chunk = array_slice($missing_oids, $i, $chunk_size);
    $oid_terms = array();
    foreach ($chunk as $oid) {
        $oid_terms[] = $db->quoteWithColumn('oid', $oid);
    }

    $sql = "SELECT DISTINCT ON (e.oid) e.oid, ed.data
            FROM entity e
            JOIN entity_data ed ON e.date = ed.date AND e.filename = ed.filename
            WHERE e.oid IN (" . implode(',', $oid_terms) . ")
            ORDER BY e.oid, e.date DESC";
    $res = $db->query($sql);

    $unit_terms = array();
    while ($row = $res->fetch_assoc()) {
        $data = json_decode($row['data']);
        $name = extract_name_for_oid($data, $row['oid']);
        if ($name === null) {
            $total_unresolved++;
            continue;
        }
        $unit_terms[] = sprintf(
            "(%s, %s)",
            $db->quoteWithColumn('oid', $row['oid']),
            $db->quoteWithColumn('name', $name)
        );
    }

    if (count($unit_terms)) {
        $db->query("INSERT INTO unit (oid, name) VALUES " . implode(',', $unit_terms) . " ON CONFLICT (oid) DO NOTHING");
        $total_filled += count($unit_terms);
    }

    error_log(sprintf(
        "processed %d/%d, filled so far: %d, unresolved so far: %d",
        min($i + $chunk_size, count($missing_oids)),
        count($missing_oids),
        $total_filled,
        $total_unresolved
    ));
}

error_log("done. total filled: {$total_filled}, total unresolved: {$total_unresolved}");
