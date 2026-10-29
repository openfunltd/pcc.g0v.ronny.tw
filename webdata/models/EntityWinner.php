<?php

// 決標公告的得標廠商，一件公告的每個得標廠商一筆，金額是該廠商各品項決標金額合計
class EntityWinner extends Pix_Table
{
    public function init()
    {
        $this->_name = 'entity_winner';
        $this->_primary = array('date', 'filename', 'company_name');

        $this->_columns['date'] = array('type' => 'int');
        $this->_columns['filename'] = array('type' => 'varchar', 'size' => 16);
        $this->_columns['company_name'] = array('type' => 'text');
        $this->_columns['company_id'] = array('type' => 'varchar', 'size' => 64);
        $this->_columns['amount'] = array('type' => 'bigint');
    }

    /**
     * 沒有統編的得標廠商，用同名廠商在其他公告的統編補上
     *
     * 共同投標的舊公告解析時主得標廠商的統編會被共同投標廠商蓋掉（2026-10 修正 Parser 前），
     * 只拿 8 碼數字的統編當候選（排除打錯、遮罩過的身分證字號），
     * 同名廠商的統編有九成以上是同一個才補，避免同名不同家被誤補。
     *
     * @param int|null $date 只補這一天的公告，null 是補全部
     */
    public static function fillCompanyId($date = null)
    {
        $db = EntityWinner::getDb();
        $date_cond = is_null($date) ? 'TRUE' : ('date = ' . intval($date));
        $w_date_cond = is_null($date) ? 'TRUE' : ('w.date = ' . intval($date));
        $db->query("UPDATE entity_winner w SET company_id = m.company_id
            FROM (
                SELECT company_name, (array_agg(company_id ORDER BY n DESC))[1] AS company_id
                FROM (
                    SELECT company_name, company_id, count(*) AS n FROM entity_winner
                    WHERE company_id ~ '^[0-9]{8}$'
                    AND company_name IN (SELECT company_name FROM entity_winner WHERE company_id IS NULL AND {$date_cond})
                    GROUP BY company_name, company_id
                ) c
                GROUP BY company_name
                HAVING max(n) >= 0.9 * sum(n)
            ) m
            WHERE w.company_id IS NULL AND w.company_name = m.company_name AND {$w_date_cond}");
    }
}
