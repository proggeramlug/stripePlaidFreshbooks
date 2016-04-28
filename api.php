<?php
include ("vendor/autoload.php");
include ("config.php");

$invoiceId = intval($_POST['invoice_id']);
$clientId = intval($_POST['client_id']);

$fb = new Freshbooks\FreshBooksApi($config['freshbooks']['apiUrl'], $config['freshbooks']['token']);
$fb->setMethod('invoice.get');

$fb->post(array(
		"invoice_id" => $invoiceId 
));

$fb->request();

$amount = 0;

if ($fb->success())
{
	// echo 'successful! the full response is in an array below';
	$res = $fb->getResponse();
	
	if ($res['invoice']['client_id'] != $clientId)
	{
		die(json_encode(array(
				"success" => false,
				"error" => "Invalid link" 
		)));
	}
	
	$amount = $res['invoice']['amount_outstanding'];
	// die(print_r($fb->getResponse(), true));
}
else
{
	die(json_encode(array(
			"success" => false,
			"error" => "Invalid link" 
	)));
	// echo $fb->getError();
	// var_dump($fb->getResponse());
}

if ($amount == 0)
{
	die(json_encode(array(
			"success" => false,
			"error" => "All amounts already paid!" 
	)));
}

function plaidCall($f, $post, $type = "post")
{
	global $config;
	// add our credentials
	
	if (! is_array($post))
	{
		die("invalid input");
	}
	
	$post['client_id'] = $config['plaid']['clientId'];
	$post['secret'] = $config['plaid']['secret'];
	
	$fieldsString = "";
	
	// generate the url
	$url = $config['baseurl'] . $f;
	
	// generate the string
	foreach ($post as $key => $value)
	{
		$fieldsString .= $key . '=' . $value . '&';
	}
	
	rtrim($fieldsString, '&');
	
	// open connection
	$ch = curl_init();
	
	// if it is a post call, set the url, number of POST vars, POST data
	if ($type == "post")
	{
		curl_setopt($ch, CURLOPT_POST, count($post));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
	}
	// for get just add it to the url
	else if ($type == "get")
	{
		$url = $url . "?" . $fieldsString;
	}
	// die($url);
	// init curl
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// execute post
	$result = curl_exec($ch);
	$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	// close connection
	curl_close($ch);
	// get the results
	$r = json_decode($result, true);
	// add the response code
	$r['http_response'] = $httpStatus;
	// return it
	return $r;
}

if ($_GET['action'] == "plaid")
{
	$req = $_POST;
	if (isset($_GET['get']) && $_GET['get'] == 1)
		$req = $_GET;
	
	$res = plaidCall("exchange_token", array(
			"public_token" => $req['token'],
			"account_id" => $req['account_id'] 
	));
	
	if (! isset($res['stripe_bank_account_token']))
	{
		die(json_encode(array(
				"success" => false,
				"error" => print_r($res, true) 
		)));
	}
	
	\Stripe\Stripe::setApiKey($config['stripe']['key']);
	
	$res2 = \Stripe\Charge::create(array(
			"amount" => $amount * 100,
			"currency" => "usd",
			"source" => $res['stripe_bank_account_token'] 
	));
	
	if ($res2['status'] == "pending")
	{
		$fb->setMethod('payment.create');
		
		$fb->post(array(
				"payment" => array(
						"invoice_id" => $invoiceId,
						"amount" => $amount,
						"type" => 'ACH',
						'notes' => 'Pending:' . $res2['id'] 
				) 
		));
		
		$fb->request();
		
		if ($fb->success())
		{
			die(json_encode(array(
					"success" => true,
					"msg" => "Successsfully paid " . $amount . " USD!" 
			)));
		}
		else
		{
			die(json_encode(array(
					"success" => false,
					"error" => "$fb->getError()" 
			)));
		}
	}
}
