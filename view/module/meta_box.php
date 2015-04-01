<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>

<li id="box-<?php echo $id; ?>" class="manage-column column-box-<?php echo $id; ?> control-section accordion-section<?php echo $hidden; ?>">
	<h3 class="accordion-section-title" title="<?php echo $name; ?>" tabindex="0">
		<?php echo $name; ?>
	</h3>
	<div class="accordion-section-content cas-rule-content" data-cas-module="<?php echo $module; ?>" id="cas-<?php echo $id; ?>">

<?php 	if($description) : ?>
		<p><?php echo $description; ?></p>
<?php
		endif;
		echo $panels;
?>
		<p class="button-controls">
			<span class="add-to-group">
				<input data-cas-condition="<?php echo $id; ?>" data-cas-module="<?php echo $module; ?>" type="button" name="cas-condition-add" class="js-cas-condition-add button" value="<?php _e("Add to Group",WPCACore::DOMAIN); ?>">
			</span>
		</p>
	</div>
</li>