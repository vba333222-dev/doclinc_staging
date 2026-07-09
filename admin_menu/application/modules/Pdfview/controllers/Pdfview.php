<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	class Pdfview extends CI_Controller { 
		function __construct(){
			parent::__construct();
			if ($this->session->userdata('is_login') == FALSE) {
				redirect('/', 'refresh');
			}
			if ($this->session->userdata('level') !== 'admin') {
				redirect('home', 'refresh');
			}
			// $this->load->model('Pdf_view_m'); 
		}
		private function report_key($segment = 3)
		{
			$value = (string) $this->uri->segment($segment);
			return preg_replace('/[^A-Za-z0-9_\-.]/', '', $value);
		}
		private function pdf_dependency_available()
		{
			foreach (array(
				FCPATH . 'vendor/autoload.php',
				APPPATH . 'libraries/dompdf-master/autoload.inc.php',
				APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'dompdf-master' . DIRECTORY_SEPARATOR . 'autoload.inc.php',
				FCPATH . 'dompdf-master/autoload.inc.php',
			) as $autoload) {
				if (is_file($autoload)) {
					require_once $autoload;
					if (class_exists('Dompdf\Dompdf', FALSE) && class_exists('Dompdf\Options', FALSE)) {
						return TRUE;
					}
				}
			}

			return class_exists('Dompdf\Dompdf', FALSE) && class_exists('Dompdf\Options', FALSE);
		}

		private function ensure_pdf_dependency()
		{
			if ($this->pdf_dependency_available()) {
				return TRUE;
			}

			$this->output
				->set_status_header(503)
				->set_content_type('text/html', 'utf-8')
				->set_output('<h1>PDF tidak tersedia</h1><p>Modul PDF belum tersedia di lingkungan ini.</p>');
			return FALSE;
		}

	    public function index()
	    {
	        // panggil library yang kita buat sebelumnya yang bernama pdfgenerator
			if (!$this->ensure_pdf_dependency()) {
				return;
			}
	        $this->load->library('pdfgenerator');
	        
	        // title dari pdf
	        $this->data['title_pdf'] = 'Laporan Penjualan Toko Kita';
	        
	        // filename dari pdf ketika didownload
	        $file_pdf = 'laporan_penjualan_toko_kita';
	        // setting paper
	        $paper = 'A4';
	        //orientasi paper potrait / landscape
	        $orientation = "portrait";
	        
			$html = $this->load->view('laporan_pdf',$this->data, true);	    
	        
	        // run dompdf
	        $this->pdfgenerator->generate($html, $file_pdf,$paper,$orientation);
	    }
		public function Test_kesehatan_mental_report()
		{
			$nomor=$this->report_key(3);
			$data['data_report']=$this->db->query("SELECT
										tbl_trans_test_kesehatan_mental.nomor,
										tbl_trans_test_kesehatan_mental.nama,
										tbl_team.nama AS nama_psikolog,
										tbl_team.no_sipp,
										tbl_trans_test_kesehatan_mental.jenis_kelamin,
										tbl_trans_test_kesehatan_mental.usia,
										tbl_trans_test_kesehatan_mental.keterangan,
										tbl_trans_test_kesehatan_mental.create_date,
										tbl_asoka_mates.status_pernikahan,
										tbl_asoka_mates.tempat_lahir,
										tbl_asoka_mates.tanggal_lahir,
										tbl_asoka_mates.pendidikan_terakhir,
										(SELECT v_hitung_stress.skor_stress FROM v_hitung_stress WHERE v_hitung_stress.nomor=tbl_trans_test_kesehatan_mental.nomor) AS skor_stress,
										(SELECT v_hitung_stress.makna FROM v_hitung_stress WHERE v_hitung_stress.nomor=tbl_trans_test_kesehatan_mental.nomor) AS makna_stress,
										(SELECT v_hitung_kecemasan.skor_kecemasan FROM v_hitung_kecemasan WHERE v_hitung_kecemasan.nomor=tbl_trans_test_kesehatan_mental.nomor) AS skor_kecemasan,
										(SELECT v_hitung_kecemasan.makna FROM v_hitung_kecemasan WHERE v_hitung_kecemasan.nomor=tbl_trans_test_kesehatan_mental.nomor) AS makna_kecemasan,
										(SELECT v_hitung_depresi.skor_depresi FROM v_hitung_depresi WHERE v_hitung_depresi.nomor=tbl_trans_test_kesehatan_mental.nomor) AS skor_depresi,
										(SELECT v_hitung_depresi.makna FROM v_hitung_depresi WHERE v_hitung_depresi.nomor=tbl_trans_test_kesehatan_mental.nomor) AS makna_depresi
									FROM
										tbl_trans_test_kesehatan_mental 
									LEFT JOIN tbl_asoka_mates ON tbl_asoka_mates.email=tbl_trans_test_kesehatan_mental.create_user
									LEFT JOIN tbl_trans_berkas_tes ON tbl_trans_berkas_tes.id_order_detail = tbl_trans_test_kesehatan_mental.id_order_detail
									LEFT JOIN tbl_order_detail ON tbl_order_detail.id_order_detail = tbl_trans_test_kesehatan_mental.id_order_detail
									LEFT JOIN tbl_team ON tbl_team.username = tbl_trans_berkas_tes.id_psikolog
									WHERE tbl_trans_test_kesehatan_mental.nomor='".$nomor."' GROUP BY tbl_trans_test_kesehatan_mental.nomor"); 
			 
	        // panggil library yang kita buat sebelumnya yang bernama pdfgenerator
			if (!$this->ensure_pdf_dependency()) {
				return;
			}
	        $this->load->library('pdfgenerator');
	        
	        // title dari pdf
	        $this->data['title_pdf'] = 'Hasil Test Kesehatan Mental';
	        
	        // filename dari pdf ketika didownload
	        $file_pdf = 'laporan_hasil_kesehatan_mental';
	        // setting paper
	        $paper = 'A4';
	        //orientasi paper potrait / landscape
	        $orientation = "portrait";
	        
			$html = $this->load->view('laporan_hasil_kesehatan_mental',$data, true);	    
	        
	        // run dompdf
	        $this->pdfgenerator->generate($html, $file_pdf,$paper,$orientation);
		}

		public function Test_kepribadian_report()
		{
			
			$nomor=$this->report_key(3);
			$data['data_report']=$this->db->query("SELECT
														tbl_trans_test_kepribadian.id_trans_kepribadian,
														tbl_trans_test_kepribadian.id_test_kepribadian,
														tbl_trans_test_kepribadian.nomor,
														tbl_trans_test_kepribadian.id_user,
														tbl_trans_test_kepribadian.nama,
														tbl_team.nama AS nama_psikolog,
														tbl_team.no_sipp,
														tbl_trans_test_kepribadian.jenis_kelamin,
														tbl_trans_test_kepribadian.usia,
														tbl_trans_test_kepribadian.answer, 
														tbl_trans_test_kepribadian.create_date,
														tbl_trans_test_kepribadian.keterangan_klinis,
														tbl_trans_test_kepribadian.kondisi_psikologis,
														tbl_trans_test_kepribadian.rekomendasi,
														tbl_asoka_mates.tempat_lahir,
														tbl_asoka_mates.tanggal_lahir,
														tbl_asoka_mates.pendidikan_terakhir,
														tbl_asoka_mates.status_pernikahan
													FROM
														tbl_trans_test_kepribadian 
													INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
													LEFT JOIN tbl_trans_berkas_tes ON tbl_trans_berkas_tes.id_order_detail = tbl_trans_test_kepribadian.id_order_detail
													LEFT JOIN tbl_order_detail ON tbl_order_detail.id_order_detail = tbl_trans_test_kepribadian.id_order_detail
													LEFT JOIN tbl_team ON tbl_team.username = tbl_trans_berkas_tes.id_psikolog
													WHERE tbl_trans_test_kepribadian.nomor='$nomor'  GROUP BY tbl_trans_test_kepribadian.nomor ORDER BY tbl_trans_test_kepribadian.id_test_kepribadian");

			$data['data_report_detail1']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 1 AND 20");
			
			$data['data_report_detail2']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 21 AND 40");
			$data['data_report_detail3']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 41 AND 60");
			$data['data_report_detail4']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 61 AND 80");
			$data['data_report_detail5']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 81 AND 100");
			$data['data_report_detail6']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 101 AND 120");
			$data['data_report_detail7']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 121 AND 140");
			$data['data_report_detail8']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 141 AND 160");
			$data['data_report_detail9']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 161 AND 180");
			$data['data_report_detail10']=$this->db->query("SELECT
																tbl_trans_test_kepribadian.id_trans_kepribadian,
																tbl_trans_test_kepribadian.id_test_kepribadian,
																tbl_trans_test_kepribadian.nomor, 
																tbl_trans_test_kepribadian.answer 
															FROM
																tbl_trans_test_kepribadian 
															INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
															WHERE tbl_trans_test_kepribadian.nomor='$nomor' AND
															 tbl_trans_test_kepribadian.id_test_kepribadian BETWEEN 181 AND 195");
	        // panggil library yang kita buat sebelumnya yang bernama pdfgenerator
			if (!$this->ensure_pdf_dependency()) {
				return;
			}
	        $this->load->library('pdfgenerator');
	        
	        // title dari pdf
	        $this->data['title_pdf'] = 'Hasil Test Kepribadian';
	        // $data='';
	        // filename dari pdf ketika didownload
	        $file_pdf = 'hasil_test_kepribadian';
	        // setting paper
	        $paper = 'A4';
	        //orientasi paper potrait / landscape
	        $orientation = "portrait";
	        
			$html = $this->load->view('test_kepribadian_report',$data, true);	    
	        
	        // run dompdf
	        $this->pdfgenerator->generate($html, $file_pdf,$paper,$orientation);
		}

		public function final_report_test_kepribadian()
		{ 
			$nomor=$this->report_key(3);
			// panggil library yang kita buat sebelumnya yang bernama pdfgenerator
			if (!$this->ensure_pdf_dependency()) {
				return;
			}
	        $this->load->library('pdfgenerator');
			$data['data_report']=$this->db->query("SELECT
													tbl_trans_test_kepribadian.id_trans_kepribadian,
													tbl_trans_test_kepribadian.id_test_kepribadian,
													tbl_trans_test_kepribadian.nomor,
													tbl_trans_test_kepribadian.id_user,
													tbl_trans_test_kepribadian.nama,
													tbl_team.nama AS nama_psikolog,
													tbl_team.no_sipp,
													tbl_trans_test_kepribadian.jenis_kelamin,
													tbl_trans_test_kepribadian.usia,
													tbl_trans_test_kepribadian.answer, 
													tbl_trans_test_kepribadian.create_date,
													tbl_trans_test_kepribadian.keterangan_klinis,
													tbl_trans_test_kepribadian.kondisi_psikologis,
													tbl_trans_test_kepribadian.rekomendasi,
													tbl_asoka_mates.tempat_lahir,
													tbl_asoka_mates.tanggal_lahir,
													tbl_asoka_mates.pendidikan_terakhir,
													tbl_asoka_mates.status_pernikahan
												FROM
													tbl_trans_test_kepribadian 
												INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_kepribadian.id_user
												LEFT JOIN tbl_trans_berkas_tes ON tbl_trans_berkas_tes.id_order_detail = tbl_trans_test_kepribadian.id_order_detail
												LEFT JOIN tbl_order_detail ON tbl_order_detail.id_order_detail = tbl_trans_test_kepribadian.id_order_detail
												LEFT JOIN tbl_team ON tbl_team.username = tbl_trans_berkas_tes.id_psikolog
												WHERE tbl_trans_test_kepribadian.nomor='$nomor'  GROUP BY tbl_trans_test_kepribadian.nomor ORDER BY tbl_trans_test_kepribadian.id_test_kepribadian");
	        // title dari pdf
	        $this->data['title_pdf'] = 'Hasil Test Kepribadian';
	        // $data='';
	        // filename dari pdf ketika didownload
	        $file_pdf = 'hasil_test_kepribadian';
	        // setting paper
	        $paper = 'A4';
	        //orientasi paper potrait / landscape
	        $orientation = "portrait";
	        
			$html = $this->load->view('final_report_test_kepribadian',$data, true);	    
	        
	        // run dompdf
	        $this->pdfgenerator->generate($html, $file_pdf,$paper,$orientation);
		}

		public function Test_papi_report()
		{
			$nomor=$this->report_key(3);
			$data['data_report']=$this->db->query("SELECT
														tbl_trans_test_papi.nomor_test,
														tbl_trans_test_papi.nama,
														tbl_team.nama AS nama_psikolog,
														tbl_team.no_sipp,
														tbl_trans_test_papi.jenis_kelamin,
														tbl_trans_test_papi.usia,
														tbl_trans_test_papi.create_date,
														tbl_asoka_mates.tempat_lahir,
														tbl_asoka_mates.tanggal_lahir,
														tbl_asoka_mates.pendidikan_terakhir,
														tbl_asoka_mates.status_pernikahan
													FROM
														tbl_trans_test_papi 
													INNER JOIN tbl_asoka_mates ON tbl_asoka_mates.id_mates = tbl_trans_test_papi.id_user
													LEFT JOIN tbl_trans_berkas_tes ON tbl_trans_berkas_tes.id_order_detail = tbl_trans_test_papi.id_order_detail
													LEFT JOIN tbl_order_detail ON tbl_order_detail.id_order_detail = tbl_trans_test_papi.id_order_detail
													LEFT JOIN tbl_team ON tbl_team.username = tbl_trans_berkas_tes.id_psikolog
													WHERE tbl_trans_test_papi.nomor_test='$nomor'  GROUP BY tbl_trans_test_papi.nomor_test");
			$data['data_papi_a'] = $this->db->query("SELECT * FROM v_test_papi_a WHERE nomor_test='$nomor'"); 
			$data['data_papi_b'] = $this->db->query("SELECT * FROM v_test_papi_b WHERE nomor_test='$nomor'"); 
			$data['data_papi_c'] = $this->db->query("SELECT * FROM v_test_papi_c WHERE nomor_test='$nomor'"); 
			$data['data_papi_d'] = $this->db->query("SELECT * FROM v_test_papi_d WHERE nomor_test='$nomor'"); 
			$data['data_papi_e'] = $this->db->query("SELECT * FROM v_test_papi_e WHERE nomor_test='$nomor'"); 
			$data['data_papi_f'] = $this->db->query("SELECT * FROM v_test_papi_f WHERE nomor_test='$nomor'"); 
			$data['data_papi_g'] = $this->db->query("SELECT * FROM v_test_papi_g WHERE nomor_test='$nomor'"); 
			$data['data_papi_i'] = $this->db->query("SELECT * FROM v_test_papi_i WHERE nomor_test='$nomor'"); 
			$data['data_papi_k'] = $this->db->query("SELECT * FROM v_test_papi_k WHERE nomor_test='$nomor'"); 
			$data['data_papi_l'] = $this->db->query("SELECT * FROM v_test_papi_l WHERE nomor_test='$nomor'"); 
			$data['data_papi_n'] = $this->db->query("SELECT * FROM v_test_papi_n WHERE nomor_test='$nomor'"); 
			$data['data_papi_o'] = $this->db->query("SELECT * FROM v_test_papi_o WHERE nomor_test='$nomor'"); 
			$data['data_papi_p'] = $this->db->query("SELECT * FROM v_test_papi_p WHERE nomor_test='$nomor'"); 
			$data['data_papi_r'] = $this->db->query("SELECT * FROM v_test_papi_r WHERE nomor_test='$nomor'"); 
			$data['data_papi_s'] = $this->db->query("SELECT * FROM v_test_papi_s WHERE nomor_test='$nomor'"); 
			$data['data_papi_t'] = $this->db->query("SELECT * FROM v_test_papi_t WHERE nomor_test='$nomor'"); 
			$data['data_papi_v'] = $this->db->query("SELECT * FROM v_test_papi_v WHERE nomor_test='$nomor'"); 
			$data['data_papi_w'] = $this->db->query("SELECT * FROM v_test_papi_w WHERE nomor_test='$nomor'"); 
			$data['data_papi_x'] = $this->db->query("SELECT * FROM v_test_papi_x WHERE nomor_test='$nomor'"); 
			$data['data_papi_z'] = $this->db->query("SELECT * FROM v_test_papi_z WHERE nomor_test='$nomor'"); 

	        // panggil library yang kita buat sebelumnya yang bernama pdfgenerator
			if (!$this->ensure_pdf_dependency()) {
				return;
			}
	        $this->load->library('pdfgenerator');
	        
	        // title dari pdf
	        $this->data['title_pdf'] = 'Hasil Test Papi Kostik';
	        // $data='';
	        // filename dari pdf ketika didownload
	        $file_pdf = 'hasil_test_papi';
	        // setting paper
	        $paper = 'A4';
	        //orientasi paper potrait / landscape
	        $orientation = "portrait";
	        
			$html = $this->load->view('test_papi_report',$data, true);	    
	        
	        // run dompdf
	        $this->pdfgenerator->generate($html, $file_pdf,$paper,$orientation);
		}
	}
