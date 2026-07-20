<?php

include(__DIR__ . '/../init.inc.php');
date_default_timezone_set('Asia/Taipei');
ini_set('memory_limit', '2G');

$path = $_SERVER['argv'][1];
if (!file_exists($path)) {
    throw new Exception("Usage: php import.php [path] [date]");
}

$now = $_SERVER['argv'][2] ? strtotime($_SERVER['argv'][2]) : time();
$end = $_SERVER['argv'][3] ? strtotime($_SERVER['argv'][3]) : ($now - 5 * 86400);

$oid_mapping = file_exists($path . '/oidmapping.json') ? json_decode(file_get_contents($path . '/oidmapping.json')) : new StdClass;

$check_include = function($obj, $fields) {
    foreach ($fields as $field) {
        if (!property_exists($obj, $field) or '' === trim($obj->{$field})) {
            return false;
        }
    }
    return true;
};

$counter = array();
while ($now >= $end) {
    $ymd = date('Ymd', $now);
    touch($path . '/heartbeat');
    $year = date('Y', $now);
    $list_source = $path . "/list/{$ymd}.html.gz";
    $list_source_np = $path . "/list/np-{$ymd}.html.gz";

    $now -= 86400;

    $records = array();
    if (file_Exists($list_source)) {
        $list_content = gzdecode(file_get_contents($list_source));
        $records = array_merge($records, Parser::parse_list($list_content));
    }
    if (file_Exists($list_source_np)) {
        $list_content = gzdecode(file_get_contents($list_source_np));
        $records = array_merge($records, Parser::parse_list_np($list_content));
    }

    if (!$records) {
        continue;
    }
    $total = count($records);
    error_log("importing {$ymd} " . $total);
    Entity::getDb()->query("BEGIN");
    $entity_terms = array();
    $entity_data_terms = array();
    $unit_names = array();

    foreach ($records as $seq => $record) {
        $filename = Entity::getFilename($record);
        $url = Entity::updateUrl($record, $ymd, $filename);
        $record = $filename;
        if (strpos($record, 'idpr') !== false) {
            continue;
        }

        $entry_target = $path . "/entry-json/{$year}/{$ymd}-{$record}.json.gz";
        if (!file_exists($entry_target)) {
            continue;
        }

        $obj = json_decode(gzdecode(file_get_contents($entry_target)));
        $oid = null;
        $name = null;
        $title = null;
        $companies = new StdClass;
        $companies->names = array();
        $companies->ids = array();
        $companies->name_key = new StdClass;
        $companies->id_key = new StdClass;
        foreach ($obj as $k => $v) {
            if (!is_scalar($v) or !trim($v)) {
                continue;
            }
            foreach (array('得標廠商', '廠商名稱') as $m) {
                if (strrpos($k, $m) === strlen($k) - strlen($m)) {
                    $companies->names[$v] = $v;
                    if (!property_exists($companies->name_key, $v)) {
                        $companies->name_key->{$v} = array();
                    }
                    $companies->name_key->{$v}[$k] = $k;
                }
            }

            foreach (array('廠商代碼') as $m) {
                if (strrpos($k, $m) === strlen($k) - strlen($m)) {
                    $companies->ids[$v] = $v;
                    if (!property_exists($companies->id_key, $v)) {
                        $companies->id_key->{$v} = array();
                    }
                    $companies->id_key->{$v}[$k] = $k;
                }
            }
        }
        $companies->ids = array_values($companies->ids);
        $companies->names = array_values($companies->names);
        foreach ($companies->id_key as $k => $v) {
            $companies->id_key->{$k} = array_values($v);
        }
        foreach ($companies->name_key as $k => $v) {
            $companies->name_key->{$k} = array_values($v);
        }

        if ($obj->type == '系統錯誤訊息') {
            continue;
        }
        $brief = new StdClass;
        if (in_array($obj->type, array(
            '重大/巨額採購效益評估公告',
        ))) {
            if ($check_include($obj, ['標案內容:招標機關代碼', '標案內容:招標機關名稱', '標案內容:標案案號', '標案內容:標案名稱'])) {
                $oid = $obj->{'標案內容:招標機關代碼'};
                $name = $obj->{'標案內容:招標機關名稱'};
                $caseid = $obj->{'標案內容:標案案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } else {
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("找不到欄位值");
            }
        } else if (in_array($obj->type, array(
            '公開招標公告', '公開招標更正公告',
            '限制性招標(經公開評選或公開徵求)公告', '限制性招標(經公開評選或公開徵求)更正公告',
            '經公開評選或公開徵求之限制性招標公告', '經公開評選或公開徵求之限制性招標更正公告',
            '選擇性招標(建立合格廠商名單)公告', '選擇性招標(建立合格廠商名單)更正公告',
            '選擇性招標(建立合格廠商名單後續邀標)公告',
            '選擇性招標(建立合格廠商名單後續邀標)更正公告',
            '選擇性招標(個案)公告', '選擇性招標(個案)更正公告',
            '公開取得報價單或企劃書公告', '公開取得報價單或企劃書更正公告',
            '公開取得電子報價單公告',
        ))) {
            if ($check_include($obj, array('機關資料:機關代碼', '採購資料:標案案號', '機關資料:機關名稱'))) {
                $oid = $obj->{'機關資料:機關代碼'};
                $name = $obj->{'機關資料:機關名稱'};
                $caseid = $obj->{'採購資料:標案案號'};
                $title = $obj->{'採購資料:標案名稱'};
                $brief->category = $obj->{'採購資料:標的分類'};

            } else {
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("找不到欄位值");
            }
        } elseif (in_array($obj->type, array(
            '決標公告', '更正決標公告',
            '定期彙送', '更正定期彙送',
        ))) {
            if ($check_include($obj, array('機關資料:機關代碼', '採購資料:標案案號', '機關資料:機關名稱'))) {
                $oid = $obj->{'機關資料:機關代碼'};
                $name = $obj->{'機關資料:機關名稱'};
                $caseid = $obj->{'採購資料:標案案號'};
                $title = $obj->{'採購資料:標案名稱'};
            } else if ($check_include($obj, array('機關資料:機關代碼', '已公告資料:標案案號', '機關資料:機關名稱'))) {
                $oid = $obj->{'機關資料:機關代碼'};
                $name = $obj->{'機關資料:機關名稱'};
                $caseid = $obj->{'已公告資料:標案案號'};
                $title = $obj->{'已公告資料:標案名稱'};
            } else {
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("test");
            }

        } elseif (in_array($obj->type, array(
            '公開徵求廠商提供參考資料', '公開徵求廠商提供參考資料更正公告',
            '公開徵求廠商提供參考資料公告',
        ))) {
            if ($check_include($obj, array('標案內容:機關代碼', '標案內容:案號', '標案內容:機關名稱'))) {
                $oid = $obj->{'標案內容:機關代碼'};
                $name = $obj->{'標案內容:機關名稱'};
                $caseid = $obj->{'標案內容:案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } else if ($check_include($obj, array('標案內容:機關代碼', '標案內容:標案案號', '標案內容:機關名稱'))) {
                $oid = $obj->{'標案內容:機關代碼'};
                $name = $obj->{'標案內容:機關名稱'};
                $caseid = $obj->{'標案內容:標案案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } else {
                continue;
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("找不到欄位值");
            }
        } elseif (in_array($obj->type, array(
            '財物變賣公告', '財物變賣更正公告',
            '財物出租公告', '財物出租更正公告',
            '招標文件公開閱覽公告資料公告', '招標文件公開閱覽公告資料更正公告',
        ))) {
            if ($check_include($obj, array('標案內容:機關名稱', '標案內容:標案案號'))) {
                $name = $obj->{'標案內容:機關名稱'};
                $caseid = $obj->{'標案內容:標案案號'};
                if (property_exists($obj, '標案內容:財物名稱')) {
                    $title = $obj->{'標案內容:財物名稱'};
                } else {
                    $title = $obj->{'標案內容:標案名稱'};
                }
            } else {
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("找不到欄位值");
            }
        } elseif (in_array($obj->type, array(
            '拒絕往來廠商名單公告', '拒絕往來廠商名單更正公告',
        ))) {
            if ($check_include($obj, array('標案內容:刊登機關', '標案內容:標案案號'))) {
                $name = $obj->{'標案內容:刊登機關'};
                $caseid = $obj->{'標案內容:標案案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } else {
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                continue;
                throw new Exception("找不到欄位值");
            }
        } elseif (in_array($obj->type, array(
            '公示送達公告', '公示送達更正公告',
        ))) {
            if ($check_include($obj, array('標案內容:提報機關', '標案內容:案號'))) {
                $name = $obj->{'標案內容:提報機關'};
                $caseid = $obj->{'標案內容:案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } elseif ($check_include($obj, array('標案內容:提報機關', '標案內容:標案案號'))) {
                $name = $obj->{'標案內容:提報機關'};
                $caseid = $obj->{'標案內容:標案案號'};
                $title = $obj->{'標案內容:標案名稱'};
            } else {
                continue;
                error_log($entry_target);
                error_log($obj->url);
                error_log($obj->type);
                throw new Exception("找不到欄位值");
            }
        } elseif (in_array($obj->type, array(
            '無法決標公告', '更正無法決標公告',
            '撤銷無法決標公告', '撤銷更正無法決標公告',
        ))) {
            $oid = $obj->{'無法決標公告:機關代碼'};
            $name = $obj->{'無法決標公告:機關名稱'};
            $caseid = $obj->{'無法決標公告:標案案號'};
            $title = $obj->{'無法決標公告:標案名稱'};
        } else {
            error_log($entry_target);
            error_log($obj->url);
            error_log($obj->type);
            throw new Exception("找不到 {$obj->type}");
        }
        if (!is_null($name) and property_exists($oid_mapping->name_dup, $name)) {
            $name = $oid_mapping->name_dup->{$name};
        }
        if (!is_null($oid) and property_exists($oid_mapping->oid_dup, $oid)) {
            $oid = $oid_mapping->oid_dup->{$oid};
        }
        if (is_null($oid)) {
            $oid = $oid_mapping->name_to_oid->{$name};
        }
        if (!is_null($oid) and !is_null($name) and trim($name) !== '' and !array_key_exists($oid, $unit_names)) {
            $unit_names[$oid] = $name;
        }
        if (is_null($title) or trim($title) == '') {
            // 真的 title 是空白的例外
            if (!in_array($obj->url, array(
                'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20101217&fn=RML-2-80744.xml',
            ))) {
            error_log($entry_target);
            error_log($obj->url);
            error_log($obj->type);
            throw new Exception("title not found");
            }
        }
        $brief->title = $title;
        $brief->type = $obj->type;
        $brief->companies = $companies;

        $keys = array_keys(get_object_vars($obj));

        $entity_terms[] = sprintf("(%d, %s, %s, %s, %s)",
            intval($ymd), // date
            Entity::getDb()->quoteWithColumn('filename', str_replace('.xml', '', $record)),
            Entity::getDb()->quoteWithColumn('oid', $oid),
            Entity::getDb()->quoteWithColumn('brief', json_encode($brief, JSON_UNESCAPED_UNICODE)),
            Entity::getDb()->quoteWithColumn('job_number', $caseid)
        );
        $entity_data_terms[] = sprintf("(%d, %s, %s, %s)",
            intval($ymd), // date
            Entity::getDb()->quoteWithColumn('filename', str_replace('.xml', '', $record)),
            Entity::getDb()->quoteWithColumn('data', json_encode($obj, JSON_UNESCAPED_UNICODE)),
            Entity::getDb()->quoteWithColumn('keys', json_encode($keys, JSON_UNESCAPED_UNICODE))
        );
    }
    if (count($entity_terms)) {
        Entity::getDb()->query("DELETE FROM entity WHERE date = {$ymd}");
        Entity::getDb()->query("DELETE FROM entity_data WHERE date = {$ymd}");
        Entity::getDb()->query("INSERT INTO entity (date, filename, oid, brief, job_number) VALUES " . implode(',', $entity_terms));
        Entity::getDb()->query("INSERT INTO entity_data (date, filename, data, keys) VALUES " . implode(',', $entity_data_terms));
        error_log("inserted " . count($entity_terms) . " records");
        $entity_terms = array();
        $entity_data_terms = array();
    }

    // 補齊 unit 表：機關代碼在 unit 表裡不存在的話，就用這批資料裡看到的機關名稱補上去。
    // 只補「原本不存在」的 oid，不覆蓋既有資料，避免 oidmapping.json 是舊資料造成的名稱誤蓋。
    if (count($unit_names)) {
        $oid_terms = array();
        foreach (array_keys($unit_names) as $oid) {
            $oid_terms[] = Entity::getDb()->quoteWithColumn('oid', $oid);
        }
        $existing_oids = array();
        $res = Entity::getDb()->query("SELECT oid FROM unit WHERE oid IN (" . implode(',', $oid_terms) . ")");
        while ($row = $res->fetch_assoc()) {
            $existing_oids[$row['oid']] = true;
        }
        $new_unit_terms = array();
        foreach ($unit_names as $oid => $name) {
            if (array_key_exists($oid, $existing_oids)) {
                continue;
            }
            $new_unit_terms[] = sprintf("(%s, %s)",
                Entity::getDb()->quoteWithColumn('oid', $oid),
                Entity::getDb()->quoteWithColumn('name', $name)
            );
        }
        if (count($new_unit_terms)) {
            Entity::getDb()->query("INSERT INTO unit (oid, name) VALUES " . implode(',', $new_unit_terms));
            error_log("inserted " . count($new_unit_terms) . " new unit mapping(s)");
        }
    }

    Entity::getDb()->query("COMMIT");
}
