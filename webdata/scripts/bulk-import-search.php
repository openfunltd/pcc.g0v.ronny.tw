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

        $data = [
            'type' => $brief->type,
            'title' => $brief->title,
            'date' => $entity->date,
            'oid' => $entity->oid,
            'companies' => $brief->companies,
            'special_budget' => json_decode($entity_datas[$entity->filename])->{'採購資料:是否含特別預算:特別預算類型'},
        ];
        Elastic::dbBulkInsert('entry', $id, $data);
    }

	error_log("indexed {$date}");
}
Elastic::dbBulkCommit();
