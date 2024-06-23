<?php

require 'vendor/autoload.php'; // Pastikan Composer autoload disertakan

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Fungsi untuk mengirim permintaan GET menggunakan Guzzle
function get_guzzle($base_url, $url = null, $string_post, $headers) {
//    $username = 'MUABBUser';
//    $password = 'Kj+%QSGT4wfUxbw';
    try {
        $client = new Client([
            'base_uri' => $base_url,
            'body' => $string_post,
            'headers' => ['Authorization' => $headers["Authorization"]],
            'timeout' => 20,
            'verify' => false,
        ]);

        $response = $client->request('GET', $url);
        $status_code = $response->getStatusCode();
        if (isset($response->getHeader('Native-Id')[0])) {
            $native_id = $response->getHeader('Native-Id')[0];
            header('Native-Id:' . $native_id);
        }

        if (isset($response->getHeader('File-Name')[0])) {
            $file_name = $response->getHeader('File-Name')[0];
            header('File-Name:' . $file_name);
        }
        header('http-status:' . $status_code);
        return $response->getBody();
    } catch (RequestException $e) {
        $responseBody = $e->getResponse()->getBody(true);
        $status_code = $e->getResponse()->getStatusCode();
        header('http-status:' . $status_code);
        return false;
    }
}

// Fungsi untuk mengirim permintaan POST menggunakan Guzzle
function post_guzzle($base_url, $url = null, $string_post, $headers = null) {
//    $username = 'MUABBUser';
//    $password = 'Kj+%QSGT4wfUxbw';
    try {
        $client = new Client([
            'base_uri' => $base_url,
            'body' => $string_post,
            'headers' => ['Authorization' => $headers["Authorization"]],
            'timeout' => 50,
            'verify' => false,
            'defaults' => [
                'config' => [
                    'curl' => [
                        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                    ]
                ]
            ]
        ]);

        $response = $client->request('POST', $url);
        $status_code = $response->getStatusCode();
        header('http-status:' . $status_code);
        return $response->getBody();
    } catch (RequestException $e) {
        print_r($e);
        $responseBody = $e->getResponse()->getBody(true);
        $status_code = $e->getResponse()->getStatusCode();
        echo $status_code . ' gagal';
        header('http-status:' . $status_code);
        return false;
    }
}
function fopdatapower() {
    $headers = apache_request_headers();

    $auth = $headers["Authorization"] ?? "";
    $url = $headers["UrlPost"] ?? "";

    $body = file_get_contents('php://input');

    if ($body == NULL) {
        echo get_guzzle($url, '', $body, $headers);
    } else {
        echo post_guzzle($url, '', $body, $headers);
    }
    die;
}

// Fungsi untuk tes koneksi ionic
function ionic_test() {
    echo 'Connect';
    echo json_encode(array('Your POST', $_POST));
}

