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
	<?php do_action('wpca/meta_box/before',$post_type); ?>
	<ul></ul>
	<div class="cas-group-sep">
		<?php _e('Or',WPCACore::DOMAIN); ?>
	</div>
	<div class="cas-group-new">
		<select class="js-wpca-add-or">
			<option value="0">-- <?php _e("Select content type",WPCACore::DOMAIN); ?> --</option>
<?php
			foreach ($options as $key => $value) {
				echo '<option data-default="'.$value['default_value'].'" value="'.$key.'">'.$value['name'].'</option>';
			}

?>
		</select>
	</div>
	<?php do_action('wpca/meta_box/after',$post_type); ?>
</div>