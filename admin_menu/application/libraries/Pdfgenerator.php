<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// panggil autoload dompdf nya
// require_once 'dompdf/autoload.inc.php';
require_once 'dompdf-master/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;
class Pdfgenerator {
    public function generate($html, $filename='uhuy', $paper = '', $orientation = '', $stream=TRUE)
    {   
        $options = new Options();
        $options->set('isRemoteEnabled', TRUE);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        if ($stream) {
            $dompdf->stream("test.pdf", array("Attachment" => false));
        } else {
            return $dompdf->output();
        }
    }
}