// Fungsi untuk mengirim OTP via WhatsApp
function wa_otp() {
    $token = get_wa_otp_token();

    if ($token) {
        if (!isset($_POST['otp'])) {
            echo json_encode();
        }
        $otp = $_POST['otp'];
        $body = [
            'messaging_product' => "whatsapp",
            'recipient_type' => "individual",
            'to' => "6289654901005",
            'type' => 'template',
            'template' => [
                'name' => "ztwa_otp",
                'language' => [
                    'code' => 'en_US'
                ],
                'components' => [
                    [
                        'type' => "body",
                        'parameters' => [
                            [
                                'type' => "text",
                                'text' => "$otp"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $curl_opt_array = [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
        ];
        $prod_url = "https://graph.jatismobile.com/v15.0/410384633980770/messages";
        try {
            $client = new Client([
                'base_uri' => $prod_url,
                'body' => json_encode($body),
                'headers' => ['Authorization' => "Bearer $token"],
                'timeout' => 50,
                'defaults' => [
                    'config' => [
                        'curl' => [
                            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                        ]
                    ]
                ]
            ]);

            $response = $client->request('POST', $prod_url);
            $status_code = $response->getStatusCode();

            $response = json_decode($response->getBody());
            echo json_encode(array($response, 'token' => $token));
        } catch (RequestException $e) {
            print_r($e);
            $responseBody = $e->getResponse()->getBody(true);
            $status_code = $e->getResponse()->getStatusCode();
            echo $status_code . 'gagal';
            header('http-status:' . $status_code);
            return false;
        }
    }
}



function con_filehub() {
    $username = "PRD-MULTISTRADA-EU";
    $rsa = PublicKeyLoader::load(file_get_contents(__DIR__ . '/ssh/multistrada_private_prod.ppk'));
    $sftp = new SFTP('filehub.michelin.net');

    if (!$sftp->login($username, $rsa)) {
        header('http-status:401');
        return false;
    } else {
        return $sftp;
    }
}

function PRDex() {
    echo 'show';
}

function gl_file($file_name = null) {
    if ($file_name == null) {
        if ($sftp = con_filehub()) {
            $dir = "/EU/PRD/ES/MULTISTRADA/Inbound";
            $files = $sftp->nlist($dir, true);
            $result = array();
            $prefix = 'GLJRNL_MSTRADA_ID_890';
            $files = preg_grep("/^$prefix.*/i", $files);
            foreach ($files as $file) {
                $dir_backup = "/EU/PRD/ES/MULTISTRADA/Archive/Inbound/";

                if ($sftp->put($dir_backup . '/' . $file, $sftp->get($dir . '/' . $file))) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    if (!$sftp->get($dir . '/' . $file, __DIR__ . '/public/msa_files/' . $file)) {
                        header('http-status:204');
                    } else {
                        header('http-status:200');
                        echo $file . ';';
                        $sftp->delete($dir . '/' . $file);
                    }
                } else {
                    header('http-status:204');
                }
            }
        }
        die;
    } else {
        $file_name = str_replace("%20", " ", $file_name);
        header('http-status:200');

        $file_path = __DIR__ . '/public/msa_files/' . $file_name;
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            header('http-status:404');
            echo "File not found.";
        }
    }
}


function gl_file_archive($file_name = null) {
    $fileinput = file_get_contents('php://input'); // Get content/body from request
    if (isset($_GET['filename'])) {
        $filename = $_GET['filename']; // Get file name from request
        $sftp = con_filehub(); // Call connection function sftp
        if ($sftp) {
            header('HTTP/1.1 200 OK'); // Show http status 200
            $dir = "/EU/PRD/ES/MULTISTRADA/Archive/"; // Set directory that will put file
            $sftp->put($dir . $filename, $fileinput); // Put file data on directory
            echo $dir . $filename; // Show file directory that have created
        }
        die;
    }
}

function gl_file_error($file_name = null) {
    $fileinput = file_get_contents('php://input'); // Get content/body from request
    if (isset($_GET['filename'])) {
        $filename = $_GET['filename']; // Get file name from request
        $sftp = con_filehub(); // Call connection function sftp
        if ($sftp) {
            header('HTTP/1.1 200 OK'); // Show http status 200
            $dir = "/EU/PRD/ES/MULTISTRADA/Error/"; // Set directory that will put file
            $sftp->put($dir . $filename, $fileinput); // Put file data on directory
            echo $dir . $filename; // Show file directory that have created
        }
        die;
    }
}
function gl_file_delete($file_name=null) {
    if($file_name != null) {
        if ($sftp = $this->con_filehub()) {
            $dir = "/EU/PRD/ES/MULTISTRADA/Inbound";
            $sftp->delete($dir.'/'.$file_name);
            header('http-status:200');
        }
    }
}
function read_data_inv() {
    $i = 0;
    foreach (glob('/var/www/html/fop/public/msa_files/vp_dmz/*') as $file) {
        $fileName = basename($file);
        $url = 'http://192.168.2.47/fop/public/msa_files/vp_dmz/' . $fileName;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $json = curl_exec($ch);

        $to_sftp = $json;

        if ($sftp = $this->con_filehub()) {
            $dir = "/EU/PRD/ES/MULTISTRADA/Outbound/";
            if ($sftp->put($dir . $fileName, $to_sftp)) {
                if (!unlink($file)) {
                    echo ("Error deleting $file <br>");
                } else {
                    echo ("Deleted $file <br>");
                }
            }
        }

        if ($i == 3) {
            die;
        }
        $i++;
    }
}
function get_data_sync_vp(){
    //  die;
    $to_sftp='';
    $this_date_time=date('YmdHis');
    $this_date=date('Y-m-d');
    $this_datex=date('d-m-Y');
    $naming='APXIMPT_MSTRADA_VP_ID_890_'.$this_date_time.'';
    // echo $naming;die;
    //$url = "http://10.255.238.70/vendor_portal/blackbox/get_data_inv";
    $url = "http://10.255.238.70/vendor_portal/blackbox/get_data_inv";



    $f = fopen('php://output', 'w');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "customer:customer");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // add by edu for https
    $output = curl_exec($ch);
    $output = json_decode(base64_decode($output), true);

    // die;
    // echo '<pre>';
    // print_r($output);
    //  die;
    if(count($output) < 1)die;
    $file_name='APXIMPT_MSTRADA_VP_ID_890_'.$this_date_time;
    $file_ext='APXIMPT_MSTRADA_VP_ID_890_'.$this_date_time.'.cfo';
    $header=array('"HEADER_FILE"','"VP"','"ACJ"','"VP INVOICES"','"INCREMENTAL"',"\"$this_datex\"","\"$file_name\"","\"$file_ext\"","\"$this_date_time\"","\"$file_name\"",'""','""','""','"TRANSACTION"','"EN"','"1.0"','"890"','""','""');
    // fputcsv($f, $header, ";");
    $block=array('"HEADER_BLOCK"','"APINVOICE"','"BEG_FLDS"','""','"END_FLDS"');
    //  fputcsv($f, $block, ";");
    // foreach ($output as $line) {
    //   fputcsv($f, $line, ";");
    // }

    $myfile = fopen("/var/www/html/fop/public/msa_files/$naming.cfo", "w") or die("Unable to open file!");
    $to_sftp .=implode(";",$header);
    $header=implode(";",$header);

    $header .= "\r\n";
    $to_sftp .= "\r\n";
    fwrite($myfile, $header);
    $to_sftp .=implode(";",$block);
    $block=implode(";",$block);

    $block .= "\r\n";
    $to_sftp .=$block;
    fwrite($myfile, $block);
    $row_file=1;
    $invoice_batch_name='890_VP_'.str_replace('-','',$this_date);
    $num_header=0;
    $no_reg=array();
    $skb='';
    foreach ($output as $line) {


        if(isset($line['header']['is_skb'])){
            $is_skb= $line['header']['is_skb'];
        }
        $wht_code='';
        if(isset($line['header']['wht_code'])){
            $wht_code= $line['header']['wht_code'];
        }
        $remark_posting='CONVERTED INVOICE';
        if(isset($line['header']['remark_posting'])){
            $remark_posting=$line['header']['remark_posting'];
        }
        $invoice_num=$line['header']['invoice_number'];
        $invoice_date=$line['header']['invoice_date'];
        $invoice_date=date('d-m-Y', strtotime($invoice_date));
        $po_number=$line['header']['ref_po_local'];
        $vendor_account=$line['header']['vendor_account'];
        $vendor_name=$line['header']['vendor_name'];
        $tax_invoice=$line['header']['tax_invoice'];
        //   $wht_code=$line['header']['wht_code'];

        $vendor_site_code=$line['header']['vendor_site_code'];
        $accounting_date=date('d-m-Y', strtotime($line['header']['accounting_date']));
        //$accounting_date='31-12-2021';
        $invoice_tax_amount=$line['header']['tax_amount'];
        $invoice_tax_amount=$line['header']['tax_amount'];
        $amount_dpp=$line['header']['amount'];

        $amount=round($line['header']['amount']+$invoice_tax_amount,2);



        $date_update=$date = date('Y-m-d', strtotime($line['header']['date_updated']));
        $currency='IDR';
        $EXCHANGE_RATE='""';
        $EXCHANGE_RATE_TYPE='""';
        $EXCHANGE_DATE='""';
        $TERMS_NAME='""';

        // $tax_invoice="\"$tax_invoice\"";
        // if($tax_invoice == ''){
        //   $tax_invoice='""';
        // }



        //$invoice_num='"${invoice_num}"';

        // $data_header=array('"APINVOICE"','"INVOICE_HEADER(2)"','"SDS-BROKERAGE(3)"','"(4)"',"\"$invoice_num\""."(5)",'"STANDARD(6)"',"\"$invoice_date\""."(7)",'"WHICH?"',"\"$po_number\""."(8)","\"$vendor_account\""."(9)","\"$vendor_name\""."(10)","\"$amount\""."12","\"$currency\""."13",'"14"','"15"','"16"','"17"','"18"','"19"','"20"','"21"','"22"','"23"','"24"','"25"','"26"','"27"','"28"','"29"','"30"','"31"','"32"','"33"','"34"','"35"','"36"','"37"','"38"','"39"','"40"','"41"','"42"','"43"','"44"','"45"','"46"','"47"','"48"','"49"','"50"','"51"','"52"','"52"','"53"','"54"','"55"','"56"','"57"','"58"','"59"','"MICH_EMEDICAL_INV(60)"','"61"','"62"','"63"','"64"','"65"','"66"','"MICH_IMPORTED_INVOICE(67)"','"68"','"69"','"70"','"71"','"72"','"20-10-2020(73)"','"74"','"75"','"76"','"77"','"78"','"79"','"80"','"81"','"82"','"83"','"84"','"85"','"86"','"87"','"88"','"89"','"90"','"100"','"101"','"102"','"103"','"104"','"105"','"106"','"107"','"108"','"109"','"110"','"111"','"112"','"113"','"114"','"115"','"116"','"117"','"118"','"118"','"110"','"451(111)"','"112"','"113"','"114"','"115"','"116"','"117"','"118"','"119"','"120"','"121"','"122"','"123"','"124"','"125"','"126"','"127"','"128"','"129"','"130"','"131"','"132"','"133"','"134"','"135"','"136"','"137"','"138"','"139"','"140"','"141"','"142"','"143"','"144"','"145"','"146"','"147"','"148"','"149"','"150"','"151"','"152"','"153"','"154"','"155"','"156"','"157"','"158"','"159"','"160"','"161"','"451"','"162"','"163"','"164"','"451(165)"','"4210000(166)"','"DDD(167)"','"00160A(168)"','"8010(169)"','"000(170)"','"171"','"172"','"173"','"174"','"175"','"176"');
        $data_header=array('"APINVOICE"','"INVOICE_HEADER"',"\"$invoice_batch_name\"",'""',"\"$invoice_num\"",'"STANDARD"',"\"$invoice_date\"","\"$po_number\"","\"$vendor_account\"","\"$vendor_name\"","\"$vendor_site_code\"","\"$amount\"","\"$currency\"",'""','"Spot"',"\"$invoice_date\"",'""',"\"$remark_posting\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""',$tax_invoice,'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"VP"','""','""','""','""','""','""','"MICH_IMPORTED_INVOICE"','""','""','""','""','""',"\"$accounting_date\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"');
        $data=implode(";", $data_header);
        $to_sftp .=implode(";", $data_header);
        $data .= "\r\n";
        $to_sftp .="\r\n";
        fwrite($myfile, $data);
        $x=1;
        $y=0;
        $amount_tax=0;
        $row_file=$row_file+1;
        foreach($line['header']['line'] as $detail){


            //before improve wht 4
            // if($is_skb == 'YES'){
            //   $wht_code='';
            // }else{
            //   if($detail['line_type'] != 'Goods'){
            //       $wht_code=$line['header']['wht_code'];
            //   }else{
            //       $wht_code='';
            //   }

            // }

            if($is_skb == 'YES'){
                $wht_code='';
            } else {
                if ($line['header']['accounting_date'] >= '2022-12-13' ) {
                    $wht_code = $detail['wht_code'];
                }else{

                    if($detail['line_type'] != 'Goods'){
                        $wht_code=$line['header']['wht_code'];
                    }else{
                        $wht_code='';
                    }

                }


                if(substr($line['header']['wht_code'],0,9) == '890-WHT 4' || substr($line['header']['wht_code'],0,8) == '890-WHT4'){
                    $wht_code=$line['header']['wht_code'];
                }
            }
            // echo '<pre>';
            // print_r($detail);die;
            if($detail['po_number'] == ''){
                continue;
            }
            if($detail['rir_qty'] <= 0 ){
                echo $invoice_num;die;
            }
            $line_id=$x;
            $amount_line=round($detail['price'] * $detail['rir_qty'],2);
            $rir_number=$detail['rir_number'];
            $rir_date=$detail['rir_date'];
            $rir_qty=floatval($detail['rir_qty']);
            $item_name=str_replace(';','.', $detail['item_name']);
            $itemid=$detail['itemid'];
            $unit=$detail['unit'];
            $do_number=$detail['do_number'];
            $do_date=$detail['do_date'];
            $do_qty=round(floatval($detail['do_qty']));
            $price=(floatval($detail['price']));
            $price_qty=$price * $rir_qty;
            $tax_item=$detail['tax_item'];
            $line_po=$detail['line_po'];
            $tax_clasification_code=$detail['taxgroup'];
            $shipmentlinenumber=$detail['polinelocation'];
            $chargeaccount=$detail['chargeaccount'];
            $chargeaccount=explode('-', $chargeaccount);
            //  print_r($chargeaccount);die;
            $localaccount=$chargeaccount[1];
            $site=$chargeaccount[2];
            $section=$chargeaccount[3];
            $structure=$chargeaccount[4];
            $intercompany=$chargeaccount[5];

            if(substr($tax_invoice, 0,2) == '07'){
                $tax_clasification_code='"ID VAT D_NC_VE"';
                $tax_clasification_code='ID NO TAX';
            }
            //echo $tax_clasification_code.' - '.substr($tax_invoice, 0,2).' - '.$tax_invoice;die;

            //$shipmentlinenumber=1;
            // if($invoice_tax_amount < 1){
            //   $tax_clasification_code='ID NO TAX';
            // }
            $tax_amount=$amount_tax + (($amount_line/100) * $tax_item );
            $data_line=array('"APINVOICE"','"INVOICE_LINE"'."(2)","\"$invoice_num\""."(3)","\"$x\""."(4)",'"ITEM"'."(5)",'""',"\"$amount_line\""."(7)",'"(8)"',"\"$tax_invoice\""."(9)","\"$tax_amount\""."(10)",'""',"\"$tax_item\""."(12)",'"(13)"',"\"$po_number\""."(14)","\"$line_po\""."(15)","\"$do_number\""."(16)",'"(17)"',"\"$unit\""."(18)","\"$item_name\""."(19)","\"$rir_qty\""."(20)",'"(21)"',"\"$price_qty\""."(22)",'"(23)"','"(24)"','"(25)"',"\"$vendor_account\""."(26)","\"$date_update\""."(27)","\"$vendor_account\""."(28)","\"$date_update\""."(29)"
            ,'"30"','"31"','"32"','"33"','"34"','"35"','"36"','"37"','"38"','"39"','"40"','"41"','"42"','"43"','"44"','"45"','"46"','"47"','"48"','"49"','"50"','"51"','"52"','"53"','"54"','"55"','"56"','"57"','"58"','"59"','"60"','"61"','"62"','"63"','"64"','"65"','"66"','"67"','"68"','"69"','"70"','"71"','"72"','"73"','"74"','"75"','"76"','"77"','"78"','"79"','"80"','"81"','"82"','"83"','"84"','"85"','"86"','"87"','"88"','"89"','"90"','"91"','"92"','"93"','"94"','"95"','"96"','"97"','"98"','"99"','"100"','"101"','"102"','"104"','"105"','"106"'
            ,'"107"','"108"','"109"','"110"','"111"','"112"','"113"','"114"','"111"','"112"','"113"','"114"','"115"','"116"','"117"','"118"','"119"','"120"','"121"','"122"','"123"','"124"','"125"','"126"','"127"','"128"','"129"','"130"','"131"','"132"','"133"','"134"','"135"','"136"','"137"','"138"','"139"','"140"','"141"','"142"','"143"','"144"','"145"','"146"','"147"','"TH NO TA"','"(148)"','"149"','"150"','"151"','"152"','"153"','"154"','"155"','"156"','"157"','"158"','"159"','"160"','"161"','"163"','"451(164)"','"165"','"166"','"167"','"168"','"169"','"451(170)"','"4210903(171)"','"DDD(172)"','"00160A(173)"','"8010(174)"','"000(175)"');
            $data_line=array('"APINVOICE"','"INVOICE_LINE"',"\"$invoice_num\"","\"$x\"",'"ITEM"','""',"\"$amount_line\"",'""','""'."",'""','""','""','""',"\"$po_number\"","\"$line_po\"","\"$shipmentlinenumber\"",'""',"\"$unit\"","\"$item_name\"","\"$rir_qty\"",'""',"\"$price\"",'""','""',"\"$wht_code\"",'""','""','""','""'
            ,'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""',"\"$tax_clasification_code\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""');
            $data=implode(";", $data_line);
            $to_sftp .=implode(";", $data_line);
            $data .= "\r\n";
            $to_sftp .="\r\n";
            fwrite($myfile, $data);




            $x++;
            $y++;
            $row_file=$row_file+1;
        }
        $is_wht_borned=false;
        if (strpos($wht_code, '-N-') !== false) {
            // haystack contains needle
            $is_wht_borned=true;
        }

        if(isset($wht_code) && $wht_code != '' && $is_wht_borned){
            $invoice_num=$invoice_num.'_WHT';
            $amount_wht=round(($amount_dpp* $line['header']['wht_value'])/100,2);
            $tax_clasification_code_no_tax='ID NO TAX';
            $whtaccount='6310000';
            $site="MUA";
            $section='98260A';
            $structure="8010";
            $intercompany="000";

            $remark_wht='WHT '.$po_number;

            $data_header=array('"APINVOICE"','"INVOICE_HEADER"',"\"$invoice_batch_name\"",'""',"\"$invoice_num\"",'"STANDARD"',"\"$invoice_date\"",'""',"\"$vendor_account\"","\"$vendor_name\"","\"$vendor_site_code\"","\"$amount_wht\"","\"$currency\"",'""','"Spot"',"\"$invoice_date\"",'""',"\"$remark_wht\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"VP"','""','""','""','""','""','""','"MICH_IMPORTED_INVOICE"','""','""','""','""','""',"\"$accounting_date\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"');
            $data=implode(";", $data_header);
            $to_sftp .=implode(";", $data_header);
            $data .= "\r\n";
            $to_sftp .="\r\n";
            fwrite($myfile, $data);

            $data_line=array('"APINVOICE"','"INVOICE_LINE"',"\"$invoice_num\"","\"$x\"",'"ITEM"','""',"\"$amount_wht\"",'""',"\"$remark_wht\""."",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""'
            ,'""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""','""',"\"$tax_clasification_code_no_tax\"",'""','""','""','""','""','""','""','""','""','""','""','""','""','""','"890"','""','""','""','""','""','"890"',"\"$whtaccount\"","\"$site\"","\"$section\"","\"$structure\"","\"$intercompany\"",'""','""','""','""','""');
            $data=implode(";", $data_line);
            $to_sftp .=implode(";", $data_line);
            $data .= "\r\n";
            $to_sftp .="\r\n";
            fwrite($myfile, $data);
            $row_file=$row_file+2;
            $num_header++;

        }



        $no_reg[]=$line['header']['no_reg'];
        $num_header++;
    }


    $data_footer=array('"FOOTER_BLOCK"',"APINVOICE","\"$num_header\"");
    $to_sftp .=implode(";", $data_footer);
    $data=implode(";", $data_footer);
    $data .= "\r\n";
    $to_sftp .="\r\n";
    fwrite($myfile, $data);
    $row_file=$row_file+2;
    $data_footer_end=array('"FOOTER_FILE"',"\"$row_file\"");
    $to_sftp .=implode(";", $data_footer_end);
    $data=implode(";", $data_footer_end);
    $data .= "\r\n";
    $to_sftp .= "\r\n";
    fwrite($myfile, $data);

    fclose($myfile);

    if ($sftp=$this->con_filehub()) {
        $dir = "/EU/PRD/ES/MULTISTRADA/Outbound/";
        echo $dir. $file_ext;
        if($sftp->put($dir. $file_ext,$to_sftp)){
            $this->push_status_inv_to_portal($no_reg,"$naming.cfo");
        }
    }

    curl_close($ch);
    return $output;
}

function fetch_remote_file_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $content = curl_exec($ch);
    curl_close($ch);
    return $content;
}

function upload_to_sftp($sftp, $remote_dir, $file_name, $content) {
    if ($sftp->put($remote_dir . $file_name, $content)) {
        echo ("Uploaded $file_name <br>");
    } else {
        echo ("Failed to upload $file_name <br>");
    }
}


function push_status_inv_to_portal($data,$filename) {
    // print_r($data);
    // echo $filename;

    try{
        $url = "http://10.255.238.70/vendor_portal/blackbox/update_status_inv_fop"; //change to https edu
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://10.255.238.70/vendor_portal',
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        $response = $client->request('POST', 'http://10.255.238.70/vendor_portal/blackbox/update_status_inv_fop', [

            'form_params' =>

                [
                    'no_reg'     => $data,
                    'fop_file_name'     => $filename,
                    'contents' => '12223322'
                ]
            ,
        ]);

        // echo '<pre';
        //    print_r($response->getBody());
        $body = $response->getBody();
        echo $body;
    } catch (ClientException $e) {
        // echo $e->getRequest();
        echo $e->getResponse();
    }


}

function sync_vpfiles(){

    //got filename that no sync yet
    $url = "http://10.255.238.70/vendor_portal/blackbox/invoice_files";

    $f = fopen('php://output', 'w');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "customer:customer");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // add by edu for https
    $output = curl_exec($ch);

    $filename= $output;

    //got content of filename
    $content = "http://10.255.238.70/vendor_portal/blackbox/invoice_file/$filename";
    //  echo $url;
    $content= file_get_contents($content);
    $myfile = fopen("/var/www/html/fop/msa_files/msa_dmzx/$filename", "w") or die("Unable to open file!");
    fwrite($myfile,$content);
    fclose($myfile);

    $content = "http://10.255.238.70/vendor_portal/blackbox/invoice_file_update/$filename";
    //  echo $url;
    $update= file_get_contents($content);
    echo $update;



}


function push_status_inv_to_portalx() {
    // print_r($data);
    //die;
    $data[0]='00209/MSA-WEBREG/22/09';
    $filename='APXIMPT_MSTRADA_VP_ID_890_20220919080408.cfo';
    //  die;
    try{
        $url = "http://10.255.238.70/vendor_portal/blackbox/update_status_inv_fop"; //change to https edu
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://10.255.238.70/vendor_portal',
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        $response = $client->request('POST', 'http://10.255.238.70/vendor_portal/blackbox/update_status_inv_fop', [

            'form_params' =>

                [
                    'no_reg'     => $data,
                    'fop_file_name'     => $filename,
                    'contents' => '12223322'
                ]
            ,
        ]);
        //  die;
        // echo '<pre';
        //  print_r($response->getBody());
        $body = $response->getBody();
        echo $body;
    } catch (ClientException $e) {
        // echo $e->getRequest();
        echo $e->getResponse();
    }


}

function download_file($file){
    //   $this->load->helper('download');
    $data = file_get_contents("msa_files/".$file);
    return $this->response->download($file,$data);
    // force_download($file, $data);
}
function upload_sftp(){

    foreach(glob('/var/www/html/fop/public/msa_files/vp_dmz/*') as $file) {

        echo $file;

    }


    if ($sftp=$this->con_filehub()) {
        // $dir = "/EU/PRD/ES/MULTISTRADA/Outbound/";
        // echo $dir. $file_ext;
        // if($sftp->put($dir. $file_ext,$to_sftp)){
        //   $this->push_status_inv_to_portal($no_reg,"$naming.cfo");
        // }
    }
}


// Panggil fungsi sesuai dengan parameter URL 'action'
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'fopdatapower') {
        fopdatapower();
    } elseif ($action == 'ionic_test') {
        ionic_test();
    } elseif ($action == 'wa_otp') {
        wa_otp();
    }elseif($action == 'con_filehub'){
        con_filehub();
    }elseif ($action == 'get_wa_otp_token') {
        wa_otp();
    }   elseif ($action == 'PRDex') {
        PRDex();
    } elseif ($action == 'gl_file') {
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        gl_file($file_name);
    }elseif($action == 'gl_file_archive'){
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        gl_file_archive($file_name);
    }elseif($action == 'gl_file_error'){
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        gl_file_error($file_name);
    }elseif($action == 'upload_sftp'){
        upload_sftp();
    }elseif($action == 'download_file'){
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        download_file($file_name);
    }elseif($action == 'push_status_inv_to_portalx'){
        push_status_inv_to_portalx();
    }elseif($action == 'sync_vpfiles'){
        sync_vpfiles();
    }elseif($action == 'push_status_inv_to_portal'){
        $data=$_GET['data'];
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        push_status_inv_to_portal($data,$file_name);
    }elseif($action == 'upload_to_sftp'){
        $sftp = $_GET['sftp'];
        $remote_dir = $_GET['remote_dir'];
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        $content = $_GET['content'];
        upload_to_sftp($sftp,$remote_dir,$file_name,$content);
    }elseif($action == 'fetch_remote_file_content'){
        $url = $_GET['url'];
        fetch_remote_file_content($url);
    }elseif($action == 'get_data_sync_vp'){
        get_data_sync_vp();
    }elseif($action == 'read_data_inv'){
        read_data_inv();
    }elseif($action == 'gl_file_delete'){
        $file_name = isset($_GET['file_name']) ? $_GET['file_name'] : null;
        gl_file_delete($file_name);
    }
}

// Fungsi placeholder untuk mendapatkan token OTP WhatsApp
function get_wa_otp_token() {
    echo 'tes';
}
?>
