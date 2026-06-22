<?php 
    class Login_m extends MX_Controller{
        function __construct(){
            parent::__construct();
           $this->db = $this->load->database('default', TRUE);
        }
        public function auth($username)
        {
            return $this->db
                ->where('username', $username)
                ->get('users');
		}
		public function update_password($user_id, $password_hash)
		{
			return $this->db
				->where('userId', $user_id)
				->update('users', array('password' => $password_hash, 'updated_at' => date('Y-m-d H:i:s')));
		}
		public function log_login_event($action, $user_id = NULL, $metadata = array())
		{
			if (!$this->db->table_exists('audit_logs')) {
				return false;
			}

			$db_debug = $this->db->db_debug;
			$this->db->db_debug = FALSE;

			$result = $this->db->insert('audit_logs', array(
				'actor_user_id' => $user_id,
				'action' => $action,
				'entity_type' => 'auth',
				'entity_id' => $user_id,
				'ip_address' => $this->input->ip_address(),
				'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
				'metadata_json' => json_encode($metadata),
				'created_at' => date('Y-m-d H:i:s'),
			));

			$this->db->db_debug = $db_debug;
			return $result;
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
