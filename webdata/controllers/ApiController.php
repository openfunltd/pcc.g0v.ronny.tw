<?php

class ApiController extends Pix_Controller
{
    public function getinfoAction()
    {
        return $this->json(array(
            '最新資料時間' => date('c', Entity::search(1)->max('date')->date),
            '最舊資料時間' => date('c', Entity::search(1)->min('date')->date),
            '公告數' => count(Entity::search(1)),
        ));
    }

    public function indexAction()
    {
        return $this->json(array(
            array(
                'url' => '/api/getinfo',
                'description' => '列出最新最舊資料時間和總公告數等資訊，無參數',
            ),
            array(
                'url' => '/api/',
                'description' => '列出 API 列表，無參數',
            ),
            array(
                'url' => '/api/searchbycompanyname',
                'description' => '依公司名稱搜尋, query: 公司名稱, page: 頁數(1開始)',
            ),
            array(
                'url' => '/api/searchbytitle',
                'description' => '依標案名稱搜尋, query: 公司名稱, page: 頁數(1開始)',
            ),
        ));
    }

    public function searchbycompanynameAction()
    {
        $start = microtime(true);

        $result = new StdClass;
        $result->query = strval($_GET['query']);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => array('query' => sprintf('companies.names:"%s"', $result->query)),
            ),
            'size' => 100,
            'from' => $result->page * 100 - 100,
            'sort' => array('date' => 'desc'),
        );

        curl_setopt($curl, CURLOPT_URL, getenv('SEARCH_URL') . '/entry/_search');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cmd));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $ret = curl_exec($curl);
        $ret = json_decode($ret);

        $result->total_records = $ret->hits->total;
        $result->total_pages = ceil($ret->hits->total / 100);
        $result->took = 0;
        $match_ids = array();
        $unit_oids = array();
        foreach ($ret->hits->hits as $hit) {
            $match_ids[] = explode('-', $hit->_id, 2);
            $unit_oids[$hit->_source->oid] = $hit->_source->oid;
        }
        foreach (Unit::search(1)->searchIn('oid', array_keys($unit_oids)) as $unit) {
            $unit_oids[$unit->oid] = $unit->name;
        }
        $result->records = array();
        foreach (Entity::search(1)->searchIn(array('date', 'filename'), $match_ids)->order('date DESC') as $entity) {
            $record = $entity->toArray();
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_oids[$record['oid']];
            $record['unit_url'] = '/index/unit/' . urlencode($record['oid']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    public function searchbytitleAction()
    {
        $start = microtime(true);

        $result = new StdClass;
        $result->query = strval($_GET['query']);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => array('query' => sprintf('title:"%s"', $result->query)),
            ),
            'size' => 100,
            'from' => $result->page * 100 - 100,
            'sort' => array('date' => 'desc'),
        );

        curl_setopt($curl, CURLOPT_URL, getenv('SEARCH_URL') . '/entry/_search');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cmd));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $ret = curl_exec($curl);
        $ret = json_decode($ret);

        $result->total_records = $ret->hits->total;
        $result->total_pages = ceil($ret->hits->total / 100);
        $result->took = 0;
        $match_ids = array();
        $unit_oids = array();
        foreach ($ret->hits->hits as $hit) {
            $match_ids[] = explode('-', $hit->_id, 2);
            $unit_oids[$hit->_source->oid] = $hit->_source->oid;
        }
        foreach (Unit::search(1)->searchIn('oid', array_keys($unit_oids)) as $unit) {
            $unit_oids[$unit->oid] = $unit->name;
        }
        $result->records = array();
        foreach (Entity::search(1)->searchIn(array('date', 'filename'), $match_ids)->order('date DESC') as $entity) {
            $record = $entity->toArray();
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_oids[$record['oid']];
            $record['unit_url'] = '/index/unit/' . urlencode($record['oid']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }
}
