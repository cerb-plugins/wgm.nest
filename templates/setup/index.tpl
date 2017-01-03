<h2>{'wgm.nest.common'|devblocks_translate}</h2>
<form action="javascript:;" method="post" id="frmSetupNest" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="nest">
	<input type="hidden" name="action" value="saveJson">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	<fieldset>
		<legend>Nest App Credentials</legend>
		
		<b>Product ID:</b><br>
		<input type="text" name="product_id" value="{$params.product_id}" size="64" spellcheck="false"><br>
		<br>
		<b>Product Secret:</b><br>
		<input type="password" name="product_secret" value="{$params.product_secret}" size="64" spellcheck="false"><br>
		<br>
		<div class="status"></div>
	
		<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
	</fieldset>
</form>

<script type="text/javascript">
$(function() {
	$('#frmSetupNest BUTTON.submit')
		.click(function(e) {
			genericAjaxPost('frmSetupNest','',null,function(json) {
				$o = $.parseJSON(json);
				if(false == $o || false == $o.status) {
					Devblocks.showError('#frmSetupNest div.status',$o.error);
				} else {
					Devblocks.showSuccess('#frmSetupNest div.status',$o.message);
				}
			});
		})
	;
});
</script>