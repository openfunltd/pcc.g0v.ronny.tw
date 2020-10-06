<?php

class Parser
{
    public static function parse_list_np($content)
    {
        $doc = new DOMDocument;
        @$doc->loadHTML($content);

        $records = array();
        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if (strpos($a_dom->getAttribute('class'), 'tenderLink') !== false) {
                $href = $a_dom->getAttribute('href');
                if (preg_match('#/tps/tpam/main/tps/tpam/tpam_tender_detail.do\?searchMode=common&scope=F&primaryKey=([0-9]*)#', $href)) {
                    $records[] = $href;
                } elseif (preg_match('#/tps/main/pms/tps/atm/atmNonAwardAction.do\?searchMode=common&method=nonAwardContentForPublic&pkAtmMain=[0-9]#', $href)) {
                    $records[] = $href;
                } elseif (preg_match('#/tps/main/pms/tps/atm/atmAwardAction.do\?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=[0-9]*&tenderCaseNo=[^&]*#', $href)) {
                    $records[] = $href;
                } elseif (preg_match('#/tps/tps/tp/main/pms/tps/tp/InitialDocumentPublicRead.do\?pMenu=common&method=getTpReadFormal&tpReadSeq=[0-9]*#', $href)) {
                    $records[] = $href;
                } elseif ($href == '#') {
                    // TODO: 財物變更公告
                } elseif (preg_match('#javascript:document.tpAppealForm[0-9]*.submit\(\);#', $href)) {
                    // TODO: 公開徵求廠商提供參考資料(不刊公報)
                } else {
                    print_r($href);
                    exit;
                }
            }
        }
        return $records;
    }

    public static function parse_list($content)
    {
        $doc = new DOMDocument;
        $content = str_replace('<br/>', "\n", $content);
        @$doc->loadHTML($content);

        $records = array();
        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if ($a_dom->getAttribute('class') != 'tenderLink') {
                continue;
            }

            $records[] = $a_dom->getAttribute('href');
        }
        unset($dom);

        return $records;
    }

    public static function parse_plain_only($dom, $doc = null)
    {
        $str = '';
        if (is_scalar($dom)) {
            return $dom;
        }
        if (is_null($dom)) {
            throw new Exception("parse_plain_only null ?", 999);
        }
        foreach ($dom->childNodes as $node) {
            if ($node->nodeName == 'img') {
                // <img src="/CNSServlet/KaiCGI?page=F&amp;code=2A27&amp;size=14" alt="罕用字" border="0" style="vertical-align:middle;">師傅企業股份有限公司
                if (preg_match('#/CNSServlet/KaiCGI\?page=([^&]*)&code=([^&]*)&size=14#', $node->getAttribute('src'), $matches)) {
                } elseif ($node->getAttribute('src') == '../../../../images/space.gif') {
                    $str = '';
                    continue;
                } else {
                    throw new Exception('不認得的圖片網址 ' . $node->getAttribute('src'), 999);
                }
                $str = $str . CNS2UTF8::convert($matches[1], $matches[2]);
            } else if ($node->nodeName == 'br') {
                $str = $str . "\n";
            } else if ($node->nodeName == 'page' or $node->nodeName == 'code') {
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=63934354
                $str = $str . $node->nodeValue;
            } else if ($node->nodeName == '#text') {
                $str = $str . $node->nodeValue;
            } else if ($node->nodeName == 'font' and $node->getAttribute('size') == 3) {
                $str = $str . $node->nodeValue;
            } else if ($node->nodeName == 'span' and $node->getAttribute('style') == 'color:red;font-weight:bold') {
                //    <span style="color:red;font-weight:bold" title="前次公告為：000">001</span>
                $str = $str . $node->nodeValue;
            } elseif (in_array($node->getAttribute('id'), array('pkPurAnnounce', 'toPurQuery'))) { 
                // do nothing
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52597213&tenderCaseNo=N107221330153
            } elseif ($node->getAttribute('class') == 'remind_msg' and trim($node->nodeValue) == '') {
                // do nothing
            } elseif ($node->getAttribute('class') == 'remind_msg' and trim($node->nodeValue) == '(原招標公告內容:最低標)') {
                // do nothing
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52598334&tenderCaseNo=20181008

            } else if ($node->getAttribute('id') == 'pkPurAnnounce' and $node->getAttribute('value') == '0') {
                // <input type="hidden" id="pkPurAnnounce" value="0">A
                // do nothing
            } else if (preg_match('/display:\s*none/', $node->getAttribute('style'))) {
                // do nothing
            } elseif ($doc and preg_match('/onclick="openPurCommonWindow\((\d+)\)" value="採購評選委員名單"/', $doc->saveHTML($node), $matches)) {
                //                                         <div align="left">
                //                                                                                         <input type="button" class="titlefont80" onclick="openPurCommonWindow(34982)" value="採購評選委員名單" style="text-align: center">
                //                                                                                                                                 </div>
                $ret = array(
                    'extra' => true,
                    'null' => trim($str),
                    '採購評選委員名單' => 'http://web.pcc.gov.tw/tps/pur/PurDetailPeople.do?method=viewPurDetailPeople&pkPurAnnounce=' . $matches[1],
                );
                return $ret;
            } else {
                return $dom;
            }
        }
        return $str;
    }

    public function parseHTML($content, $url)
    {
        if (strpos($content, '系統發生錯誤(Error:500)。')) {
            throw new Exception("500: $url", 500);
        }

        if (strpos($content, '您尚未登入或已被登出本系統')) {
            throw new Exception("403: $url", 403);
        }

        if (strpos($content, '政府電子採購網_失敗訊息畫面')) {
            throw new Exception("404: $url", 400);
        }

        if (strpos($content, '找不到標案')) {
            throw new Exception("404: $url", 400);
        }

        if (strlen($content) > 30 * 1024 * 1024) {
            throw new Exception("too big: $url", 888);
        }

        // 修正變更
        $content = preg_replace_callback('#<span\s+style=\'color:red;font-weight:bold\'\s+title=\'[^\']*\'>(.*)</span>#', function($matches) {
            return $matches[1];
        }, $content);
         
        $doc = new DOMDocument;
        @$doc->loadHTML($content, LIBXML_PARSEHUGE | LIBXML_BIGLINES | LIBXML_COMPACT);

        $type = null;
        foreach ($doc->getElementsByTagName('h1') as $h1_dom) {
            if ($h1_dom->nodeValue != '' and $h1_dom->nodeValue != '政府電子採購網') {
                $type = trim($h1_dom->nodeValue);
                break;
            }
        }

        if (!$type) {
            $types = array(
                '公開招標公告',
                '公開招標更正公告',
                '公開取得報價單或企劃書公告',
                '公開取得報價單或企劃書更正公告',
                '公開徵求廠商提供參考資料',
                '公開徵求廠商提供參考資料更正公告',
                '招標文件公開閱覽公告資料更正公告',
                '招標文件公開閱覽公告資料公告',
                '公示送達公告',
                '公示送達更正公告',
                '拒絕往來廠商名單公告',
                '拒絕往來廠商名單更正公告',
                '財物變賣公告',
                '財物變賣更正公告',
                '財物出租公告',
                '財物出租更正公告',
                '限制性招標(經公開評選或公開徵求)公告',
                '限制性招標(經公開評選或公開徵求)更正公告',
                '選擇性招標(建立合格廠商名單)公告',
                '選擇性招標(建立合格廠商名單)更正公告',
                '選擇性招標(個案)公告',
                '選擇性招標(個案)更正公告',
                '選擇性招標(建立合格廠商名單後續邀標)公告',
                '選擇性招標(建立合格廠商名單後續邀標)更正公告',
            );
            foreach ($types as $t) {
                if (strpos(str_replace('(不刊公報)', '', $content), '<font size="4">' . $t . '</font>')) {
                    $type = $t; 
                    break;
                }
            }
            if (!$type) {
                foreach ($doc->getElementsByTagName('h2') as $h2_dom) {
                    if (in_array(str_replace('(不刊公報)', '', $h2_dom->nodeValue), $types)) {
                        $type = $h2_dom->nodeValue;
                        break;
                    }
                }
            }
        }

        if (!$type) {
            if (strpos($content, '無法決標的理由')) {
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=63980180
                $type = '無法決標公告';
            } else {
                throw new Exception("找不到 type", 999);
            }
        }


        $values = new StdClass;
        $values->type = $type;
        if ($doc->getElementById('hidden_message_id')) {
            $values->type2 = trim($doc->getElementById('hidden_message_id')->nodeValue);
        }
        $values->url = $url;

        $prefix = array();
        $common_seq = array();


        // 先找 #print_area 下的第一個 table
        if ($doc->getElementById('print_area')) {
            $table_dom = $doc->getElementById('print_area')->getElementsByTagName('table')->item(0);
        } elseif ($doc->getElementById('printArea')) {
            $table_dom = $doc->getElementById('printArea')->getElementsByTagName('table')->item(0);
        } elseif ($doc->getElementById('printRange')) {
            // http://web.pcc.gov.tw/tps/tps/tp/main/pms/tps/tp/InitialDocumentPublicRead.do?pMenu=common&method=getTpReadFormal&tpReadSeq=50019433
            $table_dom = $doc->getElementById('printRange')->getElementsByTagName('table')->item(0);
        } elseif ($doc->getElementById('print')) {
            // Ex: https://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20171031&fn=PPW-1-50088559.xml
            // 公開徵求廠商提供參考資料
            $table_dom = $doc->getElementById('print')->parentNode->parentNode->nextSibling->nextSibling->getElementsByTagName('table')->item(0);
        } else {
            $table_dom = false;
        }

        if (!$table_dom) {
            if (trim(readline("來自 {$url} 的連結解析失敗，找不到 #print_area 下的第一個 table ，請問需要跳過他嗎？")) == "y") {
                return false;
            } else {
                throw new Exception("找不到 #print_area 下的第一個 table", 999);
            }
        }

        // 先看看有沒有 tbody ，有的話就從 tbody 出發
        $table_tr_nodes = null;
        foreach ($table_dom->childNodes as $childNode) {
            if ($childNode->nodeName == 'tbody') {
                $table_tr_nodes = $childNode->childNodes ;
                break;
            }
        }
        if (is_null($table_tr_nodes)) {
            $table_tr_nodes = $table_dom->childNodes;
        }

        // 從每個 tr 開始查起
        $rowspans = array();
        $comment_segment = false;
        foreach ($table_tr_nodes as $table_child_node) {
            if ($table_child_node->nodeName == 'tr') {
                // 有 tr ，往下跑
            } elseif ($table_child_node->nodeName == '#text' and trim($table_child_node->nodeValue) == '') {
                continue;
            } elseif ($table_child_node->nodeName == '#comment') {
                if (preg_match('#delimiter (.*) Start#', $table_child_node->nodeValue, $matches)) {
                    $comment_segment = $matches[1];
                }
                continue;
            } elseif (in_array($table_child_node->nodeName, array('script', 'link', 'caption'))) {
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=62895087 這什麼鬼
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=64052981 caption
                continue;
            } else {
                echo $doc->saveHTML($table_child_node);
                throw new Exception("不是 tr, 空白, #comment ，那是什麼？", 999);
            }

            $tr_dom = $table_child_node;
            // 如果是 th colspan="3" 就跳過不管，這是招標公告的標題
            if ($tr_dom->childNodes->item(1) and $tr_dom->childNodes->item(1)->nodeName == 'th' and $tr_dom->childNodes->item(1)->getAttribute('colspan') == 3) {
                continue;
            }
            if ($tr_dom->childNodes->item(1) and $tr_dom->childNodes->item(1)->nodeName == 'caption' and $tr_dom->childNodes->item(1)->getAttribute('colspan') == 3) {
                continue;
            }


            // 如果是 td colspan="4" 就跳過不管，這是決標公告的標題
            if ($tr_dom->childNodes->item(1) and $tr_dom->childNodes->item(1)->nodeName == 'td' and $tr_dom->childNodes->item(1)->getAttribute('colspan') == 4) {
                continue;
            }
            if ($tr_dom->childNodes->item(1) and $tr_dom->childNodes->item(1)->nodeName == 'caption' and $tr_dom->childNodes->item(1)->getAttribute('colspan') == 4) {
                continue;
            }

            if (strpos($type, '無法決標公告') !== FALSE and $tr_dom->getAttribute('height') == 1) {
                // 無法決標公告 高度為1 是第一行空白
                continue;
            }
            if (strpos($type, '無法決標公告') !== FALSE and trim($tr_dom->nodeValue) == '') {
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=63483700 後面有個空白的 tr...
                continue;
            }

            // 抓 th 和 td doms 出來
            $doms = array();
            foreach ($tr_dom->childNodes as $childNode) {
                if (in_array($childNode->nodeName, array('td', 'th'))) {
                    $doms[] = $childNode;
                } elseif ($childNode->nodeName == '#text' and trim($childNode->nodeValue) == '') {
                    continue;
                } else {
                    echo $doc->saveHTML($tr_dom);
                    throw new Exception("tr 內除了 th, td 外有其他 childNode", 999);
                }
            }

            // 開始包含 rowspans 內，計算三欄值是多少
            $result = array();
            if ($rowspans[0]['counter'] and count($doms) >= 3) {
                $prev_title = trim($rowspans[0]['dom']->parentNode->getAttribute('class'));
                $new_title = trim($doms[0]->parentNode->getAttribute('class'));
                if ($prev_title == 'tender_table_tr_3' and $new_title == 'tender_table_tr_4') {
                    $rowspans[0]['counter'] --;
                } else {
                    foreach ($doms as $d) {
                        echo $doc->saveHTML($d) . "\n";
                    }
                    throw new Exception("Test {$prev_title} {$new_title}", 999);
                }
            }
            while ($doms) {
                // 先看看現在位置有沒有 rowspan
                $idx = count($result);

                if (array_key_exists($idx, $rowspans) and $rowspans[$idx]['counter']) {
                    $result[] = $rowspans[$idx]['dom'];
                    $rowspans[$idx]['counter'] --;
                } else if ($dom = array_shift($doms)) {
                    if ($dom->getAttribute('rowspan')) {
                        $rowspans[count($result)] = array(
                            'dom' => $dom,
                            'counter' => intval($dom->getAttribute('rowspan')) - 1,
                        );
                    }
                    $result[] = $dom;
                } else {
                    echo $doc->saveHTML($tr_dom);
                    throw new Exception("預期每行都是三欄，但是這邊抓不到三欄", 999);
                }
            }

            // 在 決標公告 左邊欄只會佔一格
            if (count($result) == 1 and $result[0]->nodeName == 'td' and $result[0]->getAttribute('rowspan')) {
                $rowspans[0]['dom'] = $rowspans[0]['dom']->getElementsByTagName('span')->item(0);
                continue;
            }

            if (count($result) == 2 and $result[1]->childNodes->item(1)->nodeName == 'table') {
                $results = array(); // XXX
                foreach ($result[1]->getElementsByTagName('table')->item(0)->childNodes as $subTableChildNode) {
                    if ($subTableChildNode->nodeName == '#text' and trim($subTableChildNode->nodeValue) == '') {
                        continue;
                    } elseif ($subTableChildNode->nodeName == '#comment') {
                        continue;
                    } elseif ($subTableChildNode->nodeName == 'tr') {
                        // tr 裡預期只會有 th+td
                        $new_result = array($result[0]);

                        foreach ($subTableChildNode->childNodes as $node) {
                            if ($node->nodeName == '#text' and trim($node->nodeValue) == '') {
                                continue;
                            } else if (in_array($node->nodeName, array('td', 'th'))) {
                                $new_result[] = $node;
                            } else {
                                var_dump($doc->saveHTML($node));
                                throw new Exception("遇到未知的情況", 999);
                            }
                        }
                        $results[] = $new_result;
                    } else {
                        var_dump($doc->saveHTML($subTableChildNode));
                        throw new Exception("遇到未知的情況", 999);
                    }
                }
            } elseif (count($result) == 1 and strpos($type, '無法決標公告') !== FALSE and $result[0]->nodeName == 'td' and $result[0]->getElementsByTagName('tr')->length and in_array(trim($result[0]->getElementsByTagName('tr')->item(0)->nodeValue), array('最有利標', '評分及格最低標'))) {
                if (!$doc->getElementById('mat_venderArguTd')) {
                    //http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=60466484 沒東西哪招...
                    continue;
                }
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=64058256 含最有利標表格
                $results = array(array(
                    '最有利標',
                    '評選委員',
                    $doc->getElementById('mat_venderArguTd'),
                ));
            } elseif (count($result) == 2 and (strpos($type, '無法決標公告') !== false)) {
                // 無法決標公告 只會有兩欄
                $results = array(array('無法決標公告', $result[0], $result[1]));

            } else if (count($result) != 3) {
                if ($comment_segment == '撤銷公告' and count($result) == 2 and strpos(trim($result[0]->nodeValue), '撤銷公告') === 0) {
                    // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=64012480
                    $results = array(array("撤銷公告", $result[0], $result[1]));
                } else {
                    print_r($rowspans);
                    print_r($values);
                    print_r($result);
                    echo $doc->saveHTML($tr_dom);
                    throw new Exception("{$type} 應該要有三欄, 結果只有 " . count($result) . " 欄", 999);
                }
            } else {
                $results = array($result);
            }

            foreach ($results as $result) {
            // 開始針對 result 三個欄位解析
            // 0 跟 1 都應該是純文字
            $result[0] = self::parse_plain_only($result[0]);
            if (!is_scalar($result[0])) {
                echo $doc->saveHTML($tr_dom);
                throw new Exception("預期三欄的第一欄要是純文字", 999);
            }

            // 第 2 欄雖然是純文字，但有可能遇到前面空一個全形空白或是 span.shift 的情況其實是與上方同分類
            $result[1] = self::parse_plain_only($result[1]);
            if (is_scalar($result[1])) {
                $key = trim($result[1]);
            } elseif ($result[1]->childNodes->item(0)->nodeName == 'span' and $result[1]->childNodes->item(0)->getAttribute('class') == 'shift') {
                $key = trim('　' . trim($result[1]->childNodes->item(0)->nodeValue));
            } else if (!is_scalar($result[0]) or !is_scalar($result[1])) {
                if (in_array($url, array(
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200611&fn=TIQ-6-52813580.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200610&fn=TIQ-5-52813580.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200620&fn=TIQ-5-52822704.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200618&fn=TIQ-5-52820837.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200618&fn=TIQ-5-52820858.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200618&fn=TIQ-5-52820871.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200617&fn=TIQ-5-52819276.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200623&fn=TIQ-5-52824544.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200703&fn=TIQ-5-52831978.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200702&fn=TIQ-5-52830677.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200702&fn=TIQ-5-52830881.xml',
                    'http://web.pcc.gov.tw/tps/tpam/main/tps/tpam/tpam_tender_detail.do?searchMode=common&scope=F&primaryKey=3887615',
                    'http://web.pcc.gov.tw/tps/tpam/main/tps/tpam/tpam_tender_detail.do?searchMode=common&scope=F&primaryKey=3887614',
                ))) {
                continue;
                }
                if ($tr_dom->getElementsByTagName('th')->length and trim($tr_dom->getElementsByTagName('th')->item(0)->nodeValue) == '疑義、異議、申訴及檢舉受理單位') {
                    continue;
                }

                echo $doc->saveHTML($tr_dom);
                var_dump($url);
                throw new Exception("預期三欄的一二欄應該是純文字", 999);
            }

            $category = preg_replace('#\s*#', '', $result[0]);
            if ($category == '英文公告') {
                // TODO: 英文公告忘了加 shift ，會有重覆問題
                continue;
            }
            if ($comment_segment == '撤銷公告') {
                // http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmHistoryAction.do?method=review&searchMode=common&pkPmsMainHist=64012480
                $category = '撤銷公告';
            }

            // 特別處理 key
            if ($key == '品項編號') {
                $key .= trim($result[2]->nodeValue);
            }
            $key = preg_replace("#\s+#", "", $key);

            if (preg_match('#^投標廠商[0-9]+\(共同投標廠商\)$#', $key, $matches)) {
                if (!array_key_exists($category, $common_seq)) {
                    $common_seq[$category] = 0;
                }
                $common_seq[$category] ++;
                $key .= $common_seq[$category];
            }
            $key = str_replace(html_entity_decode('&nbsp;'), '', $key);

            $indent = 0;
            while (preg_match('#^　#u', $key)) {
                $key = preg_replace('#^　#u', '', $key);
                $indent ++;
            }
            $key = str_replace('　', '', $key);

            if ($indent) {
                $key = $prefix[$indent - 1] . ':' . $key;
            }

            if (array_key_exists($indent, $prefix)) {
                $origin_key = $prefix[$indent];
            } else {
                $origin_key = null;
            }
            $prefix[$indent] = $key;

            if (!property_exists($values, $category . ':' . $key)) {
                // 沒出現過的 key ，免做事
            } else if (in_Array($key, array(
                '評選委員',
                '刊登公報',
            ))) {
                // 評選委員在下面處理了，這邊免做事
            } else if (in_array($type, array(
                '招標文件公開閱覽公告資料公告',
                '標的分類',
            ))) {
                // 免做事
                // https://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20171031&fn=DTG-1-50019365.xml
                // 本來就會重覆
            } else if (in_array($type, array(
                '財物出租更正公告',
                '財物變賣更正公告',
                '財物變賣更正公告(不刊公報)',
                '公示送達更正公告',
                '招標文件公開閱覽公告資料更正公告',
                '公開徵求廠商提供參考資料更正公告',
                '拒絕往來廠商名單更正公告',
            ))) {
                $key .= ':更正值';
            } elseif (strpos($key, '品項編號') !== false) { // 品項編號重覆不做事
                // http://web.pcc.gov.tw/tps/tpam/main/tps/tpam/tpam_tender_detail.do?searchMode=common&scope=F&primaryKey=51291039

            } else {
                echo $doc->saveHTML($result[1]);
                echo $doc->saveHTML($result[2]);
                throw new Exception("重覆的 key {$category}:{$key}", 999);
            }


            $td_dom = self::parse_plain_only($result[2], $doc);

            // 第 3 欄看情況
            if (is_scalar($td_dom)) {
                if (strpos($key, '金額') or in_Array($key, array(
                    '標的分類',
                    '契約是否訂有依物價指數調整價金規定',
                )) or strpos($key, '廠商電話') or strpos($key, '履約起迄日期')) {
                    $values->{$category . ':' . $key} = preg_replace('#\s*#', '', $td_dom);
                } else {
                    $values->{$category . ':' . $key} = trim($td_dom);
                }
            } elseif (is_array($td_dom) and $td_dom['extra'] === true) {
                unset($td_dom['extra']);
                foreach ($td_dom as $k => $v) {
                    if ($k == 'null') {
                        $values->{$category . ':' . $key} = $v;
                    } else {
                        $values->{$category . ':' . $key . ':' . $k} = $v;
                    }
                }
            } elseif ('評選委員' == $key) {
                if (!$table_dom = $td_dom->getElementsByTagName('table')->item(0)) {
                    throw new Exception("找不到評選委員的 table", 999);
                }
                $columns = array();
                foreach ($table_dom->getElementsByTagName('tr')->item(0)->getElementsByTagName('th') as $th_dom) {
                    $columns[] = trim($th_dom->nodeValue);
                }

                $records = array();

                for ($i = 1; $i < $table_dom->getElementsByTagName('tr')->length; $i ++) {
                    $tr_dom = $table_dom->getElementsByTagName('tr')->item($i);
                    $td_doms = $tr_dom->getElementsByTagName('td');
                    if ($td_doms->length != count($columns)) {
                        continue;
                    }
                    $rows = array();
                    foreach ($td_doms as $td_dom) {
                        $rows[] = trim($td_dom->nodeValue);
                    }
                    $records[] = array_combine($columns, $rows);
                }
                if (!property_exists($values, $category . ':' . $key)) {
                    $values->{$category . ':' . $key} = array();
                }
                $values->{$category . ':' . $key}[] = $records;
            } elseif (trim($key) == '') {
                // Ex: https://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20161230&fn=BDM-1-52033899.xml 是否接受機關補助
                if ($origin_key == '是否受機關補助') {
                    if (!$table_dom = $td_dom->getElementsByTagName('table')->item(0)) {
                        throw new Exception("找不到機關補助的 table", 999);
                    }
                    $columns = array();
                    foreach ($table_dom->getElementsByTagName('tr')->item(0)->getElementsByTagName('td') as $td_dom) {
                        $columns[] = trim($td_dom->nodeValue);
                    }

                    $values->{$category . ':' . $origin_key} = array();

                    for ($i = 1; $i < $table_dom->getElementsByTagName('tr')->length; $i ++) {
                        $tr_dom = $table_dom->getElementsByTagName('tr')->item($i);
                        $td_doms = $tr_dom->getElementsByTagName('td');
                        if ($td_doms->length != count($columns)) {
                            continue;
                        }
                        $rows = array();
                        foreach ($td_doms as $td_dom) {
                            $rows[] = trim($td_dom->nodeValue);
                        }
                        $values->{$category . ':' . $origin_key}[] = array_combine($columns, $rows);
                    }
                } else {
                    throw new Exception("未知的 {$prefix[0]}", 999);
                }
            } elseif (in_array($key, array(
                '預算金額是否公開',
                '預算金額',
                '限制性招標依據之法條',
                '截止投標',
                '開標時間',
            ))) {
                // TODO: 先不管他
                $values->{$category . ':' . $key} = trim($td_dom->nodeValue);

            } elseif ($td_dom->childNodes->length == 0) {
            } elseif ($key == '是否適用條約或協定之採購') {
                // <strong>是否ooxx</strong>否<hr> ...
                $sub_key = null;
                foreach ($td_dom->childNodes as $node) {
                    if ($node->nodeName == '#text' and trim($node->nodeValue) == '') {
                        continue;
                    }
                    if ($node->nodeName == 'style' or $node->nodeName == 'hr') {
                        $sub_key = null;
                        continue;
                    }
                    if ($node->nodeName == 'strong') {
                        $sub_key = trim($node->nodeValue);
                        $sub_key = preg_replace('#：$#', '', $sub_key);
                        continue;
                    }
                    if ($node->nodeName == '#text' and !is_null($sub_key)) {
                        $values->{$category . ":{$key}:{$sub_key}"} = trim($node->nodeValue);
                        continue;
                    }
                    if ($node->nodeName == 'div' and $node->getAttribute('class') == 'gpa_shift') {
                        $values->{$category . ":{$key}:{$sub_key}:理由"} = trim(explode('：', $node->nodeValue)[1]);
                        continue;
                    }
                    print_r($values);
                    printf("key=%s, td->childNodes=%d, HTML=%s, wrong_node=%s", 
                        $category . ':' . $key,
                        $td_dom->childNodes->length,
                        $doc->saveHTML($td_dom),
                        $doc->saveHTML($node)
                    );
                    exit;
                }
            } elseif (strpos($key, '是否') === 0 or in_array($key, array(
                '後續擴充',
                '本案是否可能遲延付款',
            ))) {
                if ($word = trim($td_dom->childNodes->item(0)->nodeValue) and in_array($word, array('是', '否'))) {
                    $word = trim($td_dom->childNodes->item(0)->nodeValue);
                } else if ($span_dom = $td_dom->getElementsByTagName('span')->item(0) and $span_dom->getAttribute('style') == 'color:red;font-weight:bold') {
                    $word = trim($span_dom->nodeValue);
                } else {
                    $word = trim($td_dom->childNodes->item(0)->nodeValue);
                }
                if ($key == '是否須繳納押標金' and $td_dom->getElementsByTagName('div')->length) {
                    if (preg_match('#^\s*押標金額度：\s*(.*)$#m', $td_dom->getElementsByTagName('div')->item(0)->nodeValue, $matches)) {
                        $values->{"{$category}:{$key}:押標金額度"} = $matches[1];
                        $word = '是';
                    }
                }
                if (!in_array($word, array('是', '否'))) {
                    var_dump($word);
                    echo $doc->saveHTML($td_dom);
                    throw new Exception("{$key} 應該只有是跟否", 999);
                }

                $values->{"{$category}:{$key}"} = $word;

                if ($table_dom = $td_dom->getElementsByTagName('table')->item(0)) {
                    foreach ($table_dom->getElementsByTagName('tr') as $tr_dom) {
                        // 兩種情況，一個是 th > td ，另一個是 td colspan=2
                        $th_dom = $tr_dom->getElementsByTagName('th')->item(0);
                        $td_dom = $tr_dom->getElementsByTagName('td')->item(0);

                        if ($th_dom and $td_dom) {
                            $values->{"{$category}:{$key}:" . trim($th_dom->nodeValue)} = trim($td_dom->nodeValue);
                            if ($img_dom = $th_dom->getElementsByTagName('img')->item(0) and $img_dom->getAttribute('src') == '/tps/images/question_mark.jpg') {
                                $values->{"{$category}:{$key}:" . trim($th_dom->nodeValue) . ':remind'} = trim($img_dom->getAttribute('title'));
                            }
                        } elseif ($td_dom and $td_dom->getAttribute('colspan') == 2) {
                            if ($span_dom = $td_dom->getElementsByTagName('span')->item(0) and $span_dom->getAttribute('class') == 'link' and $a_dom = $span_dom->getElementsByTagName('a')->item(0)) {
                               
                                $values->{"{$category}:{$key}:" . trim($a_dom->nodeValue)} = 'http://web.pcc.gov.tw' . trim($a_dom->getAttribute('href'));
                            } else if (trim($td_dom->nodeValue) != '') {
                                $terms = explode("：", trim($td_dom->nodeValue));
                                if (count($terms) != 2) {
                                    echo $doc->saveHTML($table_dom);
                                    throw new Exception("{$key} 的表格出現奇怪的東西", 999);
                                }
                                $values->{"{$category}:{$key}:" . trim($terms[0])} = trim($terms[1]);
                            }
                        } else {
                            echo $doc->saveHTML($table_dom);
                            throw new Exception("{$key} 的表格出現奇怪的東西", 999);
                        }
                    }
                } elseif ($div_dom = $td_dom->getElementsByTagName('div')->item(0) and $div_dom->getAttribute('class') == 'shift' and $div_dom->getAttribute('id') != 'span_isDeposite') {
                    $key_wait = '';
                    $previous_key = null;
                    for ($i = 0; $i < $div_dom->childNodes->length; $i ++) {
                        $childNode = $div_dom->childNodes->item($i);

                        if ($childNode->nodeName == 'br') {
                            continue;
                        } elseif ($childNode->nodeName == '#text' and trim($childNode->nodeValue) == '') {
                            continue;
                        } elseif ($childNode->nodeName == 'strong') {
                            $childNode = $childNode->childNodes->item(0);
                        } elseif ($childNode->nodeName == 'div' and $childNode->getAttribute('class') == 'warn_msg_yellow') {
                            $values->{"{$category}:{$key}:remind"} = trim($childNode->nodeValue);
                            continue;

                        } elseif ($childNode->nodeName != '#text') {
                            echo $doc->saveHTML($div_dom);
                            throw new Exception("{$key} 的 div 出現奇怪的東西", 999);
                        }

                        if (preg_match('#：$#u', trim($childNode->nodeValue))) {
                            $key_wait = trim($childNode->nodeValue);
                            continue;
                        }

                        $terms = explode("：", trim($key_wait . $childNode->nodeValue), 2);
                        $key_wait = '';
                        if (count($terms) != 2) {
                            if (is_null($previous_key)) {
                                echo $doc->saveHTML($div_dom);
                                throw new Exception("{$key} 的 div 出現奇怪的東西", 999);
                            }
                            $values->{"{$category}:{$key}:{$previous_key}"} .= "\n" . trim($terms[0]);
                        } else {
                            $previous_key = trim($terms[0]);
                            $values->{"{$category}:{$key}:" . trim($terms[0])} = trim($terms[1]);
                        }
                    }
                }
            } elseif ($td_dom->childNodes->length == 3 and in_array('shift', explode(' ', trim($td_dom->childNodes->item(1)->getAttribute('class')))) or ($td_dom->childNodes->item(1)->nodeName=='span' and $td_dom->childNodes->item(1)->childNodes->item(1)->nodeName == 'span' and in_array('shift', explode(' ', trim($td_dom->childNodes->item(1)->childNodes->item(1)->getAttribute('class')))))) {
                $values->{$category . ':' . $key} = trim($td_dom->childNodes->item(0)->nodeValue);
                if (trim($td_dom->childNodes->item(1)->nodeValue)) {
                    if ($td_dom->childNodes->item(1)->nodeName == 'table') {
                        // 原產地國別 的情況
                        foreach ($td_dom->childNodes->item(1)->getElementsByTagName('tr') as $tr_dom) {
                            $td_doms = $tr_dom->getElementsByTagName('td');
                            if ($td_doms->length != 2) {
                                continue;
                            }
                            $values->{"{$category}:{$key}:" . trim($td_doms->item(0)->nodeValue)} = trim($td_doms->item(1)->nodeValue);
                        }
                    } else {
                        $values->{"{$category}:{$key}:remind"} = trim($td_dom->childNodes->item(1)->nodeValue);
                    }
                }
            } elseif ($td_dom->getElementsByTagName('span')->length and in_array('shift', explode(' ', trim($td_dom->getElementsByTagName('span')->item(0)->getAttribute('class'))))) {
                $v = '';
                for ($i = 0; $i < $td_dom->childNodes->length; $i ++) {
                    $childNode = $td_dom->childNodes->item($i);

                    if ($childNode->nodeName == '#text') {
                        $v = trim($v . $childNode->nodeValue);
                    } elseif ($childNode->nodeName == 'br') {
                    } elseif ($childNode->nodeName == 'span' and in_array('shift', explode(' ', $childNode->getAttribute('class')))) {
                        $values->{"{$category}:{$key}"} = $v;
                        $values->{"{$category}:{$key}:remind"} = trim($childNode->nodeValue);
                    }
                }
            } elseif ($td_dom->childNodes->length == 3 and ($td_dom->childNodes->item(1)->getAttribute('class') == 'remind_msg' or ($td_dom->childNodes->item(1)->nodeName=='span' and $td_dom->childNodes->item(1)->childNodes->item(1)->nodeName == 'span' and $td_dom->childNodes->item(1)->childNodes->item(1)->getAttribute('class') == 'remind_msg'))) {
                $values->{$category . ':' . $key} = trim($td_dom->childNodes->item(0)->nodeValue);
                if (trim($td_dom->childNodes->item(1)->nodeValue)) {
                    $values->{$category . ':' . $key . ':remind'} = trim($td_dom->childNodes->item(1)->nodeValue);
                }
            } elseif ($key == '履約執行機關') {
                if (!preg_match('#機關代碼：(.*)機關名稱：(.*)#', trim($td_dom->nodeValue), $matches))  {
                    throw new Exception('履約執行機關未知內容', 999);
                }
                $values->{$category . ':履約執行機關:機關代碼'} = $matches[1];
                $values->{$category . ':履約執行機關:機關名稱'} = $matches[2];

            } elseif (trim($key) == '疑義、異議、申訴及檢舉受理單位') {
                if (!$table_dom = $td_dom->getElementsByTagName('table')->item(0)) {
                    throw new Exception("找不到 疑義、異議、申訴及檢舉受理單位 的 table", 999);
                }
                foreach ($table_dom->getElementsByTagName('tr') as $tr_dom) {
                    $th_dom = $tr_dom->getElementsByTagName('th')->item(0);
                    $td_dom = $tr_dom->getElementsByTagName('td')->item(0);
                    if (!$th_dom or !$td_dom) {
                        continue;
                    }
                    $values->{"{$category}:{$key}:" . trim($th_dom->nodeValue)} = trim($td_dom->nodeValue);
                }
            } elseif (strpos($key, '原產地國別')) {
                if (!$table_dom = $td_dom->getElementsByTagName('table')->item(0)) {
                    throw new Exception("找不到原產地國別的 table", 999);
                }
                foreach ($table_dom->getElementsByTagName('tr') as $tr_dom) {
                    $td_doms = $tr_dom->getElementsByTagName('td');
                    if ($td_doms->length != 2) {
                        continue;
                    }
                    if ($td_doms->item(0)->nodeValue == '原產地國別') {
                        $values->{$category . ":{$key}"} = trim($td_doms->item(1)->nodeValue);
                    } elseif ($td_doms->item(0)->nodeValue == '原產地國別得標金額') {

                        $values->{$category . ":{$key}:得標金額"} = trim($td_doms->item(1)->childNodes->item(0)->nodeValue);
                    } else {
                        echo $doc->saveHTML($tr_dom);
                        throw new Exception("不明的 td " . $td_doms->item(0)->nodeValue, 999);
                    }
                }
            } else {
                if (in_array($values->url, array(
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52684690&tenderCaseNo=1080118',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52749007&tenderCaseNo=108-007',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20190911&fn=BDM-1-52870133.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191021&fn=BDM-1-52899553.xml',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52928697&tenderCaseNo=10901',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191118&fn=BDM-1-52925172.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191129&fn=BDM-1-52938712.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191128&fn=BDM-1-52936789.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191231&fn=BDM-1-52978355.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200529&fn=BDM-1-53101274.xml',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=53156005&tenderCaseNo=dh1090708',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200915&fn=BDM-1-53190625.xml',
                    )) and $category == '已公告資料' and $key == '本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購') {

                    if (strpos(trim($td_dom->nodeValue), '是') === 0) {
                        $values->{'已公告資料:本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購'} = '是';
                    } else {
                        $values->{'已公告資料:本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購'} = '否';
                    }
                    continue;
                } else if (in_array($values->url, array(
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52684690&tenderCaseNo=1080118',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20190418&fn=BDM-1-52748563.xml',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52749007&tenderCaseNo=108-007',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52928697&tenderCaseNo=10901',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191128&fn=BDM-1-52936789.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20191231&fn=BDM-1-52978355.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200629&fn=BDM-1-53125787.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200720&fn=BDM-2-52814463.xml',
                    'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20200915&fn=BDM-1-53190625.xml',
                    )) and $category == '已公告資料' and $key == '本採購是否屬「涉及國家安全」採購') {
                    if (strpos(trim($td_dom->nodeValue), '是') === 0) {
                        $values->{'已公告資料:本採購是否屬「涉及國家安全」採購'} = '是';
                    } else {
                        $values->{'已公告資料:本採購是否屬「涉及國家安全」採購'} = '否';
                    }
                    continue;
                }

                if ($values->url == 'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=52844991&tenderCaseNo=108-C024' and $category == '已公告資料' and $key == '本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購') {
                    $values->{'已公告資料:本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購'} = '否';
                    continue;
                }
                if ($values->url == 'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20100323&fn=BDM-1-50055366.xml' and $category == '決標資料' and $key == '附加說明') {
                    $values->{'決標資料:附加說明'} = "1.計壹家廠商投標,經前簽核准<...未能取得三家以上廠商之書面報價,由主持人當場改為限制性招標>,審查招標文件符合招標文件規定.
                    2.開價格標,01標160000元整低於底價176100元整得標.";
                    continue;
                } elseif ($values->url == 'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20190813&fn=BDM-1-52846530.xml' and $category == '已公告資料' and $key == '本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購') {
                    $values->{'已公告資料:本採購是否屬「具敏感性或國安(含資安)疑慮之業務範疇」採購'} = '是';
                    continue;
                } elseif (($category . ':' . $key) == '其他:附加說明') {
                    $values->{'其他:附加說明'} = trim($td_dom->nodeValue);
                    continue;
                } elseif (in_array($values->url . ':' . $category . ':' . $key, array(
                    'http://web.pcc.gov.tw/tps/tpam/main/tps/tpam/tpam_tender_detail.do?searchMode=common&scope=F&primaryKey=3865364:領投開標:收受投標文件地點',
                    'http://web.pcc.gov.tw/tps/tpam/main/tps/tpam/tpam_tender_detail.do?searchMode=common&scope=F&primaryKey=2549810:領投開標:收受投標文件地點',
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=2477440&tenderCaseNo=98P021:決標資料:附加說明',
                ))) {
                    $values->{$category . ':' . $key} = trim($td_dom->nodeValue);
                    continue;
                } elseif (in_array($values->url, array(
                    'http://web.pcc.gov.tw/tps/main/pms/tps/atm/atmAwardAction.do?newEdit=false&searchMode=common&method=inquiryForPublic&pkAtmMain=2597921&tenderCaseNo=98S013',
                ))) {
                    $values->{$category . ':' . $key} = trim($doc->saveHTML($td_dom));
                    continue;
                }
                print_r($values);
                printf("key=%s, td->childNodes=%d, HTML=%s", 
                    $category . ':' . $key,
                    $td_dom->childNodes->length,
                    $doc->saveHTML($td_dom)
                );
                exit;
            }
            }
        }
        return $values;
    }
}
