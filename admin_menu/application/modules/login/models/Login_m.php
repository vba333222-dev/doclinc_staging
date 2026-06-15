<?php 
    class Login_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function isThere($email,$password){
            return $this->db->query("SELECT * FROM users
                                     WHERE email = '$email' AND password='$password' AND status='aktif' AND role='admin'");
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
           return $this->db->query("UPDATE users SET password='$encrypted' WHERE email='$email'");
        }
    }