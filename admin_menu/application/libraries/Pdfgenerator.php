<?php
defined('BASEPATH') OR exit('No direct script access allowed');

foreach (array(
    FCPATH . 'vendor/autoload.php',
    APPPATH . 'libraries/dompdf-master/autoload.inc.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'dompdf-master' . DIRECTORY_SEPARATOR . 'autoload.inc.php',
    FCPATH . 'dompdf-master/autoload.inc.php',
) as $pdf_autoload) {
    if (is_file($pdf_autoload)) {
        require_once $pdf_autoload;
        break;
    }
}

class Pdfgenerator {
    public function generate($html, $filename='uhuy', $paper = '', $orientation = '', $stream=TRUE)
    {   
        if (!class_exists('Dompdf\Dompdf') || !class_exists('Dompdf\Options')) {
            return FALSE;
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', TRUE);
        $dompdf = new \Dompdf\Dompdf($options);
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
