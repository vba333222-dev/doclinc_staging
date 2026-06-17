<?php 
    class Landing_page_m extends MX_Controller{
        function __construct(){
            parent::__construct();
            $this->db  = $this->load->database('default', TRUE);
        }
        public function getDataHome(){
            $query = $this->db->query("SELECT * FROM tbl_home");
            return $query;
        }
        public function getDataAbout(){
            $query = $this->db->query("SELECT * FROM tbl_about");
            return $query;
        }
        public function getDataNewsEvents(){
            $query = $this->db->query("SELECT * FROM tbl_news ORDER BY id DESC");
            return $query;
        }
        public function getDataServices(){
            $query = $this->db->query("SELECT * FROM tbl_services");
            return $query;
        }
        public function getDataHarga(){
            $query = $this->db->query("SELECT * FROM tbl_harga");
            return $query;
        }
        public function update_tagline($tagline){
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_home SET tagline = '$tagline', modify_date='$modify_date'");
            return $query;
        }
        public function about_update_desc($desc){
            $isi = addslashes($desc);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_about SET `desc` = '$isi', modify_date='$modify_date'");
            return $query;
        }
        public function about_update_visi($visi){
            $isi = addslashes($visi);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_about SET visi = '$isi', modify_date='$modify_date'");
            return $query;
        }
        public function about_update_misi($misi){
            $isi = addslashes($misi);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_about SET misi = '$isi', modify_date='$modify_date'");
            return $query;
        }
        public function about_update_pict($datename){
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_about SET pict = '$datename', modify_date='$modify_date'");
            return $query;
        }
        public function news_events_add($title,$content,$preview,$filename,$status){
            $create_date = date('Y-m-d H:i:s');
            if ($status=='') {
                if (empty(substr($filename,10))) {
                    $query = $this->db->query("INSERT tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = 'news.png', 
                                           status = 'aktif',
                                           create_date='$create_date'");
                }else{
                    $query = $this->db->query("INSERT tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = '$filename', 
                                           status = 'aktif',
                                           create_date='$create_date'");
                }
            } else {
                if (empty(substr($filename,10))) {
                    $query = $this->db->query("INSERT tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = 'news.png', 
                                           status = '$status', 
                                           create_date='$create_date'");
                }else{
                    $query = $this->db->query("INSERT tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = '$filename', 
                                           status = '$status', 
                                           create_date='$create_date'");
                }
            }
            
            return $query;
        }
        public function news_events_update($id,$title,$content,$preview,$filename,$status){
            $modify_date = date('Y-m-d H:i:s');
            if ($status=='') {
                if (empty(substr($filename,10))) {
                $query = $this->db->query("UPDATE tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           modify_date='$modify_date'
                                           WHERE id = '$id'");
                }else{
                    $query = $this->db->query("UPDATE tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = '$filename', 
                                           modify_date='$modify_date'
                                           WHERE id = '$id'");
                }
            } else {
                if (empty(substr($filename,10))) {
                    $query = $this->db->query("UPDATE tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           status= '$status', 
                                           modify_date='$modify_date'
                                           WHERE id = '$id'");
                }else{
                    $query = $this->db->query("UPDATE tbl_news SET 
                                           title = '$title', 
                                           content = '$content', 
                                           preview_content = '$preview', 
                                           thumbnail = '$filename', 
                                           status= '$status', 
                                           modify_date='$modify_date'
                                           WHERE id = '$id'");
                }
            }
            
            return $query;
        }
        public function news_events_delete($id){
            $query = $this->db->query("DELETE FROM tbl_news WHERE id = '$id'");
            return $query;
        }
        public function service_detail($id,$service){
            $query = $this->db->query("SELECT * FROM tbl_services_detail WHERE id_service = '$id'");
            return $query;
        }
        public function service_detail_sub($id,$title){
            $query = $this->db->query("SELECT * FROM tbl_services_sub_detail WHERE id_service_detail = '$id'");
            return $query;
        }
        public function service_data_harga($id_jenis){
            $query = $this->db->query("SELECT * FROM tbl_harga WHERE id_jenis = '$id_jenis'");
            return $query;
        }
        public function service_update($id,$service,$short_desc,$no_wa){
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_services SET 
                                       service = '$service', 
                                       short_desc = '$short_desc', 
                                       no_wa = '$no_wa', 
                                       modify_date='$modify_date'
                                       WHERE id = '$id'");
            return $query;
        }
        public function service_add_new($service,$short_desc,$no_wa){
            $create_date = date('Y-m-d H:i:s');
            $query = $this->db->query("INSERT tbl_services SET 
                                       service = '$service', 
                                       short_desc = '$short_desc', 
                                       no_wa = '$no_wa', 
                                       create_date='$create_date'");
            return $query;
        }
        public function service_delete($id){
            $query = $this->db->query("DELETE FROM tbl_services WHERE id = '$id'");
            return $query;
        }
        public function service_detail_update($id,$title,$content){
            $modify_date = date('Y-m-d H:i:s');
            $isi = addslashes($content);
            $query = $this->db->query("UPDATE tbl_services_detail SET 
                                       title = '$title', 
                                       content = '$isi', 
                                       modify_date='$modify_date'
                                       WHERE id = '$id'");
            return $query;
        }
        public function service_detail_add_new($id,$title,$content){
            $create_date = date('Y-m-d H:i:s');
            $isi = addslashes($content);
            $query = $this->db->query("INSERT tbl_services_detail SET 
                                       id_service = '$id', 
                                       title = '$title', 
                                       content = '$isi', 
                                       create_date='$create_date'");
            return $query;
        }
        public function service_detail_delete($id){
            $query = $this->db->query("DELETE FROM tbl_services_detail WHERE id = '$id'");
            return $query;
        }
        public function service_detail_sub_update($id,$paket,$desc){
            $modify_date = date('Y-m-d H:i:s');
            $isi = addslashes($desc);
            $query = $this->db->query("UPDATE tbl_services_sub_detail SET 
                                       paket = '$paket', 
                                       `desc` = '$isi', 
                                       modify_date='$modify_date'
                                       WHERE id = '$id'");
            return $query;
        }
        public function service_detail_sub_add_new($id,$paket,$desc){
            $create_date = date('Y-m-d H:i:s');
            $isi = addslashes($desc);
            $query = $this->db->query("INSERT tbl_services_sub_detail SET 
                                       id_service_detail = '$id', 
                                       paket = '$paket', 
                                       `desc` = '$isi', 
                                       create_date='$create_date'");
            return $query;
        }
        public function service_detail_sub_delete($id){
            $query = $this->db->query("DELETE FROM tbl_services_sub_detail WHERE id = '$id'");
            return $query;
        }
        public function service_harga_update($id,$nama_paket,$harga,$harga2,$exp_diskon){
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_harga SET 
                                       nama_paket = '$nama_paket', 
                                       harga = '$harga', 
                                       harga2 = '$harga2',
                                       expired_diskon = '$exp_diskon',
                                       modify_date='$modify_date'
                                       WHERE id = '$id'");
            return $query;
        }
        public function service_harga_add_new($idservicesubdetail,$nama_paket,$harga,$harga2,$exp_diskon){
            $create_date = date('Y-m-d H:i:s');
            $query = $this->db->query("INSERT tbl_harga SET 
                                       id_jenis = '$idservicesubdetail', 
                                       nama_paket = '$nama_paket', 
                                       harga = '$harga',
                                       harga2 = '$harga2',
                                       expired_diskon = '$exp_diskon',
                                       create_date='$create_date'");
            return $query;
        }
        public function service_harga_delete($id){
            $query = $this->db->query("DELETE FROM tbl_harga WHERE id = '$id'");
            return $query;
        }
        
        public function getDataContactus()
        {
            $query = $this->db->query("SELECT * FROM tbl_contact");
            return $query;
        }
        public function contact_address_update($address)
        { 
            $isi = addslashes($address);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET address = '$isi', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function contact_address2_update($address2)
        { 
            $isi = addslashes($address2);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET address2 = '$isi', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function contact_email_update($email)
        { 
            $isi = addslashes($email);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET email = '$isi', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function contact_phone_update($phone)
        {
            $isi = addslashes($phone);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET phone = '$phone', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function contact_maps_update($gmap)
        {
            $isi = addslashes($gmap);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET gmap = '$isi', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function contact_maps2_update($gmap2)
        {
            $isi = addslashes($gmap2);
            $modify_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_contact SET gmap2 = '$isi', modify_date='$modify_date', modify_user='...'");
            return $query;
        }
        public function getDataPartner()
        { 
            $query = $this->db->query("SELECT * FROM tbl_partner");
            return $query;
        }
        public function add_partner($datename, $alt_name)
        { 
            $create_date = date('Y-m-d H:i:s');
            $query = $this->db->query("INSERT tbl_partner SET logo = '$datename', alt_name= '$alt_name', create_user='..', create_date='$create_date'");
            return $query;
        }
        
        public function partner_delete($id){
            $query = $this->db->query("DELETE FROM tbl_partner WHERE id = '$id'");
            return $query;
        }
        public function partner_update($id, $logo, $alt_name)
        {
            $modify_date = date('Y-m-d H:i:s'); 
            $query = $this->db->query("UPDATE tbl_partner SET logo='$logo', alt_name='$alt_name', modify_date='$modify_date' WHERE id = '$id'");
            return $query;
        }
        public function getDataTheTeam()
        { 
            $query = $this->db->query("SELECT
                                            tbl_team.id,
                                            m_level.id AS id_level,
                                            m_level.`name` AS nama_level,
                                            tbl_team.nama,
                                            tbl_team.jabatan,
                                            tbl_team.no_sipp,
                                            tbl_team.quotes,
                                            tbl_team.email,
                                            tbl_team.username,
                                            tbl_team.ig,
                                            tbl_team.twitter,
                                            tbl_team.fb,
                                            tbl_team.avatar ,
                                            tbl_team.signature 
                                        FROM
                                            tbl_team
                                            INNER JOIN tbl_user ON tbl_team.username = tbl_user.username
                                            INNER JOIN m_level ON tbl_user.`level` = m_level.id
                                        ORDER BY tbl_team.id");
            return $query;
        }
        public function getDataLevel()
        { 
            $query = $this->db->query("SELECT * FROM m_level WHERE id<>10");
            return $query;
        }
        public function cek_username($username)
        {
            $query = $this->db->query("SELECT username FROM tbl_user WHERE username='$username'");
            return $query;
        }
        public function add_the_team($nama, $jabatan, $no_sipp, $quotes, $level, $email, $username, $password, $ig, $twitter, $fb, $datename, $datename_sign)
        {
            $create_date = date('Y-m-d H:i:s');
            $this->db->query("INSERT tbl_team SET nama = '$nama', jabatan= '$jabatan', no_sipp= '$no_sipp', quotes='$quotes', email='$email', username='$username', ig='$ig', twitter='$twitter', fb='$fb', avatar='$datename', signature='$datename_sign', create_date='$create_date'");
            $this->db->query("INSERT tbl_user SET username='$username', email='$username', level='$level', password='$password', `status`='1', create_date='$create_date'");
        }
         
        public function the_team_delete($id,$username)
        {
            $query = $this->db->query("DELETE FROM tbl_team WHERE id = '$id'");
            $query2 = $this->db->query("UPDATE tbl_user SET status='0' WHERE username = '$username'");
            return $query;
        }

        public function the_team_update($id, $nama, $jabatan, $no_sipp, $quotes, $ig, $twitter, $fb, $avatar, $signature, $email, $username, $password, $level)
        { 
            $query = $this->db->query("UPDATE tbl_team SET nama='$nama', jabatan='$jabatan', no_sipp='$no_sipp', quotes='$quotes', email='$email', ig='$ig', twitter='$twitter', fb='$fb', avatar='$avatar', signature='$signature', modify_date='".date('Y-m-d H:i:s')."' WHERE id = '$id'");
            if ($password!='') {
                $date = date('Y-m-d H:i:s');
                $sha1password = sha1($password);
                $query2 = $this->db->query("UPDATE tbl_user SET password='$sha1password', modify_date='$date' WHERE username='$username'");
            }
            if ($level!='') {
                $date = date('Y-m-d H:i:s');
                $query3 = $this->db->query("UPDATE tbl_user SET level='$level', modify_date='$date' WHERE username='$username'");
            }
            return $query;
        }

        public function getDataFaq()
        { 
            $query = $this->db->query("SELECT * FROM tbl_faq");
            return $query;
        }
        public function add_faq($question, $answer)
        { 
            $query = $this->db->query("INSERT tbl_faq SET question='$question', answer='$answer', create_date='".date('Y-m-d H:i:s')."'");
            return $query;
        }
        public function faq_update($id,$question,$answer)
        {
            $query = $this->db->query("UPDATE tbl_faq SET question='$question', answer='$answer', modify_date='".date('Y-m-d H:i:s')."' WHERE id='$id'");
            return $query;
        }
        public function delete_faq($id)
        {
            $query = $this->db->query("DELETE FROM tbl_faq WHERE id = '$id'");
            return $query;
        }
        

        public function getDataPortofolio(){ 
            $query = $this->db->query("SELECT * FROM tbl_portofolio");
            return $query;
        }
        public function add_portofolio($title, $description, $datename, $status){
            $create_date = date('Y-m-d H:i:s');
            $query = $this->db->query("INSERT tbl_portofolio SET title = '$title', description= '$description', pict='$datename', status='$status', create_date='$create_date'");
            return $query; 
        }
        public function portofolio_update($id, $title, $description, $pict, $status){
            $create_date = date('Y-m-d H:i:s');
            $query = $this->db->query("UPDATE tbl_portofolio SET title = '$title', description= '$description', pict='$pict', status='$status', modify_date='$create_date' WHERE id='$id'");
            return $query; 
        }
        public function portofolio_delete($id){
            $query = $this->db->query("DELETE FROM tbl_portofolio WHERE id = '$id'");
            return $query;
        }
        public function getDataHargaDetail($id_harga){
            $query = $this->db->query("SELECT
                                            tbl_harga_detail.id,
                                            tbl_harga_detail.id_harga,
                                            tbl_harga_detail.id_alat_test,
                                            tbl_alat_tes.nama_alat_tes
                                        FROM
                                            tbl_harga_detail INNER JOIN tbl_alat_tes ON tbl_harga_detail.id_alat_test=tbl_alat_tes.id_alat_tes
                                        WHERE
                                            tbl_harga_detail.id_harga = '$id_harga'");
            return $query;
        }
        public function service_harga_detail_delete_alat($id)
        {
            $query = $this->db->query("DELETE FROM tbl_harga_detail WHERE id = '$id'");
            return $query;
        }
        public function getDataAlatTest()
        {
            $query = $this->db->query("SELECT * FROM tbl_alat_tes");
            return $query;
        }
        public function service_harga_detail_add_alat($id_harga,$alat_test)
        {
            $query = $this->db->query("INSERT tbl_harga_detail SET id_harga='$id_harga', id_alat_test='$alat_test'");
            return $query;
        }
    }