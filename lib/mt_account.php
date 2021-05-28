<?php

include_once 'cs_ipv4.php';
include_once 'cs_uisp.php';

class MT_Account extends MT {

    public function upgrade() {
        $this->move();
    }

    public function move() {
        $this->data->actionObj = 'before';
        if ($this->delete()) {
            $this->data->actionObj = 'entity';
            $this->insert();
            $this->set_message('service id:' . $this->entity->id . ' was updated');
            return;
        }
        $this->set_error('unable to delete old service');
    }

    public function edit() {
        $id = $this->entity->id;
        $this->post = $this->data();
        if ($this->write($this->post->device, 'set')) {
            $this->set_message('service id:' . $id . ' was updated');
            $this->save($this->post->save, 'update');
            return true;
        }
        return false;
    }

    public function insert() {
        $id = $this->entity->id;
        $this->post = $this->data();
        if ($this->write($this->post->device, 'add')) {
            $this->set_message('service id:' . $id . ' was created');
            $this->save($this->post->save);
            return true;
        }
        return false;
    }

    public function suspend() {
        global $conf;
        $id = $this->entity->id;
        $action = 'suspended';
        $data = new stdClass();
        $data->{$this->disableProperty} = $conf->disabled_profile;
        if ($this->data->unsuspendFlag) {
            $action = 'unsuspended';
            $data->{$this->disableProperty} = '';
        }
        if ($this->write($data)) {
            if ($this->data->unsuspendFlag && $conf->unsuspend_date_fix) {
                $this->fix();
            }
            $this->set_message('service id:' . $id . ' was ' . $action);
            return true;
        }
        return false;
    }

    public function delete() {
        $id = $this->{$this->data->actionObj}->id;
        if ($this->write(false, 'remove')) {
            $this->set_message('service id:' . $id . ' was deleted');
            if (in_array($this->data->changeType, ['delete', 'move', 'upgrade'])) {
                $this->clear();
            }
            return true;
        }
        return false;
    }

    protected function fix() {
        global $conf;
        $clientId = $this->data->extraData->entity->clientId;
        $id = $this->data->entityId;
        $this->trim();  // trim after aquiring data
        $u = new CS_UISP();
        if ($u->request('/clients/services/' . $id . '/end', 'PATCH')) {//end service
            $u->request('/clients/services/' . $id, 'DELETE'); //delete service
            sleep($conf->unsuspend_fix_wait);
            $u->request('/clients/' . $clientId . '/services', 'POST', $this->entity); //recreate service
        }
    }

    protected function trim() {
        $vars = $this->trim_fields();
        foreach ($vars as $var) {
            unset($this->entity->$var);
        }
        $this->trim_attrbs();
    }

    protected function trim_fields() {
        global $conf;
        return ['id', 'clientId', 'status', 'servicePlanId', 'invoicingStart',
            'hasIndividualPrice', 'totalPrice', 'currencyCode', 'servicePlanName',
            'servicePlanPrice', 'servicePlanType', 'downloadSpeed', 'uploadSpeed',
            'hasOutage', 'lastInvoicedDate', 'suspensionReasonId', 'serviceChangeRequestId',
            'downloadSpeedOverride', 'uploadSpeedOverride', 'trafficShapingOverrideEnd',
            'trafficShapingOverrideEnabled', $conf->mac_addr_attr, $conf->device_name_attr,
            $conf->pppoe_user_attr, $conf->pppoe_pass_attr, 'unmsClientSiteId',
            'clientName'];
    }

    protected function trim_attrbs() {
        $vars = ["id", "serviceId", "name", "key", "clientZoneVisible"];
        foreach ($this->entity->attributes as $attrb) {
            foreach ($vars as $var) {
                unset($attrb->$var);
            }
        }
    }
    
    public function get_queue_type(){ //hard code for tim
        $ul = $this->entity->uploadSpeed."mbps upload";
        $dl = $this->entity->downloadSpeed."mbps download";
        $queues = array($ul => 'default-small',$dl=>'default-small');
        $api = $this->connect();
        $api->write('/queue/type/print');
        foreach($api->read()as $item){
            $queues[strtolower($item['name'])] = $item['name'];
        }
        return $queues[$ul]."/".$queues[$dl];
    }

    protected function ip_get($device = false) {
        $addr = null;
        if (in_array($this->data->changeType, ['insert', 'move', 'upgrade'])) {
            $ip = new CS_IPv4();
            $addr = $ip->assign($device);  // acquire new address
        } else {
            $db = new CS_SQLite();
            $addr = $db->get_val($this->before->id, 'address'); //reuse old address
        }
        if (!$addr) {
            $this->set_error('no ip addresses to assign');
            return false;
        }
        return $addr;
    }

    protected function ip_clear($addr) {
        $ip = new CS_IPv4();
        $ip->clear($addr);
    }

    protected function save($data) {
        $db = new CS_SQLite();
        return $db->{$this->data->changeType}($data);
    }

    protected function clear() {
        $db = new CS_SQLite();
        $db->delete($this->{$this->data->actionObj}->id);
    }

}
