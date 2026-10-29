# 台灣政府採購公告 API — `tw.openfun~api~procurement`

給 AI 閱讀的使用指引。人類可在 https://data.openfun.tw/datasets/tw.openfun~api~procurement 看到資料集說明。
若需要政府採購法制的背景知識（招標/決標方式、押標金、巨額採購門檻、特別預算法源等），詳見 [knowledge.md](knowledge.md)。

## ⚠️ 開始之前（AI agent 必讀）

**Base URL**：`https://pcc-api.openfun.app/api`
**認證**：使用 data.openfun.tw 核發的 Bearer Token 可解除流量限制；不帶 Token 仍可呼叫，但可能因超過流量門檻被擋（門檻隨時調整，不保證特定數字）。**例外**：`searchbyrefusedvendor`、`listrecentrefusedvendor` 這兩支拒絕往來廠商名單相關端點**強制要求**帶有效 Token，未帶或帶無效 Token 會直接回 `401`，不像其他端點可以匿名呼叫。
**回應格式**：一律 `application/json`，CORS 完全開放（前端可直接呼叫）。

最簡查詢範例：
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://pcc-api.openfun.app/api/getinfo"
```

**取得 Token 方式（Device Authorization Grant）：**
```bash
# 步驟一：取得驗證連結
curl -X POST https://data.openfun.tw/api/v1/auth/device

# 步驟二：在瀏覽器開啟回應中的 verification_uri_complete，用 Google 帳號登入授權

# 步驟三：輪詢取得 Token（約 10-30 秒後成功）
curl -X POST https://data.openfun.tw/api/v1/auth/token \
  -d "device_code=DEVICE_CODE_FROM_STEP1"
```
若無 Token，也可在 https://data.openfun.tw/user 登入後從 Dashboard 取得長效 API 金鑰。

禁止用 WebFetch 抓 HTML 頁面，請直接呼叫 API。

## ⚠️ 資料涵蓋範圍與查詢眉角

- **query 等參數含中文/非 ASCII 字元時，一定要做 URL encode，不要把原始字串直接接在雙引號 URL 裡給 curl**：`curl "...?query=威智精密股份有限公司"` 這種寫法，curl 不會自動幫你編碼，未編碼的原始 UTF-8 位元組在 HTTP request line 裡是不合法的，伺服器會直接判定為 malformed request 並關閉連線——你會收到**完全空白、沒有任何錯誤訊息**的回應，很難排查。正確寫法是用 `curl -G --data-urlencode "query=威智精密股份有限公司" URL`（範例見下方）。
- **資料涵蓋起始日**：「刊登公報」類公告從 2010-01-02 開始，「不刊登公報」類公告從 2012-06-19 開始，更早期的標案不在資料庫內。
- **`unit_id` 是機關代碼，不是機關名稱**：呼叫 `listbyunit` / `tender` 前，建議先呼叫 `/api/unit` 或用 `searchbycompanyname`/`searchbytitle` 結果裡的 `unit_id` 取得正確代碼（格式類似 `3.76.53.97.30` 這種點號分隔的階層代碼），不要自己猜代碼。`listbyunit` 也可以改用 `unit_name` 以機關名稱前綴查（例如 `彰化縣政府` 會比對到 `彰化縣政府建設處` 等各處），但名稱前綴只看名稱，縣立學校等名稱不同的下屬機關不會被比對到；要列出整個機關體系請用 `unit_id` 加 `include_children=1`。
- **縣市政府的「處」「局」多半沒有自己的機關代碼**：例如彰化縣政府各處的標案都掛在 `3.76.47 彰化縣政府` 底下，只能靠公告裡的「機關資料:單位名稱」（例如 `水利資源處`）區分。`listbyunit`/`unitstat` 用 `dept_name` 參數做前綴比對（`水利資源處` 也會比對到 `水利資源處水利工程科`），各機關實際有哪些單位名稱寫法，可以先用 `unitstat` 的 `dept_names` 清單查。
- **2006 年以前的公告大多沒有「單位名稱」**：原始公告頁面有這個欄位但幾乎都是空白，2006 年下半年起才普遍填寫，所以用 `dept_name` 篩選時會漏掉 2006 年以前的標案（`unitstat` 的 `dept_names` 裡 `dept_name: null` 那一列大多是這些）。2007 年以後仍有少數公告沒有單位名稱，集中在招標文件公開閱覽、財物出租、財物變賣、拒絕往來廠商名單等公告類型。
- **得標廠商統編有部分是用同名廠商補上的**：舊資料解析共同投標的決標公告時，主得標廠商的統編會被共同投標廠商蓋掉；這類沒有統編的得標廠商，若同名廠商在其他公告出現的 8 碼統編有九成以上是同一個，就用該統編補上。仍查不到統編（`company_id: null`）的多半是事務所、自然人或外國廠商，`unitstat` 排行會以名稱分組。
- **`columns[]` 參數是「區塊:欄位」的階層 key（用 `:` 分隔）**，例如 `機關資料:聯絡人`、`已公告資料:決標方式`。不同公告類型（決標公告/無法決標公告/財物變賣公告…）欄位不一定存在，指定一個該公告沒有的欄位時，對應值會是 `null`，不會噴錯。
- **`searchbyspecialbudget` 的 `query` 必須是精確的特別預算名稱**（底層用 exact match 查詢），建議先呼叫 `searchallspecialbudget` 取得所有特別預算名稱清單，再用清單裡的名稱去查，不要自己猜名稱。
- **分頁大小不一致**：`searchbycompanyname`/`searchbycompanyid`/`searchbytitle`/`searchbyspecialbudget`/`searchbyrefusedvendor` 每頁 100 筆；`listbyunit` 預設每頁 1000 筆（可用 `limit` 調小）；`listbydate`/`tender` 不分頁，一次回傳全部符合的筆數；`listrecentrefusedvendor` 固定回傳最近 100 筆，不分頁。
- **拒絕往來廠商名單只有廠商名稱，沒有統一編號**：政府採購網「拒絕往來廠商名單公告」原始頁面本身就沒有公布廠商統編，所以 `searchbyrefusedvendor` 只能用公司名稱查，回傳資料裡也不會有廠商統編欄位。

## 端點與查詢說明

所有端點皆為 `GET`，掛在 `https://pcc-api.openfun.app/api/` 下。

