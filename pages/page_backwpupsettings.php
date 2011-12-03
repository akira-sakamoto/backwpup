<?PHP
if (!defined('ABSPATH')) 
	die();
?>
<div class="wrap">
<?PHP
screen_icon();
echo "<h2>".esc_html( __('BackWPup Settings', 'backwpup'))."</h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
?>
<form id="posts-filter" action="<?PHP echo backwpup_admin_url('admin.php')."?page=backwpupsettings";?>" method="post">
<?PHP wp_nonce_field('backwpup-cfg'); ?>
<input type="hidden" name="action" value="update" />
<h3><?PHP _e('Send Mail','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set the options for email sending. The settings will be used in jobs for sending backups via email or for sending log files.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="mailsndemail"><?PHP _e('Sender email','backwpup'); ?></label></th> 
<td><input name="mailsndemail" type="text" id="mailsndemail" value="<?PHP echo $backwpup_cfg['mailsndemail'];?>" class="regular-text" />
</td> 
</tr> 
<tr valign="top"> 
<th scope="row"><label for="mailsndname"><?PHP _e('Sender name','backwpup'); ?></label></th> 
<td><input name="mailsndname" type="text" id="mailsndname" value="<?PHP echo $backwpup_cfg['mailsndname'];?>" class="regular-text" /></td> 
</tr>
</table> 

<h3><?PHP _e('Logs','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set Logfile related options.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="logfolder"><?PHP _e('Log file Folder','backwpup'); ?></label></th>
<td><input name="logfolder" type="text" id="logfolder" value="<?PHP echo $backwpup_cfg['logfolder'];?>" class="regular-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="maxlogs"><?PHP _e('Max. Log Files in Folder','backwpup'); ?></label></th> 
<td><input name="maxlogs" type="text" id="maxlogs" value="<?PHP echo $backwpup_cfg['maxlogs'];?>" class="small-text code" />
<span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span>
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><?PHP _e('Compression','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Compression','backwpup'); ?></span></legend><label for="gzlogs"> 
<input name="gzlogs" type="checkbox" id="gzlogs" value="1" <?php checked($backwpup_cfg['gzlogs'],true); ?><?php if (!function_exists('gzopen')) echo " disabled=\"disabled\""; ?> /> 
<?PHP _e('Gzip Log files!','backwpup'); ?></label> 
</fieldset></td>
</tr>
</table>
<h3><?PHP _e('Jobs','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set Job related options.','backwpup'); ?></p>
<table class="form-table">
<tr valign="top"> 
<th scope="row"><label for="jobstepretry"><?PHP _e('Max. retrys for job steps','backwpup'); ?></label></th> 
<td><input name="jobstepretry" type="text" id="jobstepretry" value="<?PHP echo $backwpup_cfg['jobstepretry'];?>" class="small-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="jobscriptretry"><?PHP _e('Max. retrys for job script retries','backwpup'); ?></label></th> 
<td><input name="jobscriptretry" type="text" id="jobscriptretry" value="<?PHP echo $backwpup_cfg['jobscriptretry'];?>" class="small-text code" <?php if (defined('ALTERNATE_WP_CRON') and ALTERNATE_WP_CRON) echo " disabled=\"disabled\""; ?> />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><?PHP _e('PHP zip class','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('PHP zip class','backwpup'); ?></span></legend><label for="phpzip"> 
<input name="phpzip" type="checkbox" id="phpzip" value="1" <?php checked($backwpup_cfg['phpzip'],true); ?><?php if (!class_exists('ZipArchive')) echo " disabled=\"disabled\""; ?> /> 
<?PHP _e('Use PHP zip class if available! Normaly PCL Zip class will used.','backwpup'); ?></label> 
</fieldset></td>
</tr>
<tr valign="top"> 
<th scope="row"><?PHP _e('Unload Translation','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Unload Translation','backwpup'); ?></span></legend><label for="unloadtranslations"> 
<input name="unloadtranslations" type="checkbox" id="unloadtranslations" value="1" <?php checked($backwpup_cfg['unloadtranslations'],true); ?> /> 
<?PHP _e('Unload all WordPress Translations on Job run to reduce Memory.','backwpup'); ?></label> 
</fieldset></td>
</tr>
	<tr valign="top">
		<th scope="row"><label for="tempfolder"><?PHP _e('Temp file Folder','backwpup'); ?></label></th>
		<td><input name="tempfolder" type="text" id="tempfolder" value="<?PHP echo $backwpup_cfg['tempfolder'];?>" class="regular-text code" />
		</td>
	</tr>
</table>

<h3><?PHP _e('WP Admin Bar','backwpup'); ?></h3>
<p><?PHP _e('Will you see BackWPup in the WordPress Admin Bar?','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?PHP _e('Admin Bar','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Admin Bar','backwpup'); ?></span></legend><label for="showadminbar"> 
<input name="showadminbar" type="checkbox" id="showadminbar" value="1" <?php checked($backwpup_cfg['showadminbar'],true); ?> /> 
<?PHP _e('Show BackWPup Links in Admin Bar.','backwpup'); ?></label> 
</fieldset></td>
</tr>
</table>

<h3><?PHP _e('Http basic authentication','backwpup'); ?></h3>
<p><?PHP _e('Is your blog behind a http basic authentication (.htaccess)? Then you must set the username and password for authentication to get jobs working.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><label for="httpauthuser"><?PHP _e('Username:','backwpup'); ?></label></th> 
<td><input name="httpauthuser" type="text" id="httpauthuser" value="<?PHP echo $backwpup_cfg['httpauthuser'];?>" class="regular-text" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="httpauthpassword"><?PHP _e('Password:','backwpup'); ?></label></th>
<td><input name="httpauthpassword" type="password" id="httpauthpassword" value="<?PHP echo base64_decode($backwpup_cfg['httpauthpassword']);?>" class="regular-text" />
</tr>
</table>

	<h3><?PHP _e('Direct Job start','backwpup'); ?></h3>
	<p><?PHP _e('If you would start job with a url, to use your hosters cron or so, you must setup some security.','backwpup'); ?></p>
	<p><?PHP _e('When you use it it will not looked for job activation or job cron settings!','backwpup'); ?></p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="jobrunauthkey"><?PHP _e('Set auth key:','backwpup'); ?></label></th>
			<td><input name="jobrunauthkey" type="text" id="jobrunauthkey" value="<?PHP echo $backwpup_cfg['jobrunauthkey'];?>" class="text code" />
				<span><?PHP _e('A sample key is:','backwpup'); echo substr(  md5( AUTH_KEY ) ,5,17 ) ?> </span><br />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?PHP _e('URL to use','backwpup'); ?></th>
			<td><?PHP echo BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&_wpnonce='.$backwpup_cfg['jobrunauthkey'].'&starttype=runext&jobid=<em>JOBID</em>'; ?><br />
				<span><?PHP _e('replace <em>JOBID</em> with the ID of job to start','backwpup'); ?></span>
		</tr>
	</table>

<h3><?PHP _e('WP-Cron','backwpup'); ?></h3>
<p><?PHP _e('If you would use the cron job of your hoster you must point it to the url:','backwpup'); echo ' <i>'.get_option('siteurl').'/wp-cron.php</i>'; ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?PHP _e('Use cron service of backwpup.com','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Use cron service of backwpup.com','backwpup'); ?></span></legend><label for="apicronservice"> 
<input name="apicronservice" type="checkbox" id="apicronservice" value="1" <?php checked($backwpup_cfg['apicronservice'],true); ?> />
<?PHP _e('If you check this, the job schedule will submited to backwpup.com. Backwpup.com will call your blog wp-cron.php to start. <em>Use this service only if you have not a cron service of your hoster, or a blog that has a few visitors.</em> The cron service can start cron behind a basic authentication, on that the http authentication data will transferd too! Please make a little donation for the plugin if you use this servcie. The service can be removed by me without a massage.','backwpup'); ?><br />
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_new"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" title="PayPal - The safer, easier way to pay online!"></a>
</label> 
</fieldset>
</td>
</table>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"  /></p>
</form>
</div>