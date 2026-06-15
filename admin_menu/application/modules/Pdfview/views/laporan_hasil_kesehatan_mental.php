<!DOCTYPE html>
<?php	
    $nomor = '';
    $nama = '';
    $nama_psikolog = '';
    $no_sipp = '';
    $pendidikan ='';
    $jk = '';
    $status = '';
    $ttl = '';
    $tgl_asesmen = '';
    $skor_stress = '';
    $makna_stress ='';
    $skor_kecemasan = '';
    $makna_kecemasan = '';
    $skor_depresi = '';
    $makna_depresi = '';
    $saran = ''; 
             
    foreach ($data_report->result_array() as $x){ 
        $nomor = $x['nomor'];
        $nama = $x['nama'];
        $nama_psikolog = $x['nama_psikolog'];
        $no_sipp = $x['no_sipp'];
        $pendidikan = $x['pendidikan_terakhir'];
        $jk = $x['jenis_kelamin'];
        $status = $x['status_pernikahan'];
        $ttl = $x['tempat_lahir'].','.date('d-M-Y',strtotime($x['tanggal_lahir']));
        $tgl_asesmen = date('d-m-Y',strtotime($x['create_date']));
        $skor_stress = $x['skor_stress'];
        $makna_stress = $x['makna_stress'];
        $skor_kecemasan = $x['skor_kecemasan'];
        $makna_kecemasan = $x['makna_kecemasan'];
        $skor_depresi = $x['skor_depresi'];
        $makna_depresi = $x['makna_depresi'];
        $saran = $x['keterangan'];
        
     }
