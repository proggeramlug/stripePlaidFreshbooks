<?php
include ("vendor/autoload.php");
include ("config.php");

$invoiceId = intval($_GET['invoiceId']);
$clientId = intval($_GET['clientId']);

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
		die("Invalid link!");
	}
	$amount = $res['invoice']['amount_outstanding'];
}
else
{
	die("error!");
}

if ($amount == 0)
{
	die("all balances paid!");
}

$title = "Pay Invoice " . $res['invoice']['number'];

include ("header.php");

?>
<h1>Invoice <?php echo $res['invoice']['number'];?> has a total of <?php echo $amount;?> outstanding</h1>
<button id='linkButton'>Pay with your bank account (ACH)</button>

<script src="https://cdn.plaid.com/link/stable/link-initialize.js"></script>
<script>
var linkHandler = Plaid.create({
  env: '<?php if ($config['debug']) echo 'tartan'; else echo 'production';?>',
  clientName: "FB:<?php echo $res['invoice']['client_id'];?>",
  key: '<?php echo $config['plaid']['publicKey'];?>',
  product: 'auth',
  selectAccount: true,
  onSuccess: function(public_token, metadata) {
    // Send the public_token and account ID to your app server.
    console.log('public_token: ' + public_token);
    console.log('account ID: ' + metadata.account_id);
    $.post('api.php?action=plaid',{invoice_id:<?php echo $invoiceId; ?>,client_id:<?php echo $clientId;?>,account_id:metadata.account_id, token:public_token},function(e){
		console.log(e);
		if (e.success)
		{
			document.location.href='paid.php?amount=<?php echo $amount;?>'
		}
		else {
			alert('Error: '+e.error);
		}

    },'json');
  },
});

// Trigger the Link UI
document.getElementById('linkButton').onclick = function() {
  linkHandler.open();
};
</script>
<?php
include ("footer.php");
?>
