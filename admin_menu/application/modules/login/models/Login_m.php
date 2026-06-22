<?php 
    class Login_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function get_admin_by_email($email){
            return $this->db
                ->where('email', $email)
                ->where('status', 'aktif')
                ->where('role', 'admin')
                ->get('users');
        }
        public function cekUsername($email) {
            $query=$this->db->query("SELECT
                                        *
                                    FROM
                                        users 
                                    WHERE email = '$email'");
            return $query->result(); 
        }
        public function verification($decode_email){
            $now = date('Y-m-d H:i:s');
            $this->db->query("UPDATE users SET status = 'aktif', modify_date = '$now' WHERE email = '$decode_email' ");
        }
        public function reset_password($email,$encrypted){
           return $this->db
                ->where('email', $email)
                ->update('users', array('password' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')));
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
    }
