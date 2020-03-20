<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2020 by Joachim Jensen
 */

$quick_links = array(
    __('Blog', WPCA_DOMAIN) => array(
        'modules' => array('post_type-post'),
        'options' => array('exposure' => 2)
    ),
    __('Posts by Author', WPCA_DOMAIN) => array(
        'modules' => array('post_type-post','author'),
        'options' => array('exposure' => 0)
    ),
    __('Posts in Category', WPCA_DOMAIN) => array(
        'modules' => array('post_type-post','taxonomy-category'),
        'options' => array('exposure' => 0)
    )
);

if (post_type_exists('product')) {
    $quick_links[__('Shop', WPCA_DOMAIN)] = array(
        'modules' => array('post_type-product'),
        'options' => array('exposure' => 2)
    );
}

echo $nonce; ?>
<div id="cas-groups">
    <?php do_action('wpca/meta_box/before', $post_type); ?>
    <ul data-vm="collection:$collection"></ul>
    <div class="cas-group-sep" data-vm="toggle:length($collection)">
        <span><?php _e('Or', WPCA_DOMAIN); ?></span>
    </div>
    <div class="cas-group-new">
        <div>
            <select class="wpca-conditions-add js-wpca-add-or">
                <option></option>
            </select>
            <span style="vertical-align: middle;"><em>or</em> <strong>Quick Add:</strong></span>
        </div>
        <div>
            <?php foreach ($quick_links as $label => $conditions) : ?>
            <a class="js-wpca-add-quick" href="#"
                data-config='<?php echo json_encode($conditions); ?>'><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php do_action('wpca/meta_box/after', $post_type); ?>
</div>