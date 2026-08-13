<?php 
    class Kelola_tindakan_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_tindakan()
        {
            return $this->db->query("SELECT NULL AS konsul_id WHERE 1=0");
        }
        public function edit_tindakan($konsul_id, $saran)
        {
            return false;
        }
        public function delete_tindakan($konsul_id, $remark)
        {
            return false;
        }
    }
