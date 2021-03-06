<?php
header('Content-type: application/json');
include "./phpxmlrpc/lib/xmlrpc.inc";
require_once(__ROOT__ . '/config.php');

try {
	$fromDate = new DateTime($_POST["fromDate"]);
	$toDate = new DateTime($_POST["toDate"] . " 23:59:59");
} catch (Exception $e) {
	echo json_encode(array(
		"error" => array(
			"code" => 99, 
			"message" => $e->getMessage()
		)
	));
    	return(0);
}

$fromDate->sub(new DateInterval('P1D'));
$period = new DatePeriod(
		$fromDate,
		new DateInterval('P1D'),
		$toDate
		);

$reservas = array();

$rcounter = 0;

$xmlrpc_client = new xmlrpc_client('/zxrvs/', 'wubook.net', 443, 'https');
//$xmlrpc_client->setDebug(1);
$xmlrpc_client->setSSLVerifyPeer(0);

$wbToken = getToken();
foreach($period as $date){
    	$wbDate = $date->format("d/m/Y");
	$params = array(
        	new xmlrpcval($wbToken, 'string'),
        	new xmlrpcval($wbLcode, 'string'),
        	new xmlrpcval($wbDate, 'string')
	);
	$xmlrpc_message = new xmlrpcmsg('fetch_reservations_day', $params);
	$xmlrpc_response = $xmlrpc_client->send($xmlrpc_message);
	if (!checkResponse($xmlrpc_response)) return 0;
	$lista = php_xmlrpc_decode($xmlrpc_response->value());
	//array_walk_recursive($lista,'utf8_encode');
	if ($rcounter++ > 20) {
		releaseToken($wbToken);
		$wbToken = getToken();
		$rcounter = 0;
	}
	foreach ($lista as $reserva) {
		$dto = $reserva["dto"] + 57600;
		if ($dto < $toDate->getTimestamp())
			$reservas[] = $reserva;
	}
}

$reservas_sd = array_unique($reservas, SORT_REGULAR);

$aud = array();

foreach ($reservas_sd as $reserva) {
	$rcode = $reserva["rcode"];
	$params = array(
        	new xmlrpcval($wbToken, 'string'),
        	new xmlrpcval($wbLcode, 'string'),
        	new xmlrpcval($rcode, 'string')
	);
	$xmlrpc_message = new xmlrpcmsg('fetch_invoices', $params);
	$xmlrpc_response = $xmlrpc_client->send($xmlrpc_message);
	if (!checkResponse($xmlrpc_response)) return 0;
	$lista = php_xmlrpc_decode($xmlrpc_response->value());
	if ($rcounter++ > 20) {
		releaseToken($wbToken);
		$wbToken = getToken();
		$rcounter = 0;
	}
	$ccode = (isset($lista[0]["main_customer_id"]))?$lista[0]["main_customer_id"]:"";
	$params = array(
                	new xmlrpcval($wbToken, 'string'),
                	new xmlrpcval($wbLcode, 'string'),
                	new xmlrpcval(array('ccode' => new xmlrpcval($ccode, 'string')), 'struct')
        );
	$xmlrpc_message = new xmlrpcmsg('fetch_customer', $params);
        $xmlrpc_response = $xmlrpc_client->send($xmlrpc_message);
        if (!checkResponse($xmlrpc_response)) return 0;
        $customer = php_xmlrpc_decode($xmlrpc_response->value());
	$birthdate = $customer[0]["birthday"] . "/" . $customer[0]["birthmonth"] . "/" . $customer[0]["birthyear"];
	foreach ($lista as $invoice) {
		$vat_8 = isset($invoice["paidvats"]["8,0"])?$invoice["paidvats"]["8,0"]:"0";
		$vat_16 = isset($invoice["paidvats"]["16,0"])?$invoice["paidvats"]["16,0"]:"0";
		$pinfo = parserpi($invoice["payinfo"]);
		$th = "";
		$flname = explode('/', $invoice["main_customer"], 2);
		foreach ($reserva["roomspricing"] as $room) {
			if ($th != "") $th .= " + ";
			$th .= $room["type"];
		}		
		
		$aud[] = array_map('utf8_encode', array(
			"rcode" => $rcode,
			"sn" => $invoice["sn"],
			"vat_8" => $vat_8,
			"vat_16" => $vat_16,
			"camount" => $invoice["camount"],
			"dpayamount" => $invoice["dpayamount"],
			"gen_doc_type" => $customer[0]["gen_doc_type"],
			"get_doc_number" => $customer[0]["gen_doc_number"],
			"birthdate" => $birthdate,
			"first_name" => $flname[1],
			"last_name" => $flname[0],
			"cash" => (isset($pinfo["Cash"]))?$pinfo["Cash"]:"0",
			"cc" => (isset($pinfo["Credit/Debit"]))?$pinfo["Credit/Debit"]:"0",
			"other" => (isset($pinfo["Other"]))?$pinfo["Other"]:0,
			"adults" => $reserva["adults"],
			"th" => $th,
			"dfrom" => date('d-m-Y', $reserva["dfrom"] + 57600),
			"dto" => date('d-m-Y', $reserva["dto"] + 57600)
		));
	}
}

echo json_encode($aud);

releaseToken($wbToken);


/*
Se necesitaría: id de la reserva zak, número de la factura, valor de IVA, valor total de l reserva, valor total pagado, datos del cliente ( tipo de documento, # documento, fecha de cumpleaños, nombre completo, nacionalidad), tipo de pago y cuánto pagó por cada tipo de pago, número de clientes , tipo de habitación
*/





function getToken() {
	global $xmlrpc_client, $wbUser, $wbPassword, $wbPkey;
	$token_params = array(
        	new xmlrpcval($wbUser, 'string'),
       		new xmlrpcval($wbPassword, 'string'),
        	new xmlrpcval($wbPkey, 'string')
	);
	$xmlrpc_message = new xmlrpcmsg('acquire_token', $token_params);
	$xmlrpc_response = $xmlrpc_client->send($xmlrpc_message)->value();
	return php_xmlrpc_decode($xmlrpc_response)[1];
}

function releaseToken($token) {
	global $xmlrpc_client;
	$params = array(
                new xmlrpcval($token, 'string')
        );
	$xmlrpc_message = new xmlrpcmsg('release_token', $params);
	$xmlrpc_response = $xmlrpc_client->send($xmlrpc_message)->value();
}

function parserpi($string) {
	$total = array();
	$payments = explode(",", $string);
	foreach ($payments as $payment) {
		$formated = preg_replace("/^\ ?(.{10}):\ /", "", $payment);
		$split = explode(" ", preg_replace("/\ \((.*)\)/", "", $formated));
		$type = $split[0];
		$amount = $split[count($split) - 1];
		if (isset($total[$type])) {
			$total[$type] += $amount;
		} else {
			$total[$type] = $amount;
		}
	}
	return $total;
}
function checkResponse($response) {
	if ($response->faultCode()) {
		echo json_encode(array("error" => array(
			"code" => htmlentities($response->faultCode()), 
			"message" => htmlentities($response->faultString())
		)));
    		return (false);
	} else {
		return (true);
	} 
}

?>
