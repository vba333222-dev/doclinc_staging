<?php 
    class Kelola_tindakan_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_tindakan()
        {
            return $this->db->query("SELECT
                                        *
                                    FROM
                                        konsultasi
                                    ORDER BY konsul_id DESC");
        }
    }