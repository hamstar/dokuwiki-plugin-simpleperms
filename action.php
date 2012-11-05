<?php
//error_reporting(E_ERROR | E_WARNING | E_PARSE);
/**
 * Proof of concept plugin for simple per page  permissions
 * in dokuwiki
 *
 * @license    WTFPL 2 (http://sam.zoy.org/wtfpl/)
 * @author     Robert Mcleod <hamstar@telescum.co.nz>
 * @modified   Bhavic Patel <bhavic@hides.me>
 *
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

/**
 * All DokuWiki plugins to interfere with the event system
 * need to inherit from this class
 */
class action_plugin_simpleperms extends DokuWiki_Action_Plugin
{	
    const META_NAME = "simpleperms";

    const LEVEL_PUBLIC_RW = 1;
    const LEVEL_PUBLIC_R = 0;
    const LEVEL_PRIVATE = -1;
    
    /**
     * Registers a callback function for a given event
     */
    function register(&$controller)
    {
         
        
        // Add hooks
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess', array());
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display', array());
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_html_editform_output', array());
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown', array());
    }

    function handle_action_act_preprocess(&$event, $param) {
        
        global $ACT;

        switch ( $ACT )
        {
            case "edit":               
                $this->_set_ownership();
                break;

            default:
                return;
        }
    }

    function handle_tpl_content_display(&$event, $param) {

        global $ACT;
        global $ID;

        switch ( $ACT )
        {
            case "show":
                if ( !$this->_page_exists() )
                    return;

                if ( !$this->_user_can_read() )
                    $this->hide_page($event);

                if ( !$this->_user_can_edit() )
                    $this->hide_edit_button();

                // This line is handy for troubleshooting permissions errors
                // msg( print_r( p_get_metadata( $ID, self::META_NAME ), 1 ) );
                break;

            case "edit":
                if ( $this->_page_exists() && !$this->_user_can_edit() )
                    $this->hide_page($event);

            default:
                return;
        }
    }

    function handle_html_editform_output( &$event, $param ) {
        
        // No globals in dokuwiki-ajax-land
        global $ID;
        $JSINFO["id"] = $ID;

        $this->insert_dropdown( $event ); // this is always edit
        
        // This line is handy for troubleshooting permissions errors
        // msg( print_r( $this->_get_simpleperm_metadata(), 1 ) );
    }

    function handle_ajax_call_unknown( &$event, $param ) {

        switch ( $event->data )
        {
            case "update.level":
                $this->apply_permissions();
                $event->preventDefault();
                $event->stopPropagation();
                break;

            default:
                return;
        }
    }

    /**
     * Insert the select element into the page
     * Added checks
     */
    function insert_dropdown(&$event)
    {
        // don't add perms select if not owner
        if ( !$this->_user_is_owner() )
            return;
        
        $pos = $event->data->findElementByAttribute('class', 'summary');
        
        $dropdown = $this->_generate_permissions_dropdown();
        $event->data->insertElement($pos++, $dropdown);
    }
    
    function _set_ownership() {

        if ( $this->_page_exists() )
            return;

        // make the user the owner before they even save the page!
        $this->_add_simpleperm_metadata();
    }

    /**
     * Adds the simpleperm metadata to the page
     * Ensures only the author can do this
     */
    function apply_permissions()
    {
        
        $id = cleanID($_POST["id"]);
        
        $json = new StdClass;
        $json->error = 0;

        try {

            // TODO: Make this check the owner
            // - currently the dropdown will be hidden from non-owners but
            //   that won't stop people posting directly to this script to
            //   unlock a page
            // - the ajax call apparently doesn't have access to $_SESSION
            //   from which to determine the user... no access to $INFO
            //   global either... wtf...?
            // Don't let anyone else set permissions
            // if ( !$this->_user_is_owner() )
            //     throw new Exception("You don't own this page");

            // Check if the simpleperm value was given in the request
            if ( !isset( $_POST['level'] ) )
                throw new Exception("Permission data not set");
            
            $json->id = $id;

            // get the metadata and make it output with the json
            $meta = p_get_metadata( $id, self::META_NAME);
            $json->old_level = $meta["level"];
            $json->old_owner = $meta["owner"];
            
            // Set the meta key
            $meta["level"] = $_POST["level"];

            // Save the metadata
            if ( !p_set_metadata( $id, array(self::META_NAME => $meta) ) )
                throw new Exception("Saving metadata failed");
            // switch ( $_POST['level'] )
            // {
            //     case self::LEVEL_PRIVATE:
            //         $this->_make_page_private();
            //         break;
            //     case self::LEVEL_PUBLIC_R:
            //         $this->_make_page_public_r();
            //         break;
            //     case self::LEVEL_PUBLIC_RW:
            //         $this->_make_page_public_rw();
            //         break;
            //     default:
            //         throw new Exception("Could not determine the permission required");
            //         break;
            // }

            // Get the metadata to check it saved
            $new_meta = p_get_metadata( $id, self::META_NAME );
            $json->level = $new_meta["level"];
            $json->owner = $new_meta["owner"];

            // did it save?
            if ( $_POST["level"] != $new_meta["level"] )
                throw new Exception("The level was not set for $id... {$_POST["level"]} was given but the level is at {$data["level"]}");

        } catch ( Exception $e ) {

            // ...no :(
            $json->error = 1;
            $json->message = "There was a problem setting the permissions: ".$e->getMessage();
        }
        
        echo json_encode($json);
    }

