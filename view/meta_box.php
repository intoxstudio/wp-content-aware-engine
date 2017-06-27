<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>
<?php echo $nonce; ?>
<div id="cas-groups">
	<?php do_action('wpca/meta_box/before',$post_type); ?>
	<ul data-vm="collection:$collection"></ul>
	<div class="cas-group-sep" data-vm="toggle:length($collection)">
		<?php _e('Or',WPCA_DOMAIN); ?>
	</div>
	<div class="cas-group-new">
		<select class="js-wpca-add-or">
			<option value="0">-- <?php _e('Select content type',WPCA_DOMAIN); ?> --</option>
<?php
			foreach ($options as $key => $value) {
				echo '<option data-placeholder="'.$value['placeholder'].'" data-default="'.$value['default_value'].'" value="'.$key.'">'.$value['name'].'</option>';
			}

?>
		</select>
	</div>
	<?php do_action('wpca/meta_box/after',$post_type); ?>
</div>