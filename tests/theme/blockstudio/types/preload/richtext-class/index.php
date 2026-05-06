<?php
$classes = trim( $a['cssClass'] ?? 'text-base' );
?>
<RichText
	attribute="content"
	tag="p"
	class="<?php echo esc_attr( $classes ); ?>"
	placeholder="Content"
/>
