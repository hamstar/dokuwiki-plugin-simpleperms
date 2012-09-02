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
