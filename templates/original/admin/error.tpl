			<div class="block-form-admin" style="text-align:center;">
				<h3>{ERROR}</h3>
					<br />
					{ERROR_MSG}<br /><br />
					<a href="{RETURN_URL}">{DONT_WAIT}</a><br /><br />
			</div>

<!--// Automatic redirection. -->
			<script type="text/javascript">
				setTimeout(function(){
					document.location.replace("{RETURN_URL}");
				}, 5000);
			</script>
