<?php 
    class Home_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
    }