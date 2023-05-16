<?php

class ApiController extends Pix_Controller
{
    public function init()
    {
        $this->base = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
    }

    public function getinfoAction()
    {
        return $this->json(array(
            '最新資料時間' => date('c', strtotime(Entity::search(1)->max('date')->date)),
            '最舊資料時間' => date('c', strtotime(Entity::search(1)->min('date')->date)),
            '公告數' => count(Entity::search(1)),
        ));
    }

    public function indexAction()
    {
        return $this->json(array(
            array(
                'url' => $this->base . '/api/getinfo',
                'description' => '列出最新最舊資料時間和總公告數等資訊，無參數',
            ),
            array(
                'url' => $this->base . '/api/',
                'description' => '列出 API 列表，無參數',
            ),
            array(
                'url' => $this->base . '/api/searchbycompanyname',
                'description' => '依公司名稱搜尋, query: 公司名稱, page: 頁數(1開始)',
            ),
            array(
                'url' => $this->base . '/api/searchbytitle',
                'description' => '依標案名稱搜尋, query: 公司名稱, page: 頁數(1開始)',
            ),
            array(
                'url' => $this->base . '/api/listbydate',
                'description' => '列出特定日期的標案公告列表, date: 日期，以 YYYYMMDD 為格式',
            ),
            array(
                'url' => $this->base . '/api/listbyunit',
                'description' => '列出特定機關的標案公告列表, unit_id: 機關代碼，可透過 /api/unit 取得代碼列表',
            ),
            array(
                'url' => $this->base . '/api/unit',
                'description' => '列出機關列表，沒有參數',
            ),
            array(
                'url' => $this->base . '/api/tender',
                'description' => '列出某個標案代碼的公告詳細資料, unit_id: 單位代碼, job_number: 標案代碼',
            ),
            array(
                'url' => $this->base . '/api/searchallspecialbudget',
                'description' => '列出所有的特別預算',
            ),
            array(
                'url' => $this->base . '/api/searchbyspecialbudget',
                'description' => '搜尋特定特別預算的標案 query: 特別預算名稱',
            ),
        ));
    }

    public function searchbycompanyidAction()
    {
        $start = microtime(true);

        $result = new StdClass;
        $result->query = strval($_GET['query']);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'term' => array('companies.ids' => $result->query),
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
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            if (array_key_exists('columns', $_GET)) {

                $data = json_decode($entity->data->data);
                $data = EntityData::updateURL($data, $entity);
                $keys = $_GET['columns'];
                $record['detail'] = array_combine($keys, array_map(function($k) use ($data) { return $data->{$k}; }, $keys));
            }
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    public function searchallspecialbudgetAction()
    {
        $start = microtime(true);

        $result = new StdClass;

        $curl = curl_init();
        $cmd = array(
            'size' => 0,
            'aggs' => array(
                'uniq_specialbudget' => array(
                    'terms' => array('field' => 'special_budget'),
                ),
            ),
        );

        curl_setopt($curl, CURLOPT_URL, getenv('SEARCH_URL') . '/entry/_search');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cmd));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $ret = curl_exec($curl);
        $ret = json_decode($ret);
        $buckets = ($ret->aggregations->uniq_specialbudget->buckets);
        $buckets = array_map(function($bucket){
            $bucket->search_api_url = $this->base . '/api/searchbyspecialbudget?query=' . urlencode($bucket->key);
            return $bucket;
        }, $buckets);
        $result->buckets = $buckets;
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    public function searchbyspecialbudgetAction()
    {
        $start = microtime(true);

        $result = new StdClass;
        $result->query = strval($_GET['query']);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => array('query' => sprintf('special_budget:"%s"', $result->query)),
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
        $entity_datas = array();
        foreach (EntityData::search(1)->searchIn(array('date', 'filename'), $match_ids) as $entity_data) {
            $entity_datas[$entity_data->date . ':' . $entity_data->filename] = json_decode($entity_data->data);
        }

        foreach (Entity::search(1)->searchIn(array('date', 'filename'), $match_ids)->order('date DESC') as $entity) {
            $record = $entity->toArray();
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['special_budget'] = $entity_datas[$entity->date . ':' . $entity->filename]->{'採購資料:是否含特別預算:特別預算類型'};
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
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
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            if (array_key_exists('columns', $_GET)) {

                $data = json_decode($entity->data->data);
                $data = EntityData::updateURL($data, $entity);
                $keys = $_GET['columns'];
                $record['detail'] = array_combine($keys, array_map(function($k) use ($data) { return $data->{$k}; }, $keys));
            }
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
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            if (array_key_exists('columns', $_GET)) {

                $data = json_decode($entity->data->data);
                $data = EntityData::updateURL($data, $entity);
                $keys = $_GET['columns'];
                $record['detail'] = array_combine($keys, array_map(function($k) use ($data) { return $data->{$k}; }, $keys));
            }
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    public function listbydateAction()
    {
        $date = intval($_GET['date']);
        $entities = Entity::search(array('date' => $date));
        $units = Unit::search(1)->searchIn('oid', $entities->toArray('oid'))->toArray('name');
        $unit_oids = array();
        foreach ($units as $oid => $name) {
            $unit_oids[$oid] = $name;
        }

        $result = new StdClass;
        foreach ($entities as $entity) {
            $record = $entity->toArray();
            $record['brief'] = json_decode($record['brief']);
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_d']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_d']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        return $this->json($result);
    }

    public function unitAction()
    {
        return $this->json(
            Unit::search(1)->toArray('name')
        );
    }

    public function listbyunitAction()
    {
        $oid = strval($_GET['unit_id']);
        $page = intval($_GET['page']) ?: 1;

        $entities = Entity::search(array('oid' => $oid))->order('date DESC')->offset(1000 * ($page - 1))->limit(1000);
        $unit_name = Unit::find($oid)->name;

        $result = new StdClass;
        $result->page = $page;
        $result->total = count(Entity::search(['oid' => $oid]));
        $result->total_page = ceil($result->total / 1000);
        $result->unit_name = $unit_name;
        foreach ($entities as $entity) {
            $record = $entity->toArray();
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['brief'] = json_decode($record['brief']);
            $record['unit_name'] = $unit_name;
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        return $this->json($result);
    }

    public function tenderAction()
    {
        $oid = strval($_GET['unit_id']);
        $job_number = strval($_GET['job_number']);

        $entities = Entity::search(array('oid' => $oid, 'job_number' => $job_number))->order('date ASC');
        $unit_name = Unit::find($oid)->name;

        $result = new StdClass;
        $result->unit_name = $unit_name;
        foreach ($entities as $entity) {
            $record = $entity->toArray();
            $record['brief'] = json_decode($record['brief']);
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $data = json_decode($entity->data->data);
            $data = EntityData::updateURL($data, $entity);
            $keys = json_decode($entity->data->keys);
            $record['detail'] = array_combine($keys, array_map(function($k) use ($data) { return $data->{$k}; }, $keys));
            $record['unit_name'] = $unit_name;
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        return $this->json($result);
    }
}
