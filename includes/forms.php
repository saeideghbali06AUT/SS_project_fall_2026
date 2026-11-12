<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_redirect_dashboard( $code, $tab = '' ) {
    $args = array( 'fcp_notice' => $code );
    if ( $tab ) {
        $args['tab'] = $tab;
    }
    wp_safe_redirect( add_query_arg( $args, fcp_page_url( 'dashboard' ) ) );
    exit;
}

function fcp_handle_save_opportunity() {
    if ( ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'اجازه ثبت فرصت را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_nonce'] ) ), 'fcp_save_opportunity' ) ) {
        wp_die( 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.', 'خطای امنیتی', array( 'response' => 403 ) );
    }

    $post_id   = isset( $_POST['fcp_post_id'] ) ? absint( $_POST['fcp_post_id'] ) : 0;
    $old_post  = $post_id ? get_post( $post_id ) : null;
    $current_id = get_current_user_id();
    if ( $post_id && ( ! $old_post || 'fcp_opportunity' !== $old_post->post_type || ( (int) $old_post->post_author !== $current_id && ! current_user_can( 'manage_options' ) ) ) ) {
        wp_die( 'امکان ویرایش این آگهی وجود ندارد.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
    }

    $title       = isset( $_POST['fcp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_title'] ) ) : '';
    $description = isset( $_POST['fcp_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fcp_description'] ) ) : '';
    $category_id = isset( $_POST['fcp_category'] ) ? absint( $_POST['fcp_category'] ) : 0;
    $category    = $category_id ? get_term( $category_id, 'fcp_category' ) : null;
    if ( '' === $title || '' === $description || ! $category || is_wp_error( $category ) ) {
        fcp_redirect_dashboard( 'required' );
    }

    $work_mode = isset( $_POST['fcp_work_mode'] ) ? sanitize_key( wp_unslash( $_POST['fcp_work_mode'] ) ) : '';
    if ( ! array_key_exists( $work_mode, fcp_mode_options() ) ) {
        $work_mode = '';
    }
    $deadline = isset( $_POST['fcp_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_deadline'] ) ) : '';
    if ( $deadline ) {
        $d = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline );
        if ( ! $d || $d->format( 'Y-m-d' ) !== $deadline ) {
            fcp_redirect_dashboard( 'date' );
        }
    }

    $content = array(
        'post_title'   => $title,
        'post_content' => $description,
        'post_status'  => 'pending',
        'post_type'    => 'fcp_opportunity',
        'post_author'  => $post_id ? (int) $old_post->post_author : $current_id,
    );
    if ( $post_id ) {
        $content['ID'] = $post_id;
        $saved_id = wp_update_post( wp_slash( $content ), true );
    } else {
        $saved_id = wp_insert_post( wp_slash( $content ), true );
    }
    if ( is_wp_error( $saved_id ) || ! $saved_id ) {
        fcp_redirect_dashboard( 'error' );
    }

    wp_set_object_terms( $saved_id, array( $category_id ), 'fcp_category', false );
    $fields = array(
        '_fcp_location'      => isset( $_POST['fcp_location'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_location'] ) ) : '',
        '_fcp_work_mode'     => $work_mode,
        '_fcp_deadline'      => $deadline,
        '_fcp_requirements'  => isset( $_POST['fcp_requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fcp_requirements'] ) ) : '',
        '_fcp_contact_email' => isset( $_POST['fcp_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['fcp_contact_email'] ) ) : '',
        '_fcp_apply_url'     => isset( $_POST['fcp_apply_url'] ) ? esc_url_raw( wp_unslash( $_POST['fcp_apply_url'] ) ) : '',
    );
    foreach ( $fields as $key => $value ) {
        update_post_meta( $saved_id, $key, $value );
    }
    fcp_redirect_dashboard( 'saved' );
}

function fcp_handle_save_profile() {
    if ( ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'اجازه ویرایش پروفایل را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_profile_nonce'] ) ), 'fcp_save_profile' ) ) {
        wp_die( 'درخواست معتبر نیست.', 'خطای امنیتی', array( 'response' => 403 ) );
    }
    $id = get_current_user_id();
    $display_name = isset( $_POST['fcp_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_display_name'] ) ) : '';
    if ( $display_name ) {
        wp_update_user( array( 'ID' => $id, 'display_name' => $display_name ) );
    }
    foreach ( array(
        'department' => 'sanitize_text_field',
        'interests'  => 'sanitize_text_field',
        'bio'        => 'sanitize_textarea_field',
    ) as $key => $sanitize ) {
        $value = isset( $_POST[ 'fcp_' . $key ] ) ? call_user_func( $sanitize, wp_unslash( $_POST[ 'fcp_' . $key ] ) ) : '';
        update_user_meta( $id, '_fcp_' . $key, $value );
    }
    fcp_redirect_dashboard( 'profile' );
}
