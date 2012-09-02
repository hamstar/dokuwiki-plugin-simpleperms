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
		
		# For saving the simple permissions
		$controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'add_metadata', array());
	}

	function insert_dropdown(&$event, $param) {
		$pos = $event->data->findElementByAttribute('class','summary');

		# note: default is private
		$out = <<<EOF
<div class="summary" style="margin-right: 10px;">"
	<span>Permissions: <select name="simpleperm">
		<option value="-1">Private</option>
		<option value="0">Public Read</option>
		<option value="1">Public Edit</option>
	</select></span>
</div>
EOF

		$event->data->insertElement($pos++,$out);
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

		# set the perms
		switch ( $INPUT->post->int('simpleperm') ) {
		case 0:
			$data = array(
				'public_rw' => false,
				'public_r' => true,
				'private' => false
			);
			break;
		case 1:
			$data = array(
				'public_rw' => true,
				'public_r' => true,
				'private' => false
			);
			break;
		case -1:
		default:
			$data = array(
				'public_rw' => false,
				'public_r' => false,
				'private' => true
			);
			break;
		}

		p_set_metadata( $ID, $data );
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
}
