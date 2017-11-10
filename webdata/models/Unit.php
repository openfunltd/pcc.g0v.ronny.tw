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
}
