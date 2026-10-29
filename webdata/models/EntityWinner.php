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
}
