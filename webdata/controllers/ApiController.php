<?php

/**
 * @OA\Info(title="台灣政府採購公告 API", version="0.1")
 */
class ApiController extends Pix_Controller
{
    public function init()
    {
        $this->base = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
    }

    /**
     * 取得資料狀況 API
     *
     * @OA\Get(
     *  path="/api/getinfo", summary="取得資料狀況 API", description="取得資料狀況 API",
     *  @OA\Response( response="200", description="取得資料狀況 API",
     *    @OA\JsonContent(
     *      @OA\Property(property="最新資料時間", type="string", example="2021-01-01T00:00:00+08:00"),
     *      @OA\Property(property="最舊資料時間", type="string", example="2021-01-01T00:00:00+08:00"),
     *      @OA\Property(property="公告數", type="integer", example=100),
     *    ),
     *  ),
     * )
     */
    public function getinfoAction()
    {
        return $this->json(array(
            '最新資料時間' => date('c', strtotime(Entity::search(1)->max('date')->date)),
            '最舊資料時間' => date('c', strtotime(Entity::search(1)->min('date')->date)),
            '公告數' => count(Entity::search(1)),
        ));
    }

    /**
     * API 列表
     *
     * @OA\Get(
     *   path="/api/", summary="API 列表", description="API 列表",
     *   @OA\Response( response="200", description="API 列表",
     *     @OA\JsonContent( type="array",
     *       example={{
     *         "url": "https://pcc.g0v.ronny.tw/api/getinfo",
     *         "description": "列出最新最舊資料時間和總公告數等資訊，無參數"
     *         },
     *         {
     *         "url": "https://pcc.g0v.ronny.tw/api/",
     *         "description": "列出 API 列表"
     *       }},
     *       @OA\Items( type="object",
     *         @OA\Property(property="url", type="string", example="https://pcc.g0v.ronny.tw/api/"),
     *         @OA\Property(property="description", type="string", example="列出 API 列表"),
     *       ),
     *     ),
     *   ),
     * )
     */
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

