<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_register_rest() {
    register_rest_route( 'fcp/v1', '/search', array(
        'methods'             => 'GET',
        'callback'            => 'fcp_rest_search',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
        ),
    ) );
}

function fcp_rest_search( WP_REST_Request $request ) {
    $q = trim( (string) $request->get_param( 'q' ) );
    if ( mb_strlen( $q ) < 2 ) {
        return rest_ensure_response( array() );
    }
    $q = mb_substr( $q, 0, 80 );
    $query = new WP_Query( array(
        'post_type'           => 'fcp_opportunity',
        'post_status'         => 'publish',
        'posts_per_page'      => 6,
        's'                   => $q,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ) );
    $out = array();
    foreach ( $query->posts as $post ) {
        $term     = fcp_opportunity_category( $post->ID );
        $author   = get_userdata( (int) $post->post_author );
        $deadline = fcp_deadline_info( $post->ID );
        $out[]    = array(
            'title'    => get_the_title( $post ),
            'url'      => get_permalink( $post ),
            'category' => $term ? $term->name : '',
            'author'   => $author ? $author->display_name : '',
            'note'     => $deadline['note'],
        );
    }
    return rest_ensure_response( $out );
}
