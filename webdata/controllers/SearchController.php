<?php

class SearchController extends Pix_Controller
{
    public function bytitleAction()
    {
        $curl = curl_init();
        $cmd = array(
            'query' => array(
                'query_string' => array('query' => sprintf('title:"%s"', $_GET['query'])),
            ),
            'size' => 100,
            'sort' => array('date' => 'desc'),
        );
        curl_setopt($curl, CURLOPT_URL, getenv('SEARCH_URL') . '/entry/_search');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cmd));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $ret = curl_exec($curl);
        return $this->json(json_decode($ret));
    }
}
