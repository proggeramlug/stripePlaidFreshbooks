<?php
$title = "Successfully paid!";
include ("header.php");
?>
<h1>Thank you!</h1>
<p>You have successfully paid USD<?php echo $_GET['amount'];?>!</p>
<?php
include ("footer.php");
?>