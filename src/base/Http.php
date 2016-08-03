<?php

namespace bilibili\pikachu\base;

use bilibili\pikachu\middleware\clockwork\Monitor;

class Http
{
    protected $api_path = '';
    protected $url = '';
    protected $headers = [];
    protected $withHeader = false;
    protected $query = [];
    protected $info = null;
    protected $error = null;

    protected function getHandler()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        return $ch;
    }

    public function setPath($api_path)
    {
        $this->api_path = $api_path;

        return $this;
    }

    public function setHost($host)
    {
        $this->headers[] = "Host: {$host}";

        return $this;
    }

    public function setHeader($header)
    {
        $this->headers[] = $header;

        return $this;
    }

    public function withHeader()
    {
        $this->withHeader = true;

        return $this;
    }

    public function Get($api, $query = '')
    {
        $ch = $this->getHandler();
        $query = is_array($query) ? http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $query; // PHP_QUERY_RFC3986 : Space will be turn to %20
        $this->query = $query;
        $this->url = $this->api_path.$api.($query ? "?{$query}" : '');
        if ($this->headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }

        return $this->run($ch);
    }

    public function Post($api, $query = '')
    {
        $ch = $this->getHandler();
        $query = is_array($query) ? http_build_query($query) : $query;
        $this->query = $query;
        $this->url = $this->api_path.$api;
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        if ($this->headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }

        return $this->run($ch);
    }

    /**
     * Returns a json.
     *
     * @param string       $api_path The api path
     * @param string|array $file     The filepath (string) or fileinfo ['filepath' => '', 'filetype' => ''] (array)
     *
     * @return string
     */
    public function Put($api, $file = '', $query = [])
    {
        $ch = $this->getHandler();
        $query = is_array($query) ? http_build_query($query) : $query;
        $this->url = $this->api_path.$api.($query ? "?{$query}" : '');

        if (is_array($file)) {
            if (isset($file['filepath'])) {
                $fp = fopen($file['filepath'], 'r');
            } elseif (isset($file['fp'])) {
                $fp = $file['fp'];
            }
            $this->headers[] = "Content-Type: {$file['filetype']}";
        } else {
            $fp = fopen($file, 'r');
            if ($img_info = getimagesize($file)) { // get mime type wher file is a image
                $this->headers[] = "Content-Type: {$img_info['mime']}";
            }
        }
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        if ($this->headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }

        return $this->run($ch);
    }

    protected function run($ch)
    {
        curl_setopt($ch, CURLOPT_URL, $this->url);
        if ($this->withHeader) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $this->error = ['errno' => curl_errno($ch), 'error' => curl_error($ch)];
            App::getInstance()->getLogger()->error('curl: '.$this->url.','.curl_error($ch));
        } else {
            $this->info = curl_getinfo($ch);
            if (Registry::getInstance()->debug) {
                $middleware = Monitor::getInstance();
                $middleware->httpRequest($this->info['url'], $this->info['total_time'], $this->query ? $this->query : '');
            }
        }
        curl_close($ch);

        return $response;
    }

    public function getCurlInfo()
    {
        return $this->info;
    }

    public function getError()
    {
        return $this->error;
    }
}
