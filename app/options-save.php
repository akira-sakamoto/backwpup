<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

if ($_REQUEST['action2']!='-1' and !empty($_REQUEST['doaction2'])) 
	$_REQUEST['action']=$_REQUEST['action2'];

switch($_REQUEST['action']) {
case 'delete': //Delete Job
	$jobs=get_option('backwpup_jobs'); 
	if (is_Array($_POST['jobs'])) {
		check_admin_referer('actions-jobs');
		foreach ($_POST['jobs'] as $jobid) {
			unset($jobs[$jobid]);
			if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
				wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
			}
		}
		$backwpup_message=str_replace('%1',implode(", ",$_POST['jobs']),__('Jobs %1 deleted', 'backwpup'));
	} else {
		$jobid = (int) $_GET['jobid'];
		check_admin_referer('delete-job_' . $jobid);			
		unset($jobs[$jobid]);
		if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
			wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
		}
		$backwpup_message=str_replace('%1',$jobid,__('Job %1 deleted', 'backwpup'));
	}
	update_option('backwpup_jobs',$jobs);
	$_REQUEST['action']='';
	break;
case 'delete-logs': //Delete Log
	$cfg=get_option('backwpup'); //Load Settings
	if (is_Array($_POST['logfiles'])) {
		check_admin_referer('actions-logs');
		$num=0;
		foreach ($_POST['logfiles'] as $logfile) {
			if (is_file($cfg['dirlogs'].'/'.$logfile)) 
				unlink($cfg['dirlogs'].'/'.$logfile);
			$num++;
		}
		$backwpup_message=$num.' '.__('Logs deleted', 'backwpup');
	} else {
		check_admin_referer('delete-log_' . $_GET['logfile']);
		if (is_file($cfg['dirlogs'].'/'.$_GET['logfile'])) 
			unlink($cfg['dirlogs'].'/'.$_GET['logfile']);
		$backwpup_message=__('Log deleted', 'backwpup');
	}
	$_REQUEST['action']='logs';
	break;
case 'savecfg': //Save config form Setings page
	check_admin_referer('backwpup-cfg');
	$cfg=get_option('backwpup'); //Load Settings
	$cfg['mailsndemail']=sanitize_email($_POST['mailsndemail']);
	$cfg['mailsndname']=$_POST['mailsndname'];
	$cfg['mailmethod']=$_POST['mailmethod'];
	$cfg['mailsendmail']=untrailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes($_POST['mailsendmail']))));
	$cfg['mailsecure']=$_POST['mailsecure'];
	$cfg['mailhost']=$_POST['mailhost'];
	$cfg['mailuser']=$_POST['mailuser'];
	$cfg['mailpass']=base64_encode($_POST['mailpass']);
	$cfg['memorylimit']=$_POST['memorylimit'];
	$cfg['disablewpcron']=$_POST['disablewpcron']==1 ? true : false;
	$cfg['maxlogs']=abs((int)$_POST['maxlogs']);
	$cfg['dirlogs']=trailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes(trim($_POST['dirlogs'])))));
	$cfg['dirtemp']=trailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes(trim($_POST['dirtemp'])))));
	//set def. folders
	if (empty($cfg['dirtemp']) or $cfg['dirtemp']=='/')
		$cfg['dirtemp']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'uploads/';
	if (empty($cfg['dirlogs']) or $cfg['dirlogs']=='/') {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}

	if (update_option('backwpup',$cfg))
		$backwpup_message=__('Settings saved', 'backwpup');
	$_REQUEST['action']='settings';
	break;
case 'copy': //Copy Job
	$jobid = (int) $_GET['jobid'];
	check_admin_referer('copy-job_'.$jobid);
	$jobs=get_option('backwpup_jobs');
	//generate new ID
	foreach ($jobs as $jobkey => $jobvalue) {
		if ($jobkey>$heighestid) $heighestid=$jobkey;
	}
	$newjobid=$heighestid+1;
	$jobs[$newjobid]=$jobs[$jobid];
	$jobs[$newjobid]['name']=__('Copy of','backwpup').' '.$jobs[$newjobid]['name'];
	$jobs[$newjobid]['activated']=false;
	update_option('backwpup_jobs',$jobs);
	$backwpup_message=__('Job copied', 'backwpup');
	$_REQUEST['action']='';
	break;
case 'download': //Download Backup
	check_admin_referer('download-backup_'.basename($_GET['file']));
	if (is_file($_GET['file'])) {
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($_GET['file']));
		@readfile($_GET['file']);
	} else {
		header('HTTP/1.0 404 Not Found');
		die(__('File does not exist.', 'backwpup'));
	}	
	$_REQUEST['action']='logs';
	break;
