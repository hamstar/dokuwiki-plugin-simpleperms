<?php
/**
 * Proof of concept plugin for simple per page  permissions
 * in dokuwiki
 *
 * @license    WTFPL 2 (http://sam.zoy.org/wtfpl/)
 * @author     Robert Mcleod <hamstar@telescum.co.nz>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to interfere with the event system
 * need to inherit from this class
 */
class action_plugin_simpleperms extends DokuWiki_Action_Plugin {

	/**
	* Registers a callback function for a given event
	*/
	function register(&$controller) {

		# For adding simple permissions to the edit form
		$controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'insert_dropdown', array());

		# Restrict access to editing page
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'restrict_editing', array());
		
		# For saving the simple permissions
		$controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'add_metadata', array());
		
		# For checking permissions before opening
		$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'block_if_private_page', array());

		# Hide edit button where applicable
		$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'hide_edit_button', array());
	}

	/**
	 * Insert the select element into the page
	 */
	function insert_dropdown(&$event, $param) {

		# don't add perms select if not author
		if ( !$this->_user_is_creator() )
			return;

		$pos = $event->data->findElementByAttribute('class','summary');

		$dropdown = $this->_generate_dropdown();
		$event->data->insertElement($pos++,$dropdown);
	}

	/**
	 * Restricts editing of pages where needed
	 */
	function restrict_editing( &$event, $param ) {

		global $ACT;

		if ( $ACT != 'save' )
			return; # wat the?

		# Only author can edit private pages
		if ( $this->_private() && !$this->_user_is_creator() )
			$event->preventDefault();

		# Public can edit if they have permission
		if ( !$this->_public_can_edit() )
			$event->preventDefault();
	}

	/**
	 * Adds the simpleperm metadata to the page
	 * Ensures only the author can do this
	 */
	function add_metadata( &$event, $param ) {

		global $ACT;
		global $INPUT;
		global $ID;

		# Check it is a save operation
		if ( $ACT != "save" )
			return; # what the?

		# don't add perms if not author
		if ( !$this->_user_is_creator() )
			return;

		# Check if the simpleperm value was given in the request
		if ( !$INPUT->post->has('simpleperm') )
			return; # hmmm.. select must not have gone on the page

		# Generate and set the metadata
		$data = $this->_generate_metadata( $INPUT->post->int('simpleperm') );
		p_set_metadata( $ID, $data );
	}

	/**
	 * Doesn't allow the page to be viewed if its private
	 */
	function block_if_private_page( &$event, $param ) {

		# Its a private page and user not author, block access
		if ( $this->_private() && !$this->_user_is_creator() )
			$this->event->preventDefault();
	}

	/**
	 * Hides the edit button if the user only has read perms
	 */
	function hide_edit_button( &$event, $param ) {

		$this->_check_metadata_exists(); # this should modify $INFO if it doesn't already have simpleperm metadata
		
		if ( $this->_user_is_creator() )
			return;

		if ( $this->_public_can_edit() )
			return;

		$edit_button = '<input type="submit" value="Edit this page" class="button" accesskey="e" title="Edit this page [E]" />';
		$event->data = str_replace( $edit_button, "", $event->data, 
	}

	/**
	 * TODO: test that this method works as expected
	 * Make sure methods that use the metadata get the right INFO[meta]
	 * array after calling this on a previously unrestricted page
	 */
	function _check_metadata_exists() {

		global $INFO;
		global $ID;

		if ( 
			!isset($INFO['meta']['public_rw']) || 
			!isset($INFO['meta']['public_r']) || 
			!isset($INFO['meta']['private']) )
		) {
			# Metadata not set
			$data = array(
				'public_rw' => false,
				'public_r' => false,
				'private' => true
			);

			p_set_metadata( $ID, $data );
			$INFO['meta'] = p_get_metadata( $ID, array(), true );
		}

	}

	/**
	 * @return true if public can edit
	 */
	function _public_can_edit() {

		global $INFO;

		return $INFO['meta']['public_rw'];
	}

	/**
	 * @return true if public can read
	 */
	function _public_can_read() {

		global $INFO;

		return $INFO['meta']['public_r'];
	}

	/**
	 * @return true if private
	 */
	function _private() {

		global $INFO;

		return $INFO['meta']['private'];
	}

	/**
	 * @return true if the current user is creator
	 */
	function _user_is_creator() {

		global $INFO;

		return ( $INFO['meta']['creator'] == $INFO['userinfo']['name'] );
	}

	/**
	 * @return the html for the dropdown permissions selection
	 */
	function _generate_dropdown() {

		global $INFO;
		$m = $INFO['meta'];

		# Build a permissions matrix
		$perms = ((int)$m['private']) . ((int)$m['public_r']) . ((int)$m['public_rw'])

		# Set default text for each selected
		list( 
			$private_selected,
			$public_r_selected,
			$public_rw_selected
		) = array("", "", "");

		# Match the matrix against the 
		switch ( $perms ) {
			case "011":
			case "001"
				$public_rw_selected = " selected";
				break;
			case "010":
				$public_r_selected = " selected";
				break;
			case "100":
			default:
				$private_selected = " selected";
				break;
		}

		# note: default is private
		$out = <<<EOF
		<div class="summary" style="margin-right: 10px;">"
			<span>Permissions: <select name="simpleperm">
				<option value="-1"$private_selected>Private</option>
				<option value="0"$public_r_selected>Public Read</option>
				<option value="1"$public_rw_selected>Public Edit</option>
			</select></span>
		</div>
EOF;

		return $out;
	}

	/**
	 * @return array of metadata describing the simple permissions
	 */
	function _generate_metadata( $sp ) {
		
		# set the perms
		switch ( $sp ) {
		case 0: # public read 010/2
			$data = array(
				'public_rw' => false,
				'public_r' => true,
				'private' => false
			);
			break;
		case 1: # public edit 011/3
			$data = array(
				'public_rw' => true,
				'public_r' => true,
				'private' => false
			);
			break;
		case -1: # private 100/4
		default:
			$data = array(
				'public_rw' => false,
				'public_r' => false,
				'private' => true
			);
			break;
		}

		return $data;
	}
}
