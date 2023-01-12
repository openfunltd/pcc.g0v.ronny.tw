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
        if (property_exists($data, 'url')) {
            $data->url = Entity::updateURL($data->url, $entity->date, $entity->filename);
        }
        return $data;
    }
}
