<?php
session_start();
session_destroy(); // Rips off the wristband
header("Location: login.php");
exit();
?>