    /**
     * 依公司統一編號搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbycompanyid", summary="依公司統一編號搜尋 API", description="依公司統一編號搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="公司統一編號", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer") ),
     *   @OA\Parameter( name="columns", in="query", description="要顯示詳細欄位", required=false, @OA\Schema(type="string") ),
     *   @OA\Response( response="200", description="依公司名稱搜尋 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="搜尋公司名稱"),
     *       @OA\Property(property="page", type="integer", example=1),
     *       @OA\Property(property="total_records", type="integer", example=304),
     *       @OA\Property(property="total_pages", type="integer", example=4),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="detail", type="object",
     *             description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="url", type="string", example="https://web.pcc.gov.tw/prkms/tender/"),
     *             @OA\Property(property="機關資料:機關代碼", type="string", example="3.76.47"),
     *             @OA\Property(property="機關資料:機關名稱", type="string", example="彰化縣政府"),
     *             @OA\Property(property="fetched_at", type="string", example="2017-08-28T15:03:43+08:00"),
     *           ),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $result->total_records = $ret->hits->total->value;
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

    /**
     * 列出所有的特別預算 API
     *
     * @OA\Hidden(
     *   path="/api/searchallspecialbudget", summary="列出所有的特別預算 API", description="列出所有的特別預算 API",
     *   @OA\Response( response="200", description="列出所有的特別預算 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="budgets", type="array",
     *         @OA\Items( type="object", example={{
     *           "search_api_url": "https://pcc.g0v.ronny.tw/api/searchbyspecialbudget?query=特別預算名稱１",
     *           },
     *           {
     *           "search_api_url": "https://pcc.g0v.ronny.tw/api/searchbyspecialbudget?query=特別預算名稱２",
     *           }},
     *           @OA\Property(property="search_api_url", type="string", example="特別預算名稱"),
     *         ),
     *       ),
     *     @OA\Property(property="took", type="number", example=0.123),
     *     ),
     *   ),
     * )
     */
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

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $buckets = ($ret->aggregations->uniq_specialbudget->buckets);
        $buckets = array_map(function($bucket){
            $bucket->search_api_url = $this->base . '/api/searchbyspecialbudget?query=' . urlencode($bucket->key);
            return $bucket;
        }, $buckets);
        $result->buckets = $buckets;
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    /**
     * 搜尋特定特別預算的標案 API
     *
     * @OA\Get(
     *   path="/api/searchbyspecialbudget", summary="搜尋特定特別預算的標案 API", description="搜尋特定特別預算的標案 API",
     *   @OA\Parameter( name="query", in="query", description="特別預算名稱", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer") ),
     *   @OA\Response( response="200", description="搜尋特定特別預算的標案 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="搜尋公司名稱"),
     *       @OA\Property(property="page", type="integer", example=1),
     *       @OA\Property(property="total_records", type="integer", example=304),
     *       @OA\Property(property="total_pages", type="integer", example=4),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $result->total_records = $ret->hits->total->value;
        $result->total_pages = ceil($ret->hits->total->value / 100);
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

    /**
     * 依公司名稱搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbycompanyname", summary="依公司名稱搜尋 API", description="依公司名稱搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="公司名稱", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer") ),
     *   @OA\Parameter( name="columns", in="query", description="要顯示詳細欄位", required=false, @OA\Schema(type="string") ),
     *   @OA\Response( response="200", description="依公司名稱搜尋 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="搜尋公司名稱"),
     *       @OA\Property(property="page", type="integer", example=1),
     *       @OA\Property(property="total_records", type="integer", example=304),
     *       @OA\Property(property="total_pages", type="integer", example=4),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *           @OA\Property(property="detail", type="object",
     *             description="This property may not necessarily appear in the response.",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="url", type="string", example="https://web.pcc.gov.tw/prkms/tender/"),
     *             @OA\Property(property="機關資料:機關代碼", type="string", example="3.76.47"),
     *             @OA\Property(property="機關資料:機關名稱", type="string", example="彰化縣政府"),
     *             @OA\Property(property="fetched_at", type="string", example="2017-08-28T15:03:43+08:00"),
     *           ),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $result->total_records = $ret->hits->total->value;
        $result->total_pages = ceil($ret->hits->total->value / 100);
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
                $record['detail'] = [];
                foreach ($keys as $k) {
                    if (strpos($k, '/') === 0) {
                        foreach ($data as $key => $value) {
                            if (preg_match($k, $key)) {
                                $record['detail'][$key] = $value;
                            }
                        }
                    } else {
                        $record['detail'][$k] = $data->{$k};
                    }
                }
            }
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        return $this->json($result);
    }

    /**
     * 依標案名稱搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbytitle", summary="依標案名稱搜尋 API", description="依標案名稱搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="標案名稱", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer") ),
     *   @OA\Parameter( name="columns", in="query", description="要顯示詳細欄位", required=false, @OA\Schema(type="string") ),
     *   @OA\Response( response="200", description="依公司名稱搜尋 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="搜尋公司名稱"),
     *       @OA\Property(property="page", type="integer", example=1),
     *       @OA\Property(property="total_records", type="integer", example=304),
     *       @OA\Property(property="total_pages", type="integer", example=4),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *           @OA\Property(property="detail", type="object",
     *             description="This property may not necessarily appear in the response.",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="url", type="string", example="https://web.pcc.gov.tw/prkms/tender/"),
     *             @OA\Property(property="機關資料:機關代碼", type="string", example="3.76.47"),
     *             @OA\Property(property="機關資料:機關名稱", type="string", example="彰化縣政府"),
     *             @OA\Property(property="fetched_at", type="string", example="2017-08-28T15:03:43+08:00"),
     *           ),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
    public function searchbytitleAction()
    {
        $start = microtime(true);

        $result = new StdClass;
        $result->query = strval($_GET['query']);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => [
                    'query' => $result->query,
                    'default_field' => 'title',
                ],
            ),
            'size' => 100,
            'from' => $result->page * 100 - 100,
            'sort' => array('date' => 'desc'),
        );

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $result->total_records = $ret->hits->total->value;
        $result->total_pages = ceil($ret->hits->total->value / 100);
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

    /**
     * 列出特定日期的標案公告列表 API
     *
     * @OA\Get(
     *   path="/api/listbydate", summary="列出特定日期的標案公告列表 API", description="列出特定日期的標案公告列表 API",
     *   @OA\Parameter( name="date", in="query", description="日期(YYYYMMDD)", required=true, @OA\Schema(type="integer") ),
     *   @OA\Response( response="200", description="列出特定日期的標案公告列表 API",
     *     @OA\JsonContent( type="object",
     *     @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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

    /**
     *	列出機關列表 API
     *	@OA\Get(
     *	  path="/api/unit", summary="列出機關列表 API", description="列出機關列表 API",
     *	  @OA\Response( response="200", description="列出機關列表 API",
     *	    @OA\JsonContent( type="object",
     *	      @OA\Property(property="unit_id_01", type="string", example="機關名稱１"),
     *	      @OA\Property(property="unit_id_02", type="string", example="機關名稱２"),
     *	    ),
     *	  ),
     *	)
     */
    public function unitAction()
    {
        return $this->json(
            Unit::search(1)->toArray('name')
        );
    }

