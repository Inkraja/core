<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2006
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 * 
 * $Id$
 */

class addcharacter_pageobject extends pageobject {

	private $data = array();
	private $form_build = false;
	
	public static $shortcuts = array('form' => array('form', array('addchar')));

	public function __construct() {
		$handler = array(
			'add'	=> array('process' => 'add', 'check' => 'u_member_add', 'csrf'=>true),
			'edit'	=> array('process' => 'edit', 'check' => 'u_member_man', 'csrf'=>true),
		);

		// Permissions
		$this->user->check_auths(array('u_member_man', 'u_member_add'), 'OR');
		
		// Check if the user is logged in
		if (!$this->user->is_signedin()) {
			message_die($this->user->lang('uc_not_loggedin'));
		}
		
		$this->data['rank_id'] = $this->pdh->get('rank', 'default', array());
		
		parent::__construct('u_member_', $handler, array(), null, '', 'editid');
		$this->process();
	}
		
	//Add a new character
	public function add(){
		$this->build_form();
		$data = $this->form->return_values();
		$data['notes'] = htmlspecialchars($this->in->get('notes'), ENT_QUOTES);
		$data['picture'] = $this->in->get('picture');
		if (strlen($data['name'])){
			$this->pdh->put('member', 'addorupdate_member', array($this->url_id, $data, $data['overtakechar']));
		
			$this->pdh->process_hook_queue();
			$this->tpl->add_js('jQuery.FrameDialog.closeDialog();');
			return true;
		} else {
			$this->core->message($this->user->lang('missing_values').$this->user->lang('name'), $this->user->lang('error'), 'red', true);
			$this->display($data);
		}
	}

	public function edit(){
		$this->build_form();
		$data = $this->form->return_values();
		$data['notes'] = htmlspecialchars($this->in->get('notes'), ENT_QUOTES);
		$data['picture'] = $this->in->get('picture');
		// dont allow name-change if not in adminmode
		if(!($this->in->get('adminmode', 0) && $this->user->check_auth('a_members_man', false))) unset($data['name']);
		$id = $this->pdh->put('member', 'addorupdate_member', array($this->url_id, $data, $data['overtakechar']));

		//Transfer character history
		if ($this->in->get('adminmode', 0) && $this->user->check_auth('a_members_man', false) && ($this->url_id != $this->in->get('history_receiver', 0)) && $this->in->get('history_receiver', 0) > 0){
			$this->pdh->put('member', 'trans_member', array($this->url_id, $this->in->get('history_receiver', 0)));
		}
		$this->pdh->process_hook_queue();
		$this->tpl->add_js('jQuery.FrameDialog.closeDialog();');
	}
	
	public function display($member_data=array()) {
		// Read the Data
		if(empty($member_data)) {
			$member_data = $this->pdh->get('member', 'array', array($this->url_id));
			if($this->url_id > 0) $member_data['editid'] = $this->url_id;
			// get class data which is selected by admin /such as faction/
			$member_data = array_merge($member_data, $this->game->get_admin_classdata());
			// some modifications - TODO: solve this inconsistency
			$member_data['rankid'] = $member_data['rank_id'];
			$member_data['mainid'] = $member_data['main_id'];
		}
		$userid_real	= ($this->url_id > 0) ? $this->pdh->get('member', 'userid', array($this->url_id)) : $this->user->data['user_id'];

		// test
		if($this->in->get('ajax', false)) {
			$data = $this->game->get_dep_classes($this->in->get('parent'), $this->in->get('child'), $this->in->get('requestid'));
			$options = array(
				'options_only'	=> true,
				'options' 		=> $data,
			);
			if($this->url_id > 0) {
				$options['value'] = $this->pdh->get('member', 'profile_field', array($this->url_id, $this->in->get('child')));
			}
			echo new hdropdown('dummy', $options);
			exit;
		}
		
		$this->build_form();
		
		// Fill fields with values
		$this->form->output($member_data);

		$arrHistoryReceivers = $this->pdh->aget('member', 'name', 0, array($this->pdh->get('member', 'id_list')));
		asort($arrHistoryReceivers);
		$this->tpl->assign_vars(array(
			// Permissions
			'U_IS_EDIT'				=> ($this->url_id > 0) ? true : false,
			'USER_CAN_CONNECT'		=> ($this->user->check_auth('u_member_conn', false)) ? true : false,
			'ADMINMODE'				=> ($this->in->get('adminmode', 0) && $this->user->check_auth('a_members_man', false)),

			// Data
			'NOTES'					=> stripslashes(((isset($member_data['notes'])) ? $member_data['notes'] : '')),
			'DD_HISTORY_RECEIVER'	=> new hdropdown('history_receiver', array('options' => $arrHistoryReceivers, 'value' => $this->url_id)),
			'MEMBER_PICTURE'		=> '<input type="hidden" value="'.$member_data['picture'].'" name="picture"/>',
		));

		$this->core->set_vars(array(
			'page_title'		=> '',
			'template_file'		=> 'addcharacter.html',
			'header_format'		=> 'simple',
			'display'			=> true)
		);
	}
	
