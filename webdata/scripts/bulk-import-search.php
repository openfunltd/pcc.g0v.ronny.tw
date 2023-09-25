<?php

include(__DIR__  . '/../init.inc.php');
Pix_Table::$_save_memory = true;

$end = $_SERVER['argv'][2] ? strtotime($_SERVER['argv'][2]) : time();
$start = $_SERVER['argv'][1] ? strtotime($_SERVER['argv'][1]) - 1 : ($end - 86400 * 5);

$date = date('Ymd', $start);

while (true) {
    $date = Entity::search("date > {$date}")->order("date ASC")->first()->date;
    if (!$date) {
        break;
    }
    if ($date > date('Ymd', $end)) {
        break;
    }

	error_log("indexing {$date}");
    $entity_datas = array();
    foreach (EntityData::search(array('date' => $date)) as $entitydata) {
        $entity_datas[$entitydata->filename] = ($entitydata->data);
    }
    fwrite(STDERR, chr(27) . "kindex{$date}" . chr(27) . "\\");
    foreach (Entity::Search(array('date' => $date)) as $entity) {
        $id = $entity->date . '-' . $entity->filename;

        $brief = json_decode($entity->brief);
        unset($brief->companies->name_key);
        unset($brief->companies->id_key);

        $entity_data = json_decode($entity_datas[$entity->filename]);
        $data = [
            'type' => $brief->type,
            'title' => $brief->title,
            'date' => $entity->date,
            'oid' => $entity->oid,
            'companies' => $brief->companies,
            'special_budget' => property_exists($entity_data, '採購資料:是否含特別預算:特別預算類型') ? $entity_data->{'採購資料:是否含特別預算:特別預算類型'} : null,
        ];
        foreach ([
            '機關名稱',
            '單位名稱',
            '聯絡人',
            '招標方式',
            '決標方式',
            '標的分類',
            '財物採購性質',
            '採購金額級距',
            '決標資料類別',
            '是否受機關補助',
            '依據法條',
            '歸屬計畫類別',
        ] as $k) {
            foreach ($entity_data as $key => $value) {
                if (strpos($key, '英文') === 0) {
                    continue;
                }
                $terms = explode(':', $key);
                if (count($terms) == 2 and $terms[1] == $k) {
                    if ($k == '是否受機關補助') {
                        $k = '機關補助';
                        if ($value == '否' or $value == '') {
                            $value = [];
                        } elseif (is_scalar($value) and strpos($value, '依法須保密者免填補助機關') !== false) {
                            $value = [];
                        } elseif ($value == '是') {
                            $value = [];
                            $value[0] = new StdClass;
                            if (property_exists($entity_data, "{$key}:補助金額")) {
                                if (!preg_match('#^([0-9,]+)元$#', $entity_data->{"{$key}:補助金額"}, $matches)) {
                                    throw new Exception("{$k} 補助金額 not match");
                                }
                                $value[0]->{'補助金額'} = intval(str_replace(',', '', $matches[1]));
                            }

                            if (property_exists($entity_data, "{$key}:補助機關")) {
                                // \u00a0 轉成空白
                                $entity_data->{"{$key}:補助機關"} = str_replace(json_decode('"\u00a0"'), ' ', $entity_data->{"{$key}:補助機關"});
                                if (!preg_match('#^([^ ]+)\s+([^ ]*)$#', $entity_data->{"{$key}:補助機關"}, $matches)) {
                                    continue;
                                    echo json_encode($entity_data, JSON_UNESCAPED_UNICODE) . "\n";
                                    throw new Exception("{$k} 補助機關 not match: " . json_encode($entity_data->{"{$key}:補助機關"}, JSON_UNESCAPED_UNICODE));
                                }
                                $value[0]->{'補助機關代碼'} = $matches[1];
                                $value[0]->{'補助機關名稱'} = $matches[2];
                            }
                        } else {
                            $oldvalue = $value;
                            if (!is_array($oldvalue)) {
                                echo json_encode($oldvalue, JSON_UNESCAPED_UNICODE) . "\n";
                                echo json_encode($entity_data, JSON_UNESCAPED_UNICODE) . "\n";
                                throw new Exception("{$k} is not array: " . json_encode($oldvalue, JSON_UNESCAPED_UNICODE));
                            }
                            $value = [];
                            foreach ($oldvalue as $v) {
                                if (!property_exists($v, '項次')) {
                                    throw new Exception("{$k} 項次 not exists");
                                }
                                $v->{'項次'} = intval($v->{'項次'});

                                // [{"項次":1,"補助金額":"11,350,000元","補助機關代碼":"A.25","補助機關名稱":"文化部"}]
                                if (!preg_match('#^([0-9,]+)元$#', $v->{'補助金額'}, $matches)) {
                                    continue;
                                    echo json_encode($entity_data, JSON_UNESCAPED_UNICODE) . "\n";
                                    throw new Exception("{$k} 補助金額 not match: " . json_encode($v->{'補助金額'}, JSON_UNESCAPED_UNICODE));
                                }
                                $v->{'補助金額'} = intval(str_replace(',', '', $matches[1]));
                                $value[] = $v;
                            }
                        }
                    } else if (!is_scalar($value)) {
                        echo json_encode($entity_data, JSON_UNESCAPED_UNICODE) . "\n";
                        throw new Exception("{$k} is not scalar");
                    }
                    if (array_key_exists($k, $data)) {
                        echo json_encode($entity_data, JSON_UNESCAPED_UNICODE) . "\n";
                        throw new Exception("{$k} exists");
                    }

                    $data[$k] = $value;
                }
            }
        }
        Elastic::dbBulkInsert('entry', $id, $data);
    }

	error_log("indexed {$date}");
}
Elastic::dbBulkCommit();
