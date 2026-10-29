<?php

/**
 * @OA\Info(title="台灣政府採購公告 API", version="0.1")
 */
class ApiController extends Pix_Controller
{
    public function init()
    {
        $this->base = (($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

        // 帶 Authorization header 的 credentialed request 不能用 * ，要回傳具體 Origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Max-Age: 3600');

        // 處理 preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "getinfo",
        ]);
        $result = array(
            '最新資料時間' => date('c', strtotime(Entity::search(1)->max('date')->date)),
            '最舊資料時間' => date('c', strtotime(Entity::search(1)->min('date')->date)),
            '公告數' => count(Entity::search(1)),
        );
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => 1,
        ]);
        return $this->json($result);
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "index",
        ]);
        $result = array(
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
                'url' => $this->base . '/api/unitstat',
                'description' => '機關標案統計（各年度標案數/決標金額、得標廠商排行、單位名稱清單）, 範圍參數同 listbyunit',
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
            array(
                'url' => $this->base . '/api/searchbyrefusedvendor',
                'description' => '依公司名稱查詢是否有拒絕往來廠商名單公告記錄（需要歐噴 token）, query: 公司名稱, page: 頁數(1開始)',
            ),
            array(
                'url' => $this->base . '/api/listrecentrefusedvendor',
                'description' => '列出最近 100 筆拒絕往來廠商名單公告（需要歐噴 token）, 無參數',
            ),
        );
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => 1,
        ]);
        return $this->json($result);
    }

    /**
     * 依公司統一編號搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbycompanyid", summary="依公司統一編號搜尋 API", description="依公司統一編號搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="公司統一編號", required=true, @OA\Schema(type="string"), example="38552170" ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="columns[]", in="query", description="要額外多顯示詳細欄位", required=false,
     *     example={"機關資料:聯絡人", "已公告資料:決標方式"},
     *     @OA\Schema( type="array", @OA\Items( type="string"),),
     *   ),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchbycompanyid",
        ]);
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 列出所有的特別預算 API
     *
     * @OA\Get(
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchallspecialbudget",
        ]);
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->buckets),
        ]);
        return $this->json($result);
    }

    /**
     * 搜尋特定特別預算的標案 API
     *
     * @OA\Get(
     *   path="/api/searchbyspecialbudget", summary="搜尋特定特別預算的標案 API", description="搜尋特定特別預算的標案 API",
     *   @OA\Parameter( name="query", in="query", description="特別預算名稱", required=true, @OA\Schema(type="string"), example="前瞻基礎建設"),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer", example=1) ),
     *   @OA\Response( response="200", description="搜尋特定特別預算的標案 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="搜尋特別預算名稱"),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchbyspecialbudget",
        ]);
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 依公司名稱搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbycompanyname", summary="依公司名稱搜尋 API", description="依公司名稱搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="公司名稱", required=true, @OA\Schema(type="string"), example="開放文化基金會" ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="columns[]", in="query", description="要額外多顯示詳細欄位", required=false,
     *     @OA\Schema( type="array", @OA\Items( type="string"), default={"機關資料:聯絡人", "已公告資料:決標方式"} ),
     *   ),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchbycompanyname",
        ]);
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 依標案名稱搜尋 API
     *
     * @OA\Get(
     *   path="/api/searchbytitle", summary="依標案名稱搜尋 API", description="依標案名稱搜尋 API",
     *   @OA\Parameter( name="query", in="query", description="標案名稱", required=true, @OA\Schema(type="string"), example="開放政府國家行動方案" ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="columns[]", in="query", description="要額外多顯示詳細欄位", required=false,
     *     @OA\Schema( type="array", @OA\Items( type="string"), default={"機關資料:聯絡人", "已公告資料:決標方式"} ),
     *   ),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchbytitle",
        ]);
        $start = microtime(true);

        $result = new StdClass;
        $words = explode(' ', strval($_GET['query']));
        foreach ($words as &$word) {
            if (strpos($word, '"') === false) { $word = '"' . $word . '"'; }
        }
        $result->query = implode(' ', $words);
        $result->page = intval($_GET['page']) ?: 1;

        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => [
                    'query' => $result->query,
                    'default_operator' => 'AND',
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 列出特定日期的標案公告列表 API
     *
     * @OA\Get(
     *   path="/api/listbydate", summary="列出特定日期的標案公告列表 API", description="列出特定日期的標案公告列表 API",
     *   @OA\Parameter( name="date", in="query", description="日期(YYYYMMDD)", required=true, @OA\Schema(type="integer"), example=20120701 ),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "listbydate",
        ]);
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
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            $result->records[] = $record;
        }
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "unit",
        ]);
        $result = Unit::search(1)->toArray('name');
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result),
        ]);
        return $this->json($result);
    }

    /**
     * listbyunit / unitstat 共用的範圍參數：unit_id、unit_name、include_children、dept_name、date_from/date_to（及 listbyunit 的 type）
     * 參數錯誤時直接回 400。
     *
     * @return array conditions（entity 表的 SQL 條件，AND 起來用）、unit_id、matched_units（有帶 unit_name 時比對到的機關）
     */
    protected function _getUnitFilter($with_type)
    {
        $oid = trim(strval($_GET['unit_id'] ?? ''));
        $unit_name_prefix = trim(strval($_GET['unit_name'] ?? ''));
        $dept_name_prefix = trim(strval($_GET['dept_name'] ?? ''));
        $include_children = in_array(strval($_GET['include_children'] ?? ''), array('1', 'true'), true);
        if ($oid === '' and $unit_name_prefix === '') {
            OpenFunAPIHelper::errorJson('unit_id 與 unit_name 至少要帶一個');
        }
        foreach (array('date_from', 'date_to') as $k) {
            if (array_key_exists($k, $_GET) and !preg_match('#^\d{8}$#', strval($_GET[$k]))) {
                OpenFunAPIHelper::errorJson("{$k} 格式必須是 YYYYMMDD");
            }
        }

        $db = Entity::getDb();
        $like_prefix = function($prefix) use ($db) {
            return $db->quoteWithColumn('', addcslashes($prefix, '\\%_') . '%');
        };
        // 機關代碼是點號分隔的階層，下屬機關的代碼以「上層代碼.」開頭。
        // 用 >= '代碼.' AND < '代碼/'（'/' 是 '.' 的下一個字元）取代 LIKE '代碼.%'，
        // 才用得到 oid 索引（DB collation 是 C.UTF8，依 code point 排序，兩者等價）
        $oid_with_children = function($oids) use ($db) {
            return implode(' OR ', array_map(function($o) use ($db) {
                return sprintf("(oid = %s OR (oid >= %s AND oid < %s))",
                    $db->quoteWithColumn('oid', $o),
                    $db->quoteWithColumn('oid', $o . '.'),
                    $db->quoteWithColumn('oid', $o . '/')
                );
            }, $oids));
        };

        $conditions = array();
        $matched_units = null;
        if ($unit_name_prefix !== '') {
            // unit_name 前綴比對到的機關；有帶 unit_id 時只取該機關（include_children 時含下屬機關）範圍內的
            $units = Unit::search('name LIKE ' . $like_prefix($unit_name_prefix));
            if ($oid !== '') {
                $units = $include_children ? $units->search($oid_with_children(array($oid))) : $units->search(array('oid' => $oid));
            }
            $units = $units->order('oid')->limit(501)->toArray('name');
            if (count($units) > 500) {
                OpenFunAPIHelper::errorJson('unit_name 比對到超過 500 個機關，請用更長的前綴，或加上 unit_id 縮小範圍');
            }
            $matched_units = array();
            foreach ($units as $unit_oid => $unit_name) {
                $matched_units[] = array('unit_id' => strval($unit_oid), 'unit_name' => $unit_name);
            }
            $oids = array_map('strval', array_keys($units));
            if (!$oids) {
                $conditions[] = '0 = 1';
            } elseif ($include_children and $oid === '') {
                $conditions[] = $oid_with_children($oids);
            } else {
                $conditions[] = 'oid IN (' . implode(',', array_map(function($o) use ($db) { return $db->quoteWithColumn('oid', $o); }, $oids)) . ')';
            }
        } elseif ($include_children) {
            $conditions[] = $oid_with_children(array($oid));
        } else {
            $conditions[] = 'oid = ' . $db->quoteWithColumn('oid', $oid);
        }
        if ($dept_name_prefix !== '') {
            $conditions[] = 'dept_name LIKE ' . $like_prefix($dept_name_prefix);
        }
        if (array_key_exists('date_from', $_GET)) {
            $conditions[] = 'date >= ' . intval($_GET['date_from']);
        }
        if (array_key_exists('date_to', $_GET)) {
            $conditions[] = 'date <= ' . intval($_GET['date_to']);
        }
        if ($with_type and strval($_GET['type'] ?? '') !== '') {
            $conditions[] = "brief->>'type' = " . $db->quoteWithColumn('', strval($_GET['type']));
        }

        return array(
            'conditions' => $conditions,
            'unit_id' => $oid,
            'matched_units' => $matched_units,
        );
    }

    /**
     * 列出特定機關的標案公告列表 API
     * @OA\Get(
     *   path="/api/listbyunit", summary="列出特定機關的標案公告列表 API", description="列出特定機關的標案公告列表 API",
     *   @OA\Parameter( name="unit_id", in="query", description="機關代碼（unit_id 與 unit_name 至少要帶一個；兩個都帶時，只列 unit_id 範圍內（include_children 時含下屬機關）名稱符合 unit_name 前綴的機關）", required=false, @OA\Schema(type="string"), example="3.76.53.97.30") ,
     *   @OA\Parameter( name="unit_name", in="query", description="機關名稱前綴，例如「彰化縣政府」會比對到「彰化縣政府建設處」等，最多比對 500 個機關", required=false, @OA\Schema(type="string"), example="彰化縣政府" ),
     *   @OA\Parameter( name="include_children", in="query", description="帶 1 時一併列出下屬機關（機關代碼以「上層代碼.」開頭者）；只帶 unit_name 時，展開每個比對到的機關的下屬機關", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="dept_name", in="query", description="機關資料:單位名稱前綴，例如「水利資源處」也會比對到「水利資源處水利工程科」，縣政府各處沒有自己的機關代碼時用這個區分", required=false, @OA\Schema(type="string"), example="水利資源處" ),
     *   @OA\Parameter( name="date_from", in="query", description="公告日期下限(YYYYMMDD，含當天)", required=false, @OA\Schema(type="integer"), example=20250101 ),
     *   @OA\Parameter( name="date_to", in="query", description="公告日期上限(YYYYMMDD，含當天)", required=false, @OA\Schema(type="integer"), example=20251231 ),
     *   @OA\Parameter( name="type", in="query", description="公告類型（完全比對 brief.type）", required=false, @OA\Schema(type="string"), example="決標公告" ),
     *   @OA\Parameter( name="limit", in="query", description="每頁筆數(1~1000，預設 1000)", required=false, @OA\Schema(type="integer"), example=100 ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="columns[]", in="query", description="要額外多顯示詳細欄位", required=false,
     *     example={"機關資料:聯絡人", "已公告資料:決標方式"},
     *     @OA\Schema( type="array", @OA\Items( type="string"),),
     *   ),
     *   @OA\Response( response="200", description="列出特定機關的標案公告列表 API",
     *   @OA\JsonContent( type="object",
     *     @OA\Property(property="page", type="integer", example=1),
     *     @OA\Property(property="limit", type="integer", example=1000),
     *     @OA\Property(property="total", type="integer", example=304),
     *     @OA\Property(property="total_page", type="integer", example=4),
     *     @OA\Property(property="unit_name", type="string", example="機關名稱"),
     *     @OA\Property(property="matched_units", type="array", description="有帶 unit_name 時才有，比對到的機關",
     *       @OA\Items( type="object",
     *         @OA\Property(property="unit_id", type="string", example="3.76.47"),
     *         @OA\Property(property="unit_name", type="string", example="彰化縣政府"),
     *       ),
     *     ),
     *     @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20230829"),
     *           @OA\Property(property="filename", type="string", example="BDM-1-70370443"),
     *           @OA\Property(property="dept_name", type="string", example="水利資源處", description="機關資料:單位名稱"),
     *           @OA\Property(property="award_amount", type="integer", example=1416000, description="決標資料:總決標金額（元），沒有或不公開時為 null"),
     *           @OA\Property(property="budget_amount", type="integer", example=1500000, description="預算金額（元），沒有或不公開時為 null"),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "listbyunit",
        ]);
        $page = intval($_GET['page'] ?? 0) ?: 1;
        $limit = intval($_GET['limit'] ?? 0) ?: 1000;
        if ($limit < 1 or $limit > 1000) {
            OpenFunAPIHelper::errorJson('limit 必須介於 1 到 1000 之間');
        }
        $filter = $this->_getUnitFilter(true);
        $oid = $filter['unit_id'];

        $entities = Entity::search(1);
        foreach ($filter['conditions'] as $condition) {
            $entities = $entities->search($condition);
        }

        $result = new StdClass;
        $result->page = $page;
        $result->limit = $limit;
        $result->total = $entities->count();
        $result->total_page = ceil($result->total / $limit);
        $result->unit_name = ($oid !== '') ? (Unit::find($oid)->name ?? null) : null;
        if (!is_null($filter['matched_units'])) {
            $result->matched_units = $filter['matched_units'];
        }
        $result->records = array();

        $entities = $entities->order('date DESC')->offset($limit * ($page - 1))->limit($limit)->toRowArray();
        $unit_oids = array();
        foreach ($entities as $entity) {
            $unit_oids[$entity->oid] = null;
        }
        foreach (Unit::search(1)->searchIn('oid', array_keys($unit_oids)) as $unit) {
            $unit_oids[$unit->oid] = $unit->name;
        }
        $entity_datas = array();
        if (array_key_exists('columns', $_GET) and $entities) {
            $ids = array_map(function($e) { return array($e->date, $e->filename); }, $entities);
            foreach (EntityData::search(1)->searchIn(array('date', 'filename'), $ids) as $entity_data) {
                $entity_datas[$entity_data->date . '-' . $entity_data->filename] = $entity_data;
            }
        }
        foreach ($entities as $entity) {
            $record = $entity->toArray();
            $record['unit_id'] = $record['oid'];
            unset($record['oid']);
            $record['brief'] = json_decode($record['brief']);
            foreach (array('award_amount', 'budget_amount') as $k) {
                $record[$k] = is_null($record[$k]) ? null : intval($record[$k]);
            }
            $record['unit_name'] = $unit_oids[$record['unit_id']];
            $record['unit_api_url'] = $this->base . '/api/listbyunit?unit_id=' . urlencode($record['unit_id']);
            $record['tender_api_url'] = $this->base . '/api/tender?unit_id=' . urlencode($record['unit_id']) . '&job_number=' . urlencode($record['job_number']);
            $record['unit_url'] = '/index/unit/' . urlencode($record['unit_id']);
            $record['url'] = $entity->link();
            if (array_key_exists('columns', $_GET)) {
                $data = new StdClass;
                if ($entity_data = ($entity_datas[$entity->date . '-' . $entity->filename] ?? null)) {
                    $data = EntityData::updateURL(json_decode($entity_data->data), $entity);
                }
                $keys = (array)$_GET['columns'];
                $record['detail'] = array_combine($keys, array_map(function($k) use ($data) { return $data->{$k} ?? null; }, $keys));
            }
            $result->records[] = $record;
        }
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 機關標案統計 API
     * @OA\Get(
     *   path="/api/unitstat", summary="機關標案統計 API",
     *   description="統計機關範圍內各年度標案數/決標件數/決標金額、得標廠商排行、出現過的單位名稱。決標件數、決標金額與得標廠商只計「決標公告」，不含更正決標公告與定期彙送",
     *   @OA\Parameter( name="unit_id", in="query", description="機關代碼（unit_id 與 unit_name 至少要帶一個，用法同 listbyunit）", required=false, @OA\Schema(type="string"), example="3.76.47") ,
     *   @OA\Parameter( name="unit_name", in="query", description="機關名稱前綴（用法同 listbyunit）", required=false, @OA\Schema(type="string"), example="彰化縣政府" ),
     *   @OA\Parameter( name="include_children", in="query", description="帶 1 時含下屬機關（用法同 listbyunit）", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Parameter( name="dept_name", in="query", description="機關資料:單位名稱前綴，例如「水利資源處」也會比對到「水利資源處水利工程科」", required=false, @OA\Schema(type="string"), example="水利資源處" ),
     *   @OA\Parameter( name="date_from", in="query", description="公告日期下限(YYYYMMDD，含當天)", required=false, @OA\Schema(type="integer"), example=20230101 ),
     *   @OA\Parameter( name="date_to", in="query", description="公告日期上限(YYYYMMDD，含當天)", required=false, @OA\Schema(type="integer"), example=20261231 ),
     *   @OA\Parameter( name="top", in="query", description="得標廠商排行筆數(1~100，預設 20)", required=false, @OA\Schema(type="integer"), example=20 ),
     *   @OA\Parameter( name="winner_sort", in="query", description="得標廠商排行依據：amount（決標金額合計，預設）或 count（得標件數）", required=false, @OA\Schema(type="string"), example="amount" ),
     *   @OA\Response( response="200", description="機關標案統計 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="unit_name", type="string", example="彰化縣政府"),
     *       @OA\Property(property="years", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="year", type="integer", example=2025),
     *           @OA\Property(property="tenders", type="integer", example=1200, description="標案數（以 job_number 去重）"),
     *           @OA\Property(property="announcements", type="integer", example=3500, description="公告數"),
     *           @OA\Property(property="awards", type="integer", example=800, description="決標公告件數"),
     *           @OA\Property(property="award_amount", type="integer", example=1452020436, description="決標金額合計（元）"),
     *           @OA\Property(property="award_amount_unknown", type="integer", example=3, description="決標公告中總決標金額不公開或無法解析的件數，未計入 award_amount"),
     *         ),
     *       ),
     *       @OA\Property(property="winners", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="company_id", type="string", example="54747005", description="統編，查不到時為 null"),
     *           @OA\Property(property="company_name", type="string", example="公司名稱"),
     *           @OA\Property(property="awards", type="integer", example=12, description="得標件數"),
     *           @OA\Property(property="amount", type="integer", example=123456789, description="決標金額合計（元）"),
     *         ),
     *       ),
     *       @OA\Property(property="dept_names", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="dept_name", type="string", example="水利資源處", description="機關資料:單位名稱，未填時為 null"),
     *           @OA\Property(property="announcements", type="integer", example=8832),
     *           @OA\Property(property="tenders", type="integer", example=3000),
     *         ),
     *       ),
     *       @OA\Property(property="computed_at", type="string", example="2026-10-29T13:50:00+08:00", description="統計的計算時間，相同參數的結果會快取一天"),
     *     ),
     *   ),
     * )
     */
    public function unitstatAction()
    {
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "unitstat",
        ]);
        $start = microtime(true);
        $top = intval($_GET['top'] ?? 0) ?: 20;
        if ($top < 1 or $top > 100) {
            OpenFunAPIHelper::errorJson('top 必須介於 1 到 100 之間');
        }
        $winner_sort = strval($_GET['winner_sort'] ?? '') ?: 'amount';
        if (!in_array($winner_sort, array('amount', 'count'), true)) {
            OpenFunAPIHelper::errorJson('winner_sort 必須是 amount 或 count');
        }

        // 統計一天更新一次就夠，相同參數的結果快取一天，避免大機關每次都要重算好幾秒
        $cache_params = array('top' => $top, 'winner_sort' => $winner_sort);
        foreach (array('unit_id', 'unit_name', 'include_children', 'dept_name', 'date_from', 'date_to') as $k) {
            $cache_params[$k] = strval($_GET[$k] ?? '');
        }
        $cache_dir = sys_get_temp_dir() . '/pcc-api-unitstat-cache';
        $cache_file = $cache_dir . '/' . md5(json_encode($cache_params)) . '.json';
        if (file_exists($cache_file) and filemtime($cache_file) > time() - 86400
            and $result = json_decode(@file_get_contents($cache_file))) {
            $result->took = microtime(true) - $start;
            OpenFunAPIHelper::apiDone([
                'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
                'count' => count($result->years),
            ]);
            return $this->json($result);
        }

        $filter = $this->_getUnitFilter(false);
        $db = Entity::getDb();
        $scope = "WITH t AS (SELECT date, filename, job_number, dept_name, award_amount, brief->>'type' AS type FROM entity WHERE ("
            . implode(') AND (', $filter['conditions']) . ")) ";
        $fetch_all = function($sql) use ($db) {
            $rows = array();
            $res = $db->query($sql);
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        };
        $int_or_null = function($v) { return is_null($v) ? null : intval($v); };

        $result = new StdClass;
        $result->unit_name = ($filter['unit_id'] !== '') ? (Unit::find($filter['unit_id'])->name ?? null) : null;
        if (!is_null($filter['matched_units'])) {
            $result->matched_units = $filter['matched_units'];
        }

        $result->years = array();
        foreach ($fetch_all($scope . "SELECT date / 10000 AS year, count(DISTINCT job_number) AS tenders, count(*) AS announcements,
            count(*) FILTER (WHERE type = '決標公告') AS awards,
            sum(award_amount) FILTER (WHERE type = '決標公告') AS award_amount,
            count(*) FILTER (WHERE type = '決標公告' AND award_amount IS NULL) AS award_amount_unknown
            FROM t GROUP BY 1 ORDER BY 1") as $row) {
            $result->years[] = array(
                'year' => intval($row['year']),
                'tenders' => intval($row['tenders']),
                'announcements' => intval($row['announcements']),
                'awards' => intval($row['awards']),
                'award_amount' => intval($row['award_amount']),
                'award_amount_unknown' => intval($row['award_amount_unknown']),
            );
        }

        // 同一統編可能有改名，名稱取最近一次決標時的；沒有統編的以名稱分組
        $result->winners = array();
        foreach ($fetch_all($scope . "SELECT w.company_id, (array_agg(w.company_name ORDER BY t.date DESC))[1] AS company_name,
            count(*) AS awards, sum(w.amount) AS amount
            FROM t JOIN entity_winner w ON w.date = t.date AND w.filename = t.filename
            WHERE t.type = '決標公告'
            GROUP BY w.company_id, CASE WHEN w.company_id IS NULL THEN w.company_name END
            ORDER BY " . ($winner_sort == 'amount' ? 'amount DESC NULLS LAST, awards DESC' : 'awards DESC, amount DESC NULLS LAST') . "
            LIMIT {$top}") as $row) {
            $result->winners[] = array(
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'],
                'awards' => intval($row['awards']),
                'amount' => $int_or_null($row['amount']),
            );
        }

        $result->dept_names = array();
        foreach ($fetch_all($scope . "SELECT dept_name, count(*) AS announcements, count(DISTINCT job_number) AS tenders
            FROM t GROUP BY 1 ORDER BY 2 DESC") as $row) {
            $result->dept_names[] = array(
                'dept_name' => $row['dept_name'],
                'announcements' => intval($row['announcements']),
                'tenders' => intval($row['tenders']),
            );
        }

        $result->computed_at = date('c');
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }
        $tmp_file = $cache_file . '.' . getmypid() . '.tmp';
        @file_put_contents($tmp_file, json_encode($result, JSON_UNESCAPED_UNICODE));
        @rename($tmp_file, $cache_file);

        $result->took = microtime(true) - $start;
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->years),
        ]);
        return $this->json($result);
    }

    /**
     * 列出某個標案代碼的公告詳細資料 API
     * @OA\Get(
     *   path="/api/tender", summary="列出某個標案代碼的公告詳細資料 API", description="列出某個標案代碼的公告詳細資料 API",
     *   @OA\Parameter( name="unit_id", in="query", description="機關代碼", required=true, @OA\Schema(type="string"), example="A.41" ),
     *   @OA\Parameter( name="job_number", in="query", description="標案代碼", required=true, @OA\Schema(type="string"), example="ndc109050" ),
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
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "tender",
        ]);
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
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records)
        ]);
        return $this->json($result);
    }

    /**
     * 依公司名稱查詢拒絕往來廠商名單 API（需要歐噴 token）
     *
     * @OA\Get(
     *   path="/api/searchbyrefusedvendor", summary="依公司名稱查詢拒絕往來廠商名單 API",
     *   description="依公司名稱查詢該公司是否曾經有「拒絕往來廠商名單公告」記錄，需要有效的歐噴 token，未帶 token 會回傳 401",
     *   @OA\Parameter( name="query", in="query", description="公司名稱，未帶或空字串會回 400（實際會被驗證，不是單純文件標示必填）", required=true, @OA\Schema(type="string"), example="威智精密股份有限公司" ),
     *   @OA\Parameter( name="page", in="query", description="頁數(1開始)", required=false, @OA\Schema(type="integer"), example=1 ),
     *   @OA\Response( response="200", description="依公司名稱查詢拒絕往來廠商名單 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="query", type="string", example="威智精密股份有限公司"),
     *       @OA\Property(property="page", type="integer", example=1),
     *       @OA\Property(property="total_records", type="integer", example=1),
     *       @OA\Property(property="total_pages", type="integer", example=1),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20260908"),
     *           @OA\Property(property="filename", type="string", example="RML-1-70008731"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="拒絕往來廠商名單公告"),
     *             @OA\Property(property="title", type="string", example="機軸半成品等3項"),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="JE14282P236"),
     *           @OA\Property(property="unit_id", type="string", example="3.5.10.100"),
     *           @OA\Property(property="unit_name", type="string", example="國防部軍備局生產製造中心"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=3.5.10.100"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=3.5.10.100&job_number=JE14282P236"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/3.5.10.100"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20260908/RML-1-70008731"),
     *         ),
     *       ),
     *     ),
     *   ),
     *   @OA\Response( response="400", description="query 未帶或為空字串" ),
     *   @OA\Response( response="401", description="缺少或無效的歐噴 token" ),
     * )
     */
    public function searchbyrefusedvendorAction()
    {
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "searchbyrefusedvendor",
            'require_token' => true,
        ]);
        $start = microtime(true);

        $result = new StdClass;
        $result->query = trim(strval($_GET['query'] ?? ''));
        $result->page = intval($_GET['page']) ?: 1;

        if ($result->query === '') {
            OpenFunAPIHelper::errorJson('query 為必填參數（公司名稱），不能空白或省略');
        }

        $cmd = array(
            'query' => array(
                'query_string' => array(
                    'query' => sprintf(
                        '(type:"拒絕往來廠商名單公告" OR type:"拒絕往來廠商名單更正公告") AND companies.names:"%s"',
                        $result->query
                    ),
                ),
            ),
            'size' => 100,
            'from' => $result->page * 100 - 100,
            'sort' => array('date' => 'desc'),
            'track_total_hits' => true,
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
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }

    /**
     * 列出最近 100 筆拒絕往來廠商名單公告 API（需要歐噴 token）
     *
     * @OA\Get(
     *   path="/api/listrecentrefusedvendor", summary="列出最近 100 筆拒絕往來廠商名單公告 API",
     *   description="依公告日期由新到舊排序，列出最近 100 筆拒絕往來廠商名單公告，需要有效的歐噴 token，未帶 token 會回傳 401",
     *   @OA\Response( response="200", description="列出最近 100 筆拒絕往來廠商名單公告 API",
     *     @OA\JsonContent( type="object",
     *       @OA\Property(property="total_records", type="integer", example=100),
     *       @OA\Property(property="took", type="number", example=0.123),
     *       @OA\Property(property="records", type="array",
     *         @OA\Items( type="object",
     *           @OA\Property(property="date", type="string", example="20260908"),
     *           @OA\Property(property="filename", type="string", example="RML-1-70008731"),
     *           @OA\Property(property="brief", type="object",
     *             @OA\Property(property="type", type="string", example="拒絕往來廠商名單公告"),
     *             @OA\Property(property="title", type="string", example="機軸半成品等3項"),
     *           ),
     *           @OA\Property(property="job_number", type="string", example="JE14282P236"),
     *           @OA\Property(property="unit_id", type="string", example="3.5.10.100"),
     *           @OA\Property(property="unit_name", type="string", example="國防部軍備局生產製造中心"),
     *           @OA\Property(property="unit_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/listbyunit?unit_id=3.5.10.100"),
     *           @OA\Property(property="tender_api_url", type="string",
     *             example="https://pcc.g0v.ronny.tw/api/tender?unit_id=3.5.10.100&job_number=JE14282P236"),
     *           @OA\Property(property="unit_url", type="string", example="/index/unit/3.5.10.100"),
     *           @OA\Property(property="url", type="string", example="/index/entry/20260908/RML-1-70008731"),
     *         ),
     *       ),
     *     ),
     *   ),
     *   @OA\Response( response="401", description="缺少或無效的歐噴 token" ),
     * )
     */
    public function listrecentrefusedvendorAction()
    {
        OpenFunAPIHelper::checkUsage([
            'service' => 'pcc-api',
            'class' => "listrecentrefusedvendor",
            'require_token' => true,
        ]);
        $start = microtime(true);

        $result = new StdClass;

        $cmd = array(
            'query' => array(
                'query_string' => array(
                    'query' => '(type:"拒絕往來廠商名單公告" OR type:"拒絕往來廠商名單更正公告")',
                ),
            ),
            'size' => 100,
            'sort' => array('date' => 'desc'),
            'track_total_hits' => true,
        );

        $prefix = getenv('ELASTIC_PREFIX');
        $ret = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode($cmd));

        $result->total_records = $ret->hits->total->value;
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
            $result->records[] = $record;
        }
        $result->took = microtime(true) - $start;
        OpenFunAPIHelper::apiDone([
            'size' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE)),
            'count' => count($result->records),
        ]);
        return $this->json($result);
    }
}
