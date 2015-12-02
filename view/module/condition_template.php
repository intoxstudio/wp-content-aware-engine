<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>
<script type="text/template" id="wpca-template-<?php echo $id; ?>">
	<div class='cas-group-label'>
		<span class='js-wpca-condition-remove wpca-condition-remove dashicons dashicons-trash'></span>
		<%= label %>
	</div>
	<div class="cas-group-input">
		<input type="hidden" data-wpca-placeholder="<?php echo $placeholder; ?>" data-wpca-default="<?php echo $default; ?>" name="cas_condition[<?php echo $id; ?>]" class="js-wpca-suggest" value="" />
	</div>
	<div class="cas-group-sep"><?php _e("And",WPCACore::DOMAIN); ?></div>
</script>
