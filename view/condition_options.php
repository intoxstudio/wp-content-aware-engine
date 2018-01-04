<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 * @copyright 2018 by Joachim Jensen
 */
?>
<li>
	<label class="cae-toggle">
		<input data-vm="checked:exposureSingular" type="checkbox" />
		<div class="cae-toggle-bar wpca-pull-right"></div><?php _e('Singulars',WPCA_DOMAIN); ?>
	</label>
</li>
<li>
	<label class="cae-toggle">
		<input data-vm="checked:exposureArchive" type="checkbox" />
		<div class="cae-toggle-bar wpca-pull-right"></div><?php _e('Archives',WPCA_DOMAIN); ?>
	</label>
</li>
<li>
	<label class="cae-toggle">
		<input data-vm="checked:statusNegated" type="checkbox" />
		<div class="cae-toggle-bar wpca-pull-right"></div><?php _e('Negate conditions',WPCA_DOMAIN); ?>
	</label>
</li>
<li>
	<label class="cae-toggle">
		<input data-vm="checked:int(_ca_autoselect)" type="checkbox" />
		<div class="cae-toggle-bar wpca-pull-right"></div><?php _e('Auto-select new children of selected items',WPCA_DOMAIN); ?>
	</label>
</li>