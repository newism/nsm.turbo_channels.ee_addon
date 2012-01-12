<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require PATH_THIRD.'nsm_turbo_channels/config.php';

/**
 * NSM Turbo Channels Extension
 *
 * @package			NsmTurboChannels
 * @version			0.1.0
 * @author			Leevi Graham <http://leevigraham.com>
 * @copyright 		Copyright (c) 2007-2010 Newism <http://newism.com.au>
 * @license 		Commercial - please see LICENSE file included with this distribution
 * @link			http://expressionengine-addons.com/nsm-example-addon
 * @see 			http://expressionengine.com/public_beta/docs/development/extensions.html
 */

class Nsm_turbo_channels_ext
{
	public $addon_id		= NSM_TURBO_CHANNELS_ADDON_ID;
	public $version			= NSM_TURBO_CHANNELS_VERSION;
	public $name			= NSM_TURBO_CHANNELS_NAME;
	public $description		= 'NSM Turbo Channels';
	public $docs_url		= '';
	public $settings_exist	= true;
	public $settings		= array();

	// At leaset one hook is needed to install an extension
	// In some cases you may want settings but not actually use any hooks
	// In those cases we just use a dummy hook
	public $hooks = array('channel_entries_query_end', 'channel_entries_query_result');

	public $default_site_settings = array(
		'enabled' => true,
		'playa' => array(
			'do_override' => false,
			'disable_param' => 'custom_fields|members|member_data|entry_dates|category_fields|comments|view_counts|pagination'
		)
	);

	public $default_channel_settings = array();

	// ====================================
	// = Delegate & Constructor Functions =
	// ====================================

	/**
	 * PHP5 constructor function.
	 *
	 * @access public
	 * @return void
	 **/
	function __construct() {

		$EE =& get_instance();

		// define a constant for the current site_id rather than calling $PREFS->ini() all the time
		if (defined('SITE_ID') == FALSE) {
			define('SITE_ID', $EE->config->item('site_id'));
		}

		// Load the addons model and check if the the extension is installed
		// Get the settings if it's installed
		$EE->load->model('addons_model');
		if($EE->addons_model->extension_installed($this->addon_id)) {
			$this->settings = $this->_getSettings();
		}

		// Init the cache
		$this->_initCache();
	}

	/**
	 * Initialises a cache for the addon
	 * 
	 * @access private
	 * @return void
	 */
	private function _initCache() {

		$EE =& get_instance();

		// Sort out our cache
		// If the cache doesn't exist create it
		if (! isset($EE->session->cache[$this->addon_id])) {
			$EE->session->cache[$this->addon_id] = array();
		}

		// Assig the cache to a local class variable
		$this->cache =& $EE->session->cache[$this->addon_id];
	}


	// ===============================
	// = Hook Functions =
	// ===============================

	public function dummy_hook_function(){}


