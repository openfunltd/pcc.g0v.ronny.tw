# PLAN.md — pcc.g0v.ronny.tw 專案架構與開發歷程

> 給 AI Agent 快速認識這個 repo 的說明文件。內容基於程式碼閱讀與 git log 整理（撰寫於 2026-07-10）。

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
import.php / import-unit.php ── 把 entry-json 讀出來，寫進 PostgreSQL 的 entity / entity_data / unit 三張表
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
  init.inc.php          # 共用 bootstrap：載入 pixframework、設定 include path、連 DB
  prompt.php            # （目前內容為空/佔位）
  controllers/
    ApiController.php   # 對外 REST API 的核心（/api/* 路由），含大量 swagger-php 註解
    IndexController.php # 網頁前台（首頁 / swagger 文件頁），實際前端已搬到另一個 repo
  models/
    Entity.php           # entity 表 ORM：標案摘要（date+filename 為主鍵），含 URL 還原邏輯
    EntityData.php        # entity_data 表 ORM：標案完整解析後 JSON 資料
    Unit.php              # unit 表 ORM：機關代碼 <-> 機關名稱對照
    Parser.php             # 核心 HTML parser，把採購網的標案詳細頁 HTML 轉成 key-value JSON
    Elastic.php            # 簡易 Elasticsearch client（bulk insert/commit、建立/刪除 index）
    APILimiter.php         # 簡單的 IP rate limiter（10秒10次/60秒60次），寫 flat file 到 /tmp
    OpenFunAPIHelper.php   # 對接 OpenFun 內部的 API 用量統計 / token 驗證系統
    CNS2UTF8.php           # 舊公文 CNS11643 編碼轉 UTF-8（處理早期政府網站的中文編碼問題）
  scripts/               # 命令列腳本（cron / 手動執行），構成上面提到的 pipeline
    crawl-list.php         # 抓每日列表頁
    crawl-entry.php        # 抓標案詳細頁（含 captcha 辨識、多組 proxy 輪替）
    parse-entry.php        # HTML -> JSON
    import.php             # JSON -> entity/entity_data (PostgreSQL)
    import-unit.php        # oidmapping.json -> unit 表
    bulk-import-search.php # entity/entity_data -> Elasticsearch
    init-db.php            # 建立/重建 Elasticsearch index mapping
    get-company.php        # 目前僅載入 bootstrap，內容尚未實作（工作中）
    export-company-size.php# 匯出公司規模相關資料（工作中，尚未完整）
    test-parse-entry.php   # 迴歸測試工具：用舊 JSON 與新 Parser 解析結果 diff，驗證 parser 改動有沒有改變既有欄位
    test-url-map.php       # 驗證 Entity::updateUrl() 產生的網址是否正確
  maps/                  # CNS11643 <-> Unicode 對照表（給 CNS2UTF8 用）
  stdlibs/pixframework/  # 內嵌的 PHP MVC 框架（Pix_Table / Pix_Controller / Pix_Loader...），第三方 vendored 程式碼
  views/                 # 前台頁面模板（.phtml），前台實際上多數功能已搬到 https://github.com/ronnywang/pcc-viewer
  swagger.json           # 由 ApiController 內的 swagger-php 註解產生的 OpenAPI 文件
  docs/                  # 目前是空的