case 'saveeditjob': //Save Job settings
	$jobid = (int) $_POST['jobid'];
	check_admin_referer('edit-job');
	$jobs=get_option('backwpup_jobs'); //Load Settings
		
	if (empty($jobid)) { //generate a new id for new job
		if (is_array($jobs)) { 
			foreach ($jobs as $jobkey => $jobvalue) {
				if ($jobkey>$heighestid) $heighestid=$jobkey;
			}
			$jobid=$heighestid+1;
		} else {
			$jobid=1;
		}
	}
	
	if ($jobs[$jobid]['type']!=$_POST['type']) // set type to save
		$savetype=explode('+',$jobs[$jobid]['type']);
	else
		$savetype=$_POST['type'];
	

	$jobs[$jobid]['type']= implode('+',(array)$_POST['type']);
	$jobs[$jobid]['name']= esc_html($_POST['name']);
	$jobs[$jobid]['activated']= $_POST['activated']==1 ? true : false;
	$jobs[$jobid]['scheduletime']=mktime($_POST['schedulehour'],$_POST['scheduleminute'],0,$_POST['schedulemonth'],$_POST['scheduleday'],$_POST['scheduleyear']);
	$jobs[$jobid]['scheduleintervaltype']=(int)$_POST['scheduleintervaltype'];
	$jobs[$jobid]['scheduleintervalteimes']=(int)$_POST['scheduleintervalteimes'];
	$jobs[$jobid]['mailaddresslog']=sanitize_email($_POST['mailaddresslog']);
	$jobs[$jobid]['mailerroronly']= $_POST['mailerroronly']==1 ? true : false;	
	$jobs[$jobid]['dbexclude']=$_POST['dbexclude'];
	$jobs[$jobid]['dbshortinsert']=$_POST['dbshortinsert']==1 ? true : false;
	$jobs[$jobid]['maintenance']= $_POST['maintenance']==1 ? true : false;	
	$jobs[$jobid]['fileexclude']=stripslashes($_POST['fileexclude']);
	$jobs[$jobid]['dirinclude']=stripslashes($_POST['dirinclude']);
	$jobs[$jobid]['backuproot']= $_POST['backuproot']==1 ? true : false;
	$jobs[$jobid]['backupcontent']= $_POST['backupcontent']==1 ? true : false;
	$jobs[$jobid]['backupplugins']= $_POST['backupplugins']==1 ? true : false;
	$jobs[$jobid]['fileformart']=$_POST['fileformart'];
	$jobs[$jobid]['mailefilesize']=(float)$_POST['mailefilesize'];
	$jobs[$jobid]['backupdir']=stripslashes($_POST['backupdir']);
	$jobs[$jobid]['maxbackups']=(int)$_POST['maxbackups'];
	$jobs[$jobid]['ftphost']=$_POST['ftphost'];
	$jobs[$jobid]['ftpuser']=$_POST['ftpuser'];
	$jobs[$jobid]['ftppass']=base64_encode($_POST['ftppass']);
	$jobs[$jobid]['ftpdir']=stripslashes($_POST['ftpdir']);
	$jobs[$jobid]['ftpmaxbackups']=(int)$_POST['ftpmaxbackups'];
	$jobs[$jobid]['awsAccessKey']=$_POST['awsAccessKey'];
	$jobs[$jobid]['awsSecretKey']=$_POST['awsSecretKey'];
	$jobs[$jobid]['awsSSL']= $_POST['awsSSL']==1 ? true : false;
	$jobs[$jobid]['awsBucket']=$_POST['awsBucket'];
	$jobs[$jobid]['awsdir']=stripslashes($_POST['awsdir']);
	$jobs[$jobid]['mailaddress']=sanitize_email($_POST['mailaddress']);
	$jobs[$jobid]['awsmaxbackups']=(int)$_POST['awsmaxbackups'];
	
	$jobs[$jobid]=backwpup_check_job_vars($jobs[$jobid]); //check vars and set def.

	if (!empty($_POST['newawsBucket']) and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey'])) { //create new s3 bucket if needed
		if (!class_exists('S3')) 
			require_once('libs/S3.php');
		$s3 = new S3($_POST['awsAccessKey'], $_POST['awsSecretKey'], false);
		@$s3->putBucket($_POST['newawsBucket'], S3::ACL_PRIVATE, $_POST['awsRegion']);
		$jobs[$jobid]['awsBucket']=$_POST['newawsBucket'];
	}
	
	//save chages
	update_option('backwpup_jobs',$jobs);
	
	//update schedule
	if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
		wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
	}
	if ($jobs[$jobid]['activated']) {
		wp_schedule_event($jobs[$jobid]['scheduletime'], 'backwpup_int_'.$jobid, 'backwpup_cron',array('jobid'=>$jobid));
	}
	
	$backwpup_message=str_replace('%1',$jobs[$jobid]['name'],__('Job \'%1\' changes saved.', 'backwpup')).' <a href="admin.php?page=BackWPup">'.__('Jobs overview.', 'backwpup').'</a>';
	//go to job page
	$_REQUEST['action']='edit';
	$_REQUEST['jobid']=$jobid;

	break;
}	
?>