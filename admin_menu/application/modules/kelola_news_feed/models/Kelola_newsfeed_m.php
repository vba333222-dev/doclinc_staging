<?php
    class Kelola_newsfeed_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_data_news_feed()
        {
            return $this->db->get('feeds');
        }
        public function get_news_feed($id)
        {
            $query = $this->db->get_where('feeds', ['feedId' => $id]);
            return $query->row_array();
        }
        public function insert_news($data)
        {
            $this->db->insert('feeds', $data);
        }
        public function update_news($feedId,$gambar,$subject,$status)
        {
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
            $this->db->where('feedId', $id);
            $this->db->delete('feeds');
        }
    }