    /**
     * 列出特定機關的標案公告列表 API
     * @OA\Get(
     *   path="/api/listbyunit", summary="列出特定機關的標案公告列表 API", description="列出特定機關的標案公告列表 API",
     *   @OA\Parameter( name="unit_id", in="query", description="機關代碼", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer") ),
     *   @OA\Response( response="200", description="列出特定機關的標案公告列表 API",
     *   @OA\JsonContent( type="object",
     *     @OA\Property(property="page", type="integer", example=1),
     *     @OA\Property(property="total", type="integer", example=304),
     *     @OA\Property(property="total_page", type="integer", example=4),
     *     @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *     @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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

    /**
     * 列出某個標案代碼的公告詳細資料 API
     * @OA\Get(
     *   path="/api/tender", summary="列出某個標案代碼的公告詳細資料 API", description="列出某個標案代碼的公告詳細資料 API",
     *   @OA\Parameter( name="unit_id", in="query", description="機關代碼", required=true, @OA\Schema(type="string") ),
     *   @OA\Parameter( name="job_number", in="query", description="標案代碼", required=true, @OA\Schema(type="string") ),
     *   @OA\Response( response="200", description="列出某個標案代碼的公告詳細資料 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="unit_name", type="string", example="彰化縣政府"),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="title", type="string", example="標案名稱"),
     *             @OA\Property(property="category", type="string", example="標的分類",
     *               description="This property may not necessarily appear in the response."),
     *             @OA\Property(property="companies", type="object",
     *               @OA\Property(property="ids", type="array",
     *                 @OA\Items( type="string"), example={"公司１統編", "公司２統編"}),
     *               @OA\Property(property="names", type="array",
     *                 @OA\Items( type="string"), example={"公司１名稱", "公司２名稱"}),
     *               @OA\Property(property="id_key", type="array",
     *		       @OA\Items( type="object",
     *		         @OA\Property(property="公司１統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商1:廠商代碼"}),
     *		         @OA\Property(property="公司２統編", type="array", @OA\Items( type="string"),
     *		           example={"投標廠商:投標廠商2:廠商代碼"}),
     *		       ),
     *               ),
     *               @OA\Property(property="name_key", type="array",
     *                 @OA\Items( type="object",
     *                   @OA\Property(property="公司１名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商1:廠商名稱", "決標品項:第1品項:得標廠商1:得標廠商"}),
     *                   @OA\Property(property="公司２名稱", type="array", @OA\Items( type="string"),
     *                     example = {"投標廠商:投標廠商2:廠商名稱", "決標品項:第1品項:未得標廠商1:未得標廠商"}),
     *                 ),
     *               ),
     *             ),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="nwda1120349"),
     *           @OA\Property(property="unit_id", type="string", example="A.17.2.1"),
     *           @OA\Property(property="detail", type="object",
     *             @OA\Property(property="type", type="string", example="公告類型"),
     *             @OA\Property(property="url", type="string", example="https://web.pcc.gov.tw/prkms/tender/"),
     *             @OA\Property(property="機關資料:機關代碼", type="string", example="3.76.47"),
     *             @OA\Property(property="機關資料:機關名稱", type="string", example="彰化縣政府"),
     *             @OA\Property(property="fetched_at", type="string", example="2017-08-28T15:03:43+08:00"),
     *           ),
     *           @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=A.17.2.1"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=A.17.2.1&job_number=nwda1120349"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/A.17.2.1"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20230829/BDM-1-70370443"),
     *         ),
     *       ),
     *     ),
     *   ),
     * )
     */
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
