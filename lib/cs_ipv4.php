<?php

include_once 'cs_file.php';
include_once 'cs_sqlite.php';

Class CS_IPv4 {

    private $addr; //assign
    private $prefix;
    private $len;
    private $device; // device with pool
    private $pool; // active configured pool

    public function __construct() {
        
    }

    public function assign($device = false) {
        $this->device = $device;
        $pool = $this->get_ppp_pool();
        if ($device) {
            $pool = $this->get_device_pool();
        }
        if ($pool) {
            $this->pool = $pool;
            if ($this->find()) {
                return $this->addr;
            }
        }
        return false;
    }

    private function get_ppp_pool() {
        $file = new CS_File('ppp');
        return $file->read();
    }

    private function get_device_pool() {
        $file = new CS_File('devices');
        $devices = $file->read();
        if ($devices) {
            return $devices->{$this->device}->pool;
        }
        return false;
    }

    private function find() {
        foreach ($this->pool as $range) {
            [$this->prefix, $this->len] = explode('/', $range);
            if ($this->iterate_range()) {
                return true;
            }
        }
        return false;
    }

    private function exclusions() { //needs regex check
        $f = new CS_File('dhcp_excl');
        $list = $f->read();
        $addr = [];
        foreach ($list as $entry) {
            [$start, $end] = explode('-', $entry, 2);
            if (!$end) {
                $end = $start;
            }
            $s = ip2long($start);
            $e = ip2long($end);
            for ($i = $s; $i < $e + 1; $i++) {
                $addr[] = $i;
            }
            return $addr;
        }
        return null;
    }

    private function iterate_range() {
        $hosts = $this->hosts();
        $net = ip2long($this->network()); //net_number2dec
        $db = new CS_SQLite();
        $excl = $this->exclusions();
        for ($i = $net + 1; $i < $net + $hosts - 1; $i++) {
            if(in_array($i, $excl)){
                continue;
            }
            $addr = long2ip($i);
            $lastoct = explode('.', $addr)[3];
            if ($lastoct < 1 || $lastoct > 254) { // skip zeros and 255s
                continue;
            }
            if ($db->exists('address', $addr)) {
                continue;
            }
            $this->addr = $addr;
            return true;
        }
        return false;
    }

    private function network() { //ok here we go
        $ip = decbin(ip2long($this->prefix)); //ip2bin
        $mask = decbin(ip2long(long2ip(-1 << (32 - $this->len)))); //len2netmask2bin
        $net = $ip & $mask;
        return long2ip(bindec($net)); //back2ip
    }

    private function hosts() {
        $host_len = 32 - $this->len;
        $base = 2;
        return pow($base, $host_len);
    }

}
