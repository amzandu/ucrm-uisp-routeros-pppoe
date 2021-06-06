<?php

include_once 'mt_account.php';

class MT_DHCP_Account extends MT_Account {

    public function __construct(&$data) {
        parent::__construct($data);
        $this->path = '/ip/dhcp-server/lease/';
        $this->disableProperty = 'address-lists';
    }
    
    public function insert(){
        if(parent::insert()){
            $this->path = '/queue/simple/';
            if($this->write($this->post->queue,'add')){
                $this->set_message('service id:' 
                        . $this->entity->id . ' was created');
                $this->path = '/ip/dhcp-server/lease/';
                return true ;
            }
        }
        $this->set_error('failed to add queue');
        $this->path = '/ip/dhcp-server/lease/';
        return false ;
    }
    
    public function delete(){
        if(parent::delete()){
            $this->path = '/queue/simple/';
            if($this->write(false,'remove')){
                $this->set_message('service id:' 
                        . $this->entity->id . ' was deleted');
                $this->path = '/ip/dhcp-server/lease/';
                return true;
            }            
        }
        $this->set_error('failed to delete queue');
        $this->path = '/ip/dhcp-server/lease/';
        return false;
    }
    
    public function edit(){
        if(parent::edit()){
            $this->path = '/queue/simple/';
            if($this->write($this->post->queue,'set')){
                $this->set_message('service id:' 
                        . $this->entity->id . ' was updated');
                $this->path = '/ip/dhcp-server/lease/';
                return true ;
            }
        }
        $this->set_error('failed to update queue');
        $this->path = '/ip/dhcp-server/lease/';
        return false ;
    }

    protected function data() {
        global $conf;
        $ip = $this->ip_get($this->entity->{$conf->device_name_attr});
        if (!$ip) {
            return false;
        }
        $data = (object) array(
            'save' => $this->save_data($ip),
            'device' => $this->lease_data($ip),
            'queue' => $this->queue_data($ip),
        );
        if (in_array($this->data->changeType, ['edit', 'move'])) {
            unset($data->save->ip);
        }
        return $data;
    }
    
    protected function save_data($ip){
        global $conf ;
        return (object) array(
                        'id' => $this->entity->id,
                        'planId' => $this->entity->servicePlanId,
                        'clientId' => $this->entity->clientId,
                        'address' => $ip,
                        'status' => $this->entity->status,
                        'device' => $this->entity->{$conf->device_name_attr},
                    );
    }

    protected function lease_data($ip) {
        global $conf ;
        $list = $conf->active_list;
        if ($this->entity->status != 1) {
            $list = $conf->disabled_profile;
        }
        return (object) array(
                    'address' => $ip,
                    'mac-address' => $this->entity->{$conf->mac_addr_attr},
                    'insert-queue-before' => 'bottom',
                    'address-lists' => $list,
                    'comment' => $this->entity->id . ','.$this->entity->clientId."-" . $this->data->clientName,
        );
    }

    protected function queue_data($ip) {
        global $conf ;
        $rate = $this->entity->downloadSpeed . "M/"
                    . $this->entity->uploadSpeed . "M";
        return (object) array(
                    'name' => 'queue-<'.$this->entity->clientId."-".$this->data->clientName.'>',
                    'target' => $ip,
                    'max-limit' => $rate,
                    'limit-at' => $rate,
                    'queue' => $this->get_queue_type(),
                    'comment' => $this->entity->id . ','.$this->entity->clientId."-" . $this->data->clientName,
        );
    }

}