| 方法 | 路徑 | 說明 | 參數 |
|------|------|------|------|
| GET | `/api/` | 列出 API 列表 | 無 |
| GET | `/api/getinfo` | 取得資料狀況（最新/最舊資料時間、公告總數） | 無 |
| GET | `/api/unit` | 列出全部機關代碼對照（`{unit_id: 機關名稱}`） | 無 |
| GET | `/api/searchbycompanyname` | 依公司名稱搜尋標案 | `query`（必填，公司名稱）、`page`（選填，預設 1）、`columns[]`（選填） |
| GET | `/api/searchbycompanyid` | 依公司統一編號搜尋標案 | `query`（必填，統編）、`page`（選填）、`columns[]`（選填） |
| GET | `/api/searchbytitle` | 依標案名稱搜尋標案 | `query`（必填，標案名稱，空白會被當 AND 切詞）、`page`（選填）、`columns[]`（選填） |
| GET | `/api/listbydate` | 列出特定日期的標案公告列表 | `date`（必填，`YYYYMMDD`） |
| GET | `/api/listbyunit` | 列出特定機關的標案公告列表（依日期新到舊）。每筆另有 `dept_name`（機關資料:單位名稱）、`award_amount`（總決標金額）、`budget_amount`（預算金額），金額為整數（元），沒有或不公開時為 `null`；有帶 `unit_name` 時頂層另有 `matched_units`（比對到的機關清單） | `unit_id`（機關代碼）或 `unit_name`（機關名稱前綴，最多比對 500 個機關），至少帶一個，兩個都帶時只列 `unit_id` 範圍內（`include_children=1` 時含下屬機關）名稱符合前綴的機關；`include_children`（選填，`1` 時含下屬機關）、`date_from`/`date_to`（選填，`YYYYMMDD`，含當天）、`dept_name`（選填，機關資料:單位名稱前綴）、`type`（選填，公告類型完全比對，例如 `決標公告`）、`limit`（選填，1~1000，預設 1000）、`page`（選填）、`columns[]`（選填） |
| GET | `/api/unitstat` | 機關標案統計：各年度標案數（以 `job_number` 去重）/公告數/決標件數/決標金額、得標廠商排行（統編、名稱、件數、金額）、範圍內出現過的單位名稱與件數 | 範圍參數同 `listbyunit`（`unit_id`/`unit_name`/`include_children`/`dept_name`/`date_from`/`date_to`）；`top`（選填，得標廠商排行筆數 1~100，預設 20）、`winner_sort`（選填，`amount` 預設或 `count`）。決標件數、金額與得標廠商只計「決標公告」，不含更正決標公告與定期彙送；總決標金額不公開的件數另列在 `award_amount_unknown`。相同參數的結果會快取一天，`computed_at` 是實際計算時間 |
| GET | `/api/tender` | 取得某個標案代碼的完整公告詳細資料（含歷次公告） | `unit_id`（必填）、`job_number`（必填，標案代碼） |
| GET | `/api/searchallspecialbudget` | 列出資料庫中所有特別預算名稱 | 無 |
| GET | `/api/searchbyspecialbudget` | 搜尋特定特別預算底下的標案 | `query`（必填，特別預算精確名稱，見上方注意事項）、`page`（選填） |
| GET | `/api/searchbyrefusedvendor` 🔒需要 Token | 依公司名稱查詢是否曾有拒絕往來廠商名單公告記錄 | `query`（必填，公司名稱）、`page`（選填，預設 1） |
| GET | `/api/listrecentrefusedvendor` 🔒需要 Token | 列出最近 100 筆拒絕往來廠商名單公告（依公告日期新到舊） | 無 |

