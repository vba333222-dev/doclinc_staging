<!DOCTYPE html>
<?php   
    $nomor = '';
    $nama = '';
    $nama_psikolog = '';
    $no_sipp = '';
    $tanggal ='';
    $jeniskelamin ='';
    $usia ='';
    $pendidikan ='';
    $tempat_lahir ='';
    $tanggal_lahir ='';
    $status_pernikahan ='';
    // $keterangan_klinis ='';
             
    foreach ($data_report->result_array() as $x){ 
        $nomor = $x['nomor_test'];
        $nama = $x['nama'];
        $nama_psikolog = $x['nama_psikolog'];
        $no_sipp = $x['no_sipp'];
        $tanggal =date('d-m-Y',strtotime($x['create_date']));
        if($x['jenis_kelamin']=='L'){
            $jeniskelamin='Laki-laki';
        }else if($x['jenis_kelamin']=='P'){
            $jeniskelamin='Perempuan';
        }else{
            $jeniskelamin=$x['jenis_kelamin'];
        } 
        $usia = $x['usia']; 
        $pendidikan = $x['pendidikan_terakhir']; 
        $tempat_lahir = $x['tempat_lahir']; 
        $tanggal_lahir = $x['tanggal_lahir']; 
        $status_pernikahan =$x['status_pernikahan']; 
        // $keterangan_klinis =$x['keterangan_klinis']; 
        // $kondisi_psikologis =$x['kondisi_psikologis']; 
        // $rekomendasi =$x['rekomendasi']; 
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
                border-collapse: collapse; 
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
            h5{
                text-align: center;
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
                    <h5 class="mb-0 text-center"><?php echo strtoupper($nama); ?> </h5>
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

      
            <div class="pager font-weight-light ">
                <i>Halaman 1</i>
            </div>
            <!-- page 2 -->
            <table class="table table-bordered table-sm mb-4">
                <tr>
                    <td style="width:24%;"><b>Nama</td>
                    <td style="width:26%;">:</b> <?php echo ucwords($nama); ?> </td>
                    <td style="width:23%;"><b>Pendidikan Terakhir</td>
                    <td style="width:26%;">:</b> <?php echo $pendidikan; ?> </td>
                </tr>
                <tr>
                    <td><b>Tempat, Tanggal Lahir</td>
                    <td>:</b> <?php echo ucwords($tempat_lahir); ?>, <?php echo date('d-m-Y',strtotime($tanggal_lahir)); ?>  </td>
                    <td><b> Status Pernikahan</td>
                    <td>:</b> <?php echo $status_pernikahan;?></td>
                </tr>
                <tr>
                    <td><b>Usia</td>
                    <td>:</b> <?php echo $usia; ?> Tahun</td>
                    <td><b>Tanggal Asesmen</td>
                    <td>:</b> <?php echo $tanggal; ?> </td>
                </tr>
                <tr>
                    <td><b>Jenis Kelamin</td>
                    <td>:</b> <?php echo $jeniskelamin; ?></td>
                    <td><b>Tujuan Asesmen</td>
                    <td>:</b></td>
                </tr>
            </table>&nbsp;<br/>
            
            <table class="table table-bordered table-sm mb-2">
                <tr>
                    <th>NO  </th>
                    <th>ASPEK </th>
                    <th>SKOR  </th>
                    <th>MAKNA SKOR</th>
                </tr>
                <tr>
                  <td class="text-right">1.</td>
                  <td>G = PERAN PEKERJA KERAS<br>(Hard Intense Worked)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_g->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_g->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">2.</td>
                  <td>L = PERAN – PEMIMPIN<br>(Leadership Role)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_l->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_l->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">3.</td>
                  <td>I = PERAN – MEMBUAT KEPUTUSAN<br>(Ease in Decision Making)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_i->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_i->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">4.</td>
                  <td>T = PERAN SIBUK<br>(Pace)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_t->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_t->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">5.</td>
                  <td>V = PERAN PENUH SEMANGAT<br>(Vigorous Type)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_v->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_v->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">6.</td>
                  <td>S = PERAN HUBUNGAN SOSIAL<br>(Social Extension)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_s->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_s->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">7.</td>
                  <td>R = PERAN ORANG YANG TEORITIS<br>(Theoretical Type)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_r->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_r->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">8.</td>
                  <td>D = PERAN BEKERJA DENGAN HAL – HAL RINCI<br>(Interest in Working With Details)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_d->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_d->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">9.</td>
                  <td>C = PERAN MENGATUR<br>(Organized Type)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_c->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_c->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">10.</td>
                  <td>E = PERAN PENGENDALIAN EMOSI<br>(Emotional Resistant)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_e->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_e->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">11.</td>
                  <td>N = KEBUTUHAN MENYELESAIKAN TUGAS SECARA MANDIRI<br>(Need to FinishTask)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_n->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_n->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">12.</td>
                  <td>A = KEBUTUHAN BERPRESTASI<br>(Need to Achieve)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_a->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_a->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">13.</td>
                  <td>P = KEBUTUHAN – MENGATUR ORANG LAIN<br>(Need to Control Others)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_p->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_p->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">14.</td>
                  <td>X = KEBUTUHAN UNTUK DIPERHATIKAN<br>(Need to be Noticed)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_x->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_x->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">15.</td>
                  <td>B = KEBUTUHAN DITERIMA DALAM KELOMPOK<br>(Need to Belong to Groups)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_b->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_b->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">16.</td>
                  <td>O = KEBUTUHAN KEDEKATAN DAN KASIH SAYANG<br>(Need for Closeness and Affection)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_o->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_o->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">17.</td>
                  <td>Z = KEBUTUHAN UNTUK BERUBAH<br>(Need for Change)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_z->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_z->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">18.</td>
                  <td>K = KEBUTUHAN UNTUK AGRESIF<br>(Need to be Forceful)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_k->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_k->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">19.</td>
                  <td>F = KEBUTUHAN – MEMBANTU ATASAN<br>(Need to Support Authority)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_f->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_f->row()->makna); ?></td>
                </tr>
                <tr>
                  <td class="text-right">20.</td>
                  <td>W = KEBUTUHAN MENGIKUTI ATURAN DAN PENGAWASAN<br>(Need for Rules and Supervision)</td>
                  <td class="font-weight-bold text-center"><?= $data_papi_w->row()->skor; ?></td>
                  <td class="font-weight-bold"><?= ucfirst($data_papi_w->row()->makna); ?></td>
                </tr>
            </table> 
             <div class="pager font-weight-light ">
                <i>Halaman 2</i>
            </div>
            <!-- page 3 -->
            <div class="page_break"></div>
            <div class="card ml-auto border-0" style="width: 300px;">
                <div class="card-body p-2 text-center">
                    Serang, <?php echo $tanggal; ?><br>
                    Psikolog Penanggungjawab,<br><br><br><br><br>
                    <b><u><?= $nama_psikolog;?>,</u></b><br>
                    SIPP : <?= $no_sipp;?>
                </div>
            </div>
            <div class="pager font-weight-light ">
                <i>Halaman 3</i>
            </div>
            <!-- page 3 -->
        </div>
        <!-- konten -->
    </body>
</html>