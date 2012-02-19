<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

class BackWPup_Page_Editjob {

	public static function load() {
		global $backwpup_message;
		//Save Dropbox auth
		if ( isset($_GET['auth']) && $_GET['auth'] == 'DropBox' ) {
			$jobid = (int) $_GET['jobid'];
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'edit-job' ) ) {
				wp_nonce_ays( 'edit-job' );
				die();
			}
			$backwpup_message = '';
			if ( (int) $_GET['uid'] > 0 && ! empty($_GET['oauth_token_backwpup']) ) {
				$reqtoken = backwpup_get_option( 'temp', 'dropboxauth' );
				if ( $reqtoken['oAuthRequestToken'] == $_GET['oauth_token_backwpup'] ) {
					//Get Access Tokens
					$dropbox    = new BackWPup_Dest_Dropbox(backwpup_get_option( 'job_' . $jobid, 'droperoot' ));
					$oAuthStuff = $dropbox->oAuthAccessToken( $reqtoken['oAuthRequestToken'], $reqtoken['oAuthRequestTokenSecret'] );
					//Save Tokens
					backwpup_update_option( 'job_' . $jobid, 'dropetoken', $oAuthStuff['oauth_token'] );
					backwpup_update_option( 'job_' . $jobid, 'dropesecret', backwpup_encrypt($oAuthStuff['oauth_token_secret']) );
					$backwpup_message .= __( 'Dropbox authentication complete!', 'backwpup' ) . '<br />';
				} else {
					$backwpup_message .= __( 'Wrong Token for DropBox authentication received!', 'backwpup' ) . '<br />';
				}
			} else {
				$backwpup_message .= __( 'No DropBox authentication received!', 'backwpup' ) . '<br />';
			}
			backwpup_delete_option( 'temp', 'dropboxauth' );
			$_POST['jobid'] = $jobid;
		}

		//Save and check Job settings
		if ( (isset($_POST['save']) || isset($_POST['authbutton'])) && ! empty($_POST['jobid']) ) {
			global $wpdb;
			check_admin_referer( 'edit-job' );
			$main = 'job_' . (int) $_POST['jobid'];
			backwpup_update_option( $main, 'jobid', (int) $_POST['jobid'] );
			foreach ( (array) $_POST['type'] as $key => $value ) {
				$value = strtoupper( $value );
				if ( ! in_array( $value, backwpup_job_types() ) )
					unset($_POST['type'][$key]);
			}
			sort( $_POST['type'] );
			backwpup_update_option( $main, 'type', $_POST['type'] );
			backwpup_update_option( $main, 'name', esc_html( $_POST['name'] ) );
			if ( $_POST['activetype'] == '' || $_POST['activetype'] == 'wpcron' || $_POST['activetype'] == 'backwpupapi' )
				backwpup_update_option( $main, 'activetype', $_POST['activetype'] );
			backwpup_update_option( $main, 'cronselect', $_POST['cronselect'] == 'advanced' ? 'advanced' : 'basic' );
			if ( $_POST['cronselect'] == 'advanced' ) {
				if ( empty($_POST['cronminutes']) || $_POST['cronminutes'][0] == '*' ) {
					if ( ! empty($_POST['cronminutes'][1]) )
						$_POST['cronminutes'] = array( '*/' . $_POST['cronminutes'][1] );
					else
						$_POST['cronminutes'] = array( '*' );
				}
				if ( empty($_POST['cronhours']) || $_POST['cronhours'][0] == '*' ) {
					if ( ! empty($_POST['cronhours'][1]) )
						$_POST['cronhours'] = array( '*/' . $_POST['cronhours'][1] );
					else
						$_POST['cronhours'] = array( '*' );
				}
				if ( empty($_POST['cronmday']) || $_POST['cronmday'][0] == '*' ) {
					if ( ! empty($_POST['cronmday'][1]) )
						$_POST['cronmday'] = array( '*/' . $_POST['cronmday'][1] );
					else
						$_POST['cronmday'] = array( '*' );
				}
				if ( empty($_POST['cronmon']) || $_POST['cronmon'][0] == '*' ) {
					if ( ! empty($_POST['cronmon'][1]) )
						$_POST['cronmon'] = array( '*/' . $_POST['cronmon'][1] );
					else
						$_POST['cronmon'] = array( '*' );
				}
				if ( empty($_POST['cronwday']) || $_POST['cronwday'][0] == '*' ) {
					if ( ! empty($_POST['cronwday'][1]) )
						$_POST['cronwday'] = array( '*/' . $_POST['cronwday'][1] );
					else
						$_POST['cronwday'] = array( '*' );
				}
				$cron = implode( ",", $_POST['cronminutes'] ) . ' ' . implode( ",", $_POST['cronhours'] ) . ' ' . implode( ",", $_POST['cronmday'] ) . ' ' . implode( ",", $_POST['cronmon'] ) . ' ' . implode( ",", $_POST['cronwday'] );
				backwpup_update_option( $main, 'cron', $cron );
			} else {
				if ( $_POST['cronbtype'] == 'mon' )
					backwpup_update_option( $main, 'cron', $_POST['moncronminutes'] . ' ' . $_POST['moncronhours'] . ' ' . $_POST['moncronmday'] . ' * *' );
				if ( $_POST['cronbtype'] == 'week' )
					backwpup_update_option( $main, 'cron', $_POST['weekcronminutes'] . ' ' . $_POST['weekcronhours'] . ' * * ' . $_POST['weekcronwday'] );
				if ( $_POST['cronbtype'] == 'day' )
					backwpup_update_option( $main, 'cron', $_POST['daycronminutes'] . ' ' . $_POST['daycronhours'] . ' * * *' );
				if ( $_POST['cronbtype'] == 'hour' )
					backwpup_update_option( $main, 'cron', $_POST['hourcronminutes'] . ' * * * *' );
			}
			backwpup_update_option( $main, 'cronnextrun', BackWPup_Cron::cron_next( backwpup_get_option( $main, 'cron' ) ) );

			wp_clear_scheduled_hook( 'backwpup_cron', array( 'main'=> $main ) );
			if ( backwpup_get_option( $main, 'activetype' ) == 'wpcron' ) {
				$cronnxet = backwpup_get_option( $main, 'cronnextrun' );
				$offset   = get_option( 'gmt_offset' ) * 3600;
				wp_schedule_single_event( $cronnxet - $offset, 'backwpup_cron', array( 'main'=> $main ) );
			}

			backwpup_update_option( $main, 'mailaddresslog', sanitize_email( $_POST['mailaddresslog'] ) );
			backwpup_update_option( $main, 'mailerroronly', (isset($_POST['mailerroronly']) && $_POST['mailerroronly'] == 1) ? true : false );
			backwpup_update_option( $main, 'wpdbsettings',(isset($_POST['wpdbsettings']) && $_POST['wpdbsettings'] == 1) ? true : false );
			if (!backwpup_get_option( $main, 'wpdbsettings')) {
				backwpup_update_option( $main, 'dbhost', $_POST['dbhost'] );
				backwpup_update_option( $main, 'dbuser', $_POST['dbuser'] );
				backwpup_update_option( $main, 'dbpassword', backwpup_encrypt($_POST['dbpassword']) );
				backwpup_update_option( $main, 'dbname', trim($_POST['dbname']) );
				$charset=explode('_',trim($_POST['dbcollation']));
				backwpup_update_option( $main, 'dbcharset', $charset[0] );
				backwpup_update_option( $main, 'dbcollation', trim($_POST['dbcollation']) );
			} else {
				backwpup_delete_option( $main, 'dbhost' );
				backwpup_delete_option( $main, 'dbuser' );
				backwpup_delete_option( $main, 'dbpassword' );
				backwpup_delete_option( $main, 'dbname' );
				backwpup_delete_option( $main, 'dbcharset' );
				backwpup_delete_option( $main, 'dbcollation' );
			}
			//connect to db
			$backwpupsql=new wpdb(backwpup_get_option( $main, 'dbuser' ),backwpup_decrypt(backwpup_get_option($main, 'dbpassword' )),backwpup_get_option( $main, 'dbname' ),backwpup_get_option( $main, 'dbhost' ));
			$backwpupsql->set_charset($backwpupsql->dbh,backwpup_get_option( $main, 'dbcharset' ),backwpup_get_option( $main, 'dbcollation' ));
			$check_db_tables = array();
			if ( isset($_POST['jobtabs']) ) {
				foreach ( $_POST['jobtabs'] as $dbtable ) {
					$check_db_tables[] = rawurldecode( $dbtable );
				}
			}
			$tables    = $backwpupsql->get_col( 'SHOW TABLES FROM `' . backwpup_get_option( $main, 'dbname' ) . '`' );
			$dbexclude = array();
			foreach ( $tables as $dbtable ) {
				if ( ! in_array( $dbtable, $check_db_tables ) )
					$dbexclude[] = $dbtable;
			}
			backwpup_update_option( $main, 'dbexclude', $dbexclude );
			backwpup_update_option( $main, 'dbdumpfile', $_POST['dbdumpfile'] );
			unset($backwpupsql);
			if ( $_POST['dbdumpfilecompression'] == '' || $_POST['dbdumpfilecompression'] == 'gz' || $_POST['dbdumpfilecompression'] == 'bz2' )
				backwpup_update_option( $main, 'dbdumpfilecompression', $_POST['dbdumpfilecompression'] );
			backwpup_update_option( $main, 'maintenance', (isset($_POST['maintenance']) && $_POST['maintenance'] == 1) ? true : false );
			backwpup_update_option( $main, 'wpexportfile', $_POST['wpexportfile'] );
			backwpup_update_option( $main, 'pluginlistfile', $_POST['pluginlistfile'] );
			if ( $_POST['wpexportfilecompression'] == '' || $_POST['wpexportfilecompression'] == 'gz' || $_POST['wpexportfilecompression'] == 'bz2' )
				backwpup_update_option( $main, 'wpexportfilecompression', $_POST['wpexportfilecompression'] );
			$fileexclude = explode( ',', stripslashes( $_POST['fileexclude'] ) );
			foreach ( $fileexclude as $key => $value ) {
				$fileexclude[$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($fileexclude[$key]) )
					unset($fileexclude[$key]);
			}
			sort( $fileexclude );
			backwpup_update_option( $main, 'fileexclude', implode( ',', $fileexclude ) );
			$dirinclude = explode( ',', stripslashes( $_POST['dirinclude'] ) );
			foreach ( $dirinclude as $key => $value ) {
				$dirinclude[$key] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) ) );
				if ( $dirinclude[$key] == '/' || empty($dirinclude[$key]) || ! is_dir( $dirinclude[$key] ) )
					unset($dirinclude[$key]);
			}
			sort( $dirinclude );
			backwpup_update_option( $main, 'dirinclude', implode( ',', $dirinclude ) );
			backwpup_update_option( $main, 'backupexcludethumbs', (isset($_POST['backupexcludethumbs']) && $_POST['backupexcludethumbs'] == 1) ? true : false );
			backwpup_update_option( $main, 'backupspecialfiles', (isset($_POST['backupspecialfiles']) && $_POST['backupspecialfiles'] == 1) ? true : false );
			backwpup_update_option( $main, 'backuproot', (isset($_POST['backuproot']) && $_POST['backuproot'] == 1) ? true : false );
			if ( ! isset($_POST['backuprootexcludedirs']) || ! is_array( $_POST['backuprootexcludedirs'] ) )
				$_POST['backuprootexcludedirs'] = array();
			foreach ( $_POST['backuprootexcludedirs'] as $key => $value ) {
				$_POST['backuprootexcludedirs'][$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($_POST['backuprootexcludedirs'][$key]) || $_POST['backuprootexcludedirs'][$key] == '/' || ! is_dir( $_POST['backuprootexcludedirs'][$key] ) )
					unset($_POST['backuprootexcludedirs'][$key]);
			}
			sort( $_POST['backuprootexcludedirs'] );
			backwpup_update_option( $main, 'backuprootexcludedirs', $_POST['backuprootexcludedirs'] );
			backwpup_update_option( $main, 'backupcontent', (isset($_POST['backupcontent']) && $_POST['backupcontent'] == 1) ? true : false );
			if ( ! isset($_POST['backupcontentexcludedirs']) || ! is_array( $_POST['backupcontentexcludedirs'] ) )
				$_POST['backupcontentexcludedirs'] = array();
			foreach ( $_POST['backupcontentexcludedirs'] as $key => $value ) {
				$_POST['backupcontentexcludedirs'][$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($_POST['backupcontentexcludedirs'][$key]) || $_POST['backupcontentexcludedirs'][$key] == '/' || ! is_dir( $_POST['backupcontentexcludedirs'][$key] ) )
					unset($_POST['backupcontentexcludedirs'][$key]);
			}
			sort( $_POST['backupcontentexcludedirs'] );
			backwpup_update_option( $main, 'backupcontentexcludedirs', $_POST['backupcontentexcludedirs'] );
			backwpup_update_option( $main, 'backupplugins', (isset($_POST['backupplugins']) && $_POST['backupplugins'] == 1) ? true : false );
			if ( ! isset($_POST['backuppluginsexcludedirs']) || ! is_array( $_POST['backuppluginsexcludedirs'] ) )
				$_POST['backuppluginsexcludedirs'] = array();
			foreach ( $_POST['backuppluginsexcludedirs'] as $key => $value ) {
				$_POST['backuppluginsexcludedirs'][$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($_POST['backuppluginsexcludedirs'][$key]) || $_POST['backuppluginsexcludedirs'][$key] == '/' || ! is_dir( $_POST['backuppluginsexcludedirs'][$key] ) )
					unset($_POST['backuppluginsexcludedirs'][$key]);
			}
			sort( $_POST['backuppluginsexcludedirs'] );
			backwpup_update_option( $main, 'backuppluginsexcludedirs', $_POST['backuppluginsexcludedirs'] );
			backwpup_update_option( $main, 'backupthemes', (isset($_POST['backupthemes']) && $_POST['backupthemes'] == 1) ? true : false );
			if ( ! isset($_POST['backupthemesexcludedirs']) || ! is_array( $_POST['backupthemesexcludedirs'] ) )
				$_POST['backupthemesexcludedirs'] = array();
			foreach ( $_POST['backupthemesexcludedirs'] as $key => $value ) {
				$_POST['backupthemesexcludedirs'][$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($_POST['backupthemesexcludedirs'][$key]) || $_POST['backupthemesexcludedirs'][$key] == '/' || ! is_dir( $_POST['backupthemesexcludedirs'][$key] ) )
					unset($_POST['backupthemesexcludedirs'][$key]);
			}
			sort( $_POST['backupthemesexcludedirs'] );
			backwpup_update_option( $main, 'backupthemesexcludedirs', $_POST['backupthemesexcludedirs'] );
			backwpup_update_option( $main, 'backupuploads', (isset($_POST['backupuploads']) && $_POST['backupuploads'] == 1) ? true : false );
			if ( ! isset($_POST['backupuploadsexcludedirs']) || ! is_array( $_POST['backupuploadsexcludedirs'] ) )
				$_POST['backupuploadsexcludedirs'] = array();
			foreach ( $_POST['backupuploadsexcludedirs'] as $key => $value ) {
				$_POST['backupuploadsexcludedirs'][$key] = str_replace( '//', '/', str_replace( '\\', '/', trim( $value ) ) );
				if ( empty($_POST['backupuploadsexcludedirs'][$key]) || $_POST['backupuploadsexcludedirs'][$key] == '/' || ! is_dir( $_POST['backupuploadsexcludedirs'][$key] ) )
					unset($_POST['backupuploadsexcludedirs'][$key]);
			}
			sort( $_POST['backupuploadsexcludedirs'] );
			backwpup_update_option( $main, 'backupuploadsexcludedirs', $_POST['backupuploadsexcludedirs'] );
			backwpup_update_option( $main, 'backuptype', $_POST['backuptype'] );
			backwpup_update_option( $main, 'fileformart', $_POST['fileformart'] );
			backwpup_update_option( $main, 'mailefilesize', isset($_POST['mailefilesize']) ? (float) $_POST['mailefilesize'] : 0 );
			$_POST['backupdir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes($_POST['backupdir']) ) ) ) );
			if ( $_POST['backupdir'][0]=='.' || ($_POST['backupdir'][0]!='/' && !preg_match('#^[a-zA-Z]:/#', $_POST['backupdir'])))
				$_POST['backupdir'] = trailingslashit( str_replace( '\\', '/', ABSPATH ) ). $_POST['backupdir'];
			if ( $_POST['backupdir'] == '/' )
				$_POST['backupdir'] = '';
			backwpup_update_option( $main, 'backupdir', $_POST['backupdir'] );
			backwpup_update_option( $main, 'maxbackups', isset($_POST['maxbackups']) ? (int) $_POST['maxbackups'] : 0 );
			backwpup_update_option( $main, 'backupsyncnodelete', (isset($_POST['backupsyncnodelete']) && $_POST['backupsyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'ftpsyncnodelete', (isset($_POST['ftpsyncnodelete']) && $_POST['ftpsyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'awssyncnodelete', (isset($_POST['awssyncnodelete']) && $_POST['awssyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'GStoragesyncnodelete', (isset($_POST['GStoragesyncnodelete']) && $_POST['backupsyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'msazuresyncnodelete', (isset($_POST['msazuresyncnodelete']) && $_POST['msazuresyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'rscsyncnodelete', (isset($_POST['rscsyncnodelete']) && $_POST['rscsyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'dropesyncnodelete', (isset($_POST['dropesyncnodelete']) && $_POST['dropesyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'sugarsyncnodelete', (isset($_POST['sugarsyncnodelete']) && $_POST['sugarsyncnodelete'] == 1) ? true : false );
			backwpup_update_option( $main, 'ftphost', isset($_POST['ftphost']) ? $_POST['ftphost'] : '' );
			backwpup_update_option( $main, 'ftphostport', ! empty($_POST['ftphostport']) ? (int) $_POST['ftphostport'] : 21 );
			backwpup_update_option( $main, 'ftptimeout', ! empty($_POST['ftptimeout']) ? (int) $_POST['ftptimeout'] : 10 );
			backwpup_update_option( $main, 'ftpuser', isset($_POST['ftpuser']) ? $_POST['ftpuser'] : '' );
			backwpup_update_option( $main, 'ftppass', isset($_POST['ftppass']) ? backwpup_encrypt( $_POST['ftppass'] ) : '' );
			$_POST['ftpdir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['ftpdir'] ) ) ) ) );
			if ( substr( $_POST['ftpdir'], 0, 1 ) != '/' )
				$_POST['ftpdir'] = '/' . $_POST['ftpdir'];
			if ( $_POST['ftpdir'] == '/' )
				$_POST['ftpdir'] = '';
			backwpup_update_option( $main, 'ftpdir', $_POST['ftpdir'] );
			backwpup_update_option( $main, 'ftpmaxbackups', isset($_POST['ftpmaxbackups']) ? (int) $_POST['ftpmaxbackups'] : 0 );
			backwpup_update_option( $main, 'ftpssl', (isset($_POST['ftpssl']) && $_POST['ftpssl'] == 1) ? true : false );
			backwpup_update_option( $main, 'ftppasv', (isset($_POST['ftppasv']) && $_POST['ftppasv'] == 1) ? true : false );
			backwpup_update_option( $main, 'dropemaxbackups', isset($_POST['dropemaxbackups']) ? (int) $_POST['dropemaxbackups'] : 0 );
			backwpup_update_option( $main, 'droperoot', (isset($_POST['droperoot']) && $_POST['droperoot'] == 'dropbox') ? 'dropbox' : 'sandbox' );
			$_POST['dropedir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['dropedir'] ) ) ) ) );
			if ( substr( $_POST['dropedir'], 0, 1 ) == '/' )
				$_POST['dropedir'] = substr( $_POST['dropedir'], 1 );
			if ( $_POST['dropedir'] == '/' )
				$_POST['dropedir'] = '';
			backwpup_update_option( $main, 'dropedir', $_POST['dropedir'] );
			backwpup_update_option( $main, 'awsAccessKey', isset($_POST['awsAccessKey']) ? $_POST['awsAccessKey'] : '' );
			backwpup_update_option( $main, 'awsSecretKey', isset($_POST['awsSecretKey']) ? $_POST['awsSecretKey'] : '' );
			backwpup_update_option( $main, 'awsrrs', (isset($_POST['awsrrs']) && $_POST['awsrrs'] == 1) ? true : false );
			backwpup_update_option( $main, 'awsdisablessl', (isset($_POST['awsdisablessl']) && $_POST['awsdisablessl'] == 1) ? true : false );
			backwpup_update_option( $main, 'awsssencrypt', (isset($_POST['awsssencrypt']) && $_POST['awsssencrypt'] == 'AES256') ? 'AES256' : '' );
			backwpup_update_option( $main, 'awsBucket', isset($_POST['awsBucket']) ? $_POST['awsBucket'] : '' );
			$_POST['awsdir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['awsdir'] ) ) ) ) );
			if ( substr( $_POST['awsdir'], 0, 1 ) == '/' )
				$_POST['awsdir'] = substr( $_POST['awsdir'], 1 );
			if ( $_POST['awsdir'] == '/' )
				$_POST['awsdir'] = '';
			backwpup_update_option( $main, 'awsdir', $_POST['awsdir'] );
			backwpup_update_option( $main, 'awsmaxbackups', isset($_POST['awsmaxbackups']) ? (int) $_POST['awsmaxbackups'] : 0 );
			backwpup_update_option( $main, 'GStorageAccessKey', isset($_POST['GStorageAccessKey']) ? $_POST['GStorageAccessKey'] : '' );
			backwpup_update_option( $main, 'GStorageSecret', isset($_POST['GStorageSecret']) ? $_POST['GStorageSecret'] : '' );
			backwpup_update_option( $main, 'GStorageBucket', isset($_POST['GStorageBucket']) ? $_POST['GStorageBucket'] : '' );
			$_POST['GStoragedir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['GStoragedir'] ) ) ) ) );
			if ( substr( $_POST['GStoragedir'], 0, 1 ) == '/' )
				$_POST['GStoragedir'] = substr( $_POST['GStoragedir'], 1 );
			if ( $_POST['GStoragedir'] == '/' )
				$_POST['GStoragedir'] = '';
			backwpup_update_option( $main, 'GStoragedir', $_POST['GStoragedir'] );
			backwpup_update_option( $main, 'GStoragemaxbackups', isset($_POST['GStoragemaxbackups']) ? (int) $_POST['GStoragemaxbackups'] : 0 );
			backwpup_update_option( $main, 'msazureHost', isset($_POST['msazureHost']) ? $_POST['msazureHost'] : 'blob.core.windows.net' );
			backwpup_update_option( $main, 'msazureAccName', isset($_POST['msazureAccName']) ? $_POST['msazureAccName'] : '' );
			backwpup_update_option( $main, 'msazureKey', isset($_POST['msazureKey']) ? $_POST['msazureKey'] : '' );
			backwpup_update_option( $main, 'msazureContainer', isset($_POST['msazureContainer']) ? $_POST['msazureContainer'] : '' );
			$_POST['msazuredir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['msazuredir'] ) ) ) ) );
			if ( substr( $_POST['msazuredir'], 0, 1 ) == '/' )
				$_POST['msazuredir'] = substr( $_POST['msazuredir'], 1 );
			if ( $_POST['msazuredir'] == '/' )
				$_POST['msazuredir'] = '';
			backwpup_update_option( $main, 'msazuredir', $_POST['msazuredir'] );
			backwpup_update_option( $main, 'msazuremaxbackups', isset($_POST['msazuremaxbackups']) ? (int) $_POST['msazuremaxbackups'] : 0 );
			backwpup_update_option( $main, 'sugaruser', isset($_POST['sugaruser']) ? $_POST['sugaruser'] : '' );
			backwpup_update_option( $main, 'sugarpass', isset($_POST['sugarpass']) ? backwpup_encrypt( $_POST['sugarpass'] ) : '' );
			$_POST['sugardir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['sugardir'] ) ) ) ) );
			if ( substr( $_POST['sugardir'], 0, 1 ) == '/' )
				$_POST['sugardir'] = substr( $_POST['sugardir'], 1 );
			if ( $_POST['sugardir'] == '/' )
				$_POST['sugardir'] = '';
			backwpup_update_option( $main, 'sugardir', $_POST['sugardir'] );
			backwpup_update_option( $main, 'sugarroot', isset($_POST['sugarroot']) ? $_POST['sugarroot'] : '' );
			backwpup_update_option( $main, 'sugarmaxbackups', isset($_POST['sugarmaxbackups']) ? (int) $_POST['sugarmaxbackups'] : 0 );
			backwpup_update_option( $main, 'rscUsername', isset($_POST['rscUsername']) ? $_POST['rscUsername'] : '' );
			backwpup_update_option( $main, 'rscAPIKey', isset($_POST['rscAPIKey']) ? $_POST['rscAPIKey'] : '' );
			backwpup_update_option( $main, 'rscContainer', isset($_POST['rscContainer']) ? $_POST['rscContainer'] : '' );
			$_POST['rscdir'] = trailingslashit( str_replace( '//', '/', str_replace( '\\', '/', trim( stripslashes( $_POST['rscdir'] ) ) ) ) );
			if ( substr( $_POST['rscdir'], 0, 1 ) == '/' )
				$_POST['rscdir'] = substr( $_POST['rscdir'], 1 );
			if ( $_POST['rscdir'] == '/' )
				$_POST['rscdir'] = '';
			backwpup_update_option( $main, 'rscdir', $_POST['rscdir'] );
			backwpup_update_option( $main, 'rscmaxbackups', isset($_POST['rscmaxbackups']) ? (int) $_POST['rscmaxbackups'] : 0 );
			backwpup_update_option( $main, 'mailaddress', isset($_POST['mailaddress']) ? sanitize_email( $_POST['mailaddress'] ) : '' );


			if ( ! empty($_POST['newawsBucket']) && ! empty($_POST['awsAccessKey']) && ! empty($_POST['awsSecretKey']) ) { //create new s3 bucket if needed
				try {
					$s3 = new AmazonS3(array( 'key'				  => $_POST['awsAccessKey'],
											  'secret'			   => $_POST['awsSecretKey'],
											  'certificate_authority'=> true ));
					$s3->disable_ssl( backwpup_get_option( $main, 'awsdisablessl' ) );
					$req = $s3->create_bucket( $_POST['newawsBucket'], $_POST['awsRegion'] );
					if ( empty($req->body->Message) ) {
						$backwpup_message .= sprintf( __( 'S3 bucket "%s" created.', 'backwpup' ), $_POST['newawsBucket'] ) . '<br />';
						backwpup_update_option( $main, 'awsBucket', $_POST['newawsBucket'] );
					} else {
						$backwpup_message .= sprintf( __( 'S3 bucket create: %s', 'backwpup' ), $req->body->Message ) . '<br />';
					}
				} catch ( Exception $e ) {
					$backwpup_message .= sprintf( __( 'S3 bucket create: %s', 'backwpup' ), $e->getMessage() ) . '<br />';
				}
			}

			if ( ! empty($_POST['newmsazureContainer']) && ! empty($_POST['msazureHost']) && ! empty($_POST['msazureAccName']) && ! empty($_POST['msazureKey']) ) { //create new s3 bucket if needed
				try {
					$storageClient = new Microsoft_WindowsAzure_Storage_Blob($_POST['msazureHost'], $_POST['msazureAccName'], $_POST['msazureKey']);
					$result        = $storageClient->createContainer( $_POST['newmsazureContainer'] );
					if ( ! empty($result->Name) ) {
						backwpup_update_option( $main, 'msazureContainer', $result->Name );
						$backwpup_message .= sprintf( __( 'MS azure container "%s" created.', 'backwpup' ), $result->Name ) . '<br />';
					}
				} catch ( Exception $e ) {
					$backwpup_message .= sprintf( __( 'MS azure container create: %s', 'backwpup' ), $e->getMessage() ) . '<br />';
				}
			}

			if ( ! empty($_POST['rscUsername']) && ! empty($_POST['rscAPIKey']) && ! empty($_POST['newrscContainer']) ) { //create new Rackspase Container if needed
				try {
					$auth = new CF_Authentication($_POST['rscUsername'], $_POST['rscAPIKey']);
					if ( $auth->authenticate() ) {
						$conn             = new CF_Connection($auth);
						$public_container = $conn->create_container( $_POST['newrscContainer'] );
						$public_container->make_private();
						backwpup_update_option( $main, 'rscContainer', $_POST['newrscContainer'] );
						$backwpup_message .= sprintf( __( 'Rackspase Cloud container "%s" created.', 'backwpup' ), $_POST['newrscContainer'] ) . '<br />';
					}
				} catch ( Exception $e ) {
					$backwpup_message .= sprintf( __( 'Rackspase Cloud container create: %s', 'backwpup' ), $e->getMessage() ) . '<br />';
				}
			}


			if ( isset($_POST['authbutton']) && $_POST['authbutton'] == __( 'Delete DropBox authentication!', 'backwpup' ) ) {
				backwpup_update_option( $main, 'dropetoken', '' );
				backwpup_update_option( $main, 'dropesecret', '' );
				$backwpup_message .= __( 'Dropbox authentication deleted!', 'backwpup' ) . '<br />';
			}

			//get DropBox auth
			if ( isset($_POST['authbutton']) && $_POST['authbutton'] == __( 'DropBox authenticate!', 'backwpup' ) ) {
				$dropbox = new BackWPup_Dest_Dropbox(backwpup_get_option( $main, 'droperoot' ));
				// let the user authorize (user will be redirected)
				$response = $dropbox->oAuthAuthorize( backwpup_admin_url( 'admin.php' ) . '?page=backwpupeditjob&jobid=' . backwpup_get_option( $main, 'jobid' ) . '&auth=DropBox&_wpnonce=' . wp_create_nonce( 'edit-job' ) );
				// save oauth_token_secret
				backwpup_update_option( 'temp', 'dropboxauth', array( 'oAuthRequestToken'	   => $response['oauth_token'],
																	  'oAuthRequestTokenSecret' => $response['oauth_token_secret'] ) );
				//forward to auth page
				wp_redirect( $response['authurl'] );
			}

			//make api call to backwpup.com
			do_action( 'backwpup_api_cron_update' );

			$_POST['jobid'] = backwpup_get_option( $main, 'jobid' );
			$url            = backwpup_jobrun_url( 'runnow', backwpup_get_option( $main, 'jobid' ), false );
			$backwpup_message .= str_replace( '%1', backwpup_get_option( $main, 'name' ), __( 'Job \'%1\' changes saved.', 'backwpup' ) ) . ' <a href="' . backwpup_admin_url( 'admin.php' ) . '?page=backwpup">' . __( 'Jobs overview', 'backwpup' ) . '</a> | <a href="' . $url['url'] . '">' . __( 'Run now', 'backwpup' ) . '</a>';
		}


		$dests = explode( ',', strtoupper( BACKWPUP_DESTS ) );
		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		add_meta_box( 'backwpup_jobedit_backupfile', __( 'Backup File', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'backupfile' ), get_current_screen()->id, 'side', 'default' );
		add_meta_box( 'backwpup_jobedit_sendlog', __( 'Send log', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'sendlog' ), get_current_screen()->id, 'side', 'default' );
		if ( in_array( 'FOLDER', $dests ) )
			add_meta_box( 'backwpup_jobedit_destfolder', __( 'Backup to Folder', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destfolder' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'MAIL', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destmail', __( 'Backup to E-Mail', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destmail' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'FTP', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destftp', __( 'Backup to FTP Server', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destftp' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'DROPBOX', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destdropbox', __( 'Backup to Dropbox', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destdropbox' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'SUGARSYNC', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destsugarsync', __( 'Backup to SugarSync', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destsugarsync' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'S3', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_dests3', __( 'Backup to Amazon S3', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'dests3' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'GSTORAGE', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destgstorage', __( 'Backup to Google storage', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destgstorage' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'MSAZURE', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destazure', __( 'Backup to Micosoft Azure (Blob)', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destazure' ), get_current_screen()->id, 'normal', 'default' );
		if ( in_array( 'RSC', $dests ) )
			add_meta_box( 'nosync_backwpup_jobedit_destrsc', __( 'Backup to Rackspace Cloud', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'destrsc' ), get_current_screen()->id, 'normal', 'default' );
		add_filter( 'hidden_meta_boxes', array( 'BackWPup_Page_Editjob_Metaboxes', 'displayneeded' ) );

		//add columns
		add_screen_option( 'layout_columns', array( 'max'	 => 2,
													'default' => 2 ) );

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab( array(
			'id'		 => 'overview',
			'title'	  => __( 'Overview' ),
			'content'	=>
			'<p>' . '</p>'
		) );

	}


	/**
	 *
	 * Output javascript
	 *
	 * @return nothing
	 */
	public static function javascript() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'backwpup_editjob', plugins_url( '', dirname( __FILE__ ) ) . '/js/editjob.js', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), true );
		wp_localize_script('backwpup_editjob','BackWPup',array('ajaxurl'=>plugins_url( '', dirname( __FILE__ ) ) . '/ajax.php','abspath'=>ABSPATH));
	}

	/**
	 *
	 * Output css
	 *
	 * @return nothing
	 */
	public static function css() {
		wp_enqueue_style( 'backwpup_editjob', plugins_url( '', dirname( __FILE__ ) ) . '/css/editjob.css', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), 'screen' );
	}


	public static function page() {
		global $wpdb, $screen_layout_columns, $backwpup_message;
		if ( ! empty($_REQUEST['jobid']) )
			check_admin_referer( 'edit-job' );

		//may be needed to ensure that a special box is always available
		add_meta_box( 'backwpup_jobedit_save', __( 'Job Type', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'save' ), get_current_screen()->id, 'side', 'high' );
		add_meta_box( 'backwpup_jobedit_schedule', __( 'Job Schedule', 'backwpup' ), array( 'BackWPup_Page_Editjob_Metaboxes', 'schedule' ), get_current_screen()->id, 'side', 'core' );

		//generate jobid if not exists
		if ( empty($_REQUEST['jobid']) ) {
			$_REQUEST['jobid'] = $wpdb->get_var( "SELECT value FROM `" . $wpdb->prefix . "backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC LIMIT 1", 0, 0 );
			$_REQUEST['jobid'] ++;
		}
		$main = 'job_' . (int) $_REQUEST['jobid'];

		?>
	<div class="wrap">
		<?php
		screen_icon();
		echo "<h2>" . esc_html( __( 'BackWPup Job Settings', 'backwpup' ) ) . "&nbsp;<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpupeditjob', 'edit-job' ) . "\" class=\"button add-new-h2\">" . esc_html__( 'Add New', 'backwpup' ) . "</a></h2>";
		?>

		<?php if ( isset($backwpup_message) && ! empty($backwpup_message) ) : ?>
	<div id="message" class="updated"><p><?php echo $backwpup_message; ?></p></div>
		<?php endif; ?>

	<form name="editjob" id="editjob" method="post" action="<?php echo backwpup_admin_url( 'admin.php' ) . '?page=backwpupeditjob';?>">
	<input type="hidden" id="jobid" name="jobid" value="<?php echo (int) $_REQUEST['jobid'];?>" />
		<?php wp_nonce_field( 'edit-job' ); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php wp_nonce_field( 'backwpupeditjob_ajax_nonce', 'backwpupeditjobajaxnonce', false ); ?>
	<div id="poststuff" class="metabox-holder<?php echo 1 != $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
	<div id="side-info-column" class="inner-sidebar">
		<?php
		$side_meta_boxes = do_meta_boxes( get_current_screen()->id, 'side', $main );
		?>
	</div>

	<div id="post-body">
	<div id="post-body-content">

	<div id="titlediv">
		<div id="titlewrap">
			<label class="hide-if-no-js" style="visibility:hidden" id="title-prompt-text" for="title"><?php _e( 'Enter Job name here', 'backwpup' ); ?></label>
			<input type="text" name="name" size="30" tabindex="1" value="<?php echo backwpup_get_option( $main, 'name' );?>" id="title" autocomplete="off" />
		</div>
	</div>

	<div class="inside">
		<div>
			<?php
			if ( backwpup_get_option( 'cfg', 'jobrunauthkey' ) ) {
				echo '<strong>' . __( 'External start link:', 'backwpup' ) . '</strong> ';
				$url = backwpup_jobrun_url( 'runext', backwpup_get_option( $main, 'jobid' ), false );
				echo '<span>' . $url['url'] . '</span><br />';
			}
			echo '<strong>' . __( 'Commandline start:', 'backwpup' ) . '</strong> ';
			$abspath = '';
			if ( WP_PLUGIN_DIR != ABSPATH . 'wp-content/plugins' )
				$abspath = '-abspath=' . str_replace( '\\', '/', ABSPATH );
			echo "<span>" . sprintf( 'php %1$s -jobid=%2$d %3$s', realpath( dirname( __FILE__ ) . '/../job.php' ), backwpup_get_option( $main, 'jobid' ), $abspath ) . "</span><br />";
			?>
		</div>
	</div>

	<div id="databasejobs" class="stuffbox"<?php if ( ! in_array( "OPTIMIZE", backwpup_get_option( $main, 'type' ) ) && ! in_array( "DB", backwpup_get_option( $main, 'type' ) ) && ! in_array( "CHECK", backwpup_get_option( $main, 'type' ) ) ) echo ' style="display:none;"';?>>
		<h3><?php _e( 'Database Jobs', 'backwpup' ); ?></h3>
		<div class="inside">
			<div>
				<b><?php _e( 'Database connection for use:', 'backwpup' ); ?></b><br />
				<input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'wpdbsettings' ), true, true );?> name="wpdbsettings" value="1" /> <?php _e( 'Use WordPress DB connection.', 'backwpup' );?><br />
				<div id="dbconnection"<?php if (backwpup_get_option( $main, 'wpdbsettings' )) echo ' style="display:none;"';?>>
					<?php _e( 'Host:', 'backwpup' );?><br />
					<input class="text" type="text" id="dbhost" name="dbhost" value="<?php echo backwpup_get_option( $main, 'dbhost' );?>" /><br />
					<?php _e( 'User:', 'backwpup' );?><br />
					<input class="text" type="text" id="dbuser" name="dbuser" value="<?php echo backwpup_get_option( $main, 'dbuser' );?>" /><br />
					<?php _e( 'Password:', 'backwpup' );?><br />
					<input class="text" type="password" id="dbpassword" name="dbpassword" value="<?php echo backwpup_decrypt(backwpup_get_option( $main, 'dbpassword' ));?>" /><br />
					<?php _e( 'Collation:', 'backwpup' );?><br />
						<select id="dbcollation" name="dbcollation">
							<?php
								$charset='';
							  	$colations=$wpdb->get_results('SHOW COLLATION',ARRAY_A);
							  	foreach($colations as $colation ) {
									  if ($charset=='') {
										  echo '<optgroup label="'.$colation['Charset'].'">';
										  $charset=$colation['Charset'];
									  }
									if ($charset!=$colation['Charset']) {
										echo '</optgroup>';
										echo '<optgroup label="'.$colation['Charset'].'">';
										$charset=$colation['Charset'];
									}
									$selected='';
									if (backwpup_get_option( $main, 'dbcharset' )==$colation['Charset']) {
										if (!backwpup_get_option( $main, 'dbcollation' ) && !empty($colation['Default']))
											$selected=' selected="selected"';
										if (backwpup_get_option($main, 'dbcollation' )==$colation['Collation'])
											$selected=' selected="selected"';
									}
									echo '<option value="'.$colation['Collation'].'"'.$selected.'>'.$colation['Collation'].'</option>';
							  	}
								echo '</optgroup>';
							?>
						</select>
					<br />
					<?php _e( 'Database:', 'backwpup' );?><br />
					<?php
					BackWPup_Ajax_Editjob::db_databases(array('dbselected'=>backwpup_get_option( $main, 'dbname' ),'dbuser' =>backwpup_get_option( $main, 'dbuser' ),
														   'dbpassword'=>backwpup_get_option( $main, 'dbpassword' ),'dbhost'=>backwpup_get_option( $main, 'dbhost' ),
														   'dbcharset'=>backwpup_get_option( $main, 'dbcharset' )));
					?>
					<br />
				</div>
				<b><?php _e( 'Database tables for use:', 'backwpup' ); ?></b>
				<input type="button" id="dball" value="<?php _e( 'all', 'backwpup' ); ?>"> <input type="button" id="dbnone" value="<?php _e( 'none', 'backwpup' ); ?>"> <input type="button" id="dbwp" value="<?php echo $wpdb->prefix; ?>">

				<?php
				BackWPup_Ajax_Editjob::db_tables(array('dbname'=>backwpup_get_option( $main, 'dbname' ),'dbuser' =>backwpup_get_option( $main, 'dbuser' ),
													   'dbpassword'=>backwpup_get_option( $main, 'dbpassword' ),'dbhost'=>backwpup_get_option( $main, 'dbhost' ),
													   'dbcharset'=>backwpup_get_option( $main, 'dbcharset' ),'jobmain'=>$main));
				?>

			</div>
						<span id="dbdump"<?php if ( ! in_array( "DB", backwpup_get_option( $main, 'type' ) ) ) echo ' style="display:none;"';?>>
						<strong><?php _e( 'Filename for Dump:', 'backwpup' );?></strong> <input class="long-text" type="text" name="dbdumpfile" value="<?php echo backwpup_get_option( $main, 'dbdumpfile' );?>" />.sql
						<br /><strong><?php _e( 'Copmpression for dump:', 'backwpup' );?></strong>
							<?php
							echo ' <input class="radio" type="radio"' . checked( '', backwpup_get_option( $main, 'dbdumpfilecompression' ), false ) . ' name="dbdumpfilecompression" value="" />' . __( 'none', 'backwpup' );
							if ( function_exists( 'gzopen' ) )
								echo ' <input class="radio" type="radio"' . checked( 'gz', backwpup_get_option( $main, 'dbdumpfilecompression' ), false ) . ' name="dbdumpfilecompression" value="gz" />' . __( 'GZip', 'backwpup' );
							else
								echo ' <input class="radio" type="radio"' . checked( 'gz', backwpup_get_option( $main, 'dbdumpfilecompression' ), false ) . ' name="dbdumpfilecompression" value="gz" disabled="disabled" />' . __( 'GZip', 'backwpup' );
							if ( function_exists( 'bzopen' ) )
								echo ' <input class="radio" type="radio"' . checked( 'bz2', backwpup_get_option( $main, 'dbdumpfilecompression' ), false ) . ' name="dbdumpfilecompression" value="bz2" />' . __( 'BZip2', 'backwpup' );
							else
								echo ' <input class="radio" type="radio"' . checked( 'bz2', backwpup_get_option( $main, 'dbdumpfilecompression' ), false ) . ' name="dbdumpfilecompression" value="bz2" disabled="disabled" />' . __( 'BZip2', 'backwpup' );
							?>
						</span><br />
			<input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'maintenance' ), true, true );?> name="maintenance" value="1" /> <?php _e( 'Set Blog Maintenance Mode on Database Operations', 'backwpup' );?>
			<br />
		</div>
	</div>

	<div id="wpexport" class="stuffbox"<?php if ( ! in_array( "WPEXP", backwpup_get_option( $main, 'type' ) ) ) echo ' style="display:none;"';?>>
		<h3><label for="wpexport"><?php _e( 'Wordpress Export', 'backwpup' ); ?></label></h3>

		<div class="inside">
			<?php _e( 'Filename for the WP export file:', 'backwpup' );?>
			&nbsp;<input class="long-text" type="text" name="wpexportfile" value="<?php echo backwpup_get_option( $main, 'wpexportfile' );?>" />.xml&nbsp;
			<?php
			_e( 'Compression:', 'backwpup' );
			echo ' <input class="radio" type="radio"' . checked( '', backwpup_get_option( $main, 'wpexportfilecompression' ), false ) . ' name="wpexportfilecompression" value="" />' . __( 'none', 'backwpup' );
			if ( function_exists( 'gzopen' ) )
				echo ' <input class="radio" type="radio"' . checked( 'gz', backwpup_get_option( $main, 'wpexportfilecompression' ), false ) . ' name="wpexportfilecompression" value="gz" />' . __( 'GZip', 'backwpup' );
			else
				echo ' <input class="radio" type="radio"' . checked( 'gz', backwpup_get_option( $main, 'wpexportfilecompression' ), false ) . ' name="wpexportfilecompression" value="gz" disabled="disabled" />' . __( 'GZip', 'backwpup' );
			if ( function_exists( 'bzopen' ) )
				echo ' <input class="radio" type="radio"' . checked( 'bz2', backwpup_get_option( $main, 'wpexportfilecompression' ), false ) . ' name="wpexportfilecompression" value="bz2" />' . __( 'BZip2', 'backwpup' );
			else
				echo ' <input class="radio" type="radio"' . checked( 'bz2', backwpup_get_option( $main, 'wpexportfilecompression' ), false ) . ' name="wpexportfilecompression" value="bz2" disabled="disabled" />' . __( 'BZip2', 'backwpup' );
			?>
			<br />&nbsp;<br />
			<?php _e( 'Filename for export a list of installed plugins:', 'backwpup' );?>
			&nbsp;<input class="long-text" type="text" name="pluginlistfile" value="<?php echo backwpup_get_option( $main, 'pluginlistfile' );?>" />.txt&nbsp;
		</div>
	</div>


	<div id="filebackup" class="stuffbox"<?php if ( ! in_array( "FILE", backwpup_get_option( $main, 'type' ) ) ) echo ' style="display:none;"';?>>
		<h3><label for="filebackup"><?php _e( 'File Backup', 'backwpup' ); ?></label></h3>

		<div class="inside">
			<b><?php _e( 'Blog Folders to Backup:', 'backwpup' ); ?></b><br />&nbsp;<br />

			<div id="filebackup">
				<div style="width:20%; float: left;">
					&nbsp;<b><input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backuproot' ), true, true );?> name="backuproot" value="1" /> <?php _e( 'root', 'backwpup' );?>
				</b><br />

					<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
						<?php
						echo '<i>' . __( 'Exclude:', 'backwpup' ) . '</i><br />';
						$folder = untrailingslashit( str_replace( '\\', '/', ABSPATH ) );
						if ( $dir = @opendir( $folder ) ) {
							while ( ($file = readdir( $dir )) !== false ) {
								if ( ! in_array( $file, array( '.', '..', '.svn' ) ) && is_dir( $folder . '/' . $file ) && ! in_array( $folder . '/' . $file . '/', BackWPup_File::get_exclude_wp_dirs( $folder ) ) )
									echo '<nobr><input class="checkbox" type="checkbox"' . checked( in_array( $folder . '/' . $file . '/', backwpup_get_option( $main, 'backuprootexcludedirs' ) ), true, false ) . ' name="backuprootexcludedirs[]" value="' . $folder . '/' . $file . '/"/> ' . $file . '</nobr><br />';
							}
							@closedir( $dir );
						}
						?>
					</div>
				</div>
				<div style="width:20%;float: left;">
					&nbsp;<b><input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupcontent' ), true, true );?> name="backupcontent" value="1" /> <?php _e( 'Content', 'backwpup' );?>
				</b><br />

					<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
						<?php
						echo '<i>' . __( 'Exclude:', 'backwpup' ) . '</i><br />';
						$folder = untrailingslashit( str_replace( '\\', '/', WP_CONTENT_DIR ) );
						if ( $dir = @opendir( $folder ) ) {
							while ( ($file = readdir( $dir )) !== false ) {
								if ( ! in_array( $file, array( '.', '..', '.svn' ) ) && is_dir( $folder . '/' . $file ) && ! in_array( $folder . '/' . $file . '/', BackWPup_File::get_exclude_wp_dirs( $folder ) ) )
									echo '<nobr><input class="checkbox" type="checkbox"' . checked( in_array( $folder . '/' . $file . '/', backwpup_get_option( $main, 'backupcontentexcludedirs' ) ), true, false ) . ' name="backupcontentexcludedirs[]" value="' . $folder . '/' . $file . '/"/> ' . $file . '</nobr><br />';
							}
							@closedir( $dir );
						}
						?>
					</div>
				</div>
				<div style="width:20%; float: left;">
					&nbsp;<b><input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupplugins' ), true, true );?> name="backupplugins" value="1" /> <?php _e( 'Plugins', 'backwpup' );?>
				</b><br />

					<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
						<?php
						echo '<i>' . __( 'Exclude:', 'backwpup' ) . '</i><br />';
						$folder = untrailingslashit( str_replace( '\\', '/', WP_PLUGIN_DIR ) );
						if ( $dir = @opendir( $folder ) ) {
							while ( ($file = readdir( $dir )) !== false ) {
								if ( ! in_array( $file, array( '.', '..', '.svn' ) ) && is_dir( $folder . '/' . $file ) && ! in_array( $folder . '/' . $file . '/', BackWPup_File::get_exclude_wp_dirs( $folder ) ) )
									echo '<nobr><input class="checkbox" type="checkbox"' . checked( in_array( $folder . '/' . $file . '/', backwpup_get_option( $main, 'backuppluginsexcludedirs' ) ), true, false ) . ' name="backuppluginsexcludedirs[]" value="' . $folder . '/' . $file . '/"/> ' . $file . '</nobr><br />';
							}
							@closedir( $dir );
						}
						?>
					</div>
				</div>
				<div style="width:20%; float: left;">
					&nbsp;<b><input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupthemes' ), true, true );?> name="backupthemes" value="1" /> <?php _e( 'Themes', 'backwpup' );?>
				</b><br />

					<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
						<?php
						echo '<i>' . __( 'Exclude:', 'backwpup' ) . '</i><br />';
						$folder = untrailingslashit( str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'themes' ) );
						if ( $dir = @opendir( $folder ) ) {
							while ( ($file = readdir( $dir )) !== false ) {
								if ( ! in_array( $file, array( '.', '..', '.svn' ) ) && is_dir( $folder . '/' . $file ) && ! in_array( $folder . '/' . $file . '/', BackWPup_File::get_exclude_wp_dirs( $folder ) ) )
									echo '<nobr><input class="checkbox" type="checkbox"' . checked( in_array( $folder . '/' . $file . '/', backwpup_get_option( $main, 'backupthemesexcludedirs' ) ), true, false ) . ' name="backupthemesexcludedirs[]" value="' . $folder . '/' . $file . '/"/> ' . $file . '</nobr><br />';
							}
							@closedir( $dir );
						}
						?>
					</div>
				</div>
				<div style="width:20%; float: left;">
					&nbsp;<b><input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupuploads' ), true, true );?> name="backupuploads" value="1" /> <?php _e( 'Blog uploads', 'backwpup' );?>
				</b><br />

					<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
						<?php
						echo '<i>' . __( 'Exclude:', 'backwpup' ) . '</i><br />';
						$folder = untrailingslashit( BackWPup_File::get_upload_dir() );
						if ( $dir = @opendir( $folder ) ) {
							while ( ($file = readdir( $dir )) !== false ) {
								if ( ! in_array( $file, array( '.', '..' ) ) && is_dir( $folder . '/' . $file ) && ! in_array( $folder . '/' . $file, BackWPup_File::get_exclude_wp_dirs( $folder ) ) )
									echo '<nobr><input class="checkbox" type="checkbox"' . checked( in_array( $folder . '/' . $file . '/', backwpup_get_option( $main, 'backupuploadsexcludedirs' ) ), true, false ) . ' name="backupuploadsexcludedirs[]" value="' . $folder . '/' . $file . '/"/> ' . $file . '</nobr><br />';
							}
							@closedir( $dir );
						}
						?>
					</div>
				</div>
			</div>
			<input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupexcludethumbs' ), true, true );?> name="backupexcludethumbs" value="1" /> <?php _e( 'Don\'t backup thumbnails in blog uploads folder', 'backwpup' );?>
			<br />
			<input class="checkbox" type="checkbox"<?php checked( backwpup_get_option( $main, 'backupspecialfiles' ), true, true );?> name="backupspecialfiles" value="1" /> <?php _e( 'Backup wp-config.php, robots.txt, .htaccess, .htpasswd and favicon.ico form root if it not selected', 'backwpup' );?>
			<br />&nbsp;<br />
			<b><?php _e( 'Include folders to backup:', 'backwpup' ); ?></b><br />
			<?php _e( 'Example:', 'backwpup' ); ?> <?php echo str_replace( '\\', '/', ABSPATH ); ?>,...<br />
			<input name="dirinclude" id="dirinclude" type="text" value="<?php echo backwpup_get_option( $main, 'dirinclude' );?>" class="large-text" /><br />
			<br />
			<b><?php _e( 'Exclude files/folders from backup:', 'backwpup' ); ?></b><br />
			<?php _e( 'Example:', 'backwpup' ); ?> /logs/,.log,.tmp,/temp/,....<br />
			<input name="fileexclude" id="fileexclude" type="text" value="<?php echo backwpup_get_option( $main, 'fileexclude' );?>" class="large-text" /><br />
		</div>
	</div>

		<?php do_meta_boxes( get_current_screen()->id, 'normal', $main ); ?>

		<?php do_meta_boxes( get_current_screen()->id, 'advanced', $main ); ?>

	</div>
	</div>
	</div>

	</form>
	</div>

	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready(function ($) {
			postboxes.add_postbox_toggles('<?php echo get_current_screen()->id; ?>');
		});
		//]]>
	</script>
	<?php
	}
}

