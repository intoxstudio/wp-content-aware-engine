<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 * @copyright 2018 by Joachim Jensen
 */
?>
<?php echo $nonce; ?>
<div id="cas-groups">
	<?php do_action('wpca/meta_box/before',$post_type); ?>
	<ul data-vm="collection:$collection"></ul>
	<div class="cas-group-sep" data-vm="toggle:length($collection)">
		<span><?php _e('Or',WPCA_DOMAIN); ?></span>
	</div>
	<div class="cas-group-new">
		<select class="js-wpca-add-or">
			<option value="-1">-- <?php _e('Select content type',WPCA_DOMAIN); ?> --</option>
<?php
			foreach ($options as $key => $value) {
				echo '<option data-placeholder="'.$value['placeholder'].'" data-default="'.$value['default_value'].'" value="'.$key.'">'.$value['name'].'</option>';
			}

?>
		</select>
	</div>
	<?php do_action('wpca/meta_box/after',$post_type); ?>
</div>