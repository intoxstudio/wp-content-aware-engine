<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */
?>

<div class="cas-condition cas-condition-<?php echo $id; ?>">
	<div class="cas-group-sep"><?php _e('And',WPCACore::DOMAIN); ?></div>
	<h4><?php echo $name; ?></h4>
	<ul>
<?php if(in_array($id,$data)) : ?>
		<li>
			<label>
				<input type="checkbox" name="cas_condition[<?php echo $id; ?>][]" value="<?php echo $id; ?>" checked="checked" /><?php printf(__("All %s",WPCACore::DOMAIN),$name); ?>
			</label>
		</li>
<?php
	endif;
	echo $checkboxes;
?>
		</ul>
</div>