curl 範例（`query`/`columns[]` 等含中文參數一律用 `-G --data-urlencode` 帶，不要手動拼在雙引號 URL 裡）：

```bash
# 依公司名稱搜尋，並多取兩個詳細欄位
curl -G -H "Authorization: Bearer YOUR_TOKEN" \
  --data-urlencode "query=開放文化基金會" \
  --data-urlencode "page=1" \
  --data-urlencode "columns[]=機關資料:聯絡人" \
  --data-urlencode "columns[]=已公告資料:決標方式" \
  "https://pcc-api.openfun.app/api/searchbycompanyname"

# 列出某天所有標案公告
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://pcc-api.openfun.app/api/listbydate?date=20230829"

# 取得某個標案代碼在指定機關底下的完整公告歷程
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://pcc-api.openfun.app/api/tender?unit_id=A.41&job_number=ndc109050"

# 先查有哪些特別預算，再依名稱查標案
curl -H "Authorization: Bearer YOUR_TOKEN" "https://pcc-api.openfun.app/api/searchallspecialbudget"
curl -G -H "Authorization: Bearer YOUR_TOKEN" \
  --data-urlencode "query=前瞻基礎建設" --data-urlencode "page=1" \
  "https://pcc-api.openfun.app/api/searchbyspecialbudget"

# 查詢某公司是否曾被列入拒絕往來廠商名單（這支一定要帶有效 Token，否則回 401；
# query 沒帶或空字串會回 400，不會被當成萬用字元）
curl -G -H "Authorization: Bearer YOUR_TOKEN" \
  --data-urlencode "query=威智精密股份有限公司" \
  "https://pcc-api.openfun.app/api/searchbyrefusedvendor"

# 列出最近 100 筆拒絕往來廠商名單公告（這支一定要帶有效 Token，否則回 401）
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://pcc-api.openfun.app/api/listrecentrefusedvendor"
```

搜尋類端點（`searchbycompanyname`/`searchbycompanyid`/`searchbytitle`/`searchbyspecialbudget`/`searchbyrefusedvendor`）回傳格式：

```json
{
  "query": "開放文化基金會",
  "page": 1,
  "total_records": 304,
  "total_pages": 4,
  "took": 0.123,
  "records": [
    {
      "date": "20230829",
      "filename": "BDM-1-70370443",
      "brief": { "type": "公告類型", "title": "標案名稱", "companies": { "ids": ["..."], "names": ["..."] } },
      "job_number": "nwda1120349",
      "unit_id": "A.17.2.1",
      "unit_name": "機關名稱",
      "unit_api_url": "https://pcc-api.openfun.app/api/listbyunit?unit_id=A.17.2.1",
      "tender_api_url": "https://pcc-api.openfun.app/api/tender?unit_id=A.17.2.1&job_number=nwda1120349",
      "url": "/index/entry/20230829/BDM-1-70370443"
    }
  ]
}
```

`listrecentrefusedvendor` 的 `records` 陣列格式跟上面一樣，但沒有 `query`/`page`/`total_pages` 這幾個欄位（固定回傳最近 100 筆，不分頁）。

**`total_records` 準確度**：`searchbyrefusedvendor`/`listrecentrefusedvendor` 這兩支已明確設定精確計數，`total_records` 一定是真實筆數。其他搜尋類端點（`searchbycompanyname` 等）沒有特別設定，命中筆數一旦超過 Elasticsearch 預設上限（10000），`total_records`/`total_pages` 就會被蓋牌成剛好 10000/100，不代表真實總數——查詢條件夠窄（例如指定公司名稱）通常不會踩到，只有命中結果本身很多筆時才會發生。

## 快速參考

| 項目 | 說明 |
|------|------|
| Base URL | `https://pcc-api.openfun.app/api` |
| 認證 | `Authorization: Bearer {token}` 可解除流量限制（不帶也能呼叫，`searchbyrefusedvendor`/`listrecentrefusedvendor` 除外，這兩支強制要求 Token） |
| 取得 Token | https://data.openfun.tw/user |
| 資料涵蓋起始 | 刊登公報 2010-01-02 起；不刊登公報 2012-06-19 起 |
| 機關代碼查詢 | `/api/unit` |
| 特別預算名稱查詢 | `/api/searchallspecialbudget`（用回傳值查 `searchbyspecialbudget`，不要自己猜名稱） |
| 拒絕往來廠商名單查詢 | `/api/searchbyrefusedvendor`（只能用公司名稱查，無統編欄位）、`/api/listrecentrefusedvendor`（最近 100 筆） |
| 分頁大小 | 搜尋類 API 每頁 100 筆；`listbyunit` 預設每頁 1000 筆（`limit` 可調 1~1000）；`listbydate`/`tender`/`listrecentrefusedvendor` 不分頁 |