	public function channel_entries_query_end($Channel, $sql)
	{
		// disabled? return now.
		if( empty($this->settings['enabled']) ) {
			return $sql;
		}
		
		
		$disable_param = $Channel->EE->TMPL->fetch_param('disable');
		$cfields_param = $Channel->EE->TMPL->fetch_param('custom_fields');
		$db_ref_param = $Channel->EE->TMPL->fetch_param('db_ref');
		if($disable_param == false && $cfields_param == false && $db_ref_param == false) {
			return $row;
		}
		
		$EE =& get_instance();

		$disable = $EE->TMPL->fetch_param('disable');

		if($this->settings['playa']['do_override'] == true) {
			// PLAYA SPECIFIC FUNCTION
			if($EE->TMPL->tagparts[0] == 'playa' &&
				isset($EE->TMPL->tagparams['fixed_order']) && 
				$EE->TMPL->tagparams['fixed_order'] !== ''
			) {
				// if the tag has been altered it will now match this regex
				preg_match(
					'/exp:playa:children entry_id\=\"\d+\" field_id\=\"\d+\"/',
					$EE->TMPL->tagproper,
					$playa_matches
				);
				// if the match is not empty then assume that the tag is
				// generated and safe for altering
				if( ! empty($playa_matches[0]) ) {
					$disable = $this->settings['playa']['disable_param'];
				}
			}
		}

		$Channel->enable = array(
							'categories' 		=> TRUE,
							'category_fields'	=> TRUE,
							'custom_fields'		=> TRUE,
							'member_data'		=> TRUE,
							'pagination' 		=> TRUE,
							// NEW
							'members' 			=> TRUE,
							'channel_meta' 		=> TRUE,
							'comments'	 		=> TRUE,
							'view_counts'		=> TRUE,
							'entry_meta'		=> TRUE,
							'entry_dates'		=> TRUE,
							);

		if ($disable){
			if (strpos($disable, '|') !== FALSE){
				foreach (explode("|", $disable) as $val){
					if (isset($Channel->enable[$val])){
						$Channel->enable[$val] = FALSE;
					}
				}
			}elseif (isset($Channel->enable[$disable])){
				$Channel->enable[$disable] = FALSE;
			}
		}

		$db_ref = preg_replace('/[^a-zA-Z0-9\s]/', '', $EE->TMPL->fetch_param('db_ref', ''));

		// clear db cache
		$EE->db->_reset_select();

		// set the table in the db class
		$EE->db->from('channel_titles AS t');
		// are we using categories? then make select distinct on entries
		if($Channel->cat_request == true){
			$EE->db->distinct(true);
		}

		// add the basic information to the select statement
		$EE->db->select( ($db_ref !== '' ? "/* {$db_ref} */ " : '') .'t.entry_id, t.site_id, t.channel_id', false);

		// are we needing entry meta?
		if($Channel->enable['entry_meta'] == true){
			$EE->db->select('t.forum_topic_id, t.author_id, t.ip_address, t.title, t.url_title, t.status, t.sticky, t.site_id as entry_site_id');
			$EE->db->order_by('t.sticky', 'desc');
		}
		// are we needing entry dates?
		if($Channel->enable['entry_dates'] == true){
			$EE->db->select('t.dst_enabled, t.entry_date, t.year, t.month, t.day, t.edit_date, t.expiration_date');
			$EE->db->order_by('t.entry_date', 'desc');
		}
		// are we needing view counts?
		if($Channel->enable['view_counts'] == true){
			$EE->db->select('t.view_count_one, t.view_count_two, t.view_count_three, t.view_count_four');
		}
		// are we needing comments info?
		if($Channel->enable['comments'] == true){
			$EE->db->select('t.allow_comments, t.comment_expiration_date, t.recent_comment_date, t.comment_total');
		}
		// do we want channel meta?
		if($Channel->enable['channel_meta'] == true){
			$EE->db->join('exp_channels AS w', 'w.channel_id = t.channel_id', 'left');
			$EE->db->select('w.channel_title, w.channel_name, w.channel_url, w.comment_url, w.comment_moderate, w.channel_html_formatting, w.channel_allow_img_urls, w.channel_auto_link_urls, w.comment_system_enabled');
		}
		// do we want member info?
		if($Channel->enable['members'] == true){
			$EE->db->join('members AS m', 'm.member_id = t.author_id', 'left');
			$EE->db->select('m.username, m.email, m.url, m.screen_name, m.location, m.occupation, m.interests, 
							m.aol_im, m.yahoo_im, m.msn_im, m.icq, m.signature, m.sig_img_filename, m.sig_img_width, m.sig_img_height,
							m.avatar_filename, m.avatar_width, m.avatar_height, m.photo_filename, m.photo_width, m.photo_height,
							m.group_id, m.member_id, m.bday_d, m.bday_m, m.bday_y, m.bio');
		}
		// do we want member data?
		if($Channel->enable['member_data'] == true){
			$EE->db->join('member_data AS md', 'md.member_id = t.author_id', 'left');
			$EE->db->select('md.*');
		}
		// do we want custom field data?
		if($Channel->enable['custom_fields'] == true){
			$EE->db->join('channel_data AS wd', 'wd.entry_id = t.entry_id', 'left');
			// we want to include custom fields. now check for the custom_field param
			$channel_fields_param = $EE->TMPL->fetch_param('custom_fields', "");
			if($channel_fields_param !== ''){
				$custom_field_names = explode('|', $channel_fields_param);
				// we want to force the retrieval of specific custom field data
				$site_id = $EE->config->item('site_id');
				// iterate over the columns we want and add them to the sql query
				for($i=0,$m=count($custom_field_names); $i<$m; $i+=1){
					if(!empty($Channel->cfields[ $site_id ][ $custom_field_names[ $i ] ])){
						$field_id = $Channel->cfields[ $site_id ][ $custom_field_names[ $i ] ];
						$EE->db->select('wd.field_id_'.$field_id.', wd.field_ft_'.$field_id);
					}
				}
			}else{
				$EE->db->select('wd.*');
			}
		}

		$EE->db->where_in('t.entry_id', $EE->session->cache['channel']['entry_ids']);

		$EE->db->order_by('t.entry_id', 'desc');

		$sql = $EE->db->_compile_select();

		// clear db cache
		$EE->db->_reset_select();

		return $sql;
	}


	public function channel_entries_query_result($Channel, $query_result) {
		
		// disabled? return now.
		if( empty($this->settings['enabled']) ) {
			return $query_result;
		}
		
		$disable_param = $Channel->EE->TMPL->fetch_param('disable');
		$cfields_param = $Channel->EE->TMPL->fetch_param('custom_fields');
		if($disable_param == false && $cfields_param == false) {
			return $query_result;
		}

		$default_row = array(
			// entry_meta
			'forum_topic_id' => '',
			'author_id' => '',
			'ip_address' => '',
			'title' => '',
			'url_title' => '',
			'status' => '',
			'sticky' => '',
			'entry_site_id' => '',
			// entry dates
			'dst_enabled' => '',
			'entry_date' => '',
			'year' => '',
			'month' => '',
			'day' => '',
			'edit_date' => '',
			'expiration_date' => '',
			// view counts
			'view_count_one' => '',
			'view_count_two' => '',
			'view_count_three' => '',
			'view_count_four' => '',
			// comments
			'allow_comments' => '',
			'comment_expiration_date'	 => '',
			'recent_comment_date' => '',
			'comment_total' => '',
			// channel meta
			'channel_title' => '',
			'channel_name' => '',
			'channel_url' => '',
			'comment_url' => '',
			'comment_moderate' => '',
			'channel_html_formatting' => '',
			'channel_allow_img_urls' => '',
			'channel_auto_link_urls' => '',
			'comment_system_enabled' => '',
			// member info
			'username' => '',
			'email' => '',
			'url' => '',
			'screen_name' => '',
			'location' => '',
			'occupation' => '',
			'interests' => '',
			'aol_im' => '',
			'yahoo_im' => '',
			'msn_im' => '',
			'icq' => '',
			'signature' => '',
			'sig_img_filename' => '',
			'sig_img_width' => '',
			'sig_img_height' => '',
			'avatar_filename' => '',
			'avatar_width' => '',
			'avatar_height' => '',
			'photo_filename' => '',
			'photo_width' => '',
			'photo_height' => '',
			'group_id' => '',
			'member_id' => '',
			'bday_d' => '',
			'bday_m' => '',
			'bday_y' => '',
			'bio' => ''
		);
		
		if(strpos($disable_param, 'custom_fields') || $cfields_param) {
			if( ! empty($Channel->dfields[$query_result[0]['site_id']]) ) {
				foreach ($Channel->dfields[$query_result[0]['site_id']] as $dkey => $dval) {
					$default_row['field_id_'.$dval] = '';
					$default_row['field_dt_'.$dval] = '';
			}
			}
		}

		for($i=0, $m=count($query_result); $i<$m; $i+=1) {
			$query_result[$i] = array_merge($default_row, $query_result[$i]);
		}

		return $query_result;
	}


	// ===============================
	// = Setting Functions =
	// ===============================

	/**
	 * Render the custom settings form and processes post vars
	 *
	 * @access public
	 * @return The settings form HTML
	 */
	public	function settings_form()
	{
		$EE =& get_instance();
		$EE->lang->loadfile($this->addon_id);

		$EE->load->library($this->addon_id."_helper");
		$EE->nsm_turbo_channels_helper->addJS('extension_settings.js');

		// Create the variable array
		$vars = array(
			'addon_id' => $this->addon_id,
			'channels' => $EE->channel_model->get_channels()->result(),
			'error' => FALSE,
			'input_prefix' => __CLASS__,
			'message' => FALSE,
			'self' => $this
		);

		// Are there settings posted from the form?
		if($data = $EE->input->post(__CLASS__))
		{
			if(!isset($data["enabled"]))
				$data["enabled"] = TRUE;

			foreach ($data["channels"] as &$channel) {
				if(!isset($channel["enabled_fields"]))
					$channel["enabled_fields"] = array();
			}

			// No errors ?
			if(! $vars['error'] = validation_errors())
			{
				$this->settings = $this->_saveSettings($data);
				$EE->session->set_flashdata('message_success', $this->name . ": ". $EE->lang->line('alert.success.extension_settings_saved'));
				$EE->functions->redirect(BASE.AMP.'C=addons_extensions');
			}
		}
		else
		{
			// Sometimes we may need to parse the settings
			$data = $this->settings;
		}

		foreach ($vars["channels"] as $channel) {
			$data["channels"][$channel->channel_id] = $this->getChannelSettings($channel->channel_id);
		}

		$vars["data"] = $data;

		// Return the view.
		return $EE->load->view('extension/settings', $vars, TRUE);
	}

	public function getChannelSettings($channel_id)
	{
		return (isset($this->settings['channels'][$channel_id]))
					? $this->settings['channels'][$channel_id]
					: $this->_buildChannelSettings($channel_id);
	}

	/**
	 * Builds default settings for the site
	 *
	 * @access private
	 * @param int $site_id The site id
	 * @param array The default site settings
	 */
	private function _buildDefaultSiteSettings($site_id = FALSE)
	{
		$EE =& get_instance();
		$default_settings = $this->default_site_settings;

		// No site id, use the current one.
		if(!$site_id)
			$site_id = SITE_ID;

		$default_settings['default_site_meta']['author'] = $EE->config->item('webmaster_name');
		$default_settings['default_site_meta']['site_title'] = $EE->config->item('site_name');

		// Channel preferences (if required)
		if(isset($default_settings["channels"]))
		{
			$channels = $EE->channel_model->get_channels($site_id);
			if ($channels->num_rows() > 0)
			{
				foreach($channels->result() as $channel)
				{
					$default_settings['channels'][$channel->channel_id] = $this->_buildChannelSettings($channel->channel_id);
				}
			}
		}

		// return settings
		return $default_settings;
	}

	/**
	 * Build the default channel settings
	 *
	 * @access private
	 * @param array $channel_id The target channel
	 * @return array The new channel settings
	 */
	private function _buildChannelSettings($channel_id)
	{
		return $this->default_channel_settings;
	}


	// ===============================
	// = Class and Private Functions =
	// ===============================

	/**
	 * Called by ExpressionEngine when the user activates the extension.
	 *
	 * @access		public
	 * @return		void
	 **/
	public function activate_extension() {
		$this->_createSettingsTable();
		$this->settings = $this->_getSettings();
		$this->_registerHooks();
	}

	/**
	 * Called by ExpressionEngine when the user disables the extension.
	 *
	 * @access		public
	 * @return		void
	 **/
	public function disable_extension() {
		$this->_unregisterHooks();
	}

	/**
	 * Called by ExpressionEngine updates the extension
	 *
	 * @access public
	 * @return void
	 **/
	public function update_extension($current=FALSE){}


	// ======================
	// = Settings Functions =
	// ======================

	/**
	 * The settings table
	 *
	 * @access		private
	 **/
	private static $settings_table = 'nsm_addon_settings';

	/**
	 * The settings table fields
	 *
	 * @access		private
	 **/
	private static $settings_table_fields = array(
		'id'						=> array(	'type'			 => 'int',
												'constraint'	 => '10',
												'unsigned'		 => TRUE,
												'auto_increment' => TRUE,
												'null'			 => FALSE),
		'site_id'					=> array(	'type'			 => 'int',
												'constraint'	 => '5',
												'unsigned'		 => TRUE,
												'default'		 => '1',
												'null'			 => FALSE),
		'addon_id'					=> array(	'type'			 => 'varchar',
												'constraint'	 => '255',
												'null'			 => FALSE),
		'settings'					=> array(	'type'			 => 'mediumtext',
												'null'			 => FALSE)
	);
	
	/**
	 * Creates the settings table table if it doesn't already exist.
	 *
	 * @access		protected
	 * @return		void
	 **/
	protected function _createSettingsTable() {
		$EE =& get_instance();
		$EE->load->dbforge();
		$EE->dbforge->add_field(self::$settings_table_fields);
		$EE->dbforge->add_key('id', TRUE);

		if (!$EE->dbforge->create_table(self::$settings_table, TRUE)) {
			show_error("Unable to create settings table for ".__CLASS__.": " . $EE->config->item('db_prefix') . self::$settings_table);
			log_message('error', "Unable to create settings table for ".__CLASS__.": " . $EE->config->item('db_prefix') . self::$settings_table);
		}
	}

	/**
	 * Get the addon settings
	 *
	 * 1. Load settings from the session
	 * 2. Load settings from the DB
	 * 3. Create new settings and save them to the DB
	 * 
	 * @access private
	 * @param boolean $refresh Load the settings from the DB not the session
	 * @return mixed The addon settings 
	 */
	private function _getSettings($refresh = FALSE) {

		$EE =& get_instance();
		$settings = FALSE;

		if (
			// if there are settings in the settings cache
			isset($this->cache[SITE_ID]['settings']) === TRUE 
			// and we are not forcing a refresh
			AND $refresh != TRUE
		) {
			// get the settings from the session cache
			$settings = $this->cache[SITE_ID]['settings'];
		} else {
			$settings_query = $EE->db->get_where(
									self::$settings_table,
									array(
										'addon_id' => $this->addon_id,
										'site_id' => SITE_ID
									)
								);
			// there are settings in the DB
			if ($settings_query->num_rows()) {

				if ( ! function_exists('json_decode')) {
					$$EE->load->library('Services_json');
				}

				$settings = json_decode($settings_query->row()->settings, TRUE);
				$this->_saveSettingsToSession($settings);
				log_message('info', __CLASS__ . " : " . __METHOD__ . ' getting settings from session');
			}
			// no settings for the site
			else {
				$settings = $this->_buildDefaultSiteSettings(SITE_ID);
				$this->_saveSettings($settings);
				log_message('info', __CLASS__ . " : " . __METHOD__ . ' creating new site settings');
			}
			
		}

		// Merge config settings
		foreach ($settings as $key => $value) {
			if($EE->config->item($this->addon_id . "_" . $key)) {
				$settings[$key] = $EE->config->item($this->addon_id . "_" . $key);
			}
		}

		return $settings;
	}

	/**
	 * Save settings to DB and to the session
	 *
	 * @access private
	 * @param array $settings
	 */
	private function _saveSettings($settings) {
		$this->_saveSettingsToDatabase($settings);
		$this->_saveSettingsToSession($settings);
		return $settings;
	}

	/**
	 * Save settings to DB
	 *
	 * @access private
	 * @param array $settings
	 * @return array The settings
	 */
	private function _saveSettingsToDatabase($settings) {

		$EE =& get_instance();
		$EE->load->library('javascript');

		$data = array(
			'settings'	=> $EE->javascript->generate_json($settings, true),
			'addon_id'	=> $this->addon_id,
			'site_id'	=> SITE_ID
		);
		$settings_query = $EE->db->get_where(
							'nsm_addon_settings',
							array(
								'addon_id' =>  $this->addon_id,
								'site_id' => SITE_ID
							), 1);

		if ($settings_query->num_rows() == 0) {
			$query = $EE->db->insert('exp_nsm_addon_settings', $data);
			log_message('info', __METHOD__ . ' Inserting settings: $query => ' . $query);
		} else {
			$query = $EE->db->update(
							'exp_nsm_addon_settings',
							$data,
							array(
								'addon_id' => $this->addon_id,
								'site_id' => SITE_ID
							));
			log_message('info', __METHOD__ . ' Updating settings: $query => ' . $query);
		}
		return $settings;
	}

	/**
	 * Save the settings to the session
	 *
	 * @access private
	 * @param array $settings The settings to push to the session
	 * @return array the settings unmodified
	 */
	private function _saveSettingsToSession($settings) {
		$this->cache[SITE_ID]['settings'] = $settings;
		return $settings;
	}




	// ======================
	// = Hook Functions     =
	// ======================

	/**
	 * Sets up and subscribes to the hooks specified by the $hooks array.
	 *
	 * @access private
	 * @param array $hooks A flat array containing the names of any hooks that this extension subscribes to. By default, this parameter is set to FALSE.
	 * @return void
	 * @see http://expressionengine.com/public_beta/docs/development/extension_hooks/index.html
	 **/
	private function _registerHooks($hooks = FALSE) {

		$EE =& get_instance();

		if($hooks == FALSE && isset($this->hooks) == FALSE) {
			return;
		}

		if (!$hooks) {
			$hooks = $this->hooks;
		}

		$hook_template = array(
			'class'    => __CLASS__,
			'settings' => "a:0:{}",
			'version'  => $this->version,
		);

		foreach ($hooks as $key => $hook) {
			if (is_array($hook)) {
				$data['hook'] = $key;
				$data['method'] = (isset($hook['method']) === TRUE) ? $hook['method'] : $key;
				$data = array_merge($data, $hook);
			} else {
				$data['hook'] = $data['method'] = $hook;
			}

			$hook = array_merge($hook_template, $data);
			$EE->db->insert('exp_extensions', $hook);
		}
	}

	/**
	 * Removes all subscribed hooks for the current extension.
	 * 
	 * @access private
	 * @return void
	 * @see http://expressionengine.com/public_beta/docs/development/extension_hooks/index.html
	 **/
	private function _unregisterHooks() {
		$EE =& get_instance();
		$EE->db->where('class', __CLASS__);
		$EE->db->delete('exp_extensions'); 
	}
}