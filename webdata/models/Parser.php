<?php

class Parser
{
    public static function parse_list_np($content)
    {
        $doc = new DOMDocument;
        @$doc->loadHTML($content);

        $records = array();
        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if (strpos($a_dom->getAttribute('class'), 'tenderLinkUnPublish') !== false) {
                $href = $a_dom->getAttribute('href');
                if (preg_match('#unPublish.(tender|award).(.*)#', $href, $matches)) {
                    // ttd
                    $records[] = $href;
                } elseif (preg_match('#unPublish.nonAward.(.*)#', $href, $matches)) {
                    // anaa
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.tender\.(.*)#', $href, $matches)) {
                    // aaa
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.gpa\.(.*)#', $href, $matches)) {
                    // TODO: 採購預告公告
                    continue;
                } elseif (preg_match('#unPublish\.tpRead\.(.*)#', $href, $matches)) {
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.(aspam|arpam)\.(.*)#', $href, $matches)) {
                    // TODO: 財物變更公告
                } elseif (preg_match('#/opas/aspam/public/readOneAspamDetailNew#', $href, $matches)) {
                    // TODO: 財物變更公告
                } elseif (preg_match('#/opas/arpam/public/readOneArpamDetailNew#', $href, $matches)) {
                    // TODO: 財物出租公告
                } elseif (preg_match('#unPublish\.tpAppeal\.(.*)#', $href, $matches)) {
                    // TODO: 公開徵求廠商提供參考資料(不刊公報)
                } else {
                    print_r($href);
                    echo "\n";
                    readline($np_target . ' wrong!!!');
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
            if ($a_dom->getAttribute('class') == 'tenderLinkPublish') {
                $records[] = $a_dom->getAttribute('href');
            }
        }
        unset($dom);

        return $records;
    }

    public static function getDomValue($dom)
    {
        $ret = '';
        if ($dom->nodeName == '#text') {
            return $dom->nodeValue;
        }
        foreach ($dom->childNodes as $node) {
            if ($node->nodeName == '#text') {
                $ret .= trim(preg_replace_callback('/&#\d+;/', function($m){
                    return html_entity_decode($m[0]);
                },  preg_replace('#\s+#', ' ', $node->nodeValue)));
                continue;
            }
            if (in_array($node->nodeName, ['#comment', '#cdata-section'])) {
                continue;
            }
            if (strpos($node->nodeName, '#') === 0) {
                throw new Exception($node->nodeName);
            }
            if (strpos($node->getAttribute('style'), 'display: none') !== false) {
                continue;
            }
            if ($node->nodeName == 'br') {
                $ret .= "\n";
                continue;
            }
            // https://web.pcc.gov.tw/tps/atm/AtmAwardWithoutSso/QueryAtmAwardDetail?pkAtmMain=NTM3NDIzNTg=
            if ($node->getAttribute('id') == 'Prompt_Message') {
                break;
            }
            $ret .= str_replace('&nbsp', '', self::getDomValue($node));
        }
        if ($node->nodeName == 'p') {
            return trim($ret) . "\n";
        } else {
            return trim($ret);
        }
    }

    public static function parseHTML($content, $url)
    {
        if (strlen($content) > 30 * 1024 * 1024) {
            throw new Exception("too big: $url", 888);
        }

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8" ?\>' . $content, LIBXML_PARSEHUGE | LIBXML_BIGLINES | LIBXML_COMPACT);

        $type = null;
        foreach ($doc->getElementsByTagName('div') as $div_dom) {
            if ($div_dom->getAttribute('class') != 'title_1') {
                continue;
            }
            $type = preg_replace('#\s+#', '', $div_dom->nodeValue);
            break;
        }

        if (!$type) {
            foreach ($doc->getElementsByTagName('span') as $span_dom) {
                if ($span_dom->getAttribute('class') == 'ff') {
                    $type = $span_dom->nodeValue;
                }
            }
        }

        if (!$type) {
            foreach ($doc->getElementsByTagName('font') as $span_dom) {
                if ($span_dom->getAttribute('class') == 'ff') {
                    $type = $span_dom->nodeValue;
                }
            }
        }
        if (!$type) {
            throw new Exception("找不到 type", 999);
        }


        $values = new StdClass;
        $values->type = $type;
        if ($type == '無法決標公告') {
            foreach ($doc->getElementsByTagName('div') as $div_dom) {
                if ($div_dom->getAttribute('class') == 'flo-w') {
                    $values->type2 = $div_dom->nodeValue;
                }
            }
        }
        $values->url = $url;

        $prefix = array();
        $common_seq = array();


        foreach ($doc->getElementsByTagName('table') as $table_dom) {
            $td_doms = $table_dom->getElementsByTagName('td');
            if (!$td_dom = $td_doms->item(0) or $td_dom->nodeValue != '標案內容') {
                continue;
            }
            $category = $td_dom->nodeValue;
            if (!$table_dom = $table_dom->getElementsByTagName('table')->item(0)) {
                continue;
            }
            foreach ($table_dom->childNodes as $node) {
                if ($node->nodeName != 'tr') {
                    continue;
                }
                $td_doms = $node->getElementsByTagName('td');
                $key = trim($td_doms[0]->nodeValue);
                $value = trim($td_doms[1]->nodeValue);
                $values->{"{$category}:{$key}"} = $value;
            }
        }

        // 從每個 tr 開始查起
        foreach ($doc->getElementsByTagName('table') as $table_dom) {
            if (strpos($table_dom->getAttribute('class'), 'tb_') !== 0) {
                continue;
            }
            if ($table_dom->parentNode->getAttribute('id') == 'mat_venderArguTd') {
                continue;
            }
            $prevdom = $table_dom;
            while ($prevdom = $prevdom->previousSibling) {
                if ($prevdom->nodeName == 'div' and $prevdom->getAttribute('class') == 'title_1' and $prevdom->nodeValue == '作業歷程') {
                    continue 2;
                }
                if ($prevdom->nodeName == 'div' and $prevdom->getAttribute('class') == 'title_1' and $prevdom->nodeValue == '作業歷程') {
                    continue 2;
                }
            }
            $tr_doms = [];
            foreach ($table_dom->childNodes as $node) {
                if ($node->nodeName == 'tr') {
                    $tr_doms[] = $node;
                }
            }
            while ($tr_dom = array_shift($tr_doms)) {
                $td_doms = [];
                if (strpos($tr_dom->getAttribute('style'), 'display:none') !== false) {
                    continue;
                }
                foreach ($tr_dom->childNodes as $td_dom) {
                    if ($td_dom->nodeName == 'td') {
                        // 如果只有一層 div.tbc2L ，就解開來
                        $nodes = [];
                        foreach ($td_dom->childNodes as $node) {
                            if ($node->nodeName == '#text' and trim($node->nodeValue) == '') {
                                continue;
                            }
                            $nodes[] = $node;
                        }
                        if (count($nodes) == 1 and $nodes[0]->nodeName == 'div') {
                            $td_doms[] = $nodes[0];
                        } else {
                            $td_doms[] = $td_dom;
                        }
                    }
                }

                if (count($td_doms) == 1) {
                    $category = preg_replace('#\s#', '', $td_doms[0]->nodeValue);
                    $category = str_replace(html_entity_decode('&nbsp;'), '', $category);
                    if ($category == '無法決標資料') {
                        $category = '無法決標公告';
                    }
                } elseif (count($td_doms) == 2) {
                    $key = trim($td_doms[0]->nodeValue);
                    $key = preg_replace("#\s+#", "", $key);
                    $key = str_replace(html_entity_decode('&nbsp;'), '', $key);
                    $key = str_replace('　', '', $key);

                    if (in_array($category, ['招標品項', '決標品項']) and $key == '品項編號') {
                        $item_no = $key . trim($td_doms[1]->nodeValue);
                    }

                    $value = self::getDomValue($td_doms[1]);

                    if ($category == '投標廠商') {
                        if (in_array($key, [
                            '廠商電話',
                            '履約起迄日期',
                        ])) {
                            $value = preg_replace('#\s#', '', $value);
                        } elseif (in_array($key, [
                            '決標金額',
                        ])) {
                            $value = trim(explode("\n", $value)[0]);
                        }

                        if (preg_match('#^投標廠商\d+$#', $key)) {
                            $item_no = $key;
                        } elseif (in_array($key, [
                            '投標廠商家數',
                        ])) {
                        } else {
                            $key = "{$item_no}:{$key}";
                        }

                    }

                    if (in_array($category, [
                        '決標品項',
                        '招標品項',
                    ])) {
                        if (in_array($key, [
                        ])) {
                            $value = preg_replace('#\s#', '', $value);
                        } elseif (in_array($key, [
                            '得標廠商原始投標金額',
                            '決標金額',
                            '決標單價',
                            '底價金額',
                            '標價金額',
                        ])) {
                            $value = trim(explode("\n", $value)[0]);
                        }

                        $fokey = $td_doms[0]->nodeValue;
                        $okey = $key;
                        if ($span_dom = $td_doms[0]->getElementsByTagName('span')->item(0) and $span_dom->getAttribute('class') == 'indent2') {
                            $item_no[2] = $key;
                            $key = implode(':', $item_no);
                        } else if (strpos($fokey, '　　') === 0) {
                            $item_no[2] = $key;
                            $key = implode(':', $item_no);
                        } elseif (strpos($fokey, '　') === 0) {
                            if (preg_match('#^未?得標廠商\d+$#u', $key)) {
                                // 第X品項:得標廠商X
                                $item_no[1] = $key;
                                $item_no = array_slice($item_no, 0, 2);
                            } elseif (preg_match('#^未?得標廠商#u', $item_no[1]) and preg_match('#^未?得標廠商$#u', $key)) {
                                // 第X品項:得標廠商X:得標廠商
                                $item_no[2] = $key;
                            } else {
                                $item_no[1] = $key;
                            }
                            $key = implode(':', $item_no);
                        } else {
                            if ($key == '品項編號') {
                                $item_no = ["品項編號" . $value];
                                $key = implode(':', $item_no);
                            } elseif (is_array($item_no) and preg_match('#^品項編號\d+$#', $item_no[0])) {
                                $item_no[1] = $key;
                                $key = implode(':', $item_no);
                            } else {
                                $item_no = [$key];
                            }
                        }

                        if (trim($okey) == '原產地國別') {
                            foreach ($td_doms[1]->getElementsByTagName('tr') as $tr_dom) {
                                $td_doms = $tr_dom->getElementsByTagName('td');
                                $skey = trim($td_doms->item(0)->nodeValue);
                                $svalue = trim($td_doms->item(1)->nodeValue);
                                if (in_array($skey, [
                                    '原產地國別得標金額',
                                ])) {
                                    $svalue = trim(explode("\n", $svalue)[0]);
                                }
                                if ($skey) {
                                    $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                }
                            }
                            $value = '';
                        }
                    }

                    if (in_array("{$key}", [
                        '聯絡電話',
                        '傳真號碼',
                        '標的分類',
                        '契約是否訂有依物價指數調整價金規定',
                    ])) { // 去空白
                        $value = preg_replace('#\s#', '', $value);
                    } elseif ("{$category}:{$key}" == '決標資料:總決標金額') {
                        $value = trim(explode("\n", $value)[0]);
                        if ($dom = $doc->getElementById('awardPrice')) {
                            $dom = $dom->nextSibling;
                            while ($dom) {
                                if ($dom->nodeName == 'span') {
                                    $values->{"決標資料:總決標金額:remind"} = trim(str_replace(html_entity_decode('&emsp;'), '', self::getDomValue($dom)));
                                }
                                $dom = $dom->nextSibling;
                            }
                        }

                    } elseif ($key == '是否應依公共工程專業技師簽證規則實施技師簽證') {
                        if (strpos($content, 'var isEngin = "N";')) {
                            $value = '否';
                        } elseif (strpos($content, 'var isEngin = "Y";')) {
                            $value = '是';
                        }
                    } elseif ("{$category}:{$key}" == '最有利標:評選委員') {
                        $table_dom = $td_doms[1]->getElementsByTagName('table')->item(0);
                        $table_tr_doms = $table_dom->getElementsByTagName('tr');
                        $columns = [];
                        foreach ($table_tr_doms->item(0)->getElementsByTagName('th') as $th_dom) {
                            $columns[] = trim($th_dom->nodeValue);
                        }
                        $value = [];
                        for ($i = 1; $table_tr_doms->item($i); $i ++) {
                            $rows = [];
                            foreach ($table_tr_doms->item($i)->getElementsByTagName('td') as $td_dom) {
                                $rows[] = trim($td_dom->nodeValue);
                            }
                            $value[] = array_combine($columns, $rows);
                        }

                        $value = [$value];
                    } elseif ("{$key}" == '是否受機關補助') {
                        if ($doc->getElementById('isGrant')) {
                            $value = $doc->getElementById('isGrant')->nodeValue;
                            $divnode = $doc->getElementById('isGrant');
                            while ($divnode) {
                                $divnode = $divnode->nextSibling;
                                if ($divnode->nodeName == 'div' and $divnode->getAttribute('class') == 'atsp') {
                                    foreach ($divnode->childNodes as $node) {
                                        if ($node->nodeName == 'div') {
                                            $skey = trim($node->childNodes->item(0)->nodeValue);
                                            $svalue = trim($node->childNodes->item(1)->nodeValue);
                                            if ("{$key}:{$skey}" == '是否受機關補助:補助機關') {
                                                preg_match('#^([A-Z0-9.]+)(.*)$#', $svalue, $matches);
                                                $svalue = $matches[1] . ' ' . $matches[2];
                                            }
                                            // TODO: 處理 採購資料:是否受機關補助:補助機關 可能要多筆
                                            $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                        }
                                    }
                                }
                            }
                        } else {
                            $value = trim($td_doms[1]->nodeValue);
                            if ($tr_doms[0]->getElementsByTagName('td')->item(0)->nodeValue == html_entity_decode('&nbsp;')) {
                                $columns = null;
                                $value = [];

                                foreach ($tr_doms[0]->getElementsByTagName('td')->item(1)->getElementsByTagName('tr') as $tr_dom) {
                                    if (is_null($columns)) {
                                        $columns = [];
                                        foreach ($tr_dom->getElementsByTagName('td') as $td_dom) {
                                            $columns[] = trim($td_dom->nodeValue);
                                        }
                                    } else {
                                        $row = [];
                                        foreach ($tr_dom->getElementsByTagName('td') as $td_dom) {
                                            $row[] = trim($td_dom->nodeValue);
                                        }
                                        $v = array_combine($columns, $row);
                                        if (array_key_exists('補助金額', $v)) {
                                            $v['補助金額'] = trim(explode("\n", $v['補助金額'])[0]);
                                        }
                                        $value[] = $v;
                                    }

                                }
                                array_shift($tr_doms);
                            }
                        }
                    } elseif (in_array("{$category}:{$key}", [
                        '採購資料:本採購案是否屬於建築工程',
                    ])) {
                        $nodes = $td_doms[1]->childNodes;
                        $value = trim($nodes->item(0)->nodeValue);
                        $remind = self::getDomValue($nodes->item(1));
                        if ($remind) {
                            $values->{"{$category}:{$key}:remind"} = trim($remind);
                        }
                    } elseif ("{$category}:{$key}" == '採購資料:是否含特別預算') {
                        $dom = $doc->getElementById('isSpecialBudget');
                        if ($dom) {
                            $value = mb_substr(trim($dom->nodeValue), 0, 1, 'UTF-8');
                            if ($div_dom = $dom->getElementsByTagName('div')->item(0)) {
                                foreach ($div_dom->childNodes as $dom) {
                                    if (strpos($dom->nodeValue, '：')) {
                                        list($k, $v) = explode('：', trim($dom->nodeValue), 2);
                                        $values->{"{$category}:{$key}:{$k}"} = $v;
                                    }
                                }
                            }
                        }

                    } elseif ("{$category}:{$key}" == '領投開標:是否提供電子領標') {
                        $value = $doc->getElementById('isEobtain')->nodeValue;
                        foreach ($td_doms[1]->getElementsByTagName('div') as $node) {
                            $classes = explode(' ', $node->getAttribute('class'));
                            if (in_array('tbc2a', $classes) or in_array('tbc1', $classes)) {
                                $nodes = [];
                                foreach ($node->childNodes as $cnode) {
                                    if ($cnode->nodeName == '#text' and trim($cnode->nodeValue) == '') {
                                        continue;
                                    }
                                    $nodes[] = $cnode;
                                }
                                if (count($nodes) != 2) {
                                    continue;
                                }
                                $skey = trim($nodes[0]->nodeValue);
                                $svalue = trim($nodes[1]->nodeValue);
                                $skey = preg_replace('#：$#u', '', $skey);

                                if ($skey) {
                                    $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                }

                                if ($img_dom = $node->getElementsByTagName('img')->item(0)) {
                                    if ($img_dom->getAttribute('class') == 'qq') {
                                        $values->{"{$category}:{$key}:{$skey}:remind"} = $img_dom->getAttribute('title');
                                    }
                                }
                            }
                        }
                        if ($divnode = $doc->getElementById('isPhyObtain')) {
                            $values->{"{$category}:{$key}:是否提供現場領標"} = $divnode->nodeValue;
                        }

                        foreach ($td_doms[1]->getElementsByTagName('a') as $a_dom) {
                            if ($a_dom->nodeValue == '投標須知下載') {
                                $values->{"{$category}:{$key}:投標須知下載"} = "https://web.pcc.gov.tw" . $a_dom->getAttribute('href');
                            }
                        }
                    } elseif (in_array("{$category}:{$key}", [
                        '採購資料:是否於駐地報紙或網站刊登招標公告',
                    ])) {
                        // https://web.pcc.gov.tw/prkms/urlSelector/common/atm?pk=NTM3NDI0NjY=
                        if (strpos($value, '否') === 0) {
                            $value = '否';
                        }
                    } elseif (in_array("{$category}:{$key}", [
                        '採購資料:是否適用條約或協定之採購',
                        '已公告資料:是否適用條約或協定之採購',
                    ])) {
                        foreach ($td_doms[1]->getElementsByTagName('span') as $span_dom) {
                            if (!preg_match('#(.*)：$#', trim($span_dom->nodeValue), $matches)) {
                                continue;
                            }
                            $skey = $matches[1];
                            $value = trim($span_dom->nextSibling->nodeValue);
                            $values->{"{$category}:{$key}:{$skey}"} = $value;
                            if (strpos($span_dom->nextSibling->nextSibling->nodeValue, '理由：')) {
                                $values->{"{$category}:{$key}:{$skey}:理由"} = trim(explode('：', $span_dom->nextSibling->nextSibling->nodeValue)[1]);
                            }
                        }

                        // TODO: 有的理由HTML 抓不到，要從 #agreementJsonStr 抓

                        foreach ([
                            '是否適用WTO政府採購協定(GPA)',
                            '是否適用臺紐經濟合作協定(ANZTEC)',
                            '是否適用臺星經濟夥伴協定(ASTEP)',
                        ] as $k) {
                            if (property_exists($values, "{$category}:{$key}:{$k}") and !$values->{"{$category}:{$key}:{$k}"}) {
                                $values->{"{$category}:{$key}:{$k}"} = '否';
                            }
                        }
                        continue;
                    } elseif (in_array("{$category}:{$key}", [
                        '採購資料:後續擴充',
                    ])) {
                        $value = $td_doms[1]->getElementsByTagName('span')->item(0)->nodeValue;
                        
                        if (!in_array($value, array('是', '否'))) {
                            var_dump($value);
                            echo $doc->saveHTML($td_dom);
                            throw new Exception("{$key} 應該只有是跟否", 999);
                        }
                    } elseif ("{$category}:{$key}" == '其他:疑義、異議、申訴及檢舉受理單位') {
                        foreach ($td_doms[1]->getElementsByTagName('tr') as $tr_dom) {
                            $td_doms = $tr_dom->getElementsByTagName('td');
                            $skey = trim($td_doms->item(0)->nodeValue);
                            $value = trim($td_doms->item(1)->nodeValue);
                            if ($skey) {
                                $values->{"{$category}:{$key}:{$skey}"} = $value;
                            }
                        }
                        continue;
                    } elseif (in_array("{$category}:{$key}", [
                        '已公告資料:原公告日期',
                    ])) {
                        $value = trim($td_doms[1]->childNodes->item(0)->nodeValue);
                        $values->{"{$category}:{$key}:remind"} = trim($td_doms[1]->childNodes->item(1)->nodeValue);
                    } elseif ($key == '決標方式') {
                        // TODO: https://web.pcc.gov.tw/prkms/urlSelector/common/atm?pk=NTM3NDI0MDE=
                        // 採購評選委員名單
                        if ($doc->getElementById('spnLaw2211OriAwardWay')) {
                            $value = trim($doc->getElementById('spnLaw2211OriAwardWay')->nodeValue);
                        } elseif ($doc->getElementById('fkPmsAwardWay')) {
                            // https://web.pcc.gov.tw/tps/QueryTender/query/historyTenderDetail?fkPmsMainHist=NzIxODEyMzI=
                            $value = trim($doc->getElementById('fkPmsAwardWay')->nodeValue);
                        }
                    } elseif (in_array($key, [
                        '預算金額',
                        '底價金額',
                        '總決標金額',
                    ])) { // 只取第一行
                        $value = trim(explode("\n", trim(self::getDomValue($td_doms[1]))) [0]);
                    } elseif (in_array("{$category}:{$key}", [
                        '領投開標:是否須繳納押標金',
                        '其他:是否訂有與履約能力有關之基本資格',
                        '其他:本案採購契約是否採用主管機關訂定之範本',
                        '領投開標:是否須繳納履約保證金',
                        '招標資料:是否已辦理公開閱覽',
                        '其他:是否依據採購法第99條',
                        '其他:是否訂有與履約能力有關之特定資格',
                    ])) {
                        if ($td_doms[1]->getElementsByTagName('div')->item(0)) { 
                            foreach ($td_doms[1]->childNodes as $n) {
                                if (!trim($n->nodeValue)) {
                                    continue;
                                }
                                $value = trim($n->nodeValue);
                                break;
                            }

                            $span_hit = false;
                            foreach ($td_doms[1]->getElementsByTagName('span') as $span_dom) {
                                if (preg_match('#(.*)：$#', $span_dom->nodeValue, $matches)) {
                                    $skey = $matches[1];
                                    $svalue = trim(self::nextDOM($span_dom)->nodeValue);
                                    if ($svalue) {
                                        $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                        $span_hit = true;
                                    }
                                }
                            }
                            if (!$span_hit) {
                                foreach ($td_doms[1]->getElementsByTagName('div') as $div_dom) {
                                    if (preg_match('#([^：]*)：(.+)#um', preg_replace('#：\s+#', '：', $div_dom->nodeValue), $matches)) {
                                        list($skey, $svalue) = explode('：', trim($div_dom->nodeValue), 2);
                                        $svalue = trim($svalue);
                                        $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                    } elseif ('廠商應附具之特定資格證明文件：' == $div_dom->nodeValue) {
                                        list($skey, $svalue) = explode('：', trim($div_dom->parentNode->nodeValue));
                                        $values->{"{$category}:{$key}:{$skey}"} = $svalue;
                                    }
                                }
                            }
                        }
                    }
                    $values->{"{$category}:{$key}"} = $value;

                } else {
                    echo $doc->saveHTML($tr_dom);
                    throw new Exception("{$category} 的 td 有三個");
                }
            }
        }

        if (property_exists($values, "招標資料:本案完成後所應達到之功能、效益、標準、品質或特性") and !$values->{'招標資料:本案完成後所應達到之功能、效益、標準、品質或特性'}) {
            unset($values->{'招標資料:本案完成後所應達到之功能、效益、標準、品質或特性'});
        }
        if (property_exists($values, '其他:身心障礙福利機構團體或庇護工場生產物品及服務')) {
            $v = $values->{'其他:身心障礙福利機構團體或庇護工場生產物品及服務'};
            $v = str_replace('&nbsp', '', $v);
            $v = preg_replace('#\s+#', '', $v);
            if ($v == '項目:分類:') {
                unset($values->{'其他:身心障礙福利機構團體或庇護工場生產物品及服務'});
            }
        }

        // https://web.pcc.gov.tw/tps/atm/AtmAwardWithoutSso/QueryAtmAwardDetail?pkAtmMain=NTM3NDExNTA=
        if (property_exists($values, '簽約廠商:簽約廠商家數')) {
            if (!$values->{'簽約廠商:簽約廠商家數'}) {
                unset($values->{'簽約廠商:簽約廠商家數'});
            }
        }
        if (property_exists($values, '已公告資料:是否共同投標')) {
            if (!$values->{'已公告資料:是否共同投標'}) {
                unset($values->{'已公告資料:是否共同投標'});
            }
        }
        if (property_exists($values, '無法決標公告:附加說明')) {
            $values->{'無法決標公告:附加說明'} = str_replace('<br/>', "\n", $values->{'無法決標公告:附加說明'});
        }

        if (property_exists($values, '決標資料:履約執行機關')) {
            $v = $values->{'決標資料:履約執行機關'};
            $v = implode("\n", preg_split("/\s+/", $v));
            $values->{'決標資料:履約執行機關'} = $v;
        }
        return $values;
    }

    public static function nextDom($dom)
    {
        while ($dom = $dom->nextSibling) {
            if ($dom->nodeName == '#text' and trim($dom->nodeValue) == '') {
                continue;
            }
            break;
        }
        return $dom;
    }
}
