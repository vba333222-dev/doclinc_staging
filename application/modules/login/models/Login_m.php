<?php 
    class Login_m extends MX_Controller{
        function __construct(){
            parent::__construct();
           $this->db = $this->load->database('default', TRUE);
        }
        public function auth($username, $password)
        {
            return $this->db
                ->where('username', $username)
                ->where('password', $password)
                ->get('users');
		}
		public function save_location($id,$username,$location,$lattitude,$longitude)
		{
			if (!$this->db->table_exists('locations')) {
				return false;
			}

			$db_debug = $this->db->db_debug;
			$this->db->db_debug = FALSE;

			$result = $this->db->insert('locations', [
				'id_user' => $id,
				'name' => $username,
				'location' => $location,
				'latitude' => $lattitude,
				'longitude' => $longitude,
				'create_date' => date('Y-m-d H:i:s'),
			]);

			$this->db->db_debug = $db_debug;
			return $result;
		}
    }
