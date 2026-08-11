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
            return $this->db
                ->where('email', $email)
                ->get('users')
                ->result();
        }
        public function verification($decode_email){
            $now = date('Y-m-d H:i:s');
            $data = array('status' => 'aktif');
            if ($this->db->field_exists('modify_date', 'users')) {
                $data['modify_date'] = $now;
            }
            if ($this->db->field_exists('updated_at', 'users')) {
                $data['updated_at'] = $now;
            }
            return $this->db
                ->where('email', $decode_email)
                ->update('users', $data);
        }
        public function reset_password($email,$encrypted){
            if ($this->config->item('single_active_session_enabled') !== true) {
                return $this->db
                    ->where('email', $email)
                    ->update('users', array('password' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')));
            }
            $service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
            if (!is_file($service_file)) {
                return false;
            }
            require_once $service_file;
            $service = new Session_binding_service($this->db);
            if (!$service->schemaReady() || !$this->db->trans_begin()) {
                return false;
            }
            try {
                $query = $this->db->query(
                    'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE email = ? FOR UPDATE',
                    array($email)
                );
                $user = $query ? $query->row() : null;
                if (!$user
                    || !$this->db->where('userId', (int) $user->userId)->update('users', array('password' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')))
                    || !$service->revokeLocked((int) $user->userId)
                    || $this->db->trans_status() === false
                    || !$this->db->trans_commit()) {
                    $this->db->trans_rollback();
                    return false;
                }
                return true;
            } catch (Throwable $exception) {
                $this->db->trans_rollback();
                return false;
            }
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
