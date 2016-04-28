<?php
$config = array(
		"plaid" => array(
				"clientId" => "",
				"secret" => "",
				"publicKey" => "" 
		),
		"stripe" => array(
				"key" => "" 
		),
		"freshbooks" => array(
				"apiUrl" => "",
				"token" => "" 
		),
		"debug" => false 
);

if ($config['debug'] == false)
{
	$baseurl = "https://api.plaid.com/";
}
else
{
	$baseurl = "https://tartan.plaid.com/";
}

$config['baseurl'] = $baseurl;
