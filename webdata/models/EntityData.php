<?php

class EntityData extends Pix_Table
{
    public function init()
    {
        $this->_name = 'entity_data';
        $this->_primary = array('date', 'filename');

        $this->_columns['date'] = array('type' => 'int');
        $this->_columns['filename'] = array('type' => 'varchar', 'size' => 16);
        $this->_columns['data'] = array('type' => 'jsonb');
        $this->_columns['keys'] = array('type' => 'jsonb');
    }

    public static function updateURL($data, $entity)
    {
        if (property_exists($data, 'pkPmsMain')) {
            $data->url = sprintf("https://web.pcc.gov.tw/tps/QueryTender/query/searchTenderDetail?pkPmsMain=%s", $data->pkPmsMain);
        } else if (property_exists($data, 'url')) {
            $data->url = Entity::updateURL($data->url, $entity->date, $entity->filename);
        }
        return $data;
    }

    // "1,416,000元" => 1416000，格式不符（空白、不公開…）回 null
    public static function parseAmount($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = preg_replace('#\s+#u', '', strval($value));
        if (!preg_match('#^([0-9,]+)元$#', $value, $matches)) {
            return null;
        }
        return intval(str_replace(',', '', $matches[1]));
    }

    /**
     * 從 Parser 解析出的完整資料抽出統計用欄位，給 import.php 寫入 entity / entity_winner 表
     *
     * @return array dept_name（機關資料:單位名稱）、award_amount（決標資料:總決標金額）、
     *   budget_amount（各區塊的預算金額）、winners（得標廠商統編/名稱/各品項決標金額合計）
     */
    public static function extractStat($data)
    {
        $ret = array(
            'dept_name' => null,
            'award_amount' => null,
            'budget_amount' => null,
            'winners' => array(),
        );

        foreach (array('機關資料:單位名稱', '無法決標公告:單位名稱') as $k) {
            if (property_exists($data, $k) and is_scalar($data->{$k}) and trim($data->{$k}) !== '') {
                $ret['dept_name'] = trim($data->{$k});
                break;
            }
        }
        if (property_exists($data, '決標資料:總決標金額')) {
            $ret['award_amount'] = self::parseAmount($data->{'決標資料:總決標金額'});
        }
        foreach (array('採購資料:預算金額', '已公告資料:預算金額', '標案內容:預算金額', '無法決標公告:預算金額') as $k) {
            if (property_exists($data, $k) and !is_null($amount = self::parseAmount($data->{$k}))) {
                $ret['budget_amount'] = $amount;
                break;
            }
        }

        // 得標廠商名稱只出現在決標品項，統編要從「投標廠商」區塊用名稱對回去
        $name_to_id = array();
        foreach ($data as $k => $v) {
            if (is_scalar($v) and preg_match('#^投標廠商:(投標廠商\d+):廠商名稱$#u', $k, $matches)) {
                $id_key = "投標廠商:{$matches[1]}:廠商代碼";
                if (property_exists($data, $id_key) and trim($data->{$id_key}) !== '') {
                    $name_to_id[trim($v)] = trim($data->{$id_key});
                }
            }
        }
        foreach ($data as $k => $v) {
            if (!is_scalar($v) or trim($v) === '') {
                continue;
            }
            if (!preg_match('#^(決標品項:第\d+品項:得標廠商\d+(?:\(共同投標廠商\))?):得標廠商$#u', $k, $matches)) {
                continue;
            }
            $name = trim($v);
            if (!array_key_exists($name, $ret['winners'])) {
                $ret['winners'][$name] = array(
                    'company_id' => $name_to_id[$name] ?? null,
                    'company_name' => $name,
                    'amount' => null,
                );
            }
            $amount_key = "{$matches[1]}:決標金額";
            if (property_exists($data, $amount_key) and !is_null($amount = self::parseAmount($data->{$amount_key}))) {
                $ret['winners'][$name]['amount'] = ($ret['winners'][$name]['amount'] ?? 0) + $amount;
            }
        }
        $ret['winners'] = array_values($ret['winners']);
        return $ret;
    }
}
