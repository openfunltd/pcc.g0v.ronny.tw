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

    public static function getFilename($url)
    {
        if (preg_match('#unPublish\.award\.(.*)#', $url, $matches)) {
            return   'aaa-' . base64_decode($matches[1]);
        } else if (preg_match('#unPublish\.tender\.(.*)#', $url, $matches)) {
            return   'ttd-' . base64_decode($matches[1]);
        } else if (preg_match('#unPublish\.nonAward\.(.*)#', $url, $matches)) {
            return   'anaa-' . base64_decode($matches[1]);
        } else {
            throw new Exception("unknown url: $url");
        }
    }

    public static function updateUrl($oldurl, $date = null, $filename = null)
    {
        $url = '';
        if (preg_match('#http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do\?ds=(\d+)&fn=(.*)#', $oldurl, $matches)) {
            $url = sprintf("https://web.pcc.gov.tw/prkms/tender/common/noticeDate/redirectPublic?ds=%s&fn=%s", urlencode($matches[1]), urlencode($matches[2]));
        } elseif (preg_match('#^aaa-(\d*)$#', $filename, $matches)) {
            $url = sprintf("https://web.pcc.gov.tw/prkms/urlSelector/common/atm?pk=" . base64_encode($matches[1]));
        } elseif (preg_match('#^anaa-(\d*)$#', $filename, $matches)) {
            $url = sprintf("https://web.pcc.gov.tw/prkms/urlSelector/common/nonAtm?pk=" . base64_encode($matches[1]));
        } elseif (preg_match('#^ttd-(\d*)$#', $filename, $matches)) {
            $url = sprintf("https://web.pcc.gov.tw/prkms/urlSelector/common/tpam?pk=" . base64_encode($matches[1]));
        } else {
            throw new Exception(sprintf("unknown url: %s, date: %s, filename: %s", $oldurl, $date, $filename));
        }
        return $url;
    }
}
