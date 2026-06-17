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
    $keterangan_klinis ='';
    $kondisi_psikologis ='';
    $rekomendasi ='';
             
    foreach ($data_report->result_array() as $x){ 
        $nomor = $x['nomor'];
        $nama = $x['nama']; 
        $nama_psikolog = $x['nama_psikolog'];
        $no_sipp = $x['no_sipp'];
        $tanggal =date('d-m-Y',strtotime($x['create_date']));
        if($x['jenis_kelamin']=='Laki-laki'){
            $jeniskelamin='L';
        }else{
            $jeniskelamin='P';
        } 
        $usia = $x['usia']; 
        $pendidikan = $x['pendidikan_terakhir']; 
        $tempat_lahir = $x['tempat_lahir']; 
        $tanggal_lahir = $x['tanggal_lahir']; 
        $keterangan_klinis =$x['keterangan_klinis']; 
        $kondisi_psikologis =$x['kondisi_psikologis']; 
        $rekomendasi =$x['rekomendasi']; 
     }
?>
<html lang="en">
    <head>
        <title><php echo $title_pdf;?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <style>
            @page{
                margin:20px;
            }
            body{
                font-family: Arial, Helvetica, sans-serif;
            }
            table {
                border-collapse: collapse; 
                
            }
            td > span.active{
                border-radius: 30%;
                padding: 6px;
                border: solid 1px #000;  
            }
            td > span.non-active{
                border-radius: 30%;
                padding: 6px;
                border: none;
            }
            .numberCircle {
                /* border-radius: 50%; */
                /* width: 12px;
                height: 12px;
                padding: 4px;
  */
                /* background: #fff;
                border: 2px solid #00000;
                color: #00000;  
                */
                /* line-height:30px;  */
                position: absolute; 
            }
        </style>
    </head>
    <body>
        <div class="z-index-top">
            <h1><center>LEMBAR JAWABAN MCMI - IV (195 Soal)</center></h1><br/>
            <table style="width:100%;">
                <tr>
                    <td style="width:18%;">Nomor#</td>
                    <td>: <b><?php echo $nomor; ?></b> &nbsp;</td>
                    <td style="width:20%;">Nama</td>
                    <td>: <b><?php echo $nama; ?></b></td>
                </tr>
                <tr>
                    <td>Tanggal Test</td>
                    <td>: <b><?php echo $tanggal; ?></b></td>
                    <td>Jenis Kelamin : <b><?php echo $jeniskelamin; ?></b> &nbsp;</td>
                    <td>Pendidikan : <b><?php echo $pendidikan; ?></b></td>
                </tr>
                <tr>
                    <td>Tempat, Tgl Lahir</td>
                    <td>: <b><?php echo $tempat_lahir; ?></b> / <b><?php echo date('d-m-Y',strtotime($tanggal_lahir)); ?></b></td>
                    
                    <td>Umur : <b><?php echo $usia; ?></b></td>
                    <td></td>
                </tr>
            </table><br/>
        <div>
        <table border="1" style="width:100%" >
            <tr>
                <td valign="top" style="background-color:#d9d7d7;"><center><?php
                    for($i=1;$i<=20;$i++)
                    {  
                        echo ''.$i.'<br/>';  
                    }
                    ?></center>
                </td>
                <td>  <?php 
                        foreach ($data_report_detail1->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=21;$i<=40;$i++)
                    {
                        echo $i.'<br/>';  
                    } 
                    ?></center></td>
                    
                <td>  <?php 
                        foreach ($data_report_detail2->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?></td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=41;$i<=60;$i++)
                    {
                        echo $i.'<br/>';  
                    }
                    ?></center></td>
                    <td> 
                        <?php
                            foreach ($data_report_detail3->result_array() as $q){  
                                if($q['answer']==0){
                                    echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                                }else{ 
                                    echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                                }
                            }
                        ?></td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=61;$i<=80;$i++)
                    {
                        echo $i.'<br/>';  
                    }
                    ?></center>
                </td>
                <td> 
                    <?php
                        foreach ($data_report_detail4->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=81;$i<=100;$i++)
                    {
                        echo $i.'<br/>';  
                    }
                    ?></center></td>
                <td>                     
                    <?php
                        foreach ($data_report_detail5->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=101;$i<=120;$i++)
                    {
                        echo $i.'<br/>';   
                    }
                    ?></center></td>
                <td>                     
                    <?php
                        foreach ($data_report_detail6->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=121;$i<=140;$i++)
                    {
                        echo $i.'<br/>';  
                    }
                    ?></center></td>
                    
                <td>                     
                    <?php
                        foreach ($data_report_detail7->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;"><center><?php
                    for($i=141;$i<=160;$i++)
                    {
                        echo $i.'<br/>';   
                    }
                    ?></center></td>
                <td>                     
                    <?php
                        foreach ($data_report_detail8->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>> 
               <td style="background-color:#d9d7d7;"><center><?php
                    for($i=161;$i<=180;$i++)
                    {
                        echo $i.'<br/>';   
                    }
                    ?></center></td> 
                <td>                     
                    <?php
                        foreach ($data_report_detail9->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
                <td style="background-color:#d9d7d7;" valign="top"><center><?php
                    for($i=181;$i<=195;$i++)
                    {
                        echo $i.'<br/>';
                    }
                    ?></center></td>
                    
                <td valign="top">                     
                    <?php
                        foreach ($data_report_detail10->result_array() as $q){  
                            if($q['answer']==0){
                                echo '<span class="active">+</span><span class="non-active">-</span><br/>';
                            }else{ 
                                echo '<span class="non-active">+</span><span class="active">-</span><br/>';
                            }
                        }
                    ?>
                </td>
            </tr>
        </table>
        &nbsp;
        <br/>
        
            <b>Keterangan Klinis :</b><br/>
             <?php echo $keterangan_klinis; ?><br/><br/>

             
            <b>Kondisi Psikologis :</b><br/>
             <?php echo $kondisi_psikologis; ?><br/><br/>

             
            <b>Rekomendasi :</b><br/>
             <?php echo $rekomendasi; ?><br/><br/>
            </div>
        </div> 
   
                

 
     
    </body>
</html>