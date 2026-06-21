<?php 
    class Kelola_warga_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_warga()
        {
            if (!$this->db->table_exists('users')) {
                return $this->db->query("SELECT NULL AS userId, NULL AS nama, NULL AS email, NULL AS no_hp, NULL AS tgl, NULL AS gender, NULL AS alamat, NULL AS ktp, NULL AS foto, NULL AS status WHERE 1=0");
            }

            return $this->db->get('users');
        }
        public function aktifkan_user($id_user,$user,$remark_aktif)
        {
            return $this->update_status($id_user, 'aktif', $user, $remark_aktif);
        }
        public function nonaktifkan_user($id_user,$user,$remark_nonaktif)
        {
            return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
        }

        private function update_status($id_user, $status, $user, $remark)
        {
            if (!$this->db->table_exists('users')) {
                return false;
            }

            $data = array('status' => $status);
            foreach (array('remark' => $remark, 'updated_by' => $user, 'updated_at' => date('Y-m-d H:i:s')) as $field => $value) {
                if ($this->db->field_exists($field, 'users')) {
                    $data[$field] = $value;
                }
            }

            $this->db->where('userId', $id_user);
            return $this->db->update('users', $data);
        }
    }
