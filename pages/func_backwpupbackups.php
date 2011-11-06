<?PHP
include_once( trailingslashit(ABSPATH).'wp-admin/includes/class-wp-list-table.php');

class BackWPup_Backups_Table extends WP_List_Table {
	
	private $jobid=1;
	private $dest='FOLDER';
	
	function __construct() {
		parent::__construct( array(
			'plural' => 'backups',
			'singular' => 'backup',
			'ajax' => true
		) );
	}
	
	function ajax_user_can() {
		return current_user_can(BACKWPUP_USER_CAPABILITY);
	}
	
	function prepare_items() {	
		
		$per_page = $this->get_items_per_page('backwpupbackups_per_page');
		if ( empty( $per_page ) || $per_page < 1 )
			$per_page = 20;
		
		if (isset($_GET['jobdest'])) {
			$jobdest=$_GET['jobdest'];
		} else {
			$jobdests=$this->get_dest_list();
			if (empty($jobdests))
				$jobdests=array(',');
			$jobdest=$jobdests[0];
			$_GET['jobdest']=$jobdests[0];
		}
		
		list($this->jobid,$this->dest)=explode(',',$jobdest);
		
		$backups=backwpup_get_option('TEMP','BACKUPS');
		if (!empty($backups) or $this->dest!=$backups[0]['DEST'] or $this->jobid!=$backups[0]['JOBID']) {
			$backups=backwpup_get_backup_files($this->jobid,$this->dest);
			backwpup_update_option('TEMP','BACKUPS',$backups);			
		}
		
		//if no itmes brake
		if (empty($backups)) {
			$this->items='';
			return;
		}
		
		//Sorting
		$order=isset($_GET['order']) ? $_GET['order'] : 'desc';
		$orderby=isset($_GET['orderby']) ? $_GET['orderby'] : 'time';
		$tmp = Array();
		if ($orderby=='time') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}		
		elseif ($orderby=='file') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}
		elseif ($orderby=='folder') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}
		elseif ($orderby=='size') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}	

		//by page
		$start=intval( ( $this->get_pagenum() - 1 ) * $per_page );
		$end=$start+$per_page;
		if ($end>count($backups))
			$end=count($backups);

		$this->items=array();
		for ($i=$start;$i<$end;$i++)
			$this->items[]=$backups[$i];
	
		$this->set_pagination_args( array(
			'total_items' => count($backups),
			'per_page' => $per_page,
			'jobdest' => $jobdest,
			'orderby' => $orderby,
			'order' => $order
		) );

	}
	
	function no_items() {
		_e( 'No Files found.','backwpup');
	}
	
	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );
		return $actions;
	}

	function extra_tablenav( $which ) {
		if ( 'top' != $which )
			return;
		echo '<div class="alignleft actions">';
		echo "<select name=\"jobdest\" id=\"jobdest\" class=\"postform\">\n";
		foreach ($this->get_dest_list() as $jobdest) {
			list($jobid,$dest)=explode(',',$jobdest);
			$jobname=backwpup_get_option('JOB_'.$this->jobid,'name');
			echo "\t<option value=\"".$jobdest."\" ".selected($this->jobid.','.$this->dest,$jobdest).">".$dest.": ".esc_html($jobname)."</option>\n";
		}
		echo "</select>\n";
		submit_button( __('Change Destination','backwpup'), 'secondary', '', false, array( 'id' => 'post-query-submit' ) );
		echo '</div>';
	}
	
	function get_dest_list() {
		global $wpdb;
		$jobdest=array();
		$jobids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'JOB_%' AND name='jobid' ORDER BY value");
		if (!empty($jobids)) {
			foreach ($jobids as $jobid) {
				$jobvalue=backwpup_get_job_vars($jobid);
				if (!empty($jobvalue['backupdir']) and is_dir($jobvalue['backupdir']))
					$jobdest[]=$jobid.',FOLDER';
				foreach (explode(',',strtoupper(BACKWPUP_DESTS)) as $dest) {
					$dest=strtoupper($dest);
					if ($dest=='S3' and !empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='GSTORAGE' and !empty($jobvalue['GStorageAccessKey']) and !empty($jobvalue['GStorageSecret']) and !empty($jobvalue['GStorageBucket']))
						$jobdest[]=$jobid.','.$dest;						
					if ($dest=='DROPBOX' and !empty($jobvalue['dropetoken']) and !empty($jobvalue['dropesecret']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='BOXNET' and !empty($jobvalue['boxnetauth']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='RSC' and !empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='FTP' and !empty($jobvalue['ftphost']) and function_exists('ftp_connect') and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='MSAZURE' and !empty($jobvalue['msazureHost']) and !empty($jobvalue['msazureAccName']) and !empty($jobvalue['msazureKey']) and !empty($jobvalue['msazureContainer']))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='SUGARSYNC' and !empty($jobvalue['sugarpass']) and !empty($jobvalue['sugarpass']))
						$jobdest[]=$jobid.','.$dest;					
				}

			}
		}
		return $jobdest;
	}
	
	function get_columns() {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['file'] = __('File','backwpup');
		$posts_columns['folder'] = __('Folder','backwpup');
		$posts_columns['size'] = __('Size','backwpup');
		$posts_columns['folder'] = __('Folder','backwpup');
		$posts_columns['time'] = __('Time','backwpup');
		return $posts_columns;
	}

	function get_sortable_columns() {
		return array(
			'file'    => array('file',false),
			'folder'    => 'folder',
			'size'    => 'size',
			'time'    => array('time',false)
		);
	}	
	
	function display_rows() {
		$style = '';
		foreach ( $this->items as $backup ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			$jobvalues=backwpup_get_job_vars($this->jobid);
			echo "\n\t", $this->single_row( $backup, $jobvalues, $style );
		}
	}
	
	function single_row( $backup, $jobvalue, $style = '' ) {
		list( $columns, $hidden, $sortable ) = $this->get_column_info();
		$r = "<tr $style>";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch($column_name) {
				case 'cb':
					$r .= '<th scope="row" class="check-column"><input type="checkbox" name="backupfiles[]" value="'. esc_attr($backup['file']) .'" /></th>';
					break;
				case 'file':
					$r .= "<td $attributes><strong>".$backup['filename']."</strong>";
					$actions = array();
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupbackups&action=delete&jobdest='.$this->jobid.','.$this->dest.'&paged='.$this->get_pagenum().'&backupfiles[]='.esc_attr($backup['file']), 'bulk-backups') . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Backup Archive. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$actions['download'] = "<a href=\"" . wp_nonce_url($backup['downloadurl'], 'download-backup') . "\">" . __('Download','backwpup') . "</a>";
					$r .= $this->row_actions($actions);
					$r .= "</td>";
					break;
				case 'folder':
					$r .= "<td $attributes>";
					$r .= $backup['folder'];
					$r .= "</td>";
					break;
				case 'size':
					$r .= "<td $attributes>";
					if (!empty($backup['filesize']) and $backup['filesize']!=-1) {
						$r .= backwpup_formatBytes($backup['filesize']);
					} else {
						$r .= __('?','backwpup');
					}
					$r .= "</td>";
					break;
				case 'time':
					$r .= "<td $attributes>";
					$r .= date_i18n(get_option('date_format'),$backup['time']).' @ '. date_i18n(get_option('time_format'),$backup['time']); 
					$r .= "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}


//get backup files and infos
function backwpup_get_backup_files($jobid,$dest) {
	global $backwpup_message,$backwpup_cfg;
	if (empty($jobid) or (!in_array(strtoupper($dest),explode(',',strtoupper(BACKWPUP_DESTS))) and $dest!='FOLDER'))
		return false;
	$jobvalue=backwpup_get_job_vars($jobid);
	$filecounter=0;
	$files=array();
	//Get files/filinfo in backup folder
	if ($dest=='FOLDER' and !empty($jobvalue['backupdir']) and is_dir($jobvalue['backupdir'])) {
		if ( $dir = opendir( $jobvalue['backupdir'] ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (substr($file,0,1)=='.')
					continue;
				if (is_file($jobvalue['backupdir'].$file)) {
					$files[$filecounter]['JOBID']=$jobid;
					$files[$filecounter]['DEST']=$dest;
					$files[$filecounter]['folder']=$jobvalue['backupdir'];
					$files[$filecounter]['file']=$jobvalue['backupdir'].$file;
					$files[$filecounter]['filename']=$file;
					$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=download&file='.$jobvalue['backupdir'].$file;
					$files[$filecounter]['filesize']=filesize($jobvalue['backupdir'].$file);
					$files[$filecounter]['time']=filemtime($jobvalue['backupdir'].$file);
					$filecounter++;
				}
			}
			closedir( $dir );
		}
	}
	//Get files/filinfo from Dropbox
	if ($dest=='DROPBOX' and !empty($jobvalue['dropetoken']) and !empty($jobvalue['dropesecret'])) {
		require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
		try {
			if ($jobvalue['droperoot']=='sandbox')
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
			else
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);
			$dropbox->setOAuthTokens($jobvalue['dropetoken'],$jobvalue['dropesecret']);
			$contents = $dropbox->metadata($jobvalue['dropedir']);
			if (is_array($contents)) {
				foreach ($contents['contents'] as $object) {
					if ($object['is_dir']!=true) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="https://api-content.dropbox.com/1/files/".$jobvalue['droperoot']."/".dirname($object['path'])."/";
						$files[$filecounter]['file']=$object['path'];
						$files[$filecounter]['filename']=basename($object['path']);
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloaddropbox&file='.$object['path'].'&jobid='.$jobid;
						$files[$filecounter]['filesize']=$object['bytes'];
						$files[$filecounter]['time']=strtotime($object['modified']);
						$filecounter++;
					}
				}
			}
		} catch (Exception $e) {
			$backwpup_message.='DROPBOX: '.$e->getMessage().'<br />';
		}
	}
	//Get files/filinfo from Box.net
	if ($dest=='BOXNET' and !empty($jobvalue['boxnetauth'])) {
		//create folder if needed
		$boxnetfolderid=0;
		if ($jobvalue['boxnetdir']!='/' and !empty($jobvalue['boxnetdir'])) {
			$folders=split('/',trim($jobvalue['boxnetdir'],'/'));
			foreach ($folders as $folder) {
				$raw_response=wp_remote_get('http://www.box.net/api/1.0/rest?action=create_folder&share=0&name='.urlencode($folder).'&parent_id='.$boxnetfolderid.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$jobvalue['boxnetauth']);
				if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
					$folder = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
				} elseif(is_wp_error($raw_response)) {
					$backwpup_message.=sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message());
				}
				if ($folder->status!='create_ok' and $folder->status!='s_folder_exists') {
					$backwpup_message.=sprintf(__('Box.net API on folder create: %s !!!','backwpup'),$folder->status);
					return;
				} else {
					$boxnetfolderid=(float)$folder->folder->folder_id;
				}
			}
		}
		$raw_response=wp_remote_get('http://www.box.net/api/1.0/rest?action=get_account_tree&folder_id='.$boxnetfolderid.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$jobvalue['boxnetauth'].'&params[]=nozip&params[]=onelevel&params[]=simple');
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
			$contents = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
		} elseif(is_wp_error($raw_response)) {
			$backwpup_message.=sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message());
		}
		if (is_object($contents)) {
			foreach ($contents->tree->folder->files->file as $object) {
				if ($object['is_dir']!=true) {
					$files[$filecounter]['JOBID']=$jobid;
					$files[$filecounter]['DEST']=$dest;
					$files[$filecounter]['folder']="https://www.box.net/".$jobvalue['boxnetdir']."/";
					$files[$filecounter]['file']=(float)$object->attributes()->id;
					$files[$filecounter]['filename']=(string)$object->attributes()->file_name;
					$files[$filecounter]['downloadurl']='https://www.box.net/api/1.0/download/'.$jobvalue['boxnetauth'].'/'.(float)$object->attributes()->id;
					$files[$filecounter]['filesize']=(float)$object->attributes()->size;
					$files[$filecounter]['time']=(float)$object->attributes()->updated;
					$filecounter++;
				}
			}
		}
	}
	//Get files/filinfo from Sugarsync
	if ($dest=='SUGARSYNC' and !empty($jobvalue['sugarpass']) and !empty($jobvalue['sugarpass'])) {
		if (!class_exists('SugarSync'))
			require_once (dirname(__FILE__).'/../libs/sugarsync.php');
		if (class_exists('SugarSync')) {
			try {
				$sugarsync = new SugarSync($jobvalue['sugaruser'],base64_decode($jobvalue['sugarpass']),BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
				$dirid=$sugarsync->chdir($jobvalue['sugardir'],$jobvalue['sugarroot']);
				$user=$sugarsync->user();
				$dir=$sugarsync->showdir($dirid);
				$getfiles=$sugarsync->getcontents('file');
				if (is_object($getfiles)) {
					foreach ($getfiles->file as $getfile) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;					
						$files[$filecounter]['folder']='https://'.$user->nickname.'.sugarsync.com/'.$dir;
						$files[$filecounter]['file']=(string)$getfile->ref;
						$files[$filecounter]['filename']=utf8_decode((string) $getfile->displayName);
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadsugarsync&file='.(string) $getfile->ref.'&jobid='.$jobid;
						$files[$filecounter]['filesize']=(int) $getfile->size;
						$files[$filecounter]['time']=strtotime((string) $getfile->lastModified);
						$filecounter++;							
					}
				}
			} catch (Exception $e) {
				$backwpup_message.='SUGARSYNC: '.$e->getMessage().'<br />';
			}
		}
	}
	//Get files/filinfo from S3
	if ($dest=='S3' and !empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket']))	{
		if (!class_exists('AmazonS3'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
		if (class_exists('AmazonS3')) {
			try {
				$s3 = new AmazonS3($jobvalue['awsAccessKey'], $jobvalue['awsSecretKey']);
				if (($contents = $s3->list_objects($jobvalue['awsBucket'],array('prefix'=>$jobvalue['awsdir']))) !== false) {
					foreach ($contents->body->Contents as $object) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="https://".$jobvalue['awsBucket'].".s3.amazonaws.com/".dirname((string)$object->Key).'/';
						$files[$filecounter]['file']=(string)$object->Key;
						$files[$filecounter]['filename']=basename($object->Key);
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.$object->Key.'&jobid='.$jobid;
						$files[$filecounter]['filesize']=(string)$object->Size;
						$files[$filecounter]['time']=strtotime($object->LastModified);
						$filecounter++;							
					}
				}
			} catch (Exception $e) {
				$backwpup_message.='Amazon S3: '.$e->getMessage().'<br />';
			}
		}
	}
	//Get files/filinfo from Google Storage
	if ($dest=='GSTORAGE' and !empty($jobvalue['GStorageAccessKey']) and !empty($jobvalue['GStorageSecret']) and !empty($jobvalue['GStorageBucket']))	{
		if (!class_exists('AmazonS3'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
		if (class_exists('AmazonS3')) {
			try {
				$gstorage = new AmazonS3($jobvalue['GStorageAccessKey'], $jobvalue['GStorageSecret']);
				$gstorage->set_hostname('commondatastorage.googleapis.com');
				$gstorage->allow_hostname_override(false);
				if (($contents = $gstorage->list_objects($jobvalue['GStorageBucket'],array('prefix'=>$jobvalue['GStoragedir']))) !== false) {
					foreach ($contents->body->Contents as $object) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="https://sandbox.google.com/storage/".$jobvalue['GStorageBucket']."/".dirname((string)$object->Key).'/';	
						$files[$filecounter]['file']=(string)$object->Key;
						$files[$filecounter]['filename']=basename($object->Key);
						$files[$filecounter]['downloadurl']="https://sandbox.google.com/storage/".$jobvalue['GStorageBucket']."/".(string)$object->Key;
						$files[$filecounter]['filesize']=(string)$object->Size;
						$files[$filecounter]['time']=strtotime($object->LastModified);
						$filecounter++;							
					}
				}
			} catch (Exception $e) {
				$backwpup_message.=sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()).'<br />';
			}
		}
	}
	//Get files/filinfo from Microsoft Azure
	if ($dest=='MSAZURE' and !empty($jobvalue['msazureHost']) and !empty($jobvalue['msazureAccName']) and !empty($jobvalue['msazureKey']) and !empty($jobvalue['msazureContainer'])) {
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) 
			require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
		if (class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
			try {
				$storageClient = new Microsoft_WindowsAzure_Storage_Blob($jobvalue['msazureHost'],$jobvalue['msazureAccName'],$jobvalue['msazureKey']);
				$blobs = $storageClient->listBlobs($jobvalue['msazureContainer'],$jobvalue['msazuredir']);
				if (is_array($blobs)) {
					foreach ($blobs as $blob) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="https://".$jobvalue['msazureAccName'].'.'.$jobvalue['msazureHost']."/".$jobvalue['msazureContainer']."/".dirname($blob->Name)."/";
						$files[$filecounter]['file']=$blob->Name;
						$files[$filecounter]['filename']=basename($blob->Name);
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadmsazure&file='.$blob->Name.'&jobid='.$jobid;
						$files[$filecounter]['filesize']=$blob->size;
						$files[$filecounter]['time']=strtotime($blob->lastmodified);
						$filecounter++;	
					}
				}
			} catch (Exception $e) {
				$backwpup_message.='MSAZURE: '.$e->getMessage().'<br />';
			}
		}
	}
    //Get files/filinfo from RSC
	if ($dest=='RSC' and !empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer']))	{
		if (!class_exists('CF_Authentication'))
			require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');
		if (class_exists('CF_Authentication') ) {
			try {
				$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
				$auth->ssl_use_cabundle();
				if ($auth->authenticate()) {
					$conn = new CF_Connection($auth);
					$conn->ssl_use_cabundle();
					$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
					$contents = $backwpupcontainer->get_objects(0,NULL,NULL,$jobvalue['rscdir']);
					foreach ($contents as $object) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="RSC://".$jobvalue['rscContainer']."/".dirname($object->name)."/";
						$files[$filecounter]['file']=$object->name;
						$files[$filecounter]['filename']=basename($object->name);
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadrsc&file='.$object->name.'&jobid='.$jobid;
						$files[$filecounter]['filesize']=$object->content_length;
						$files[$filecounter]['time']=strtotime($object->last_modified);
						$filecounter++;						
					}
				}
			} catch (Exception $e) {
				$backwpup_message.='RSC: '.$e->getMessage().'<br />';
			}	
		}
	}
	//Get files/filinfo from FTP
	if ($dest=='FTP' and !empty($jobvalue['ftphost']) and function_exists('ftp_connect') and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass'])) {

		if (function_exists('ftp_ssl_connect') and $jobvalue['ftpssl']) { //make SSL FTP connection
			$ftp_conn_id = ftp_ssl_connect($jobvalue['ftphost'],$jobvalue['ftphostport'],10);
		} elseif (!$jobvalue['ftpssl']) { //make normal FTP conection if SSL not work
			$ftp_conn_id = ftp_connect($jobvalue['ftphost'],$jobvalue['ftphostport'],10);
		}
		$loginok=false;
		if ($ftp_conn_id) {
			//FTP Login
			if (@ftp_login($ftp_conn_id, $jobvalue['ftpuser'], base64_decode($jobvalue['ftppass']))) {
				$loginok=true;
			} else { //if PHP ftp login don't work use raw login
				ftp_raw($ftp_conn_id,'USER '.$jobvalue['ftpuser']);
				$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($jobvalue['ftppass']));
				if (substr(trim($return[0]),0,3)<=400)
					$loginok=true;
			}
		}
		if ($loginok) {
			ftp_pasv($ftp_conn_id, $jobvalue['ftppasv']);
			if ($ftpfilelist=ftp_nlist($ftp_conn_id, $jobvalue['ftpdir'])) {
				foreach($ftpfilelist as $ftpfiles) {
					if (substr(basename($ftpfiles),0,1)=='.')
						continue;
					$files[$filecounter]['JOBID']=$jobid;
					$files[$filecounter]['DEST']=$dest;
					$files[$filecounter]['folder']="ftp://".$jobvalue['ftphost'].':'.$jobvalue['ftphostport'].dirname($ftpfiles)."/";
					$files[$filecounter]['file']=$ftpfiles;
					$files[$filecounter]['filename']=basename($ftpfiles);
					$files[$filecounter]['downloadurl']="ftp://".rawurlencode($jobvalue['ftpuser']).":".rawurlencode(base64_decode($jobvalue['ftppass']))."@".$jobvalue['ftphost'].':'.$jobvalue['ftphostport'].rawurlencode($ftpfiles);
					$files[$filecounter]['filesize']=ftp_size($ftp_conn_id,$ftpfiles);
					$files[$filecounter]['time']=ftp_mdtm($ftp_conn_id,$ftpfiles);
					$filecounter++;
				}
			}
		} else {
			$backwpup_message.='FTP: '.__('Login failure!','backwpup').'<br />';
		}
		$donefolders[]=$jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'];
	}
	return $files;
}


?>