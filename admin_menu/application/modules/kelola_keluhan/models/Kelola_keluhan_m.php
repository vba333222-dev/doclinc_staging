<?php 
    class Kelola_keluhan_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_keluhan()
        {
            if (!$this->db->table_exists('keluhan')) {
                return $this->db->query("SELECT NULL AS id_keluhan, NULL AS kategori, NULL AS nama_keluhan, NULL AS deskripsi, NULL AS remark, NULL AS status WHERE 1=0");
            }
            return $this->db->query("SELECT
                                        *
                                    FROM
                                        keluhan
                                    ORDER BY kategori DESC");
        }
        public function get_last_id() {
            if (!$this->db->table_exists('keluhan')) {
                return null;
            }
            $this->db->select('id_keluhan');
            $this->db->from('keluhan');
            $this->db->order_by('id_keluhan', 'DESC');
            $this->db->limit(1);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                return $query->row()->id_keluhan;
            } else {
                return null; // Jika tidak ada data di database
            }
        }
        public function tambah_keluhan($id_keluhan,$kategori,$namakeluhan,$deskripsi,$create_user,$create_date)
        {
            if (!$this->db->table_exists('keluhan')) {
                return false;
            }
            return $this->db->insert('keluhan', array(
                'id_keluhan' => $id_keluhan,
                'kategori' => $kategori,
                'nama_keluhan' => $namakeluhan,
                'deskripsi' => $deskripsi,
                'create_user' => $create_user,
                'create_date' => $create_date
            ));
        }
        public function edit_keluhan($id_keluhan,$kategori,$namakeluhan,$deskripsi,$modify_user,$modify_date)
        {
            if (!$this->db->table_exists('keluhan')) {
                return false;
            }
            $this->db->where('id_keluhan', $id_keluhan);
            return $this->db->update('keluhan', array(
                'kategori' => $kategori,
                'nama_keluhan' => $namakeluhan,
                'deskripsi' => $deskripsi,
                'modify_user' => $modify_user,
                'modify_date' => $modify_date
            ));
        }
        public function activate_keluhan($id_keluhan,$remark,$modify_user,$modify_date)
        {
            if (!$this->db->table_exists('keluhan')) {
                return false;
            }
            $this->db->where('id_keluhan', $id_keluhan);
            return $this->db->update('keluhan', array(
                'remark' => $remark,
                'status' => 'Aktif',
                'modify_user' => $modify_user,
                'modify_date' => $modify_date
            ));
        }
        public function delete_keluhan($id_keluhan,$remark,$modify_user,$modify_date)
        {
            if (!$this->db->table_exists('keluhan')) {
                return false;
            }
            $this->db->where('id_keluhan', $id_keluhan);
            return $this->db->update('keluhan', array(
                'remark' => $remark,
                'status' => 'Non-Aktif',
                'modify_user' => $modify_user,
                'modify_date' => $modify_date
            ));
        }
    }
