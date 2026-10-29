# PLAN.md — pcc.g0v.ronny.tw 專案架構與開發歷程

> 給 AI Agent 快速認識這個 repo 的說明文件。內容基於程式碼閱讀與 git log 整理（撰寫於 2026-07-10，2026-10-28 依後續 commit 更新）。

## 這是什麼專案

`pcc.g0v.ronny.tw` 是一個爬取「[中華民國政府電子採購網](https://web.pcc.gov.tw/)」標案公告資料，
並轉存成 PostgreSQL + Elasticsearch，再包裝成公開 REST API 的專案（g0v 公民科技社群相關專案，作者 Ronny Wang）。

整體資料流是一條 pipeline：

```
web.pcc.gov.tw (政府採購網)
    │  (透過代理伺服器 curl 爬取)
    ▼
crawl-list.php   ── 抓「每日標案列表」HTML，存成 list/{YYYYMMDD}.html.gz / list/np-{YYYYMMDD}.html.gz
    ▼
crawl-entry.php  ── 從列表頁抓出的連結，逐一下載「標案詳細內容」HTML，存成 entry/{year}/{ymd}-{filename}.gz
                     （處理 proxy 輪替、驗證碼(captcha)辨識、429/502 等錯誤重試）
    ▼
parse-entry.php  ── 用 Parser::parseHTML() 把 entry/*.gz 的 HTML 解析成結構化 JSON，
                     存成 entry-json/{year}/{ymd}-{filename}.json.gz
    ▼
import.php       ── 把 entry-json 讀出來，寫進 PostgreSQL 的 entity / entity_data 表，
                     並順手把 unit 表沒有的機關代碼用解析出的機關名稱補上
    ▼
bulk-import-search.php ── 把 entity / entity_data 摘要後寫進 Elasticsearch（entry index）
    ▼
ApiController.php (webdata/controllers/) ── 對外提供 REST API，讀 PostgreSQL 拿明細、讀 Elasticsearch 做全文/欄位搜尋
```

單純看資料庫用途分工：
- **PostgreSQL**：權威資料源（entity 摘要表 + entity_data 原始 JSON 表 + unit 機關對照表），用 `Pix_Table`（見下方框架說明）存取。
- **Elasticsearch**：搜尋索引（`entry` index，加上機關/公司名稱、特別預算等欄位），供全文/條件搜尋 API 使用，避免直接對 PostgreSQL 做重查詢。

## 目錄結構

```
webdata/
  config.php            # 環境變數（DB/ES/Proxy 帳密），已被 .gitignore，不會進 git
  init.inc.php          # 共用 bootstrap：載入 pixframework、設定 include path、連 DB、設定 API 用量 log 路徑
  skill.md              # 給 AI agent 用的 API 使用說明（端點/參數/curl 範例），由 /skill.md 提供
  knowledge.md          # 政府採購法制度背景知識（附全國法規資料庫來源），由 /knowledge.md 提供
  prompt.php            # （目前內容為空/佔位）
  controllers/
    ApiController.php   # 對外 REST API 的核心（/api/* 路由），含大量 swagger-php 註解；
                        # 2026-09 新增需 token 的拒絕往來廠商 API（searchbyrefusedvendor / listrecentrefusedvendor）
    IndexController.php # 網頁前台（首頁 / swagger 文件頁），實際前端已搬到另一個 repo
  models/
    Entity.php           # entity 表 ORM：標案摘要（date+filename 為主鍵），含 URL 還原邏輯
    EntityData.php        # entity_data 表 ORM：標案完整解析後 JSON 資料
    Unit.php              # unit 表 ORM：機關代碼 <-> 機關名稱對照
    Parser.php             # 核心 HTML parser，把採購網的標案詳細頁 HTML 轉成 key-value JSON
    Elastic.php            # 簡易 Elasticsearch client（bulk insert/commit、建立/刪除 index）
    APILimiter.php         # 簡單的 IP rate limiter（10秒10次/60秒60次），寫 flat file 到 /tmp
    OpenFunAPIHelper.php   # 歐噴各 API 共用的 token 資訊讀取（nginx gateway 驗證後的 X-Token-Info header）與用量 log
    CNS2UTF8.php           # 舊公文 CNS11643 編碼轉 UTF-8（處理早期政府網站的中文編碼問題）
  scripts/               # 命令列腳本（cron / 手動執行），構成上面提到的 pipeline
    crawl-list.php         # 抓每日列表頁
    crawl-entry.php        # 抓標案詳細頁（含 captcha 辨識、多組 proxy 輪替）
    parse-entry.php        # HTML -> JSON
    import.php             # JSON -> entity/entity_data (PostgreSQL)，並自動補 unit 表缺漏的機關代碼
    bulk-import-search.php # entity/entity_data -> Elasticsearch
    init-db.php            # 建立/重建 Elasticsearch index mapping
    backfill-unit.php      # 一次性工具：從既有 entity/entity_data 反查歷史缺漏的機關名稱補進 unit 表
  maps/                  # CNS11643 <-> Unicode 對照表（給 CNS2UTF8 用）
  stdlibs/pixframework/  # 內嵌的 PHP MVC 框架（Pix_Table / Pix_Controller / Pix_Loader...），第三方 vendored 程式碼
  views/                 # 前台頁面模板（.phtml），前台實際上多數功能已搬到 https://github.com/ronnywang/pcc-viewer
  swagger.json           # 由 ApiController 內的 swagger-php 註解產生的 OpenAPI 文件
index.php                # 前端控制器；/skill.md、/knowledge.md 在這裡直接輸出 webdata 下對應的 markdown
.htaccess                # 全部導到 index.php（前端控制器路由）
```

爬蟲執行時，`list/`、`entry/`、`entry-json/` 這些資料目錄是在執行 script 的當下目錄產生的（不在 git 裡管理），資料量很大，靠 gzip 壓縮儲存原始 HTML 與解析後 JSON 快照。

`crawl-list.php`、`crawl-entry.php`、`parse-entry.php`、`import.php` 每成功處理一筆就會 `touch` 一個 `heartbeat` 檔（前三支在當下目錄，`import.php` 在資料目錄 `$path` 下），供外部排程判斷這些長時間執行的程序是否卡住。

## 技術棧

- **語言/框架**：純 PHP + 內嵌的 `pixframework`（Pix_Table 作為簡易 ORM／查詢建構器，Pix_Controller 作為前端控制器路由）。沒有用 Composer 或現代 PHP 框架。
- **資料庫**：PostgreSQL（透過 `DATABASE_URL` 環境變數連線，`entity`/`entity_data`/`unit` 三張表，`entity`/`entity_data` 用 `jsonb` 欄位存解析後的完整資料）。
- **搜尋引擎**：Elasticsearch（透過 `ELASTIC_URL`/`ELASTIC_USER`/`ELASTIC_PASSWORD`/`ELASTIC_PREFIX` 連線，用原生 curl + REST API 呼叫，沒有用官方 client library）。
- **爬蟲**：cURL（User-Agent 讀 `FETCH_USER_AGENT` 環境變數，見下方注意事項），透過內部 proxy（`PROXY_HOST` + 多組 port 20001~20005 輪替）打政府採購網，處理驗證碼（用像素比對「連連看」圖形驗證碼的方式自動解，見 `crawl-entry.php` 內的 `$hack_captcha`）。
- **對外 API**：純手刻 REST（無框架式路由），用 swagger-php annotation 產生 `swagger.json` API 文件，掛在 `/index/swagger`。
- **用量控管**：`APILimiter`（IP-based rate limit）+ `OpenFunAPIHelper`（歐噴公司各 API 的共用基礎設施）。token 驗證由前面的 nginx gateway（`verify_token.lua`）負責，驗證結果以 Base64URL 編碼放進 `X-Token-Info` header；PHP 端只讀這個 header，在 `checkUsage(['service' => 'pcc-api', ...])` 檢查 allowed origins（同時看 Origin 與 Referer）；沒帶 token 預設當訪客放行，但帶 `'require_token' => true` 的 API（目前是拒絕往來廠商兩支）沒有有效 token 就回 401。`apiDone()` 則把用量（含 user agent、referer）寫進 `setUsageLogPath()` 指定的 log 目錄。

## 環境設定（config.php，不進 git）

`webdata/config.php` 透過 `putenv()` 設定以下變數（實際數值為機密，不應寫入程式碼或文件）：
- `DATABASE_URL`（PostgreSQL 連線字串）
- `PROXY_SECRET` / `PROXY_URL` / `PROXY_USER` / `PROXY_PASS` / `PROXY_HOST`（爬蟲用代理）
- `ELASTIC_URL` / `ELASTIC_USER` / `ELASTIC_PASSWORD` / `ELASTIC_PREFIX`（Elasticsearch）
- `FETCH_USER_AGENT`（爬蟲送出的 User-Agent；沒設定時 fallback 成一組 Firefox UA）

（舊版 `OpenFunAPIHelper` 用的 `API_COUNTER_DATABASE_URL` 已隨 2026-07 改寫移除；用量 log 路徑目前寫死在 `init.inc.php`。）

## 核心資料模型

- **Entity**（`entity` 表，主鍵 `date`+`filename`）：標案的「摘要」記錄。`brief` 欄位存精簡 JSON（type/title/companies），`oid`/`job_number` 供查詢，並提供 `link()`、`getFilename()`、`updateUrl()` 等 helper 把採購網原始（常是 base64 編碼過的）連結，還原/正規化成可直接開啟的公開網址。
- **EntityData**（`entity_data` 表，同樣主鍵 `date`+`filename`）：標案的「完整」解析結果（Parser 產出的所有 key-value），`keys` 欄位記錄有哪些欄位存在（因為每種公告類型欄位不同）。
- **Unit**（`unit` 表，主鍵 `oid`）：機關代碼 → 機關名稱對照表。最早由外部提供的靜態 `oidmapping.json`（不在 repo 中）灌入，2017 年後就沒更新過，導致 API 回傳的 `unit_name` 常為 null；2026-07 起改由 `import.php` 在每次匯入時補上新出現的機關代碼（只補不存在的，不覆蓋既有名稱），歷史缺漏則已用 `backfill-unit.php` 補齊（12,494 筆中補了 12,493 筆）。

## Parser 邏輯（webdata/models/Parser.php）

這是整個專案最複雜、最需要小心維護的部分。政府採購網的標案詳細頁是傳統多層巢狀 `<table>` 排版，且**十幾年間版面改版多次、不同公告類型（決標公告/無法決標公告/財物變賣公告...）欄位結構都不一樣**。`Parser::parseHTML()` 用大量針對特定 category/key 組合的 hardcode 規則去抽欄位（例如「總決標金額」只取換行前第一段、「投標廠商」底下要用品項序號當 key 前綴避免重名覆蓋等）。

- 因為欄位命名規則歷史上多次變動，程式裡有不少「已知例外網址」的 hardcode 修正（例如 `parse-entry.php`、`import.php` 裡對特定 `url` 的補值）。
- 過去有一支未進版控的 `test-parse-entry.php` 迴歸測試工具（拿舊 JSON 快照與新 parser 結果做欄位級 diff），但它目前已不在工作目錄中。**修改 Parser 前仍應該做同樣的事**：挑不同年代、不同公告類型的 `entry/*.gz`，比對改動前後 `Parser::parseHTML()` 的輸出，確認沒有引入非預期的資料變動。

## 爬蟲細節與注意事項

- `crawl-list.php` / `crawl-entry.php` 都會先檢查本地是否已有有效快取（檔案存在、不是驗證碼頁、不是空檔、有 `</html>` 結尾…）才決定要不要重新抓，避免重複打政府網站。
- 有「刊登公報」（`list/{ymd}.html.gz`，2010-01-02 之後）和「不刊登公報」（`list/np-{ymd}.html.gz`，2012-06-19 之後）兩種列表來源，對應 `Parser::parse_list()` 和 `Parser::parse_list_np()` 兩種解析方式，且連結格式（`unPublish.tender.*`、`unPublish.award.*`、`unPublish.nonAward.*`、`unPublish.tpRead.*` 等）也不同，`Entity::getFilename()`/`Entity::updateUrl()` 負責把這些格式統一轉換成可用網址。
- `crawl-list.php` 拿到空結果會換隨機 proxy port 重試，最多 5 次，超過就放棄當天、不寫檔，等下次執行再靠快取檢查重抓（2026-09 以前「刊登公報」段落沒檢查空字串，會寫出 20 bytes 的壞快取，讓 `crawl-entry.php` 丟 ValueError 中止）。
- **採購網會擋 User-Agent 含 Chrome/Chromium 字樣的請求**（不論版本），Firefox/Safari/不帶 UA 則正常。UA 統一由 `config.php` 的 `FETCH_USER_AGENT` 設定，若又被擋，改那裡即可。
- `crawl-entry.php` 用多組 proxy port（20001~20005）輪替避免單一 IP 被政府網站封鎖，並實作了簡易的驗證碼圖形辨識（像素比對兩張撲克牌圖形找出相同花色，模擬人工選取）。這段邏輯相當 hacky、高度依賴政府網站特定的驗證碼 UI，若採購網改版驗證碼樣式，這段程式會直接失效需要重寫。

## 開發歷程 / 重大 Milestone

只列重大轉折，細節請看 `git log`。git 歷史從 2017 年開始（更早的資料可能是用其他方式匯入，不在此 repo 歷史內）。

- **2017**：建立 pixframework 基礎、Entity/Unit model 與搜尋 API；對外參數 `oid` 改名為 `unit_id`，沿用至今。
- **2018-08**：前端搬到獨立 repo [pcc-viewer](https://github.com/ronnywang/pcc-viewer)，此 repo 之後只負責爬蟲與 API。
- **2020-10**：建立 Parser 架構，`Parser.php` 開始承擔大量欄位解析邏輯。
- **2022-06~07**：爬蟲重構成 `crawl-list` → `crawl-entry` → `parse` → `import` 的每日 pipeline；同時採購網改版，Parser 大幅調整。
- **2023-01**：新增 `Entity::updateUrl()` 等函式，統一還原各年代、各公告類型的網址格式。
- **2023-09~10**：導入 Elasticsearch 作為搜尋後端，並開始用 swagger-php 維護 API 文件。
- **2025**：加入 `APILimiter`（IP rate limit）與 `OpenFunAPIHelper`，API 納入歐噴公司共用的認證／用量統計體系。
- **2026-07**：`OpenFunAPIHelper` 改寫成 nginx gateway token 驗證架構；`import.php` 納入版控，並開始自動維護 `unit` 表。
- **2026-08**：新增 `/skill.md`、`/knowledge.md`，讓 AI agent 能使用這個 API。
- **2026-09**：新增需要 token 的拒絕往來廠商名單 API；採購網開始擋 Chrome UA，爬蟲改讀 `FETCH_USER_AGENT`。

**觀察到的整體趨勢**：這是一個長期維運型的爬蟲專案，最大的技術債/心力集中在「政府採購網不定期改版」造成的 Parser 規則維護，以及「避免被防爬蟲機制擋下」的爬蟲穩定性工程（proxy 輪替、驗證碼繞過、快取避免重複抓取）。API 層本身相對穩定，近期的變動集中在把它整合進公司（OpenFun）共用的認證/用量統計基礎設施，以及讓 AI agent 能透過 skill.md/knowledge.md 使用這個 API。

## 版本控制狀態

截至 2026-10-28（已 pull 到 `3bd45de`），PLAN.md 初版提到的未 commit 修改都已進版控；當時未追蹤的 `import-unit.php`、`test-parse-entry.php`、`test-url-map.php`、`get-company.php`、`export-company-size.php` 與 `error.data` 已不在工作目錄中（unit 表維護改由 `import.php` 負責）。`Elastic::dbQuery()` 出錯時仍會寫出 `error.data`，屬執行期產物，不應進版控（`.gitignore` 目前只排除 `config.php`）。

其他要注意的歷史狀況：
- 2026-07 有 4 個 commit（proxy 連線方式、bulk-import-search 空白修正、爬蟲心跳、PLAN.md 初版）在 origin 與本機各有一份內容相同、雜湊不同的版本，已在 `70c0b97` 合併收斂；`git log` 看到成對的重複訊息是正常的。
- 本機 `origin` 的 fetch refspec 曾誤設成 `refs/remotes/github/*`，導致 `origin/master` 長期沒更新（`bf515a1` 已修正）。pull/push 前若覺得狀態怪怪的，先檢查 `git config --get-all remote.origin.fetch`。
- `check-proxies.php`、`test-proxy.php` 是放在版控外的 proxy 維運工具，也讀 `FETCH_USER_AGENT`；2026-09 以前它們用被擋的 Chrome UA 測試，那段期間的健康檢查結果不可靠。

## 給接手 Agent 的建議

- **不要把 `config.php` 內的帳密寫進任何文件或 commit**；它已被 `.gitignore`，處理相關設定檔時也要留意別不小心把其他含機密的環境設定檔 `git add` 進去。
- 改動 `Parser.php` 前，務必先抽樣比對不同年代、不同公告類型的解析結果，確認不會讓既有欄位的解析結果跑掉——這個 parser 是十幾年資料格式演變堆疊出來的，改一處很容易讓另一個舊格式的公告解析壞掉。
- 爬蟲相關腳本（`crawl-list.php`/`crawl-entry.php`）高度依賴外部代理與政府網站的實際行為（會被擋、會出現驗證碼），修改時要注意這些腳本是長時間運行的背景程序（`while (true)` 迴圈 + `sleep`），不是一次性腳本。
- API 層（`ApiController.php`）目前用參數命名 `unit_id`（對外）對應到內部欄位 `oid`，這是刻意的設計（見 2018 年的 commit 訊息），新增 API 時應該維持這個對外命名慣例。
- 新增或修改 API 端點／參數時，記得同步更新 swagger 註解與 `webdata/skill.md`，讓給 AI agent 的說明不會跟實際 API 脫節。
- 除了 `searchbyrefusedvendor`，其他搜尋類端點（`searchbycompanyname` 等）目前還沒有做非空 query 驗證；缺 query 時會回 0 筆。若要補驗證，可以參考 `9b955c3` 的做法。
