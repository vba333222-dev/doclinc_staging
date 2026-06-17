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
			return $this->db->insert('locations', [
				'id_user' => $id,
				'name' => $username,
				'location' => $location,
				'latitude' => $lattitude,
				'longitude' => $longitude,
				'create_date' => date('Y-m-d H:i:s'),
			]);
		}
    }
