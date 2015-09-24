<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>

<?php echo $nonce; ?>
<input type="hidden" id="current_sidebar" value="<?php the_ID(); ?>" />
<div id="cas-container">
	<div id="cas-accordion" class="accordion-container postbox<?php echo (empty($groups) ? ' accordion-disabled' : ''); ?>">
		<ul class="outer-border">
		<?php do_action('wpca/modules/admin-box'); ?>
		</ul>
	</div>
	<div id="cas-groups" class="postbox<?php echo (empty($groups) ? '' : ' cas-has-groups'); ?>">
		<div class="cas-groups-header">
			<h3><?php _e('Condition Groups',WPCACore::DOMAIN); ?></h3>
			<input type="button" class="button button-primary js-cas-group-new" value="<?php _e('Add New Group',WPCACore::DOMAIN); ?>" />
		</div>
		<div class="cas-groups-body">
			<p><?php _e('Click to edit a group or create a new one. Select content on the left to add it. In each group, you can combine different types of associated content.',WPCACore::DOMAIN); ?></p>
			<strong><?php echo $title; ?>:</strong>
			<ul>
				<li class="cas-no-groups"><?php echo $no_groups; ?></li>
<?php
			$i = 0;
			foreach($groups as $group) :
?>
				<li class="cas-group-single<?php echo ($i == 0 ? ' cas-group-active' : ''); ?>">
					<div class="cas-group-body">
						<span class="cas-group-control cas-group-control-active">
							<input type="button" class="button js-cas-group-save" value="<?php _e('Save',WPCACore::DOMAIN); ?>" /> | <a class="js-cas-group-cancel" href="#"><?php _e('Cancel',WPCACore::DOMAIN); ?></a>
						</span>
						<span class="cas-group-control">
							<a class="js-cas-group-edit" href="#"><?php _ex('Edit','group',WPCACore::DOMAIN); ?></a> | <a class="submitdelete js-cas-group-remove" href="#"><?php _e('Remove',WPCACore::DOMAIN); ?></a>
						</span>
						<div class="cas-content">
							<?php do_action('wpca/modules/print-data',$group->ID); ?>
						</div>
						<div class="menu-settings cas-group-settings">
							<dl>
								<dt><?php _e("Negate group",WPCACore::DOMAIN); ?></dt>
								<dd>
									<div class="cas-switch">
										<input class="js-cas-group-option" type="checkbox" id="cas-negate-<?php echo $group->ID ?>" name="<?php echo WPCACore::PREFIX; ?>status" value="1"<?php checked($group->post_status,WPCACore::STATUS_NEGATED); ?>>
										<label for="cas-negate-<?php echo $group->ID ?>" data-on="<?php _e('Target all but this context',WPCACore::DOMAIN); ?>" data-off="<?php _e('Target this context',WPCACore::DOMAIN); ?>"></label>
									</div>
								</dd>
							</dl>
						</div>
						<input type="hidden" class="cas_group_id" name="cas_group_id" value="<?php echo $group->ID; ?>" />
					</div>
					<div class="cas-group-sep"><?php _e('Or',WPCACore::DOMAIN); ?></div>
				</li>
<?php
				$i++;
			endforeach;
?>
			</ul>
		</div>
		<div class="cas-groups-footer">
			<input type="button" class="button button-primary js-cas-group-new" value="<?php _e('Add New Group',WPCACore::DOMAIN); ?>" />
		</div>
	</div>
</div>