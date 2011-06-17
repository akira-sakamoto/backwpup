<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
// Remove header and footer form logfile
function backwpup_read_logfile($logfile) {
	if (is_file($logfile) and strtolower(substr($logfile,-3))=='.gz')
		$logfiledata=gzfile($logfile);
	elseif (is_file($logfile.'.gz'))
		$logfiledata=gzfile($logfile.'.gz');
	elseif (is_file($logfile))
		$logfiledata=file($logfile);	
	else
		return false;
	$lines=array();
	$start=false;
	foreach ($logfiledata as $line){
		$line=trim($line);
		if (strripos($line,'<body')!== false) {  // jop over header
			$start=true;
			continue;
		}
		if ($line!='</body>' and $line!='</html>' and $start) //no Footer
			$lines[]=$line;
	}
	return $lines;
}

//ajax show info div for jobs
function backwpup_working_update() {
	check_ajax_referer('backwpupworking_ajax_nonce');
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		die('-1');
	if (is_file(trim($_POST['logfile']).'.gz'))
		$_POST['logfile']=trim($_POST['logfile']).'.gz';
	$log='';
	if (is_file(trim($_POST['logfile']))) {
		if (is_file(backwpup_get_working_dir().'.running')) {
			if ($infile=backwpup_get_working_file()) {
				$warnings=$infile['WARNING'];
				$errors=$infile['ERROR'];
				$stepspersent=$infile['STEPSPERSENT'];
				$steppersent=$infile['STEPPERSENT'];
			}
		} else {
			$logheader=backwpup_read_logheader(trim($_POST['logfile']));
			$warnings=$logheader['warnings'];
			$errors=$logheader['errors'];
			$stepspersent=100;
			$steppersent=100;
			$log.='<span id="stopworking"></span>';		
		}
		$logfilarray=backwpup_read_logfile(trim($_POST['logfile']));
		for ($i=0;$i<count($logfilarray);$i++)
		//for ($i=$_POST['logpos'];$i<count($logfilarray);$i++)
				$log.=$logfilarray[$i];
		echo json_encode(array('logpos'=>count($logfilarray),'LOG'=>$log,'WARNING'=>$warnings,'ERROR'=>$errors,'STEPSPERSENT'=>$stepspersent,'STEPPERSENT'=>$steppersent));
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_working_update', 'backwpup_working_update');	
?>