<?php
    class Kelola_newsfeed_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_news_feed()
        {
            if (!$this->db->table_exists('feeds')) {
                return $this->db->query("SELECT NULL AS feedId, NULL AS subject, NULL AS gambar, NULL AS status, NULL AS create_at, NULL AS create_user WHERE 1=0");
            }
            return $this->db->get('feeds');
        }
        public function get_news_feed($id)
        {
            if (!$this->db->table_exists('feeds')) {
                return array();
            }
            $query = $this->db->get_where('feeds', ['feedId' => $id]);
            return $query->row_array();
        }
        public function insert_news($data)
        {
            if (!$this->db->table_exists('feeds')) {
                return false;
            }
            $this->db->insert('feeds', $data);
        }
        public function update_news($feedId,$gambar,$subject,$status)
        {
            if (!$this->db->table_exists('feeds')) {
                return false;
            }
            $data = [
                'subject' => $subject,
                'status' => $status
            ];
            if (!empty($gambar)) {
                $data['gambar'] = $gambar;
            }
            $this->db->where('feedId', $feedId);
            $this->db->update('feeds', $data);
            return $this->db->affected_rows() > 0;
        }
        public function delete_news($id)
        {
            if (!$this->db->table_exists('feeds')) {
                return false;
            }
            $this->db->where('feedId', $id);
            $this->db->update('feeds', array('status' => 'non-aktif'));
            return $this->db->affected_rows() > 0;
        }
    }
