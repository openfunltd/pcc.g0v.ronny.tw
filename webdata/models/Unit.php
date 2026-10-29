<?php

class Unit extends Pix_Table
{
    public function init()
    {
        $this->_name = 'unit';
        $this->_primary = 'oid';

        $this->_columns['oid'] = array('type' => 'varchar', 'size' => 64);
        $this->_columns['name'] = array('type' => 'varchar', 'size' => 64);
    }

    /**
     * 人事總處 10 碼機關代碼換算成標案系統的 unit_id 格式
     * 第 1 碼照抄，之後每 2 碼一層（純數字轉整數），最後 1 碼不用，結尾為 0 的層去掉，
     * 例如 379110500A => 3.79.11.5、A109C0000W => A.10.9C
     *
     * @return string|null 不是 10 碼代碼時回 null
     */
    public static function orgCodeToUnitId($code)
    {
        if (!preg_match('#^([0-9A-Z])([0-9A-Z]{2})([0-9A-Z]{2})([0-9A-Z]{2})([0-9A-Z]{2})[0-9A-Z]$#', $code, $matches)) {
            return null;
        }
        $terms = array_map(function($term) {
            return ctype_digit($term) ? strval(intval($term)) : ltrim($term, '0');
        }, array_slice($matches, 2));
        while (count($terms) and end($terms) === '0') {
            array_pop($terms);
        }
        return implode('.', array_merge(array($matches[1]), $terms));
    }

    /**
     * 查詢用的 unit_id 換成標案系統實際要查的 unit_id 清單
     * 可以傳人事總處 10 碼代碼；換算結果不在 unit 表時（改制後標案系統沒在用的新代碼），
     * 用 orgmap.json（build-orgmap.php 產生）加上改制前的舊代碼，例如 A.43（大陸委員會）=> A.43、3.57（行政院大陸委員會）。
     * 原代碼仍保留，因為有些新代碼本身沒有標案，但下屬機關有（例如臺中市政府 3.87 底下的 3.87.*）
     *
     * @return array unit_ids（要查的代碼，第一個是換算後的原代碼）、name（機關名稱，unit 表沒有時用官方名稱）
     */
    public static function resolveUnitIds($unit_id)
    {
        $unit_id = self::orgCodeToUnitId($unit_id) ?? $unit_id;
        if ($unit = Unit::find($unit_id)) {
            return array('unit_ids' => array($unit_id), 'name' => $unit->name);
        }
        $map = json_decode(file_get_contents(__DIR__ . '/../orgmap.json'));
        if (property_exists($map, $unit_id)) {
            return array(
                'unit_ids' => array_values(array_unique(array_merge(array($unit_id), $map->{$unit_id}->unit_ids))),
                'name' => $map->{$unit_id}->name,
            );
        }
        return array('unit_ids' => array($unit_id), 'name' => null);
    }
}
