<?PHP
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY', false); //W3TC will not loaded
define('BACKWPUP_LINE_SEPARATOR', (strstr(PHP_OS, "WIN") or strtr(PHP_OS, "OS/2")) ? "\r\n" : "\n");
//define E_DEPRECATED if PHP lower than 5.3
if ( !defined('E_DEPRECATED') )
	define('E_DEPRECATED', 8192);
if ( !defined('E_USER_DEPRECATED') )
	define('E_USER_DEPRECATED', 16384);
//try to disable safe mode
@ini_set('safe_mode', '0');
// Now user abort
@ini_set('ignore_user_abort', '0');
ignore_user_abort(true);
$backwpup_cfg = '';
$backwpup_job_object = '';
global $l10n, $backwpup_cfg, $backwpup_job_object;
//phrase commandline args
if ( defined('STDIN') ) {
	$_GET['starttype'] = 'runcmd';
	foreach ( $_SERVER['argv'] as $arg ) {
		if ( strtolower(substr($arg, 0, 7)) == '-jobid=' )
			$_GET['jobid'] = (int)substr($arg, 7);
		if ( strtolower(substr($arg, 0, 9)) == '-abspath=' )
			$_GET['ABSPATH'] = substr($arg, 9);
	}
	if ( (empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) )
		die('JOBID check');
	if ( is_file('../../../wp-load.php') ) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH'] = preg_replace('/[^a-zA-Z0-9:.\/_\-]/', '', trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH'] = str_replace(array( '../', '\\', '//' ), '', $_GET['ABSPATH']);
		if ( file_exists($_GET['ABSPATH'] . 'wp-load.php') )
			require_once($_GET['ABSPATH'] . 'wp-load.php');
		else
			die('ABSPATH check');
	}
} else { //normal start from webservice
	//check get vars
	if ( empty($_GET['starttype']) or !in_array($_GET['starttype'], array( 'restarttime', 'restart', 'runnow', 'cronrun', 'runext' )) )
		die('Starttype check');
	if ( (empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) and in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext' )) )
		die('JOBID check');
	$_GET['_wpnonce'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_GET['_wpnonce']));
	if ( empty($_GET['_wpnonce']) or !is_string($_GET['_wpnonce']) )
		die('Nonce pre check');
	if ( is_file('../../../wp-load.php') ) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH'] = preg_replace('/[^a-zA-Z0-9:.\/_\-]/', '', trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH'] = str_replace(array( '../', '\\', '//' ), '', $_GET['ABSPATH']);
		if ( file_exists($_GET['ABSPATH'] . 'wp-load.php') )
			require_once($_GET['ABSPATH'] . 'wp-load.php');
		else
			die('ABSPATH check');
	}
	if ( in_array($_GET['starttype'], array( 'restarttime', 'restart', 'cronrun', 'runnow','runext' )) and $_GET['_wpnonce']!=$backwpup_cfg['jobrunauthkey'])
		die('Nonce check');
}
if (in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext' )))  {
	if ( $_GET['jobid'] != backwpup_get_option('job_' . $_GET['jobid'], 'jobid'))
		die('Wrong JOBID check');
}
//check folders
if (!is_dir($backwpup_cfg['logfolder']) or !is_writable($backwpup_cfg['logfolder']))
	die('Log folder not exists or is not writable');
if (!is_dir($backwpup_cfg['tempfolder']) or !is_writable($backwpup_cfg['tempfolder']))
	die('Temp folder not exists or is not writable');
