jQuery(document).ready(function() {
	jQuery("#smq-select_all").click(function(){
			jQuery(".smq-select_option").prop('checked', jQuery(this).prop('checked'));
	});
});