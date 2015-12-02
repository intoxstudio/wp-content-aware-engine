<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>
<script type="text/template" id="wpca-template-group">
<div class="cas-group-body">
	<div class="cas-group-cell">
		<div class="cas-content"></div>
		<div>
			<select class="js-wpca-add-and">
				<option value="0">-- <?php _e("Select content type",WPCACore::DOMAIN); ?> --</option>
				<?php
					foreach ($options as $key => $value) {
						echo '<option value="'.$key.'">'.$value.'</option>';
					}
				?>
			</select>
		</div>
	</div>
	<div class="cas-group-cell cas-group-options">

			<div><label>
				<input class="js-cas-group-option" type="checkbox" name="<?php echo WPCACore::PREFIX; ?>status" value="1" <%= status == 'negated' ? 'checked' : '' %> />
				<?php _e("Negate conditions",WPCACore::DOMAIN); ?>
			</label></div>
		<?php do_action("wpca/group/settings",$post_type); ?>
		<input class="js-wpca-save-group button" type="button" value="Save Changes" />
	</div>
</div>
<div class="cas-group-sep">
	<?php _e('Or',WPCACore::DOMAIN); ?>
</div>
</script>