?>
<html lang="en">
    <head>
        <title><php echo $title_pdf;?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css" integrity="sha384-zCbKRCUGaJDkqS1kPbPd7TveP5iyJE0EjAuZQTgFLD2ylzuqKfdKlfG/eSrtxUkn" crossorigin="anonymous">
        <style type="text/css">
            /*MARGIN KERTAS*/
            @page{
                margin-top: 130px;
                margin-left: 30px;
                margin-right: 30px;
                margin-bottom: 50px;
            }
            /*MARGIN KERTAS*/

            /*PINDAH HALAMAN*/
            .page_break {
                page-break-before: always;
            }
            /*PINDAH HALAMAN*/
            
            /*TEMPLATE KERTAS*/
            img.template{
                position: fixed;
                top: -130px;
                left: -30px;
                height: 1125px;
                width: auto;
            }
            /*TEMPLATE KERTAS*/

            /*POSISI KONTEN DIATAS TEMPLATE KERTAS*/
            .z-index-top{
                z-index: 1;
            }
            /*POSISI KONTEN DIATAS TEMPLATE KERTAS*/

            .table-address{
                position: absolute;
                bottom: 0;
                left: 0;
            }
            table.table{
                font-size: 11pt;
            }
            .pager{
                position: absolute;
                left: 0;
                bottom: -30px;
                font-size: 11pt;
            }
            .ttd{
                position: absolute;
                right: 0;
                bottom: 0;
                font-size: 11pt;
            }
        </style>
    </head>
    <body>
        <!-- template kertas asoka -->
        <img src="<?php echo base_url();?>assets/img/template_asoka_page.jpg" class="template">
        <!-- template kertas asoka -->

        <!-- konten -->
        <div class="z-index-top">

            <!-- page 1 -->
            <div class="card mb-5 ml-auto" style="width: 250px;">
                <div class="card-body border border-dark p-2 text-center">
                    Nomor klien : <?php echo $nomor; ?>
                </div>
            </div>
            <br>
            <h4 class="text-center mt-5 pt-5"><u>DOKUMEN RAHASIA</u></h4>
            <h5 class="text-center mt-4 mb-5 pb-5">HASIL PEMERIKSAAN<br>PSIKOLOGIS</h5>
            <div class="card mb-5 mx-auto" style="width: 300px;">
                <div class="card-body border border-dark p-2 text-center">
                    <h5 class="mb-0 text-center"><?php echo $nama; ?></h5>
                </div>
            </div>
            <p class="mt-5 mb-3 pt-5 text-center">Dikeluarkan oleh :</p>
            <p class="text-center font-weight-bold">Asoka Consulting</p>
            <div class="table-address">
                <table>
                    <tr>
                        <td><img src="<?php echo base_url();?>assets/img/asoka-logo-only.jpg" height="100" width="auto"></td>
                        <td style="font-size: 10pt;">
                            <b>Asoka Consulting</b><br>
                            Perumahan Emerald Lake Blok A1 No 14 Kramatwatu,<br>
                            Serang, Banten 42161<br>
                            Phone : 0821-1592-5549<br>
                            Email : asokaconsulting@gmail.com
                        </td>
                    </tr>
                </table>
            </div>
            <!-- page 1 -->

            <div class="page_break"></div>

            <!-- page 2 -->
            <table class="table table-bordered table-sm mb-4">
                <thead>
                    <tr>
                        <th scope="col" class="bg-secondary text-white" colspan="4">HASIL PEMERIKSAAN<br>PSIKOLOG</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th align="left">Nomor Pendaftaran :</th>
                        <td><?php echo $nomor; ?></td>
                        <th align="left">Kebutuhan :</th>
                        <td>Tes Kesehatan Mental</td>
                    </tr>
                    <tr>
                        <th align="left">Nama lengkap :</th>
                        <td><?php echo $nama; ?></td>
                        <th align="left">Pendidikan terakhir :</th>
                        <td><?php echo $pendidikan; ?></td>
                    </tr>
                    <tr>
                        <th align="left">Jenis kelamin :</th>
                        <td><?php echo $jk; ?></td>
                        <th align="left">Status pernikahan :</th>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <tr>
                        <th align="left">Tempat, tgl lahir :</th>
                        <td><?php echo $ttl; ?></td>
                        <th align="left">Tanggal asesmen : </th>
                        <td><?php echo $tgl_asesmen; ?></td>
                    </tr>
                </tbody>
            </table>
           
            <table class="table table-bordered table-sm mb-4">
                <thead>
                    <tr>
                        <th scope="col" class="bg-secondary text-white" colspan="7">A. ASESMEN GANGGUAN KECEMASAN</th>
                    </tr>
                    <tr>
                        <th scope="col" class="bg-light" colspan="7" align="left">Klasifikasi Tingkat Gangguan Kecemasan:</th>
                    </tr>
                    <tr>
                        <td width="5%">NO</th>
                        <th scope="col">INDIKATOR</th>
                        <th scope="col">NORMAL</th>
                        <th scope="col">RINGAN</th>
                        <th scope="col">SEDANG</th>
                        <th scope="col">PARAH</th>
                        <th scope="col">SANGAT<br>PARAH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1.</td>
                        <td>Muncul respon otomatis <i>(autonomic arousal)</i></td>
                        <?php 
                        if($skor_kecemasan<=7){
							echo'  <td width="10%" rowspan="4" style="background-color:#c3c3c3;"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>';
						}else if($skor_kecemasan>7 && $skor_kecemasan<=9){ 
							echo'  <td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4" style="background-color:#c3c3c3;"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>';
						}else if($skor_kecemasan>7 && $skor_kecemasan<=14){
							echo' 	<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4" style="background-color:#c3c3c3;"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>';

						}else if($skor_kecemasan>14 && $skor_kecemasan<=19){
							echo' 	<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4" style="background-color:#c3c3c3;"></td>
										<td width="10%" rowspan="4"></td>';

						}else{
							echo'  <td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4"></td>
										<td width="10%" rowspan="4" style="background-color:#c3c3c3;"></td>'; 
						}
                    ?> 
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td>Efek-efek otot <i>(Skeletal musculature effects)</i></td>
                    </tr>
                    <tr>
                        <td>3.</td>
                        <td>Kecemasan situasional <i>(situational anxiety)</i></td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td>Pengalaman subjektif dari kecemasan<br><i>(subjective experience of anxious affect)</i></td>
                    </tr>
                </tbody>
            </table>
            <table class="table table-bordered table-sm mb-4">
                <thead>
                    <tr>
                        <th scope="col" class="bg-secondary text-white" colspan="7">B. ASESMEN GANGGUAN STRES</th>
                    </tr>
                    <tr>
                        <th scope="col" class="bg-light" colspan="7" align="left">Klasifikasi Tingkat Gangguan Stres:</th>
                    </tr>
                    <tr>
                        <th width="5%">NO</th>
                        <th scope="col">INDIKATOR</th>
                        <th scope="col">NORMAL</th>
                        <th scope="col">RINGAN</th>
                        <th scope="col">SEDANG</th>
                        <th scope="col">PARAH</th>
                        <th scope="col">SANGAT<br>PARAH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1.</td>
                        <td>Sulit untuk santai <i>(difficulty relaxing)</i></td> 
                        <?php
                            if($skor_stress<=14){
                                echo'  <td width="10%" rowspan="5" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>';
                            }else if($skor_stress>14 && $skor_stress<=18){ 
                                echo'  <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>';
                            }else if($skor_stress>18 && $skor_stress<=25){
                                echo' 	<td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>';

                            }else if($skor_stress>25 && $skor_stress<=33){
                                echo' 	<td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="5"></td>';

                            }else{
                                echo'  <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5"></td>
                                            <td width="10%" rowspan="5" style="background-color:#c3c3c3;"></td>'; 
                            }
                        ?>
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td>Muncul kegugupan <i>(nervous arousal)</i></td>
                    </tr>
                    <tr>
                        <td>3.</td>
                        <td>Mudah marah/gelisah <i>(easily upset/agigated)</i></td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td>Mengganggu / lebih reaktif<br><i>(irritable / over-reactive)</i></td>
                    </tr>
                    <tr>
                        <td>5.</td>
                        <td>Tidak sabar <i>(impatient)</i></td>
                    </tr>
                </tbody>
            </table>
            <div class="pager font-weight-light ">
                <i>Halaman 1</i>
            </div>
            <!-- page 2 -->

            <div class="page_break"></div>

            <!-- page 3 -->
            <table class="table table-bordered table-sm mb-4">
                <thead>
                    <tr>
                        <th scope="col" class="bg-secondary text-white" colspan="7">C. ASESMEN GANGGUAN DEPRESI</th>
                    </tr>
                    <tr>
                        <th scope="col" class="bg-light" colspan="7" align="left">Klasifikasi Tingkat Gangguan Depresi:</th>
                    </tr>
                    <tr>
                        <th width="5%">NO</th>
                        <th scope="col">INDIKATOR</th>
                        <th scope="col">NORMAL</th>
                        <th scope="col">RINGAN</th>
                        <th scope="col">SEDANG</th>
                        <th scope="col">PARAH</th>
                        <th scope="col">SANGAT<br>PARAH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1.</td>
                        <td>Disporia</td>
                        <?php 
                            if($skor_depresi<=9){
                                echo'  <td width="10%" rowspan="7" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>';
                            }else if($skor_depresi>9 && $skor_depresi<=13){ 
                                echo'  <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>';
                            }else if($skor_depresi>14 && $skor_depresi<=20){
                                echo' 	<td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>';

                            }else if($skor_depresi>21 && $skor_depresi<=27){
                                echo' 	<td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7" style="background-color:#c3c3c3;"></td>
                                            <td width="10%" rowspan="7"></td>';

                            }else{
                                echo'  <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7"></td>
                                            <td width="10%" rowspan="7" style="background-color:#c3c3c3;"></td>'; 
                            }
                        ?>
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td>Putus asa <i>(hopelessness)</i></td>
                    </tr>
                    <tr>
                        <td>3.</td>
                        <td>Devaluasi kehidupan <i>(devaluation of life)</i></td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td>Mencela diri <i>(self-deprecation)</i></td>
                    </tr>
                    <tr>
                        <td>5.</td>
                        <td>Kurang ketertarikan/keterlibatan<br><i>(lack of interest/involvement)</i></td>
                    </tr>
                    <tr>
                        <td>6.</td>
                        <td>Anhedonia</td>
                    </tr>
                    <tr>
                        <td>7.</td>
                        <td>Inersia</td>
                    </tr>
                </tbody>
            </table>
            <table class="table table-bordered table-sm mb-4">
                <tr>
                    <th class="bg-secondary text-white">D. DIAGNOSIS DAN SARAN</th>
                </tr>
                <tr>
                    <td>Berdasarkan hasil asesmen kesehatan mental, Saudara/i <?php echo $saran; ?></td>
                </tr>
            </table>
            <div class="card ml-auto border-0" style="width: 300px;">
                <div class="card-body p-2 text-center">
                    Serang, <?php echo $tgl_asesmen; ?><br>
                    Psikolog Penanggungjawab,<br><br><br><br><br>
                    <b><u><?= $nama_psikolog;?>,</u></b><br>
                    SIPP : <?= $no_sipp;?>
                </div>
            </div>
            <div class="pager font-weight-light ">
                <i>Halaman 2</i>
            </div>
            <!-- page 3 -->
        </div>
        <!-- konten -->
    </body>
</html>