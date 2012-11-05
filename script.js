simpleperms = {
	activate_dropdown: function () {

		if ( jQuery("#permission").size() == 0 )
			return false;

		jQuery("#permission").change( simpleperms.update_level );
	},

	update_level: function () {

		jQuery.post( DOKU_BASE + "lib/exe/ajax.php",
			{
				call: "update.level",
				level: jQuery("#permission").val(),
				id: JSINFO.id
			},
			function (j) {
				if ( j.error != 0 ) {
					alert( j.message );
				}
			},
			'json'
		);
	}
};

jQuery(document).ready(function() {

	simpleperms.activate_dropdown();

});