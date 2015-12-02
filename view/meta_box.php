<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>
<?php echo $nonce; ?>
<input type="hidden" id="current_sidebar" value="<?php the_ID(); ?>" />
<div id="cas-groups">
	<h2><?php echo $title; ?>:</h2>
	<ul></ul>
	<div class="cas-group-new">
		<select class="js-wpca-add-or">
			<option value="0">-- <?php _e("Select content type",WPCACore::DOMAIN); ?> --</option>
<?php
				foreach ($options as $key => $value) {
					echo '<option value="'.$key.'">'.$value.'</option>';
				}

?>
		</select>
	</div>
</div>