//check running job
$backwpupjobdata = backwpup_get_option('working', 'data');
if ( in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext', 'runcmd' )) and !empty($backwpupjobdata) )
	die('A job already running');
if ( in_array($_GET['starttype'], array( 'restart', 'restarttime' )) and (empty($backwpupjobdata) or !is_array($backwpupjobdata)) )
	die('No job running');
unset($backwpupjobdata);
//disconnect or redirect
if ( in_array($_GET['starttype'], array( 'restarttime', 'restart', 'cronrun', 'runext' )) ) {
	ob_end_clean();
	header("Connection: close");
	ob_start();
	header("Content-Length: 0");
	ob_end_flush();
	flush();
}
elseif ( $_GET['starttype'] == 'runnow' ) {
	ob_start();
	wp_redirect(backwpup_admin_url('admin.php') . '?page=backwpupworking');
	echo ' ';
	while ( @ob_end_flush() );
	flush();
}
//unload translation
if ( $backwpup_cfg['unloadtranslations'] )
	unset($l10n);

class BackWPup_job {

	private $jobdata = false;

	public function __construct() {
		//get job data
		if ( in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext', 'runcmd' )) )
			$this->start((int)$_GET['jobid']);
		else
			$this->jobdata = backwpup_get_option('working', 'data');
		//set function for PHP user defined error handling
		$this->jobdata['PHP']['INI']['ERROR_LOG'] = ini_get('error_log');
		$this->jobdata['PHP']['INI']['LOG_ERRORS'] = ini_get('log_errors');
		$this->jobdata['PHP']['INI']['DISPLAY_ERRORS'] = ini_get('display_errors');
		@ini_set('error_log', $this->jobdata['LOGFILE']);
		@ini_set('display_errors', 'Off');
		@ini_set('log_errors', 'On');
		set_error_handler(array( $this, 'errorhandler' ), E_ALL | E_STRICT);
		//Check Folder
		if (!empty($this->jobdata['STATIC']['JOB']['backupdir||']) and $this->jobdata['STATIC']['JOB']['backupdir']!=$this->jobdata['STATIC']['CFG']['tempfolder'] )
			$this->_checkfolder($this->jobdata['STATIC']['JOB']['backupdir']);
		if (!empty($this->jobdata['STATIC']['CFG']['tempfolder']))
			$this->_checkfolder($this->jobdata['STATIC']['CFG']['tempfolder']);
		if (!empty($this->jobdata['STATIC']['CFG']['logfolder']))
			$this->_checkfolder($this->jobdata['STATIC']['CFG']['logfolder']);
		//Check double running and inactivity
		if ( $this->jobdata['WORKING']['PID'] != getmypid() and $this->jobdata['WORKING']['TIMESTAMP'] > (current_time('timestamp') - 500) and $_GET['starttype'] == 'restarttime' ) {
			trigger_error(__('Job restart terminated, because other job runs!', 'backwpup'), E_USER_ERROR);
			die();
		} elseif ( $_GET['starttype'] == 'restarttime' ) {
			trigger_error(__('Job restarted, because of inactivity!', 'backwpup'), E_USER_ERROR);
		} elseif ( $this->jobdata['WORKING']['PID'] != getmypid() and $this->jobdata['WORKING']['PID'] != 0 and $this->jobdata['WORKING']['timestamp'] > (time() - 500) ) {
			trigger_error(sprintf(__('Second prozess is running, but old job runs! Start type is %s', 'backwpup'), $_GET['starttype']), E_USER_ERROR);
			die();
		}
		//set Pid
		$this->jobdata['WORKING']['PID'] = getmypid();
		// execute function on job shutdown
		register_shutdown_function(array( $this, '__destruct' ));
		if ( function_exists('pcntl_signal') ) {
			declare(ticks = 1); //set ticks
			pcntl_signal(15, array( $this, '__destruct' )); //SIGTERM
			//pcntl_signal(9, array($this,'__destruct')); //SIGKILL
			pcntl_signal(2, array( $this, '__destruct' )); //SIGINT
		}
		$this->_update_working_data(true);
		// Working step by step
		foreach ( $this->jobdata['WORKING']['STEPS'] as $step ) {
			//Set next step
			if ( !isset($this->jobdata['WORKING'][$step]['STEP_TRY']) or empty($this->jobdata['WORKING'][$step]['STEP_TRY']) ) {
				$this->jobdata['WORKING'][$step]['STEP_TRY'] = 0;
				$this->jobdata['WORKING']['STEPDONE'] = 0;
				$this->jobdata['WORKING']['STEPTODO'] = 0;
			}
			//update running file
			$this->_update_working_data(true);
			//Run next step
			if ( !in_array($step, $this->jobdata['WORKING']['STEPSDONE']) ) {
				if ( method_exists($this, strtolower($step)) ) {
					while ( $this->jobdata['WORKING'][$step]['STEP_TRY'] < $this->jobdata['STATIC']['CFG']['jobstepretry'] ) {
						if ( in_array($step, $this->jobdata['WORKING']['STEPSDONE']) )
							break;
						$this->jobdata['WORKING'][$step]['STEP_TRY']++;
						$this->_update_working_data(true);
						call_user_func(array( $this, strtolower($step) ));
					}
					if ( $this->jobdata['WORKING'][$step]['STEP_TRY'] >= $this->jobdata['STATIC']['CFG']['jobstepretry'] )
						trigger_error(__('Step aborted has too many tries!', 'backwpup'), E_USER_ERROR);
				} else {
					trigger_error(sprintf(__('Can not find job step method %s!', 'backwpup'), strtolower($step)), E_USER_ERROR);
					$this->jobdata['WORKING']['STEPSDONE'][] = $step;
				}
			}
		}
	}

	public function __destruct() {
		$args = func_get_args();
		//nothing on empty
		if ( empty($this->jobdata['LOGFILE']) )
			return;
		//Put last error to log if one
		$lasterror = error_get_last();
		if ( $lasterror['type'] == E_ERROR or $lasterror['type'] == E_PARSE or $lasterror['type'] == E_CORE_ERROR or $lasterror['type'] == E_CORE_WARNING or $lasterror['type'] == E_COMPILE_ERROR or $lasterror['type'] == E_COMPILE_WARNING )
			$this->errorhandler($lasterror['type'], $lasterror['message'], $lasterror['file'], $lasterror['line'],false);
		//Put sigterm to log
		if ( !empty($args[0]) )
			$this->errorhandler(E_USER_ERROR, sprintf(__('Signal %d send to script!', 'backwpup')), __FILE__, __LINE__,false);
		//no more restarts
		$this->jobdata['WORKING']['RESTART']++;
		if ( (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) or $this->jobdata['WORKING']['RESTART'] >= $this->jobdata['STATIC']['CFG']['jobscriptretry'] ) { //only x restarts allowed
			if ( defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON )
				$this->errorhandler(E_USER_ERROR, __('Can not restart on alternate cron....', 'backwpup'), __FILE__, __LINE__,false);
			else
				$this->errorhandler(E_USER_ERROR, __('To many restarts....', 'backwpup'), __FILE__, __LINE__,false);
			$this->end();
			exit;
		}
		$backupdata = backwpup_get_option('working', 'data');
		if ( empty($backupdata) )
			exit;
		//set PID to 0
		$this->jobdata['WORKING']['PID'] = 0;
		//Restart job
		$this->_update_working_data(true);
		$this->errorhandler(E_USER_NOTICE, sprintf(__('%d. Script stop! Will started again now!', 'backwpup'), $this->jobdata['WORKING']['RESTART']), __FILE__, __LINE__,false);
		$httpauthheader = '';
		if ( !empty($this->jobdata['STATIC']['CFG']['httpauthuser']) and !empty($this->jobdata['STATIC']['CFG']['httpauthpassword']) )
			$httpauthheader = array( 'Authorization' => 'Basic ' . base64_encode($this->jobdata['STATIC']['CFG']['httpauthuser'] . ':' . base64_decode($this->jobdata['STATIC']['CFG']['httpauthpassword'])) );
		$raw_response=@wp_remote_get(BACKWPUP_PLUGIN_BASEURL . '/backwpup-job.php?ABSPATH=' . urlencode(str_replace('\\', '/', ABSPATH)) . '&_wpnonce=' . $this->jobdata['STATIC']['CFG']['jobrunauthkey'] . '&starttype=restart', array( 'timeout' => 5, 'blocking' => true, 'sslverify' => false, 'headers' => $httpauthheader, 'user-agent' => 'BackWPup' ));
		$body=wp_remote_retrieve_body($raw_response);
		if (200 == wp_remote_retrieve_response_code($raw_response) and !empty($body))
			$this->errorhandler(E_USER_ERROR, $body, __FILE__, __LINE__,false);
		if (is_wp_error($raw_response) or 200 != wp_remote_retrieve_response_code($raw_response))
			$this->errorhandler(E_USER_ERROR, json_encode($raw_response), __FILE__, __LINE__,false);
		exit;
	}

	private function start($jobid) {
		global $wp_version, $backwpup_cfg;
		//make start on cli mode
		if ( defined('STDIN') )
			_e('Run!', 'backwpup');
		//clean var
		$this->jobdata = array();
		//get cfg
		$this->jobdata['STATIC']['CFG'] = $backwpup_cfg;
		//check exists gzip functions
		if ( !function_exists('gzopen') )
			$this->jobdata['STATIC']['CFG']['gzlogs'] = false;
		//set Logfile
		$this->jobdata['LOGFILE'] = $this->jobdata['STATIC']['CFG']['logfolder'] . 'backwpup_log_' . date_i18n('Y-m-d_H-i-s') . '.html';
		//Set job data
		$this->jobdata['STATIC']['JOB'] = backwpup_get_job_vars($jobid);
		//Set job start settings
		$this->jobdata['STATIC']['JOB']['starttime'] = current_time('timestamp'); //set start time for job
		backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'starttime', $this->jobdata['STATIC']['JOB']['starttime']);
		backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'logfile', $this->jobdata['LOGFILE']); //Set current logfile
		backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'lastbackupdownloadurl', '');
		//only for jobs that makes backups
		if ( in_array('FILE', $this->jobdata['STATIC']['JOB']['type']) or in_array('DB', $this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP', $this->jobdata['STATIC']['JOB']['type']) ) {
			//make empty file list
			if ( $this->jobdata['STATIC']['JOB']['backuptype'] == 'archive' ) {
				//set Backup folder to temp folder if not set
				if ( empty($this->jobdata['STATIC']['JOB']['backupdir']) or $this->jobdata['STATIC']['JOB']['backupdir'] == '/' )
					$this->jobdata['STATIC']['JOB']['backupdir'] = $this->jobdata['STATIC']['CFG']['tempfolder'];
				//Create backup archive full file name
				$this->jobdata['STATIC']['backupfile'] = $this->jobdata['STATIC']['JOB']['fileprefix'] . date_i18n('Y-m-d_H-i-s') . $this->jobdata['STATIC']['JOB']['fileformart'];
			}
		}
		$this->jobdata['WORKING']['BACKUPFILESIZE'] = 0;
		$this->jobdata['WORKING']['PID'] = 0;
		$this->jobdata['WORKING']['WARNING'] = 0;
		$this->jobdata['WORKING']['ERROR'] = 0;
		$this->jobdata['WORKING']['RESTART'] = 0;
		$this->jobdata['WORKING']['STEPSDONE'] = array();
		$this->jobdata['WORKING']['STEPTODO'] = 0;
		$this->jobdata['WORKING']['STEPDONE'] = 0;
		$this->jobdata['WORKING']['STEPSPERSENT'] = 0;
		$this->jobdata['WORKING']['STEPPERSENT'] = 0;
		$this->jobdata['WORKING']['TIMESTAMP'] = current_time('timestamp');
		$this->jobdata['WORKING']['ENDINPROGRESS'] = false;
		$this->jobdata['WORKING']['EXTRAFILESTOBACKUP'] = array();
		$this->jobdata['WORKING']['FILEEXCLUDES']=explode(',',trim($this->jobdata['STATIC']['JOB']['fileexclude']));
		$this->jobdata['WORKING']['FILEEXCLUDES'][] ='.tmp';
		$this->jobdata['WORKING']['FILEEXCLUDES']=array_unique($this->jobdata['WORKING']['FILEEXCLUDES']);
		//create path to remove
		if ( trailingslashit(str_replace('\\', '/', ABSPATH)) == '/' or trailingslashit(str_replace('\\', '/', ABSPATH)) == '' )
			$this->jobdata['WORKING']['REMOVEPATH'] = '';
		else
			$this->jobdata['WORKING']['REMOVEPATH'] = trailingslashit(str_replace('\\', '/', ABSPATH));
		//build working steps
		$this->jobdata['WORKING']['STEPS'] = array();
		//setup job steps
		if ( in_array('DB', $this->jobdata['STATIC']['JOB']['type']) )
			$this->jobdata['WORKING']['STEPS'][] = 'DB_DUMP';
		if ( in_array('WPEXP', $this->jobdata['STATIC']['JOB']['type']) )
			$this->jobdata['WORKING']['STEPS'][] = 'WP_EXPORT';
		if ( in_array('FILE', $this->jobdata['STATIC']['JOB']['type']) )
			$this->jobdata['WORKING']['STEPS'][] = 'FOLDER_LIST';
		if ( in_array('DB', $this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP', $this->jobdata['STATIC']['JOB']['type']) or in_array('FILE', $this->jobdata['STATIC']['JOB']['type']) ) {
			if ( $this->jobdata['STATIC']['JOB']['backuptype'] == 'archive' ) {
				$this->jobdata['WORKING']['STEPS'][] = 'CREATE_ARCHIVE';
				$backuptypeextension = '';
			} elseif ( $this->jobdata['STATIC']['JOB']['backuptype'] == 'sync' ) {
				$backuptypeextension = '_SYNC';
			}
			//ADD Destinations
			if ( !empty($this->jobdata['STATIC']['JOB']['backupdir']) and $this->jobdata['STATIC']['JOB']['backupdir'] != '/' and $this->jobdata['STATIC']['JOB']['backupdir'] != $this->jobdata['STATIC']['CFG']['tempfolder'] )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_FOLDER' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['mailaddress']) and $this->jobdata['STATIC']['JOB']['backuptype'] == 'archive' )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_MAIL';
			if ( !empty($this->jobdata['STATIC']['JOB']['ftphost']) and !empty($this->jobdata['STATIC']['JOB']['ftpuser']) and !empty($this->jobdata['STATIC']['JOB']['ftppass']) and in_array('FTP', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_FTP' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['dropetoken']) and !empty($this->jobdata['STATIC']['JOB']['dropesecret']) and in_array('DROPBOX', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_DROPBOX' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['boxnetauth']) and in_array('BOXNET', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_BOXNET' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['sugaruser']) and !empty($this->jobdata['STATIC']['JOB']['sugarpass']) and !empty($this->jobdata['STATIC']['JOB']['sugarroot']) and in_array('SUGARSYNC', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_SUGARSYNC' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['awsAccessKey']) and !empty($this->jobdata['STATIC']['JOB']['awsSecretKey']) and !empty($this->jobdata['STATIC']['JOB']['awsBucket']) and in_array('S3', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_S3' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['GStorageAccessKey']) and !empty($this->jobdata['STATIC']['JOB']['GStorageSecret']) and !empty($this->jobdata['STATIC']['JOB']['GStorageBucket']) and in_array('GSTORAGE', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_GSTORAGE' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['rscUsername']) and !empty($this->jobdata['STATIC']['JOB']['rscAPIKey']) and !empty($this->jobdata['STATIC']['JOB']['rscContainer']) and in_array('RSC', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_RSC' . $backuptypeextension;
			if ( !empty($this->jobdata['STATIC']['JOB']['msazureHost']) and !empty($this->jobdata['STATIC']['JOB']['msazureAccName']) and !empty($this->jobdata['STATIC']['JOB']['msazureKey']) and !empty($this->jobdata['STATIC']['JOB']['msazureContainer']) and in_array('MSAZURE', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['WORKING']['STEPS'][] = 'DEST_MSAZURE' . $backuptypeextension;
		}
		if ( in_array('CHECK', $this->jobdata['STATIC']['JOB']['type']) )
			$this->jobdata['WORKING']['STEPS'][] = 'DB_CHECK';
		if ( in_array('OPTIMIZE', $this->jobdata['STATIC']['JOB']['type']) )
			$this->jobdata['WORKING']['STEPS'][] = 'DB_OPTIMIZE';
		$this->jobdata['WORKING']['STEPS'][] = 'END';
		//mark all as not done
		foreach ( $this->jobdata['WORKING']['STEPS'] as $step )
			$this->jobdata['WORKING'][$step]['DONE'] = false;
		//write working date
		backwpup_update_option('working', 'data', $this->jobdata);
		//create log file
		$fd = fopen($this->jobdata['LOGFILE'], 'w');
		fwrite($fd, "<html>" . BACKWPUP_LINE_SEPARATOR . "<head>" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_version\" content=\"" . BACKWPUP_VERSION . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_logtime\" content=\"" . current_time('timestamp') . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_errors\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobid\" content=\"" . $this->jobdata['STATIC']['JOB']['jobid'] . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobname\" content=\"" . $this->jobdata['STATIC']['JOB']['name'] . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobtype\" content=\"" . implode('+', $this->jobdata['STATIC']['JOB']['type']) . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<style type=\"text/css\">" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, ".timestamp {background-color:grey;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, ".warning {background-color:yellow;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, ".error {background-color:red;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "#body {font-family:monospace;font-size:12px;white-space:nowrap;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "</style>" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<title>" . sprintf(__('BackWPup log for %1$s from %2$s at %3$s', 'backwpup'), $this->jobdata['STATIC']['JOB']['name'], date_i18n(get_option('date_format')), date_i18n(get_option('time_format'))) . "</title>" . BACKWPUP_LINE_SEPARATOR . "</head>" . BACKWPUP_LINE_SEPARATOR . "<body id=\"body\">" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: BackWPup version %1$s, WordPress version %4$s Copyright &copy; %2$s %3$s'), BACKWPUP_VERSION, date_i18n('Y'), '<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>', $wp_version) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: BackWPup job:', 'backwpup') . ' ' . $this->jobdata['STATIC']['JOB']['jobid'] . '. ' . $this->jobdata['STATIC']['JOB']['name'] . '; ' . implode('+', $this->jobdata['STATIC']['JOB']['type']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( $this->jobdata['STATIC']['JOB']['activated'] )
			fwrite($fd, __('[INFO]: BackWPup cron:', 'backwpup') . ' ' . $this->jobdata['STATIC']['JOB']['cron'] . '; ' . date_i18n('D, j M Y @ H:i', $this->jobdata['STATIC']['JOB']['cronnextrun']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( $_GET['starttype'] == 'cronrun' )
			fwrite($fd, __('[INFO]: BackWPup job started from wp-cron', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $_GET['starttype'] == 'runnow' )
			fwrite($fd, __('[INFO]: BackWPup job started manually', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $_GET['starttype'] == 'runext' )
			fwrite($fd, __('[INFO]: BackWPup job started external from url', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $_GET['starttype'] == 'runcmd' )
			fwrite($fd, __('[INFO]: BackWPup job started form commandline', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: PHP ver.:', 'backwpup') . ' ' . phpversion() . '; ' . php_sapi_name() . '; ' . PHP_OS . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( (bool)ini_get('safe_mode') )
			fwrite($fd, sprintf(__('[INFO]: PHP Safe mode is ON! Maximum script execution time is %1$d sec.', 'backwpup'), ini_get('max_execution_time')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: MySQL ver.: %s', 'backwpup'), mysql_result(mysql_query("SELECT VERSION() AS version"), 0)) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( function_exists('curl_init') ) {
			$curlversion = curl_version();
			fwrite($fd, sprintf(__('[INFO]: curl ver.: %1$s; %2$s', 'backwpup'), $curlversion['version'], $curlversion['ssl_version']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		}
		fwrite($fd, sprintf(__('[INFO]: Temp folder is: %s', 'backwpup'), $this->jobdata['STATIC']['CFG']['tempfolder']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: Logfile folder is: %s', 'backwpup'), $this->jobdata['STATIC']['CFG']['logfolder']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: Backup type is: %s', 'backwpup'), $this->jobdata['STATIC']['JOB']['backuptype']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( !empty($this->jobdata['STATIC']['backupfile']) and $this->jobdata['STATIC']['JOB']['backuptype'] == 'archive' )
			fwrite($fd, sprintf(__('[INFO]: Backup file is: %s', 'backwpup'), $this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fclose($fd);
		//test for destinations
		if ( in_array('DB', $this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP', $this->jobdata['STATIC']['JOB']['type']) or in_array('FILE', $this->jobdata['STATIC']['JOB']['type']) ) {
			$desttest = false;
			foreach ( $this->jobdata['WORKING']['STEPS'] as $deststeptest ) {
				if ( substr($deststeptest, 0, 5) == 'DEST_' ) {
					$desttest = true;
					break;
				}
			}
			if ( !$desttest )
				$this->errorhandler(E_USER_ERROR, __('No destination defined for backup!!! Please correct job settings', 'backwpup'), __FILE__, __LINE__);
		}
	}

	private function _checkfolder($folder) {
		$folder = untrailingslashit($folder);
		//check that is not home of WP
		if ( is_file($folder . '/wp-load.php') )
			return false;
		//create backup dir if it not exists
		if ( !is_dir($folder) ) {
			if ( !mkdir($folder, FS_CHMOD_DIR, true) ) {
				trigger_error(sprintf(__('Can not create folder: %1$s', 'backwpup'), $folder), E_USER_ERROR);
				return false;
			}
			//create .htaccess for apache and index.html/php for other
			if ( strtolower(substr($_SERVER["SERVER_SOFTWARE"], 0, 6)) == "apache" ) { //check for apache webserver
				if ( !is_file($folder . '/.htaccess') )
					file_put_contents($folder . '/.htaccess', "Order allow,deny" . BACKWPUP_LINE_SEPARATOR . "deny from all");
			} else {
				if ( !is_file($folder . '/index.html') )
					file_put_contents($folder . '/index.html', BACKWPUP_LINE_SEPARATOR);
				if ( !is_file($folder . '/index.php') )
					file_put_contents($folder . '/index.php', BACKWPUP_LINE_SEPARATOR);
			}
		}
		//check backup dir
		if ( !is_writable($folder) ) {
			trigger_error(sprintf(__('Not writable folder: %1$s', 'backwpup'), $folder), E_USER_ERROR);
			return false;
		}
		return true;
	}

	public function errorhandler() {
		$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
		// if error has been suppressed with an @
		if ( error_reporting() == 0 )
			return;

		$adderrorwarning = false;

		switch ( $args[0] ) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$messagetype = "<span>";
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$this->jobdata['WORKING']['WARNING']++;
				$adderrorwarning = true;
				$messagetype = "<span class=\"warning\">" . __('WARNING:', 'backwpup');
				break;
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$this->jobdata['WORKING']['ERROR']++;
				$adderrorwarning = true;
				$messagetype = "<span class=\"error\">" . __('ERROR:', 'backwpup');
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$messagetype = "<span>" . __('DEPRECATED:', 'backwpup');
				break;
			case E_STRICT:
				$messagetype = "<span>" . __('STRICT NOTICE:', 'backwpup');
				break;
			case E_RECOVERABLE_ERROR:
				$messagetype = "<span>" . __('RECOVERABLE ERROR:', 'backwpup');
				break;
			default:
				$messagetype = "<span>" . $args[0] . ":";
				break;
		}

		//log line
		$timestamp = "<span title=\"[Type: " . $args[0] . "|Line: " . $args[3] . "|File: " . basename($args[2]) . "|Mem: " . backwpup_formatBytes(@memory_get_usage(true)) . "|Mem Max: " . backwpup_formatBytes(@memory_get_peak_usage(true)) . "|Mem Limit: " . ini_get('memory_limit') . "|PID: " . getmypid() . "]\">[" . date_i18n('d-M-Y H:i:s') . "]</span> ";
		//write log file
		file_put_contents($this->jobdata['LOGFILE'], $timestamp . $messagetype . " " . $args[1] . "</span><br />" . BACKWPUP_LINE_SEPARATOR, FILE_APPEND);

		//write new log header
		if ( $adderrorwarning ) {
			$found = 0;
			$fd = fopen($this->jobdata['LOGFILE'], 'r+');
			$filepos = ftell($fd);
			while ( !feof($fd) ) {
				$line = fgets($fd);
				if ( stripos($line, "<meta name=\"backwpup_errors\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_errors\" content=\"" . $this->jobdata['WORKING']['ERROR'] . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( stripos($line, "<meta name=\"backwpup_warnings\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_warnings\" content=\"" . $this->jobdata['WORKING']['WARNING'] . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell($fd);
			}
			fclose($fd);
		}

		//write working data
		$this->_update_working_data($adderrorwarning);

		//Die on fatal php errors.
		if ( ($args[0] == E_ERROR or $args[0] == E_CORE_ERROR or $args[0] == E_COMPILE_ERROR) and $args[4]!=false)
			die();

		//true for no more php error handling.
		return true;
	}

	private function _update_working_data($mustwrite = false) {
		global $wpdb;
		$backupdata = backwpup_get_option('working', 'data');
		if ( empty($backupdata) ) {
			$this->end();
			return false;
		}
		$timetoupdate = current_time('timestamp') - 1; //only update all 1 sec.
		if ( $mustwrite or $this->jobdata['WORKING']['TIMESTAMP'] <= $timetoupdate ) {
			if ( !mysql_ping($wpdb->dbh) ) { //check MySQL connection
				trigger_error(__('Database connection is gone create a new one.', 'backwpup'), E_USER_NOTICE);
				$wpdb->db_connect();
			}
			if ( $this->jobdata['WORKING']['STEPTODO'] > 0 and $this->jobdata['WORKING']['STEPDONE'] > 0 )
				$this->jobdata['WORKING']['STEPPERSENT'] = round($this->jobdata['WORKING']['STEPDONE'] / $this->jobdata['WORKING']['STEPTODO'] * 100);
			else
				$this->jobdata['WORKING']['STEPPERSENT'] = 1;
			if ( count($this->jobdata['WORKING']['STEPSDONE']) > 0 )
				$this->jobdata['WORKING']['STEPSPERSENT'] = round(count($this->jobdata['WORKING']['STEPSDONE']) / count($this->jobdata['WORKING']['STEPS']) * 100);
			else
				$this->jobdata['WORKING']['STEPSPERSENT'] = 1;
			$this->jobdata['WORKING']['TIMESTAMP'] = current_time('timestamp');
			@set_time_limit(0);
			backwpup_update_option('working', 'data', $this->jobdata);
			if ( defined('STDIN') ) //make dots on cli mode
				echo ".";
		}
		return true;
	}

	private function end() {
		global $wpdb;
		//check if end() in progress
		if ( !$this->jobdata['WORKING']['ENDINPROGRESS'] )
			$this->jobdata['WORKING']['ENDINPROGRESS'] = true;
		else
			return;

		$this->jobdata['WORKING']['STEPTODO'] = 1;
		$this->jobdata['WORKING']['STEPDONE'] = 0;
		//Back from maintenance
		$this->_maintenance_mode(false);
		//delete old logs
		if ( !empty($this->jobdata['STATIC']['CFG']['maxlogs']) ) {
			if ( $dir = opendir($this->jobdata['STATIC']['CFG']['logfolder']) ) { //make file list
				while ( ($file = readdir($dir)) !== false ) {
					if ( 'backwpup_log_' == substr($file, 0, strlen('backwpup_log_')) and (".html" == substr($file, -5) or ".html.gz" == substr($file, -8)) )
						$logfilelist[] = $file;
				}
				closedir($dir);
			}
			if ( sizeof($logfilelist) > 0 ) {
				rsort($logfilelist);
				$numdeltefiles = 0;
				for ( $i = $this->jobdata['STATIC']['CFG']['maxlogs']; $i < sizeof($logfilelist); $i++ ) {
					unlink($this->jobdata['STATIC']['CFG']['logfolder'] . $logfilelist[$i]);
					$numdeltefiles++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error(sprintf(_n('One old log deleted', '%d old logs deleted', $numdeltefiles, 'backwpup'), $numdeltefiles), E_USER_NOTICE);
			}
		}
		//Display job working time
		if ( !empty($this->jobdata['STATIC']['JOB']['starttime']) )
			trigger_error(sprintf(__('Job done in %s sec.', 'backwpup'), current_time('timestamp') - $this->jobdata['STATIC']['JOB']['starttime']), E_USER_NOTICE);

		if ( empty($this->jobdata['STATIC']['backupfile']) or !is_file($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile']) or !($filesize = filesize($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'])) ) //Set the filesize correctly
			$filesize = 0;

		//clean up temp
		if ( !empty($this->jobdata['STATIC']['backupfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['backupfile']) )
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['backupfile']);
		if ( !empty($this->jobdata['STATIC']['JOB']['dbdumpfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile']) )
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile']);
		if ( !empty($this->jobdata['STATIC']['JOB']['wpexportfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile']) )
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile']);

		//Update job options
		$starttime = backwpup_get_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'starttime');
		if ( !empty($starttime) ) {
			backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'lastrun', $starttime);
			backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'lastruntime', (current_time('timestamp') - $starttime));
			backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'starttime', '');
		}
		$this->jobdata['STATIC']['JOB']['lastrun'] = $starttime;
		//write header info
		if ( is_writable($this->jobdata['LOGFILE']) ) {
			$fd = fopen($this->jobdata['LOGFILE'], 'r+');
			$filepos = ftell($fd);
			$found = 0;
			while ( !feof($fd) ) {
				$line = fgets($fd);
				if ( stripos($line, "<meta name=\"backwpup_jobruntime\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_jobruntime\" content=\"" . backwpup_get_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'lastruntime') . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( stripos($line, "<meta name=\"backwpup_backupfilesize\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_backupfilesize\" content=\"" . $filesize . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell($fd);
			}
			fclose($fd);
		}
		//Restore error handler
		restore_error_handler();
		@ini_set('log_errors', $this->jobdata['PHP']['INI']['LOG_ERRORS']);
		@ini_set('error_log', $this->jobdata['PHP']['INI']['ERROR_LOG']);
		@ini_set('display_errors', $this->jobdata['PHP']['INI']['DISPLAY_ERRORS']);
		//logfile end
		file_put_contents($this->jobdata['LOGFILE'], "</body>" . BACKWPUP_LINE_SEPARATOR . "</html>", FILE_APPEND);
		//gzip logfile
		if ( $this->jobdata['STATIC']['CFG']['gzlogs'] and is_writable($this->jobdata['LOGFILE']) ) {
			$fd = fopen($this->jobdata['LOGFILE'], 'r');
			$zd = gzopen($this->jobdata['LOGFILE'] . '.gz', 'w9');
			while ( !feof($fd) ) {
				gzwrite($zd, fread($fd, 4096));
			}
			gzclose($zd);
			fclose($fd);
			unlink($this->jobdata['LOGFILE']);
			$this->jobdata['LOGFILE'] = $this->jobdata['LOGFILE'] . '.gz';
			backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'logfile', $this->jobdata['LOGFILE']);
		}

		//Send mail with log
		$sendmail = false;
		if ( $this->jobdata['WORKING']['ERROR'] > 0 and $this->jobdata['STATIC']['JOB']['mailerroronly'] and !empty($this->jobdata['STATIC']['JOB']['mailaddresslog']) )
			$sendmail = true;
		if ( !$this->jobdata['STATIC']['JOB']['mailerroronly'] and !empty($this->jobdata['STATIC']['JOB']['mailaddresslog']) )
			$sendmail = true;
		if ( $sendmail ) {
			$message = '';
			//read log
			if ( substr($this->jobdata['LOGFILE'], -3) == '.gz' ) {
				$lines = gzfile($this->jobdata['LOGFILE']);
				foreach ( $lines as $line ) {
					$message .= $line;
				}
			} else {
				$message = file_get_contents($this->jobdata['LOGFILE']);
			}

			if ( empty($this->jobdata['STATIC']['CFG']['mailsndname']) )
				$headers = 'From: ' . $this->jobdata['STATIC']['CFG']['mailsndname'] . ' <' . $this->jobdata['STATIC']['CFG']['mailsndemail'] . '>' . "\r\n";
			else
				$headers = 'From: ' . $this->jobdata['STATIC']['CFG']['mailsndemail'] . "\r\n";
			//special subject
			$status = 'Successful';
			if ( $this->jobdata['WORKING']['WARNING'] > 0 )
				$status = 'Warning';
			if ( $this->jobdata['WORKING']['ERROR'] > 0 )
				$status = 'Error';
			add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
			wp_mail($this->jobdata['STATIC']['JOB']['mailaddresslog'],
				sprintf(__('[%3$s] BackWPup log %1$s: %2$s', 'backwpup'), date_i18n('d-M-Y H:i', $this->jobdata['STATIC']['JOB']['lastrun']), $this->jobdata['STATIC']['JOB']['name'], $status),
				$message, $headers);
		}
		$this->jobdata['WORKING']['STEPDONE'] = 1;
		$this->jobdata['WORKING']['STEPSDONE'][] = 'END'; //set done
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "backwpup WHERE main_name='working'");
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "backwpup WHERE main_name='temp'");
		if ( defined('STDIN') )
			_e('Done!', 'backwpup');
		exit;
	}

	private function _maintenance_mode($enable = false) {
		if ( !$this->jobdata['STATIC']['JOB']['maintenance'] )
			return;
		if ( $enable ) {
			trigger_error(__('Set Blog to maintenance mode', 'backwpup'), E_USER_NOTICE);
			if ( get_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
				update_option('wp-maintenance-mode-msqld', '1');
			} elseif ( $mamo = get_option('plugin_maintenance-mode') ) { //Support for Maintenance Mode Plugin
				$mamo['mamo_activate'] = 'on_' . current_time('timestamp');
				$mamo['mamo_backtime_days'] = '0';
				$mamo['mamo_backtime_hours'] = '0';
				$mamo['mamo_backtime_mins'] = '5';
				update_option('plugin_maintenance-mode', $mamo);
			} else { //WP Support
				if ( is_writable(ABSPATH . '.maintenance') )
					file_put_contents(ABSPATH . '.maintenance', '<?php $upgrading = ' . current_time('timestamp') . '; ?>');
				else
					trigger_error(__('Cannot set Blog to maintenance mode! Root folder is not writable!', 'backwpup'), E_USER_NOTICE);
			}
		} else {
			trigger_error(__('Set Blog to normal mode', 'backwpup'), E_USER_NOTICE);
			if ( get_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
				update_option('wp-maintenance-mode-msqld', '0');
			} elseif ( $mamo = get_option('plugin_maintenance-mode') ) { //Support for Maintenance Mode Plugin
				$mamo['mamo_activate'] = 'off';
				update_option('plugin_maintenance-mode', $mamo);
			} else { //WP Support
				@unlink(ABSPATH . '.maintenance');
			}
		}
	}

	private function _job_inbytes($value) {
		$multi = strtoupper(substr(trim($value), -1));
		$bytes = abs(intval(trim($value)));
		if ( $multi == 'G' )
			$bytes = $bytes * 1024 * 1024 * 1024;
		if ( $multi == 'M' )
			$bytes = $bytes * 1024 * 1024;
		if ( $multi == 'K' )
			$bytes = $bytes * 1024;
		return $bytes;
	}

	private function _need_free_memory($memneed) {
		if ( !function_exists('memory_get_usage') )
			return;
		//need memory
		$needmemory = @memory_get_usage(true) + $this->_job_inbytes($memneed);
		// increase Memory
		if ( $needmemory > $this->_job_inbytes(ini_get('memory_limit')) ) {
			$newmemory = round($needmemory / 1024 / 1024) + 1 . 'M';
			if ( $needmemory >= 1073741824 )
				$newmemory = round($needmemory / 1024 / 1024 / 1024) . 'G';
			if ( $oldmem = @ini_set('memory_limit', $newmemory) )
				trigger_error(sprintf(__('Memory increased from %1$s to %2$s', 'backwpup'), $oldmem, @ini_get('memory_limit')), E_USER_NOTICE);
			else
				trigger_error(sprintf(__('Can not increase memory limit is %1$s', 'backwpup'), @ini_get('memory_limit')), E_USER_WARNING);
		}
	}

	public function update_stepdone($done) {
		if ( $this->jobdata['WORKING']['STEPTODO'] > 10 and $this->jobdata['STATIC']['JOB']['backuptype'] != 'sync' )
			$this->jobdata['WORKING']['STEPDONE'] = $done;
		backwpup_job_update_working_data();
	}

	private function db_dump() {
		global $wpdb, $wp_version;

		trigger_error(sprintf(__('%d. Try for database dump...', 'backwpup'), $this->jobdata['WORKING']['DB_DUMP']['STEP_TRY']), E_USER_NOTICE);

		if ( !isset($this->jobdata['WORKING']['DB_DUMP']['TABLES']) or !is_array($this->jobdata['WORKING']['DB_DUMP']['TABLES']) )
			$this->jobdata['WORKING']['DB_DUMP']['TABLES'] = array();


		if ( count($this->jobdata['WORKING']['DB_DUMP']['TABLES']) == 0 ) {
			//build filename
			$datevars = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
			$datevalues = array( date_i18n('d'), date_i18n('D'), date_i18n('l'), date_i18n('N'), date_i18n('S'), date_i18n('w'), date_i18n('z'), date_i18n('W'), date_i18n('F'), date_i18n('m'), date_i18n('M'), date_i18n('n'), date_i18n('t'), date_i18n('L'), date_i18n('o'), date_i18n('Y'), date_i18n('a'), date_i18n('A'), date_i18n('B'), date_i18n('g'), date_i18n('G'), date_i18n('h'), date_i18n('H'), date_i18n('i'), date_i18n('s'), date_i18n('u'), date_i18n('e'), date_i18n('I'), date_i18n('O'), date_i18n('P'), date_i18n('T'), date_i18n('Z'), date_i18n('c'), date_i18n('U') );
			$this->jobdata['STATIC']['JOB']['dbdumpfile'] = str_replace($datevars, $datevalues, $this->jobdata['STATIC']['JOB']['dbdumpfile']);
			//check compression
			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' and !function_exists('gzopen') )
				$this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] = '';
			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' and !function_exists('bzopen') )
				$this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] = '';
			//add file ending
			$this->jobdata['STATIC']['JOB']['dbdumpfile'] .= '.sql';
			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' or $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
				$this->jobdata['STATIC']['JOB']['dbdumpfile'] .= '.' . $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'];

			//get tables to backup
			$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
			if ( mysql_error() )
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
			foreach ( $tables as $table ) {
				if ( !in_array($table, $this->jobdata['STATIC']['JOB']['dbexclude']) )
					$this->jobdata['WORKING']['DB_DUMP']['TABLES'][] = $table;
			}
			//Get table status
			$tablesstatus = $wpdb->get_results("SHOW TABLE STATUS FROM `" . DB_NAME . "`", ARRAY_A); //get table status
			if ( mysql_error() )
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
			foreach ( $tablesstatus as $tablestatus )
				$this->jobdata['WORKING']['DB_DUMP']['TABLESTATUS'][$tablestatus['Name']] = $tablestatus;

			$this->jobdata['WORKING']['STEPTODO'] = count($this->jobdata['WORKING']['DB_DUMP']['TABLES']);
		}

		if ( count($this->jobdata['WORKING']['DB_DUMP']['TABLES']) == 0 ) {
			trigger_error(__('No tables to dump', 'backwpup'), E_USER_WARNING);
			$this->jobdata['WORKING']['STEPSDONE'][] = 'DB_DUMP'; //set done
			return;
		}

		//Set maintenance
		$this->_maintenance_mode(true);

		if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
			$file = gzopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile'], 'wb9');
		elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
			$file = bzopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile'], 'w');
		else
			$file = fopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile'], 'wb');

		if ( !$file ) {
			trigger_error(sprintf(__('Can not create database dump file! "%s"', 'backwpup'), $this->jobdata['STATIC']['JOB']['dbdumpfile']), E_USER_ERROR);
			$this->_maintenance_mode(false);
			return;
		}


		$dbdumpheader = "-- ---------------------------------------------------------" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Dumped with BackWPup ver.: " . BACKWPUP_VERSION . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Plugin for WordPress " . $wp_version . " by Daniel Huesken" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- http://backwpup.com" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Blog Name: " . get_bloginfo('name') . BACKWPUP_LINE_SEPARATOR;
		if ( defined('WP_SITEURL') )
			$dbdumpheader .= "-- Blog URL: " . trailingslashit(WP_SITEURL) . BACKWPUP_LINE_SEPARATOR;
		else
			$dbdumpheader .= "-- Blog URL: " . trailingslashit(get_option('siteurl')) . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Blog ABSPATH: " . trailingslashit(str_replace('\\', '/', ABSPATH)) . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Blog Charset: " . get_option( 'blog_charset' ) . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Table Prefix: " . $wpdb->prefix . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Database Name: " . DB_NAME . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Database charset: " . DB_CHARSET . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Database collate: " . DB_COLLATE . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- Dumped on: " . date_i18n('Y-m-d H:i.s') . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "-- ---------------------------------------------------------" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
		//for better import with mysql client
		$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40101 SET NAMES '" . mysql_client_encoding() . "' */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40103 SET TIME_ZONE='" . $wpdb->get_var("SELECT @@time_zone") . "' */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
		if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
			gzwrite($file, $dbdumpheader);
		elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
			bzwrite($file, $dbdumpheader);
		else
			fwrite($file, $dbdumpheader);


		//make table dumps
		foreach ( $this->jobdata['WORKING']['DB_DUMP']['TABLES'] as $tablekey => $table ) {

			trigger_error(sprintf(__('Dump database table "%s"', 'backwpup'), $table), E_USER_NOTICE);
			//get more memory if needed
			$this->_need_free_memory(($this->jobdata['WORKING']['DB_DUMP']['TABLESTATUS'][$table]['Data_length'] + $this->jobdata['WORKING']['DB_DUMP']['TABLESTATUS'][$table]['Index_length']) * 2);
			$this->_update_working_data();

			$tablecreate = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Table structure for table $table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
			$tablecreate .= "DROP TABLE IF EXISTS `" . $table . "`;" . BACKWPUP_LINE_SEPARATOR;
			$tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;" . BACKWPUP_LINE_SEPARATOR;
			$tablecreate .= "/*!40101 SET character_set_client = '" . mysql_client_encoding() . "' */;" . BACKWPUP_LINE_SEPARATOR;
			//Dump the table structure
			$tablestruc = $wpdb->get_row("SHOW CREATE TABLE `" . $table . "`", 'ARRAY_A');
			if ( mysql_error() ) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), "SHOW CREATE TABLE `" . $table . "`"), E_USER_ERROR);
				return false;
			}
			$tablecreate .= $tablestruc['Create Table'] . ";" . BACKWPUP_LINE_SEPARATOR;
			$tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;" . BACKWPUP_LINE_SEPARATOR;

			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
				gzwrite($file, $tablecreate);
			elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
				bzwrite($file, $tablecreate);
			else
				fwrite($file, $tablecreate);

			//get data from table
			$datas = $wpdb->get_results("SELECT * FROM `" . $table . "`", 'ARRAY_N');
			if ( mysql_error() ) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
				return false;
			}
			//get key information
			$keys = $wpdb->get_col_info('name', -1);

			//build key string
			$keystring = '';
			if ( !$this->jobdata['STATIC']['JOB']['dbshortinsert'] )
				$keystring = " (`" . implode("`, `", $keys) . "`)";
			//colem infos
			for ( $i = 0; $i < count($keys); $i++ ) {
				$colinfo[$i]['numeric'] = $wpdb->get_col_info('numeric', $i);
				$colinfo[$i]['type'] = $wpdb->get_col_info('type', $i);
				$colinfo[$i]['blob'] = $wpdb->get_col_info('blob', $i);
			}

			$tabledata = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Dumping data for table $table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;

			if ( $this->jobdata['WORKING']['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
				$tabledata .= "/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;" . BACKWPUP_LINE_SEPARATOR;

			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
				gzwrite($file, $tabledata);
			elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
				bzwrite($file, $tabledata);
			else
				fwrite($file, $tabledata);
			$tabledata = '';

			$querystring = '';
			foreach ( $datas as $data ) {
				$values = array();
				foreach ( $data as $key => $value ) {
					if ( is_null($value) or !isset($value) ) // Make Value NULL to string NULL
						$value = "NULL";
					elseif ( $colinfo[$key]['numeric'] == 1 and $colinfo[$key]['type'] != 'timestamp' and $colinfo[$key]['blob'] != 1 ) //is value numeric no esc
						$value = empty($value) ? 0 : $value;
					else
						$value = "'" . mysql_real_escape_string($value) . "'";
					$values[] = $value;
				}
				if ( empty($querystring) )
					$querystring = "INSERT INTO `" . $table . "`" . $keystring . " VALUES" . BACKWPUP_LINE_SEPARATOR;
				if ( strlen($querystring) <= 50000 ) { //write dump on more than 50000 chars.
					$querystring .= "(" . implode(", ", $values) . ")," . BACKWPUP_LINE_SEPARATOR;
				} else {
					$querystring .= "(" . implode(", ", $values) . ");" . BACKWPUP_LINE_SEPARATOR;
					if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
						gzwrite($file, $querystring);
					elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
						bzwrite($file, $querystring);
					else
						fwrite($file, $querystring);
					$querystring = '';
				}
			}
			if ( !empty($querystring) ) //dump rest
				$tabledata = substr($querystring, 0, -strlen(BACKWPUP_LINE_SEPARATOR)-1) . ";" . BACKWPUP_LINE_SEPARATOR;

			if ( $this->jobdata['WORKING']['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
				$tabledata .= "/*!40000 ALTER TABLE `" . $table . "` ENABLE KEYS */;" . BACKWPUP_LINE_SEPARATOR;

			if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' )
				gzwrite($file, $tabledata);
			elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' )
				bzwrite($file, $tabledata);
			else
				fwrite($file, $tabledata);

			$wpdb->flush();

			unset($this->jobdata['WORKING']['DB_DUMP']['TABLES'][$tablekey]);
			$this->jobdata['WORKING']['STEPDONE']++;
		}

		//for better import with mysql client
		$dbdumpfooter = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Delete not needed values on backwpup table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "DELETE FROM `" . $wpdb->prefix . "backwpup` WHERE `main_name`='TEMP';" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "DELETE FROM `" . $wpdb->prefix . "backwpup` WHERE `main_name`='WORKING';" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;" . BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;" . BACKWPUP_LINE_SEPARATOR;

		if ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'gz' ) {
			gzwrite($file, $dbdumpfooter);
			gzclose($file);
		} elseif ( $this->jobdata['STATIC']['JOB']['dbdumpfilecompression'] == 'bz2' ) {
			bzwrite($file, $dbdumpfooter);
			bzclose($file);
		} else {
			fwrite($file, $dbdumpfooter);
			fclose($file);
		}

		trigger_error(__('Database dump done!', 'backwpup'), E_USER_NOTICE);

		//add database file to backup files
		if ( is_readable($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile']) ) {
			$this->jobdata['WORKING']['EXTRAFILESTOBACKUP'][] = $this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile'];
			trigger_error(sprintf(__('Added database dump "%1$s" with %2$s to backup file list', 'backwpup'), $this->jobdata['STATIC']['JOB']['dbdumpfile'], backwpup_formatBytes(filesize($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['dbdumpfile']))), E_USER_NOTICE);
		}
		//Back from maintenance
		$this->_maintenance_mode(false);
		$this->jobdata['WORKING']['STEPSDONE'][] = 'DB_DUMP'; //set done
	}

	private function db_check() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database check...', 'backwpup'), $this->jobdata['WORKING']['DB_CHECK']['STEP_TRY']), E_USER_NOTICE);
		if ( !isset($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']) or !is_array($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']) )
			$this->jobdata['WORKING']['DB_CHECK']['DONETABLE'] = array();

		//to backup
		$tablestobackup = array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tables as $table ) {
			if ( !in_array($table, $this->jobdata['STATIC']['JOB']['dbexclude']) )
				$tablestobackup[] = $table;
		}
		//Set num of todos
		$this->jobdata['WORKING']['STEPTODO'] = sizeof($tablestobackup);

		//check tables
		if ( count($tablestobackup) > 0 ) {
			$this->_maintenance_mode(true);
			foreach ( $tablestobackup as $table ) {
				if ( in_array($table, $this->jobdata['WORKING']['DB_CHECK']['DONETABLE']) )
					continue;
				$check = $wpdb->get_row("CHECK TABLE `" . $table . "` MEDIUM");
				if ( mysql_error() ) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					continue;
				}
				if ( $check->Msg_text == 'OK' )
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_NOTICE);
				elseif ( strtolower($check->Msg_type) == 'warning' )
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_ERROR);

				//Try to Repair tabele
				if ( $check->Msg_text != 'OK' ) {
					$repair = $wpdb->get_row('REPAIR TABLE `' . $table . '`');
					if ( mysql_error() ) {
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
						continue;
					}
					if ( $repair->Msg_type == 'OK' )
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_NOTICE);
					elseif ( strtolower($repair->Msg_type) == 'warning' )
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_ERROR);
				}
				$this->jobdata['WORKING']['DB_CHECK']['DONETABLE'][] = $table;
				$this->jobdata['WORKING']['STEPDONE'] = sizeof($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']);
			}
			$this->_maintenance_mode(false);
			trigger_error(__('Database check done!', 'backwpup'), E_USER_NOTICE);
		} else {
			trigger_error(__('No tables to check', 'backwpup'), E_USER_WARNING);
		}
		$this->jobdata['WORKING']['STEPSDONE'][] = 'DB_CHECK'; //set done
	}

	private function db_optimize() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database optimize...', 'backwpup'), $this->jobdata['WORKING']['DB_OPTIMIZE']['STEP_TRY']), E_USER_NOTICE);
		if ( !isset($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']) or !is_array($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']) )
			$this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE'] = array();

		//to backup
		$tablestobackup = array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tables as $table ) {
			if ( !in_array($table, $this->jobdata['STATIC']['JOB']['dbexclude']) )
				$tablestobackup[] = $table;
		}
		//Set num of todos
		$this->jobdata['WORKING']['STEPTODO'] = count($tablestobackup);

		//get table status
		$tablesstatus = $wpdb->get_results("SHOW TABLE STATUS FROM `" . DB_NAME . "`");
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tablesstatus as $tablestatus )
			$status[$tablestatus->Name] = $tablestatus;

		if ( count($tablestobackup) > 0 ) {
			$this->_maintenance_mode(true);
			foreach ( $tablestobackup as $table ) {
				if ( in_array($table, $this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']) )
					continue;
				if ( $status[$table]->Engine != 'InnoDB' ) {
					$optimize = $wpdb->get_row("OPTIMIZE TABLE `" . $table . "`");
					if ( mysql_error() )
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					elseif ( strtolower($optimize->Msg_type) == 'error' )
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_ERROR);
					elseif ( strtolower($optimize->Msg_type) == 'warning' )
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_NOTICE);
				} else {
					$wpdb->get_row("ALTER TABLE `" . $table . "` ENGINE='InnoDB'");
					if ( mysql_error() )
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					else
						trigger_error(sprintf(__('InnoDB Table %1$s optimize done', 'backwpup'), $table), E_USER_NOTICE);
				}
				$this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE'][] = $table;
				$this->jobdata['WORKING']['STEPDONE'] = count($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']);
			}
			trigger_error(__('Database optimize done!', 'backwpup'), E_USER_NOTICE);
			$this->_maintenance_mode(false);
		} else {
			trigger_error(__('No tables to optimize', 'backwpup'), E_USER_WARNING);
		}
		$this->jobdata['WORKING']['STEPSDONE'][] = 'DB_OPTIMIZE'; //set done
	}

	private function wp_export() {
		$this->jobdata['WORKING']['STEPTODO'] = 1;
		trigger_error(sprintf(__('%d. Try to make a WordPress Export to XML file...', 'backwpup'), $this->jobdata['WORKING']['WP_EXPORT']['STEP_TRY']), E_USER_NOTICE);
		$this->_need_free_memory('5M'); //5MB free memory
		//build filename
		$datevars = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
		$datevalues = array( date_i18n('d'), date_i18n('D'), date_i18n('l'), date_i18n('N'), date_i18n('S'), date_i18n('w'), date_i18n('z'), date_i18n('W'), date_i18n('F'), date_i18n('m'), date_i18n('M'), date_i18n('n'), date_i18n('t'), date_i18n('L'), date_i18n('o'), date_i18n('Y'), date_i18n('a'), date_i18n('A'), date_i18n('B'), date_i18n('g'), date_i18n('G'), date_i18n('h'), date_i18n('H'), date_i18n('i'), date_i18n('s'), date_i18n('u'), date_i18n('e'), date_i18n('I'), date_i18n('O'), date_i18n('P'), date_i18n('T'), date_i18n('Z'), date_i18n('c'), date_i18n('U') );
		$this->jobdata['STATIC']['JOB']['wpexportfile'] = str_replace($datevars, $datevalues, $this->jobdata['STATIC']['JOB']['wpexportfile']);

		//check compression
		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'gz' and !function_exists('gzopen') )
			$this->jobdata['STATIC']['JOB']['wpexportfilecompression'] = '';
		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'bz2' and !function_exists('bzopen') )
			$this->jobdata['STATIC']['JOB']['wpexportfilecompression'] = '';
		//add file ending
		$this->jobdata['STATIC']['JOB']['wpexportfile'] .= '.xml';
		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'gz' or $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'bz2' )
			$this->jobdata['STATIC']['JOB']['wpexportfile'] .= '.' . $this->jobdata['STATIC']['JOB']['wpexportfilecompression'];

		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'gz' )
			$this->jobdata['WORKING']['filehandel'] = gzopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile'], 'wb9');
		elseif ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'bz2' )
			$this->jobdata['WORKING']['filehandel'] = bzopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile'], 'w');
		else
			$this->jobdata['WORKING']['filehandel'] = fopen($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile'], 'wb');

		//include WP export function
		require_once(ABSPATH . 'wp-admin/includes/export.php');
		error_reporting(0); //disable error reporting
		ob_start(array( $this, '_wp_export_ob_bufferwrite' ), 1024); //start output buffering
		export_wp(); //WP export
		ob_end_clean(); //End output buffering
		error_reporting(E_ALL | E_STRICT); //enable error reporting

		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'gz' ) {
			gzclose($this->jobdata['WORKING']['filehandel']);
		} elseif ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'bz2' ) {
			bzclose($this->jobdata['WORKING']['filehandel']);
		} else {
			fclose($this->jobdata['WORKING']['filehandel']);
		}

		//add XML file to backup files
		if ( is_readable($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile']) ) {
			$this->jobdata['WORKING']['EXTRAFILESTOBACKUP'][] = $this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile'];
			trigger_error(sprintf(__('Added XML export "%1$s" with %2$s to backup file list', 'backwpup'), $this->jobdata['STATIC']['JOB']['wpexportfile'], backwpup_formatBytes(filesize($this->jobdata['STATIC']['CFG']['tempfolder'] . $this->jobdata['STATIC']['JOB']['wpexportfile']))), E_USER_NOTICE);
		}
		$this->jobdata['WORKING']['STEPDONE'] = 1;
		$this->jobdata['WORKING']['STEPSDONE'][] = 'WP_EXPORT'; //set done
	}

	public function _wp_export_ob_bufferwrite($output) {
		if ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'gz' ) {
			gzwrite($this->jobdata['WORKING']['filehandel'], $output);
		} elseif ( $this->jobdata['STATIC']['JOB']['wpexportfilecompression'] == 'bz2' ) {
			bzwrite($this->jobdata['WORKING']['filehandel'], $output);
		} else {
			fwrite($this->jobdata['WORKING']['filehandel'], $output);
		}
		$this->_update_working_data();
	}

	private function folder_list() {
		trigger_error(sprintf(__('%d. Try to make list of folder to backup....', 'backwpup'), $this->jobdata['WORKING']['FOLDER_LIST']['STEP_TRY']), E_USER_NOTICE);
		$this->jobdata['WORKING']['STEPTODO'] = 7;
		if ( empty($this->jobdata['WORKING']['STEPDONE']) )
			$this->jobdata['WORKING']['STEPDONE'] = 0;

		//Check free memory for file list
		$this->_need_free_memory('2M'); //2MB free memory

		//Folder list for blog folders
		if ( $this->jobdata['STATIC']['JOB']['backuproot'] and $this->jobdata['WORKING']['STEPDONE'] == 0 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', ABSPATH)), 100,
				array_merge($this->jobdata['STATIC']['JOB']['backuprootexcludedirs'], backwpup_get_exclude_wp_dirs(ABSPATH)));
		if ( $this->jobdata['WORKING']['STEPDONE'] == 0 )
			$this->jobdata['WORKING']['STEPDONE'] = 1;
		if ( $this->jobdata['STATIC']['JOB']['backupcontent'] and $this->jobdata['WORKING']['STEPDONE'] == 1 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', WP_CONTENT_DIR)), 100,
				array_merge($this->jobdata['STATIC']['JOB']['backupcontentexcludedirs'], backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
		if ( $this->jobdata['WORKING']['STEPDONE'] == 1 )
			$this->jobdata['WORKING']['STEPDONE'] = 2;
		if ( $this->jobdata['STATIC']['JOB']['backupplugins'] and $this->jobdata['WORKING']['STEPDONE'] == 2 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', WP_PLUGIN_DIR)), 100,
				array_merge($this->jobdata['STATIC']['JOB']['backuppluginsexcludedirs'], backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
		if ( $this->jobdata['WORKING']['STEPDONE'] == 2 )
			$this->jobdata['WORKING']['STEPDONE'] = 3;
		if ( $this->jobdata['STATIC']['JOB']['backupthemes'] and $this->jobdata['WORKING']['STEPDONE'] == 3 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', trailingslashit(WP_CONTENT_DIR) . 'themes/')), 100,
				array_merge($this->jobdata['STATIC']['JOB']['backupthemesexcludedirs'], backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR) . 'themes/')));
		if ( $this->jobdata['WORKING']['STEPDONE'] == 3 )
			$this->jobdata['WORKING']['STEPDONE'] = 4;
		if ( $this->jobdata['STATIC']['JOB']['backupuploads'] and $this->jobdata['WORKING']['STEPDONE'] == 4 )
			$this->_folder_list(backwpup_get_upload_dir(), 100,
				array_merge($this->jobdata['STATIC']['JOB']['backupuploadsexcludedirs'], backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));
		if ( $this->jobdata['WORKING']['STEPDONE'] == 4 )
			$this->jobdata['WORKING']['STEPDONE'] = 5;

		//include dirs
		if ( !empty($this->jobdata['STATIC']['JOB']['dirinclude']) and $this->jobdata['WORKING']['STEPDONE'] == 5 ) {
			$dirinclude = explode(',', $this->jobdata['STATIC']['JOB']['dirinclude']);
			$dirinclude = array_unique($dirinclude);
			//Crate file list for includes
			foreach ( $dirinclude as $dirincludevalue ) {
				if ( is_dir($dirincludevalue) )
					$this->_folder_list($dirincludevalue);
			}
		}
		if ( $this->jobdata['WORKING']['STEPDONE'] == 5 )
			$this->jobdata['WORKING']['STEPDONE'] = 6;

		$this->jobdata['WORKING']['FOLDERLIST'] = array_unique($this->jobdata['WORKING']['FOLDERLIST']); //all files only one time in list
		sort($this->jobdata['WORKING']['FOLDERLIST']);

		if ( empty($this->jobdata['WORKING']['FOLDERLIST']) )
			trigger_error(__('No Folder to backup', 'backwpup'), E_USER_ERROR);
		else
			trigger_error(sprintf(__('%1$d Folders to backup', 'backwpup'), count($this->jobdata['WORKING']['FOLDERLIST'])), E_USER_NOTICE);

		$this->jobdata['WORKING']['STEPDONE'] = 7;
		$this->jobdata['WORKING']['STEPSDONE'][] = 'FOLDER_LIST'; //set done
		$this->_update_working_data();
	}

	private function _folder_list($folder = '', $levels = 100, $excludedirs = array()) {
		if ( empty($folder) )
			return false;
		if ( !$levels )
			return false;
		$this->_update_working_data();
		$folder = trailingslashit($folder);
		if ( $dir = @opendir($folder) ) {
			$this->jobdata['WORKING']['FOLDERLIST'][] = str_replace('\\', '/', $folder);
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..', '.svn' )) )
					continue;
				if ( is_dir($folder . $file) and !is_readable($folder . $file) ) {
					trigger_error(sprintf(__('Folder "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				} elseif ( is_dir($folder . $file) ) {
					if ( in_array(trailingslashit($folder . $file), $excludedirs) or in_array(trailingslashit($folder . $file), $this->jobdata['WORKING']['FOLDERLIST']) )
						continue;
					$this->_folder_list(trailingslashit($folder . $file), $levels - 1, $excludedirs);
				}
			}
			@closedir($dir);
		}
	}

	private function _get_files_in_folder($folder) {
		$files=array();
		if ( $dir = @opendir($folder) ) {
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..', '.svn' )) )
					continue;
				foreach ($this->jobdata['WORKING']['FILEEXCLUDES'] as $exclusion) { //exclude files
					$exclusion=trim($exclusion);
					if (false !== stripos($folder.$file,trim($exclusion)) and !empty($exclusion))
						continue 2;
				}
				if ( !is_readable($folder . $file) )
					trigger_error(sprintf(__('File "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				elseif ( is_link($folder . $file) )
					trigger_error(sprintf(__('Link "%s" not followed', 'backwpup'),$folder . $file), E_USER_WARNING);
				elseif ( is_file($folder . $file) )
					$files[]=$folder . $file;
			}
			@closedir($dir);
		}
		return $files;
	}

	private function create_archive() {
		$this->jobdata['WORKING']['STEPTODO'] = count($this->jobdata['WORKING']['FOLDERLIST']) + 1;

		if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == ".zip" and class_exists('ZipArchive') ) { //use php zip lib
			trigger_error(sprintf(__('%d. Trying to create backup zip archive...', 'backwpup'), $this->jobdata['WORKING']['CREATE_ARCHIVE']['STEP_TRY']), E_USER_NOTICE);
			$numopenfiles=0;
			$zip = new ZipArchive();
			$res = $zip->open($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'], ZipArchive::CREATE);
			if ( $res !== true ) {
				trigger_error(sprintf(__('Can not create backup zip archive: %d!', 'backwpup'), $res), E_USER_ERROR);
				$this->jobdata['WORKING']['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			}
			//add extra files
			if ($this->jobdata['WORKING']['STEPDONE']==0) {
				if ( !empty($this->jobdata['WORKING']['EXTRAFILESTOBACKUP']) and $this->jobdata['WORKING']['STEPDONE'] == 0 ) {
					foreach ( $this->jobdata['WORKING']['EXTRAFILESTOBACKUP'] as $file ) {
						if ( !$zip->addFile($file, basename($file)) )
							trigger_error(sprintf(__('Can not add "%s" to zip archive!', 'backwpup'), basename($file)), E_USER_ERROR);
						$this->_update_working_data();
						$numopenfiles++;
					}
				}
				$this->jobdata['WORKING']['STEPDONE']++;
			}
			//add normal files
			for ( $i = $this->jobdata['WORKING']['STEPDONE'] - 1; $i < $this->jobdata['WORKING']['STEPTODO']-1; $i++ ) {
				$foldername=trim(str_replace($this->jobdata['WORKING']['REMOVEPATH'], '', $this->jobdata['WORKING']['FOLDERLIST'][$i]));
				if (!empty($foldername)) {
					if ( !$zip->addEmptyDir($foldername) )
						trigger_error(sprintf(__('Can not add dir "%s" to zip archive!', 'backwpup'), $foldername), E_USER_ERROR);
				}
				$files=$this->_get_files_in_folder($this->jobdata['WORKING']['FOLDERLIST'][$i]);
				if (count($files)>0) {
					foreach($files as $file) {
						$zipfilename=str_replace($this->jobdata['WORKING']['REMOVEPATH'], '', $file);
						if ( !$zip->addFile( $file,$zipfilename ) )
							trigger_error(sprintf(__('Can not add "%s" to zip archive!', 'backwpup'), $zipfilename), E_USER_ERROR);
						$this->_update_working_data();
					}
				}
				//colse and reopen, all added files are open on fs
				if ($numopenfiles>=30) { //35 works with PHP 5.2.4 on win
					if ( $zip->status > 0 ) {
						$ziperror = $zip->status;
						if ( $zip->status == 4 )
							$ziperror = __('(4) ER_SEEK', 'backwpup');
						if ( $zip->status == 5 )
							$ziperror = __('(5) ER_READ', 'backwpup');
						if ( $zip->status == 9 )
							$ziperror = __('(9) ER_NOENT', 'backwpup');
						if ( $zip->status == 10 )
							$ziperror = __('(10) ER_EXISTS', 'backwpup');
						if ( $zip->status == 11 )
							$ziperror = __('(11) ER_OPEN', 'backwpup');
						if ( $zip->status == 14 )
							$ziperror = __('(14) ER_MEMORY', 'backwpup');
						if ( $zip->status == 18 )
							$ziperror = __('(18) ER_INVAL', 'backwpup');
						if ( $zip->status == 19 )
							$ziperror = __('(19) ER_NOZIP', 'backwpup');
						if ( $zip->status == 21 )
							$ziperror = __('(21) ER_INCONS', 'backwpup');
						trigger_error(sprintf(__('Zip returns status: %s', 'backwpup'), $zip->status), E_USER_ERROR);
					}
					$zip->close();
					if ( $this->jobdata['WORKING']['STEPDONE'] == 0 )
						$this->jobdata['WORKING']['STEPDONE'] = 1;
					$zip->open($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'], ZipArchive::CREATE );
					$numopenfiles=0;
				}
				$numopenfiles++;
				$this->jobdata['WORKING']['STEPDONE']++;
			}
			//clese Zip
			if ( $zip->status > 0 ) {
				$ziperror = $zip->status;
				if ( $zip->status == 4 )
					$ziperror = __('(4) ER_SEEK', 'backwpup');
				if ( $zip->status == 5 )
					$ziperror = __('(5) ER_READ', 'backwpup');
				if ( $zip->status == 9 )
					$ziperror = __('(9) ER_NOENT', 'backwpup');
				if ( $zip->status == 10 )
					$ziperror = __('(10) ER_EXISTS', 'backwpup');
				if ( $zip->status == 11 )
					$ziperror = __('(11) ER_OPEN', 'backwpup');
				if ( $zip->status == 14 )
					$ziperror = __('(14) ER_MEMORY', 'backwpup');
				if ( $zip->status == 18 )
					$ziperror = __('(18) ER_INVAL', 'backwpup');
				if ( $zip->status == 19 )
					$ziperror = __('(19) ER_NOZIP', 'backwpup');
				if ( $zip->status == 21 )
					$ziperror = __('(21) ER_INCONS', 'backwpup');
				trigger_error(sprintf(__('Zip returns status: %s', 'backwpup'), $zip->status), E_USER_ERROR);
			}
			$zip->close();
			trigger_error(__('Backup zip archive created', 'backwpup'), E_USER_NOTICE);
			$this->jobdata['WORKING']['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
		}
		elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == ".zip" ) { //use PclZip
			define('PCLZIP_TEMPORARY_DIR', $this->jobdata['STATIC']['CFG']['tempfolder']);
			if ( ini_get('mbstring.func_overload') && function_exists('mb_internal_encoding') ) {
				$previous_encoding = mb_internal_encoding();
				mb_internal_encoding('ISO-8859-1');
			}
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			//Create Zip File
			trigger_error(sprintf(__('%d. Trying to create backup zip (PclZip) archive...', 'backwpup'), $this->jobdata['WORKING']['CREATE_ARCHIVE']['STEP_TRY']), E_USER_NOTICE);
			$this->_need_free_memory('10M'); //10MB free memory for zip
			$zipbackupfile = new PclZip($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile']);
			//add extra files
			if ( !empty($this->jobdata['WORKING']['EXTRAFILESTOBACKUP']) and $this->jobdata['WORKING']['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['WORKING']['EXTRAFILESTOBACKUP'] as $file ) {
					if ( 0 == $zipbackupfile->add(array( array( PCLZIP_ATT_FILE_NAME => $file, PCLZIP_ATT_FILE_NEW_FULL_NAME => basename($file) ) )) )
						trigger_error(sprintf(__('Zip archive add error: %s', 'backwpup'), $zipbackupfile->errorInfo(true)), E_USER_ERROR);
					$this->_update_working_data();
				}
			}
			if ( $this->jobdata['WORKING']['STEPDONE'] == 0 )
				$this->jobdata['WORKING']['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['WORKING']['STEPDONE'] - 1; $i < $this->jobdata['WORKING']['STEPTODO']-1; $i++ ) {
				$files=$this->_get_files_in_folder($this->jobdata['WORKING']['FOLDERLIST'][$i]);
				if ( 0 == $zipbackupfile->add($files, PCLZIP_OPT_REMOVE_PATH, $this->jobdata['WORKING']['REMOVEPATH']) )
					trigger_error(sprintf(__('Zip archive add error: %s', 'backwpup'), $zipbackupfile->errorInfo(true)), E_USER_ERROR);
				$this->_update_working_data();
				$this->jobdata['WORKING']['STEPDONE']++;
			}
			if ( isset($previous_encoding) )
				mb_internal_encoding($previous_encoding);
			trigger_error(__('Backup zip archive created', 'backwpup'), E_USER_NOTICE);
			$this->jobdata['WORKING']['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done

		} elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == ".tar.gz" or strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == ".tar.bz2" or strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == ".tar" ) { //tar files
			if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.gz' )
				$tarbackup = gzopen($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'], 'ab9');
			elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.bz2' )
				$tarbackup = bzopen($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'], 'w');
			else
				$tarbackup = fopen($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile'], 'ab');
			if ( !$tarbackup ) {
				trigger_error(__('Can not create tar arcive file!', 'backwpup'), E_USER_ERROR);
				$this->jobdata['WORKING']['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			} else {
				trigger_error(sprintf(__('%1$d. Trying to create %2$s archive file...', 'backwpup'), $this->jobdata['WORKING']['CREATE_ARCHIVE']['STEP_TRY'], substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_NOTICE);
			}
			//add extra files
			if ( !empty($this->jobdata['WORKING']['EXTRAFILESTOBACKUP']) and $this->jobdata['WORKING']['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['WORKING']['EXTRAFILESTOBACKUP'] as $file )
					$this->_tar_file($file, basename($file), $tarbackup);
			}
			if ( $this->jobdata['WORKING']['STEPDONE'] == 0 )
				$this->jobdata['WORKING']['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['WORKING']['STEPDONE'] - 1; $i < $this->jobdata['WORKING']['STEPTODO']-1; $i++ ) {
				$foldername=trim(str_replace($this->jobdata['WORKING']['REMOVEPATH'], '', $this->jobdata['WORKING']['FOLDERLIST'][$i]));
				if (!empty($foldername))
					$this->_tar_foldername($this->jobdata['WORKING']['FOLDERLIST'][$i],$foldername, $tarbackup);
				$files=$this->_get_files_in_folder($this->jobdata['WORKING']['FOLDERLIST'][$i]);
				if (count($files)>0) {
					foreach($files as $file)
						$this->_tar_file($file, str_replace($this->jobdata['WORKING']['REMOVEPATH'], '', $file), $tarbackup);
				}
				$this->jobdata['WORKING']['STEPDONE']++;
				$this->_update_working_data();
			}
			// Add 1024 bytes of NULLs to designate EOF
			if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.gz' ) {
				gzwrite($tarbackup, pack("a1024", ""));
				gzclose($tarbackup);
			} elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.bz2' ) {
				bzwrite($tarbackup, pack("a1024", ""));
				bzclose($tarbackup);
			} else {
				fwrite($tarbackup, pack("a1024", ""));
				fclose($tarbackup);
			}
			trigger_error(sprintf(__('%s archive created', 'backwpup'), substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_NOTICE);
		}
		$this->jobdata['WORKING']['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
		$this->jobdata['WORKING']['backupfilesize'] = filesize($this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile']);
		if ( $this->jobdata['WORKING']['backupfilesize'] )
			trigger_error(sprintf(__('Archive size is %s', 'backwpup'), backwpup_formatBytes($this->jobdata['WORKING']['backupfilesize'])), E_USER_NOTICE);
	}

	private function _tar_file($file, $outfile, $handle) {
		$this->_need_free_memory('2M'); //2MB free memory
		//split filename larger than 100 chars
		if ( strlen($outfile) <= 100 ) {
			$filename = $outfile;
			$filenameprefix = "";
		} else {
			$filenameofset = strlen($outfile) - 100;
			$dividor = strpos($outfile, '/', $filenameofset);
			$filename = substr($outfile, $dividor + 1);
			$filenameprefix = substr($outfile, 0, $dividor);
			if ( strlen($filename) > 100 )
				trigger_error(sprintf(__('File name "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $outfile, substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_WARNING);
			if ( strlen($filenameprefix) > 155 )
				trigger_error(sprintf(__('File path "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $outfile, substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_WARNING);
		}
		//get file stat
		$filestat = stat($file);
		//Set file user/group name if linux
		$fileowner = __("Unknown","backwpup");
		$filegroup = __("Unknown","backwpup");
		if ( function_exists('posix_getpwuid') ) {
			$info = posix_getpwuid($filestat['uid']);
			$fileowner = $info['name'];
			$info = posix_getgrgid($filestat['gid']);
			$filegroup = $info['name'];
		}
		// Generate the TAR header for this file
		$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$filename, //name of file  100
			sprintf("%07o", $filestat['mode']), //file mode  8
			sprintf("%07o", $filestat['uid']), //owner user ID  8
			sprintf("%07o", $filestat['gid']), //owner group ID  8
			sprintf("%011o", $filestat['size']), //length of file in bytes  12
			sprintf("%011o", $filestat['mtime']), //modify time of file  12
			"        ", //checksum for header  8
			0, //type of file  0 or null = File, 5=Dir
			"", //name of linked file  100
			"ustar ", //USTAR indicator  6
			"00", //USTAR version  2
			$fileowner, //owner user name 32
			$filegroup, //owner group name 32
			"", //device major number 8
			"", //device minor number 8
			$filenameprefix, //prefix for file name 155
			""); //fill block 512K

		// Computes the unsigned Checksum of a file's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i++ )
			$checksum += ord(substr($header, $i, 1));
		$checksum = pack("a8", sprintf("%07o", $checksum));
		$header = substr_replace($header, $checksum, 148, 8);
		if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.gz' )
			gzwrite($handle, $header);
		elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.bz2' )
			bzwrite($handle, $header);
		else
			fwrite($handle, $header);
		// read/write files in 512K Blocks
		$fd = fopen($file, 'rb');
		while ( !feof($fd) ) {
			$filedata = fread($fd, 512);
			if ( strlen($filedata) > 0 ) {
				if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.gz' )
					gzwrite($handle, pack("a512", $filedata));
				elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.bz2' )
					bzwrite($handle, pack("a512", $filedata));
				else
					fwrite($handle, pack("a512", $filedata));
			}
		}
		fclose($fd);
	}

	private function _tar_foldername($folder, $foldername, $handle) {
		//split filename larger than 100 chars
		if ( strlen($foldername) <= 100 ) {
			$foldernameprefix = "";
		} else {
			$foldernameofset = strlen($foldername) - 100;
			$dividor = strpos($foldername, '/', $foldernameofset);
			$foldername = substr($foldername, $dividor + 1);
			$foldernameprefix = substr($foldername, 0, $dividor);
			if ( strlen($foldername) > 100 )
				trigger_error(sprintf(__('Folder name "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $foldername, substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_WARNING);
			if ( strlen($foldernameprefix) > 155 )
				trigger_error(sprintf(__('Folder path "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $foldername, substr($this->jobdata['STATIC']['JOB']['fileformart'], 1)), E_USER_WARNING);
		}
		//get file stat
		$folderstat = stat($folder);
		//Set file user/group name if linux
		$folderowner = __("Unknown","backwpup");
		$foldergroup = __("Unknown","backwpup");
		if ( function_exists('posix_getpwuid') ) {
			$info = posix_getpwuid($folderstat['uid']);
			$folderowner = $info['name'];
			$info = posix_getgrgid($folderstat['gid']);
			$foldergroup = $info['name'];
		}
		// Generate the TAR header for this file
		$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$foldername, //name of file  100
			sprintf("%07o", $folderstat['mode']), //file mode  8
			sprintf("%07o", $folderstat['uid']), //owner user ID  8
			sprintf("%07o", $folderstat['gid']), //owner group ID  8
			sprintf("%011o", 0), //length of file in bytes  12
			sprintf("%011o", $folderstat['mtime']), //modify time of file  12
			"        ", //checksum for header  8
			5, //type of file  0 or null = File, 5=Dir
			"", //name of linked file  100
			"ustar ", //USTAR indicator  6
			"00", //USTAR version  2
			$folderowner, //owner user name 32
			$foldergroup, //owner group name 32
			"", //device major number 8
			"", //device minor number 8
			$foldernameprefix, //prefix for file name 155
			""); //fill block 512K

		// Computes the unsigned Checksum of a folder's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i++ )
			$checksum += ord(substr($header, $i, 1));
		$checksum = pack("a8", sprintf("%07o", $checksum));
		$header = substr_replace($header, $checksum, 148, 8);
		if ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.gz' )
			gzwrite($handle, $header);
		elseif ( strtolower($this->jobdata['STATIC']['JOB']['fileformart']) == '.tar.bz2' )
			bzwrite($handle, $header);
		else
			fwrite($handle, $header);
	}

	private function dest_folder() {
		$this->jobdata['WORKING']['STEPTODO'] = 1;
		$this->jobdata['WORKING']['STEPDONE'] = 0;
		backwpup_update_option('job_' . $this->jobdata['STATIC']['JOB']['jobid'], 'lastbackupdownloadurl', backwpup_admin_url('admin.php') . '?page=backwpupbackups&action=download&file=' . $this->jobdata['STATIC']['JOB']['backupdir'] . $this->jobdata['STATIC']['backupfile']);
		//Delete old Backupfiles
		$backupfilelist = array();
		if ( $this->jobdata['STATIC']['JOB']['maxbackups'] > 0 ) {
			if ( $dir = @opendir($this->jobdata['STATIC']['JOB']['backupdir']) ) { //make file list
				while ( ($file = readdir($dir)) !== false ) {
					if ( $this->jobdata['STATIC']['JOB']['fileprefix'] == substr($file, 0, strlen($this->jobdata['STATIC']['JOB']['fileprefix'])) )
						$backupfilelist[filemtime($this->jobdata['STATIC']['JOB']['backupdir'] . $file)] = $file;
				}
				@closedir($dir);
			}
			if ( count($backupfilelist) > $this->jobdata['STATIC']['JOB']['maxbackups'] ) {
				$numdeltefiles = 0;
				while ( $file = array_shift($backupfilelist) ) {
					if ( count($backupfilelist) < $this->jobdata['STATIC']['JOB']['maxbackups'] )
						break;
					unlink($this->jobdata['STATIC']['JOB']['backupdir'] . $file);
					$numdeltefiles++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error(sprintf(_n('One backup file deleted', '%d backup files deleted', $numdeltefiles, 'backwpup'), $numdeltefiles), E_USER_NOTICE);
			}
		}
		$this->jobdata['WORKING']['STEPDONE']++;
		$this->jobdata['WORKING']['STEPSDONE'][] = 'DEST_FOLDER'; //set done
	}

	private function dest_folder_sync() {
		$this->jobdata['WORKING']['STEPTODO']=count($this->jobdata['WORKING']['FOLDERLIST']);
		trigger_error(sprintf(__('%d. Try to sync files with folder...','backwpup'),$this->jobdata['WORKING']['DEST_FOLDER_SYNC']['STEP_TRY']),E_USER_NOTICE);

		//create not existing folders
		foreach($this->jobdata['WORKING']['FOLDERLIST'] as $folder) {
			$testfolder=str_replace($this->jobdata['WORKING']['REMOVEPATH'], '', $folder);
			if (empty($testfolder))
				continue;
			if (!is_dir($this->jobdata['STATIC']['JOB']['backupdir'].$testfolder))
				mkdir($this->jobdata['STATIC']['JOB']['backupdir'].$testfolder,FS_CHMOD_DIR, true);
		}
		//sync folder by folder
		$this->_dest_folder_sync_files($this->jobdata['STATIC']['JOB']['backupdir']);
		$this->jobdata['WORKING']['STEPDONE']++;
		$this->jobdata['WORKING']['STEPSDONE'][] = 'DEST_FOLDER_SYNC'; //set done
	}

	private function _dest_folder_sync_files($folder = '', $levels = 100) {
		if ( empty($folder) )
			return false;
		if ( !$levels )
			return false;
		$this->_update_working_data();
		$folder = trailingslashit($folder);
		//get files to sync
		$filestosync=$this->_get_files_in_folder($this->jobdata['WORKING']['REMOVEPATH'].trim(str_replace($this->jobdata['STATIC']['JOB']['backupdir'], '', $folder)));
		if ($folder==$this->jobdata['STATIC']['JOB']['backupdir']) //add extra files to sync
			$filestosync=array_merge($filestosync,$this->jobdata['WORKING']['EXTRAFILESTOBACKUP']);

		if ( $dir = @opendir($folder) ) {
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..' )) )
					continue;
				if ( !is_readable($folder . $file) ) {
					trigger_error(sprintf(__('File or folder "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				}  elseif ( is_dir($folder . $file) ) {
					$this->_dest_folder_sync_files(trailingslashit($folder . $file), $levels - 1);
					$testfolder=str_replace($this->jobdata['STATIC']['JOB']['backupdir'], '', $folder . $file);
					if (!in_array($this->jobdata['WORKING']['REMOVEPATH'].$testfolder,$this->jobdata['WORKING']['FOLDERLIST'])) {
						rmdir($folder . $file);
						trigger_error(sprintf(__('Folder deleted %s','backwpup'),$folder . $file));
					}
				} elseif ( is_file($folder . $file) ) {
					$testfile=str_replace($this->jobdata['STATIC']['JOB']['backupdir'], '', $folder . $file);
					if (in_array($this->jobdata['WORKING']['REMOVEPATH'].$testfile,$filestosync)) {
						if (filesize($this->jobdata['WORKING']['REMOVEPATH'].$testfile)!=filesize($folder . $file))
							copy($this->jobdata['WORKING']['REMOVEPATH'].$testfile,$folder . $file);
						foreach($filestosync as $key => $keyfile) {
							if ($keyfile==$this->jobdata['WORKING']['REMOVEPATH'].$testfile)
								unset($filestosync[$key]);
						}
					} else {
						unlink($folder . $file);
						trigger_error(sprintf(__('File deleted %s','backwpup'),$folder . $file));
					}
				}
			}
			@closedir($dir);
		}
		//sync new files
		foreach($filestosync as $keyfile) {
			copy($keyfile,$folder . basename($keyfile));
		}
	}
}

function backwpup_job_curl_progressfunction($handle) {
	if ( defined('CURLOPT_PROGRESSFUNCTION') ) {
		curl_setopt($handle, CURLOPT_NOPROGRESS, false);
		curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, 'backwpup_job_curl_progresscallback');
		curl_setopt($handle, CURLOPT_BUFFERSIZE, 512);
	}
}

function backwpup_job_curl_progresscallback($download_size, $downloaded, $upload_size, $uploaded) {
	global $backwpup_job_object;
	$backwpup_job_object->update_stepdone($uploaded);
}

//start class
$backwpup_job_object = new BackWPup_job();
?>