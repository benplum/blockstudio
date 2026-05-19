<div class="blockstudio-test-accordion" data-single-open="<?php echo ! empty($a['singleOpen']) ? 'true' : 'false'; ?>">
    <InnerBlocks
        tag="div"
        class="blockstudio-test-accordion__items"
        allowedBlocks="<?php echo esc_attr(wp_json_encode(['core/details'])); ?>"
    />
</div>
