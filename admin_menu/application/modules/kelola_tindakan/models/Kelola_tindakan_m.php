<?php 
    class Kelola_tindakan_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_tindakan()
        {
            if (!$this->db->table_exists('konsultasi')) {
                return $this->db->query("SELECT NULL AS konsul_id, NULL AS diagnosa, NULL AS saran WHERE 1=0");
            }
            return $this->db->query("SELECT
                                        *
                                    FROM
                                        konsultasi
                                    ORDER BY konsul_id DESC");
        }
        public function edit_tindakan($konsul_id, $saran)
        {
            if (!$this->db->table_exists('konsultasi')) {
                return false;
            }

            $this->db->where('konsul_id', $konsul_id);
            return $this->db->update('konsultasi', array(
                'saran' => $saran,
                'modify_date' => date('Y-m-d H:i:s')
            ));
        }
        public function delete_tindakan($konsul_id, $remark)
        {
            if (!$this->db->table_exists('konsultasi')) {
                return false;
            }

            $this->db->where('konsul_id', $konsul_id);
            return $this->db->update('konsultasi', array(
                'kriteria' => 'Non-Aktif',
                'modify_user' => $remark,
                'modify_date' => date('Y-m-d H:i:s')
            ));
        }
    }
