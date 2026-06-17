<?php 
    class Kelola_keluhan_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_keluhan()
        {
            return $this->db->query("SELECT
                                        *
                                    FROM
                                        keluhan
                                    ORDER BY kategori DESC");
        }
        public function get_last_id() {
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
            return $this->db->query("INSERT keluhan SET
                                     id_keluhan = '$id_keluhan',
                                     kategori = '$kategori',
                                     nama_keluhan = '$namakeluhan',
                                     deskripsi = '$deskripsi',
                                     create_user = '$create_user',
                                     create_date = '$create_date'");
        }
        public function edit_keluhan($id_keluhan,$kategori,$namakeluhan,$deskripsi,$modify_user,$modify_date)
        {
            return $this->db->query("UPDATE keluhan SET
                                     kategori = '$kategori',
                                     nama_keluhan = '$namakeluhan',
                                     deskripsi = '$deskripsi',
                                     modify_user = '$modify_user',
                                     modify_date = '$modify_date'
                                     WHERE id_keluhan = '$id_keluhan'");
        }
        public function activate_keluhan($id_keluhan,$remark,$modify_user,$modify_date)
        {
            return $this->db->query("UPDATE keluhan SET
                                     remark = '$remark',
                                     status = 'Aktif',
                                     modify_user = '$modify_user',
                                     modify_date = '$modify_date'
                                     WHERE id_keluhan = '$id_keluhan'");
        }
        public function delete_keluhan($id_keluhan,$remark,$modify_user,$modify_date)
        {
            return $this->db->query("UPDATE keluhan SET
                                     remark = '$remark',
                                     status = 'Non-Aktif',
                                     modify_user = '$modify_user',
                                     modify_date = '$modify_date'
                                     WHERE id_keluhan = '$id_keluhan'");
        }
    }