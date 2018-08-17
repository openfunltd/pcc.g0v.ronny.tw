<?php

class EntityRow extends Pix_Table_Row
{
    public function getData()
    {
        $ret = new StdClass;
        $obj = json_decode($this->data->data);
        foreach (json_decode($this->data->keys) as $k) {
            $ret->{$k} = $obj->{$k};
        }
        return $ret;
    }

    public function getBrief($k)
    {
        return json_decode($this->brief)->{$k};
    }

    public function link()
    {
        return sprintf('/index/case/%s/%s/%s/%s',
            urlencode($this->oid),
            urlencode($this->job_number),
            urlencode($this->date),
            urlencode($this->filename)
        );
    }
}

class Entity extends Pix_Table
{
    public function init()
    {
        $this->_name = 'entity';
        $this->_primary = array('date', 'filename');
        $this->_rowClass = 'EntityRow';

        $this->_columns['date'] = array('type' => 'int');
        $this->_columns['filename'] = array('type' => 'varchar', 'size' => 16);
        $this->_columns['oid'] = array('type' => 'varchar', 'size' => 64);
        $this->_columns['brief'] = array('type' => 'jsonb');
        $this->_columns['job_number'] = array('type' => 'varchar', 'size' => 64);

        $this->addIndex('oid_jobnumber_date', array('oid', 'job_number', 'date'));

        $this->_relations['data'] = array('rel' => 'has_one', 'type' => 'EntityData');
    }
}