    /**
     * Doesn't allow the page to be viewed if its private
     */
    function hide_page(&$event)
    {
        $event->preventDefault();
        echo <<<EOF
        <h1 class="sectionedit1"><a name="this_topic_does_not_exist_yet" id="this_topic_does_not_exist_yet">This topic does not exist yet</a></h1>
        <div class="level1">
            <p>
                You've followed a link to a topic that doesn't exist yet. If permissions allow, you may create it by using the <code>Create this page</code> button.
            </p>
        </div>
EOF;
        
    }
    
    /**
     * Hides the edit button if the user only has read perms
     */
    function hide_edit_button()
    {        
        $out = <<<EOF
<script>
    jQuery(document).ready(function(){
        jQuery('.edit').parent("li").remove();
    });
</script>
EOF;
        echo $out;
    }
    
    /**
     * @return the name of the user
     */
    function _get_user()
    {

        $session = reset( $_SESSION ); // gives the first element of the array
        $user = $session["auth"]["user"];

        return ( is_null( $user ) ) ? "unknown" : $user;
    }

    /**
     * @return true if the user has read access
     */
    function _user_can_read() 
    {
        if ( $this->_user_is_admin() && !$this->_page_has_owner() )
            return true;

        if ( $this->_user_is_owner() )
            return true;

        if ($this->_public_can_edit())
            return true;

        if ( $this->_public_can_read() )
            return true;

        return false;
    }

    /**
     * @return true if user can edit
     */
    function _user_can_edit()
    {
        if ( $this->_user_is_admin() && !$this->_page_has_owner() )
            return true;

        if ($this->_user_is_owner())
            return true;

        if ($this->_public_can_edit())
            return true;

        return false;
    }
    
    /**
     * @return true if public can edit
     */
    function _public_can_edit()
    {
        $data = $this->_get_simpleperm_metadata();
        return $data["level"] == self::LEVEL_PUBLIC_RW;
    }
    
    /**
     * @return true if public can read
     */
    function _public_can_read()
    {
        $data = $this->_get_simpleperm_metadata();
        return $data["level"] == self::LEVEL_PUBLIC_R;
    }
    
    /**
     * @return true if private
     */
    function _private()
    {
        $data = $this->_get_simpleperm_metadata();
        return $data["level"] == self::LEVEL_PRIVATE;
    }
    
    /**
     * @return true if the current user is creator
     */
    function _user_is_owner()
    {
        $data = $this->_get_simpleperm_metadata();
        return $data["owner"] == $this->_get_user();
    }

    function _make_user_owner()
    {
        if ( $this->_set_simpleperm_metadata( array( "owner" => $this->_get_user() ) ) !== true )
            throw new Exception("Failed to make ".$this->_get_user()." owner of the page");
    }

    /**
     * @return true if user is admin
     */
    function _user_is_admin()
    {
        return $this->_get_user() == "admin";
    }
    
    /**
     * @return true if page exists
     */
    function _page_exists()
    {
        global $INFO;
        
        return $INFO['exists'];
    }

    function _page_has_owner()
    {
        $data = $this->_get_simpleperm_metadata();
        return !is_null( $data["owner"] );
    }
    
    /**
     * @return the html for the dropdown permissions selection
     */
    function _generate_permissions_dropdown()
    {
        $data = $this->_get_simpleperm_metadata();
        $level = $data["level"];
        
        // make private selected by default
        list( $p, $pr, $prw ) = array( " selected", "", "" );

        // Only check the permissions if the page exists
        if ( $this->_page_exists() ) {
            $p = ( $level == self::LEVEL_PRIVATE ) ? " selected" : "";
            $pr = ( $level == self::LEVEL_PUBLIC_R ) ? " selected" : "";
            $prw = ( $level == self::LEVEL_PUBLIC_RW ) ? " selected" : "";
        }
        
        // Make the dropdown
        $out = <<<EOF
		<div class="summary" style="margin-right: 10px;">
			<span>Permissions: <select name="level" id="permission">
				<option value="-1"$p>Private</option>
				<option value="0"$pr>Publicly Readable</option>
				<option value="1"$prw>Publicly Editable</option>
			</select></span>
		</div>
EOF;
        
        return $out;
        
    }

    function _make_page_public_r()
    {
        if ( $this->_set_simpleperm_metadata( array("level" => self::LEVEL_PUBLIC_R ) ) !== true )
            throw new Exception( "Failed to make this page publicly readable" );
    }

    function _make_page_public_rw()
    {
        if ( $this->_set_simpleperm_metadata( array("level" => self::LEVEL_PUBLIC_RW ) ) !== true )
            throw new Exception( "Failed to make this page publicly writeable" );
    }

    function _make_page_private()
    {
        if ( $this->_set_simpleperm_metadata( array("level" => self::LEVEL_PRIVATE ) ) !== true )
            throw new Exception( "Failed to make this page private" );
    }

    function _get_simpleperm_metadata()
    {
        // global $INFO;
        global $ID;

        //if ( !isset( $INFO["meta"][ self::META_NAME ] ) )
            //$this->_add_simpleperm_metadata(); // add the data if it is not existing

        $meta = p_get_metadata( $ID, self::META_NAME );

        // if ( !empty( $INFO ) )
        //     $INFO["meta"][self::META_NAME] = $meta;

        return $meta;
    }

    function _set_simpleperm_metadata( $data )
    {
        global $ID;
        return p_set_metadata( $ID, array(self::META_NAME => $data) );
    }

    function _add_simpleperm_metadata()
    {
        $data = array(
            "level" => self::LEVEL_PRIVATE,
            "owner" => $this->_get_user()
        );

        if ( $this->_set_simpleperm_metadata( $data ) !== true )
            throw new Exception( "Couldn't add permissions metadata to this page.");

        return true;
    }

    function _set_sp_data( $owner=null, $level=null ) {

    }
}