<?php
    class Sign_up_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function save_user($data) {
            return $this->db->insert('users', $data);
        }
    }