.htaccess                # 全部導到 index.php（前端控制器路由）
```

爬蟲執行時，`list/`、`entry/`、`entry-json/` 這些資料目錄是在執行 script 的當下目錄產生的（不在 git 裡管理），資料量很大，靠 gzip 壓縮儲存原始 HTML 與解析後 JSON 快照。

## 技術棧

- **語言/框架**：純 PHP + 內嵌的 `pixframework`（Pix_Table 作為簡易 ORM／查詢建構器，Pix_Controller 作為前端控制器路由）。沒有用 Composer 或現代 PHP 框架。
- **資料庫**：PostgreSQL（透過 `DATABASE_URL` 環境變數連線，`entity`/`entity_data`/`unit` 三張表，`entity`/`entity_data` 用 `jsonb` 欄位存解析後的完整資料）。
- **搜尋引擎**：Elasticsearch（透過 `ELASTIC_URL`/`ELASTIC_USER`/`ELASTIC_PASSWORD`/`ELASTIC_PREFIX` 連線，用原生 curl + REST API 呼叫，沒有用官方 client library）。
- **爬蟲**：cURL，透過內部 proxy（`PROXY_HOST` + 多組 port 20001~20005 輪替）打政府採購網，處理驗證碼（用像素比對「連連看」圖形驗證碼的方式自動解，見 `crawl-entry.php` 內的 `$hack_captcha`）。
- **對外 API**：純手刻 REST（無框架式路由），用 swagger-php annotation 產生 `swagger.json` API 文件，掛在 `/index/swagger`。
- **用量控管**：`APILimiter`（IP-based rate limit）+ `OpenFunAPIHelper`（串接 OpenFun 平台的用量統計/token 認證系統，是作者所屬公司 OpenFun 的共用基礎設施）。

## 環境設定（config.php，不進 git）

`webdata/config.php` 透過 `putenv()` 設定以下變數（實際數值為機密，不應寫入程式碼或文件）：
- `DATABASE_URL`（PostgreSQL 連線字串）
- `PROXY_SECRET` / `PROXY_URL` / `PROXY_USER` / `PROXY_PASS` / `PROXY_HOST`（爬蟲用代理）
- `ELASTIC_URL` / `ELASTIC_USER` / `ELASTIC_PASSWORD` / `ELASTIC_PREFIX`（Elasticsearch）
- `API_COUNTER_DATABASE_URL`（`OpenFunAPIHelper` 用的另一個 PostgreSQL，記錄 API 用量統計，見程式碼內容，未出現在目前的 config.php 範例中，需要另外設定才能讓 `checkUsage`/`apiDone` 正常運作）

## 核心資料模型

- **Entity**（`entity` 表，主鍵 `date`+`filename`）：標案的「摘要」記錄。`brief` 欄位存精簡 JSON（type/title/companies），`oid`/`job_number` 供查詢，並提供 `link()`、`getFilename()`、`updateUrl()` 等 helper 把採購網原始（常是 base64 編碼過的）連結，還原/正規化成可直接開啟的公開網址。
- **EntityData**（`entity_data` 表，同樣主鍵 `date`+`filename`）：標案的「完整」解析結果（Parser 產出的所有 key-value），`keys` 欄位記錄有哪些欄位存在（因為每種公告類型欄位不同）。
- **Unit**（`unit` 表，主鍵 `oid`）：機關代碼 → 機關名稱對照表，資料來源是外部提供的 `oidmapping.json`（不在 repo 中）。

## Parser 邏輯（webdata/models/Parser.php）

這是整個專案最複雜、最需要小心維護的部分。政府採購網的標案詳細頁是傳統多層巢狀 `<table>` 排版，且**十幾年間版面改版多次、不同公告類型（決標公告/無法決標公告/財物變賣公告...）欄位結構都不一樣**。`Parser::parseHTML()` 用大量針對特定 category/key 組合的 hardcode 規則去抽欄位（例如「總決標金額」只取換行前第一段、「投標廠商」底下要用品項序號當 key 前綴避免重名覆蓋等）。

- 因為欄位命名規則歷史上多次變動，程式裡有不少「已知例外網址」的 hardcode 修正（例如 `parse-entry.php`、`import.php` 裡對特定 `url` 的補值）。
- `test-parse-entry.php` 是驗證 Parser 改動是否會破壞既有資料的迴歸測試工具：拿舊版 parser 產出的 JSON 快照，跟新版 parser 重新解析同一份 HTML 的結果做欄位級 diff，並用 `$special_check`/`$skip_space`/`$key_map` 白名單過濾「已知可接受的差異」。**修改 Parser 前應該先用這隻腳本跑過一輪，確認沒有引入非預期的資料變動。**

## 爬蟲細節與注意事項

- `crawl-list.php` / `crawl-entry.php` 都會先檢查本地是否已有有效快取（檔案存在、不是驗證碼頁、不是空檔、有 `</html>` 結尾…）才決定要不要重新抓，避免重複打政府網站。
- 有「刊登公報」（`list/{ymd}.html.gz`，2010-01-02 之後）和「不刊登公報」（`list/np-{ymd}.html.gz`，2012-06-19 之後）兩種列表來源，對應 `Parser::parse_list()` 和 `Parser::parse_list_np()` 兩種解析方式，且連結格式（`unPublish.tender.*`、`unPublish.award.*`、`unPublish.nonAward.*`、`unPublish.tpRead.*` 等）也不同，`Entity::getFilename()`/`Entity::updateUrl()` 負責把這些格式統一轉換成可用網址。
- `crawl-entry.php` 用多組 proxy port（20001~20005）輪替避免單一 IP 被政府網站封鎖，並實作了簡易的驗證碼圖形辨識（像素比對兩張撲克牌圖形找出相同花色，模擬人工選取）。這段邏輯相當 hacky、高度依賴政府網站特定的驗證碼 UI，若採購網改版驗證碼樣式，這段程式會直接失效需要重寫。

## 開發歷程 / 重大 Milestone（依 git log 整理）

專案 git 歷史從 2017 年開始（更早的資料可能是用其他方式匯入初始資料，不在此 repo 歷史內）。整體演進大致分幾個階段：

1. **2017 早期：前端 + 基礎爬蟲**
   - 建立 pixframework 基礎、Entity/Unit model、index/case/unit 頁面（`b5f13a9`, `d121c48`, `9a1547a`, `d09a76f`）。
   - 逐步加上搜尋頁面與 API（`/api/` 下的 search 系列 API）、CORS 支援（`7df82ba`）。
   - `oid` 概念改名為對外一致的 `unit_id`（`9b612d9`, `8bd4e43`），這個命名一路延續到現在的 API 參數。
   - 2018-08：前端 code 搬到獨立 repo [pcc-viewer](https://github.com/ronnywang/pcc-viewer)（`3697b01`），此 repo 之後專注在爬蟲 + API。
   - 2018-08：加入 `searchbycompanyid` API，並修過幾次 issue（#1、#2 by 外部貢獻者 kiang）。

2. **2019~2020：功能擴充**
   - 2019-04：新增「特別預算」相關 API（`bd1f2ec`）。
   - 2020-06：搜尋 API 支援自訂回傳欄位 `columns[]`（`e4b6481`）。
   - 2020-10：新增「公共工程生態檢核注意事項」欄位解析、新增 parser 程式碼架構（`aff2079`, `53e8e35`）— 這是 Parser.php 開始承擔大量欄位解析邏輯的起點。

3. **2022 年中：爬蟲重構為每日排程**
   - 2022-06：新增 `crawl-list.php`、`crawl-entry.php` 獨立腳本（`5948d4f`, `c39a68f`），確立目前這條「list→entry→parse→import」pipeline 的雛形。
   - 2022-07：政府採購網改版，Parser 需要大幅調整（`修改爬蟲`, `update parser`），`test-parse-entry.php` 這類「新舊 parser 結果比對」的驗證工具就是為了因應這次改版而生的。

4. **2023 年初：URL 正規化與 parser 大量迭代**
   - 2023-01：新增 `Entity::updateUrl()`/`Entity::getFilename()`/`EntityData::updateURL()`，統一處理不同年代、不同公告類型網址格式的還原邏輯（`0379aac`, `df09dc1`, `1e6ccaa`）。這段期間有大量「update parser」小 commit，顯示欄位解析規則持續在跟採購網的資料變動打地鼠。
   - 新增「idpr」（`unPublish.tpRead`）類公告的處理。

5. **2023 年秋：Elasticsearch 導入 + Swagger 文件化**
   - 2023-09：導入 Elasticsearch（`7b10ff2` 增加 elasticsearch 相關程式，改成新版；`17667ff` 增加 Elastic library），把原本純 PostgreSQL 查詢的搜尋 API 改為查 ES，並補上總決標金額/預算金額等聚合用欄位（`5560095`）。
   - 2023-09~10：加上 Swagger/OpenAPI 文件（`f83f71d`, `86a0fb8`, `c1be0d1` 等），開始用 swagger-php annotation 維護 API 文件，並修正幾個 GitHub issue（#5/#6/#7/#8，含外部 PR）。

6. **2025：API 認證/用量統計基礎建設**
   - 2025-03：加入 `APILimiter`（IP rate limit）與相關 blocking 機制（`c17ff6a`, `954cf5c`, `d5722e2`）。
   - 2025-05：加入 `OpenFunAPIHelper`，把這個專案接進 OpenFun 內部的 API 用量統計/token 認證系統（`51c83a8` 及後續 update）。這代表這個 API 開始被納入公司內部更大的 API 治理框架，不再是完全獨立的小專案。

7. **2026：Proxy 連線維運調整**
   - 2026-04：`修改 proxy 連線方式`（`6de25c5`）— 目前 working tree 上也有對應 proxy port 設定時機的修正（見下方「目前進行中的工作」）。

**觀察到的整體趨勢**：這是一個長期維運型的爬蟲專案，最大的技術債/心力集中在「政府採購網不定期改版」造成的 Parser 規則維護，以及「避免被防爬蟲機制擋下」的爬蟲穩定性工程（proxy 輪替、驗證碼繞過、快取避免重複抓取）。API 層本身相對穩定，近期的變動集中在把它整合進公司（OpenFun）共用的認證/用量統計基礎設施。

## 目前工作目錄中的進行中工作（尚未 commit）

跑 `git status` 時可以看到以下尚未進版本控制的變動，供接手的 Agent 參考現在正在做什麼：

- **已追蹤檔案的修改**（`M`）：
  - `crawl-list.php`：加了一行 debug log（記錄實際爬取的 URL 與 proxy port）。
  - `crawl-entry.php`：把 `$sleep_time` 從 100 調到 300（放慢爬取速度，可能是因應被政府網站限速/封鎖），並修正 proxy port 設定的時機 bug（原本在迴圈開頭就設定 curl proxy，但 `$if_id` 是稍後才更新，導致用到舊的 port；現在改成在 `$if_id` 更新後才設定 proxy），以及把 log 訊息從 `if_id=` 改成更精確的 `proxy_port=`。
  - `bulk-import-search.php`：加上 `?? false`，避免 `Entity::search(...)->first()` 找不到資料時對 null 取屬性噴 PHP 8 錯誤。
- **全新未追蹤的檔案**（`??`，這些檔案從未進過 git 歷史）：
  - `webdata/scripts/import.php`、`webdata/scripts/import-unit.php`：把 `entry-json` 匯入 PostgreSQL 的正式流程腳本（pipeline 中很關鍵的一段），目前竟然不在版本控制內 — 值得跟專案owner確認是否遺漏 `git add`，還是刻意排除。
  - `webdata/scripts/test-parse-entry.php`、`webdata/scripts/test-url-map.php`：Parser/URL 還原邏輯的驗證工具。
  - `webdata/scripts/get-company.php`：目前只有 include bootstrap，尚未實作任何邏輯。
  - `webdata/scripts/export-company-size.php`：匯出公司規模相關 CSV 的腳本，邏輯看起來還在草稿階段（只處理最新 1000 筆就 `exit`）。
  - `webdata/error.data`、`webdata/scripts/error.data`：`Elastic::dbQuery()` 寫入 ES bulk 發生錯誤時的除錯輸出，屬於執行期產生的檔案，不應該進版本控制（建議加進 `.gitignore`）。

## 給接手 Agent 的建議

- **不要把 `config.php` 內的帳密寫進任何文件或 commit**；它已被 `.gitignore`，處理相關設定檔時也要留意別不小心把其他含機密的環境設定檔 `git add` 進去。
- 改動 `Parser.php` 前，務必先用 `test-parse-entry.php` 的邏輯（或至少手動抽樣比對）確認不會讓既有欄位的解析結果跑掉——這個 parser 是十幾年資料格式演變堆疊出來的，改一處很容易讓另一個舊格式的公告解析壞掉。
- 爬蟲相關腳本（`crawl-list.php`/`crawl-entry.php`）高度依賴外部代理與政府網站的實際行為（會被擋、會出現驗證碼），修改時要注意這些腳本是長時間運行的背景程序（`while (true)` 迴圈 + `sleep`），不是一次性腳本。
- API 層（`ApiController.php`）目前用參數命名 `unit_id`（對外）對應到內部欄位 `oid`，這是刻意的設計（見 2018 年的 commit 訊息），新增 API 時應該維持這個對外命名慣例。
- `import.php`/`import-unit.php` 等關鍵 pipeline 腳本目前未被 git 追蹤，如果要基於這些檔案做修改，建議先跟使用者確認是否該補進版本控制，避免改動內容遺失。