	private function build_form() {
		if($this->form_build) return true;
		$this->form_build = true;
		// initialize form class
		$this->form->lang_prefix = 'addchar_';
		$this->form->use_tabs = true;
		$this->form->ajax_url = html_entity_decode($this->action);
		
		// Static fields
		$static_fields = array(
			'name'	=> array(
				'type'		=> 'text',
				'lang'		=> 'name',
				'required'	=> true,
				'pattern'	=> '.{3,}',
				'readonly'	=> ($this->url_id > 0 && !$this->in->get('adminmode')) ? true : false,
				'size'		=> 20
			),
		);
		if($this->url_id > 0) {
			$static_fields['editid'] = array(
				'type'	=> 'hidden',
			);
			if($this->in->get('adminmode')) {
				$static_fields['status'] = array(
					'type'	=> 'radio',
					'lang'	=> 'member_active',
				);
			}
		}
		if(!($this->in->get('adminmode') && $this->url_id > 0)) {
			$static_fields['overtakechar'] = array(
				'type'	=> 'radio',
				'lang'	=> 'overtake_char',
				'disabled' => ($this->user->check_auth('u_member_conn', false)) ? false : true,
			);
			// is the char connected to the user?
			if(!$static_fields['overtakechar']['disabled'] && !$this->in->get('adminmode')) $static_fields['overtakechar']['value'] = 1;
		}
		if($this->in->get('adminmode', 0) && $this->user->check_auth('a_members_man', false)) {
			$maincharsel = $this->pdh->aget('member', 'name', 0, array($this->pdh->get('member', 'id_list', array(false,false,true,true))));
			if (!$this->url_id){
				asort($maincharsel);
				$maincharsel[0] = $this->user->lang('mainchar');
			} else {
				$maincharsel[$this->url_id] = $this->pdh->get('member', 'name', array($this->url_id));
				asort($maincharsel);
			}
			$static_fields['mainid']	= array(
				'type'			=> 'dropdown',
				'options'		=> $maincharsel,
				'lang'			=> 'mainchar',
			);
			$tmpranks		= $this->pdh->aget('rank', 'name', 0, array($this->pdh->get('rank', 'id_list')));
			$static_fields['rankid']	= array(
				'type'			=> 'dropdown',
				'options'		=> $tmpranks,
				'lang'			=> 'rank',
				'default'		=> $this->pdh->get('rank', 'default', array()),
			);
		}
		$this->form->add_tab(array('name' => 'character', 'lang' => 'character'));
		$this->form->add_fields($static_fields, '', 'character');

		// Dynamic Tabs
		$categorynames = $this->pdh->get('profile_fields', 'categories');
		foreach($categorynames as $catname) {
			$this->form->add_tab(array('name' => $catname, 'lang' => 'uc_cat_'.$catname));
		}
		// Dynamic Fields
		$profilefields = $this->pdh->get('profile_fields', 'fields');
		foreach($profilefields as $fieldname => $fielddata) {
			$tab = (!empty($fielddata['category']) && in_array($fielddata['category'], $categorynames)) ? $fielddata['category'] : 'character';
			$this->form->add_field($fieldname, $fielddata, '', $tab);
		}
	}
}

?>