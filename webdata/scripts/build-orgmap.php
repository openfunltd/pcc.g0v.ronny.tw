<?php

// 用人事總處的機關代碼表（orglist.utf8.csv）產生 orgmap.json：官方機關代碼 → 官方機關名稱、標案系統另外使用的 unit_id
//
// 標案系統的 unit_id 大多可以由官方代碼依規則換算（見 Unit::orgCodeToUnitId()），
// 但機關改制換了新代碼後，標案系統常沿用舊代碼，例如大陸委員會官方是 A43000000A（A.43），標案卻掛在 3.57（舊代碼 357000000A 行政院大陸委員會）。
// 這支對每個「換算結果不在 unit 表」的官方代碼，沿著「舊機關代碼」「新機關代碼」欄位往前找改制前的代碼，
// 找到換算後在 unit 表裡的就記下來；都找不到時，再用機關名稱（含改制前名稱）完全比對 unit 表，只比對到一個時才採用。
//
// 用法：php build-orgmap.php orglist.utf8.csv > ../orgmap.json

include(__DIR__ . '/../init.inc.php');

$csv_file = $_SERVER['argv'][1] ?? null;
if (!$csv_file or !file_exists($csv_file)) {
    die("用法：php build-orgmap.php orglist.utf8.csv > orgmap.json\n");
}

$units = array();
$name_units = array();
foreach (Unit::search(1)->toArray('name') as $oid => $name) {
    $units[strval($oid)] = $name;
    $name_units[$name][] = strval($oid);
}

$fp = fopen($csv_file, 'r');
$columns = fgetcsv($fp);
$orgs = array();
$predecessors = array();
while ($row = fgetcsv($fp)) {
    $row = array_combine($columns, array_slice(array_pad($row, count($columns), ''), 0, count($columns)));
    $code = $row['機關代碼'];
    $orgs[$code] = $row;
    if ($row['舊機關代碼'] !== '') {
        $predecessors[$code][$row['舊機關代碼']] = $row['舊機關名稱'];
    }
    if ($row['新機關代碼'] !== '') {
        $predecessors[$row['新機關代碼']][$code] = $row['機關名稱'];
    }
}
fclose($fp);

$map = array();
$stat = array('direct' => 0, 'lineage' => 0, 'name' => 0, 'none' => 0);
foreach ($orgs as $code => $row) {
    $unit_id = Unit::orgCodeToUnitId($code);
    if (is_null($unit_id)) {
        continue;
    }
    if (array_key_exists($unit_id, $units)) {
        $stat['direct'] ++;
        continue;
    }

    // 往前追改制前的代碼（可能經過好幾次改制，一路追到底）
    $found = array();
    $names = array($row['機關名稱'] => true);
    $visited = array($code => true);
    $queue = array($code);
    while ($c = array_shift($queue)) {
        foreach ($predecessors[$c] ?? array() as $old_code => $old_name) {
            if (array_key_exists($old_code, $visited)) {
                continue;
            }
            // 層級比較低的是被併入的下屬機關（例如行政院消費者保護委員會併入行政院），不算同一個機關
            $level = intval($orgs[$c]['機關層級'] ?? 0);
            $old_level = intval($orgs[$old_code]['機關層級'] ?? 0);
            if ($level and $old_level and $old_level > $level) {
                continue;
            }
            $visited[$old_code] = true;
            $queue[] = $old_code;
            $names[$old_name] = true;
            $old_unit_id = Unit::orgCodeToUnitId($old_code);
            if (!is_null($old_unit_id) and array_key_exists($old_unit_id, $units)) {
                $found[$old_unit_id] = true;
            }
        }
    }
    $source = 'lineage';
    if (!$found) {
        $source = 'name';
        foreach (array_keys($names) as $name) {
            if (count($name_units[$name] ?? array()) == 1) {
                $found[$name_units[$name][0]] = true;
            }
        }
    }
    if (!$found) {
        $stat['none'] ++;
        continue;
    }
    $stat[$source] ++;
    $found = array_map('strval', array_keys($found));
    sort($found);
    // 同一個換算結果可能有多筆官方代碼（例如幼兒園的 3790954006 與 379095400Z），名稱用還沒裁撤的那筆
    if (!array_key_exists($unit_id, $map) or $row['裁撤註記'] !== '是') {
        $map[$unit_id]['name'] = $row['機關名稱'];
    }
    $map[$unit_id]['unit_ids'] = array_values(array_unique(array_merge($map[$unit_id]['unit_ids'] ?? array(), $found)));
}
ksort($map);

error_log(json_encode($stat));
echo json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
