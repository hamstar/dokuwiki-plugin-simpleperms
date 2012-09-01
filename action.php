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
		$controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'insert_dropdown', array());
	}

	function insert_dropdown(&$event, $param) {
		$pos = $event->data->findElementByAttribute('class','summary');

		$out = '<div class="summary" style="margin-right: 10px;">';
		$out.= 'Permissions: <select><option>Private</option><option>Public Edit</option><option>Public Read</option></select> ';
		$out.= '</div>';

		$event->data->insertElement($pos++,$out);
	}
}
