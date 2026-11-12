<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
    <meta name="theme-color" content="#fbfbfd" />
    <?php wp_head(); ?>
</head>
<body <?php body_class( array( 'fcp-portal-page', 'fcp-view-' . fcp_view_key() ) ); ?>>
<?php wp_body_open(); ?>
<a class="fcp-skip" href="#fcp-content">پرش به محتوا</a>
<div class="fcp-portal">
    <?php fcp_render_header(); ?>
    <main id="fcp-content" class="fcp-main">
        <?php fcp_render_view(); ?>
    </main>
    <?php fcp_render_footer(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
