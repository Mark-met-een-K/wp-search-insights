<form action="options.php" method="post">
<div class="settings-block {class}">
	<span class="settings-title"><h3>{title}</h3></span>
	<div>
		{content}
		<?php WPSI::$admin->save_button(true);?>
	</div>
</div>
</form>