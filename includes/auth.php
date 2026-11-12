<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------- Login UX: stay on the portal login page ---------- */

function fcp_login_failed( $username ) {
    fcp_count_failed_login( $username );
    if ( ! empty( $_POST['fcp_login'] ) ) {
        wp_safe_redirect( add_query_arg( 'login', 'failed', fcp_page_url( 'login' ) ) );
        exit;
    }
}

function fcp_login_redirect_empty( $user ) {
    if ( ! empty( $_POST['fcp_login'] ) && ( empty( $_POST['log'] ) || empty( $_POST['pwd'] ) ) ) {
        wp_safe_redirect( add_query_arg( 'login', 'empty', fcp_page_url( 'login' ) ) );
        exit;
    }
    return $user;
}

/* ---------- Brute-force throttle: 5 failures / 15 minutes per IP + username ---------- */

function fcp_throttle_key( $username ) {
    return 'fcp_lock_' . substr( hash( 'sha256', fcp_client_ip_hash() . strtolower( (string) $username ) ), 0, 32 );
}

function fcp_authenticate_throttle( $user, $username ) {
    if ( $username && (int) get_transient( fcp_throttle_key( $username ) ) >= 5 ) {
        return new WP_Error( 'fcp_locked', 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.' );
    }
    return $user;
}

function fcp_count_failed_login( $username ) {
    $key = fcp_throttle_key( $username );
    set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
}

function fcp_clear_failed_login( $user_login ) {
    delete_transient( fcp_throttle_key( $user_login ) );
}

/* ---------- Professors never see wp-admin ---------- */

function fcp_is_only_professor( $user = null ) {
    $user = $user ? $user : wp_get_current_user();
    return $user instanceof WP_User && $user->exists() && in_array( 'fcp_professor', (array) $user->roles, true ) && ! user_can( $user, 'manage_options' );
}

function fcp_hide_admin_bar( $show ) {
    return fcp_is_only_professor() ? false : $show;
}

function fcp_block_admin_for_professors() {
    if ( fcp_is_only_professor() && ! wp_doing_ajax() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        $script = isset( $_SERVER['PHP_SELF'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) : '';
        if ( 'admin-post.php' === $script ) {
            return;
        }
        wp_safe_redirect( fcp_page_url( 'dashboard' ) );
        exit;
    }
}

function fcp_login_redirect( $redirect_to, $request, $user ) {
    if ( $user instanceof WP_User && fcp_is_only_professor( $user ) ) {
        return fcp_page_url( 'dashboard' );
    }
    return $redirect_to;
}

/* ---------- Temporary passwords ---------- */

function fcp_generate_password( $length = 12 ) {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max      = strlen( $alphabet ) - 1;
    $out      = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $out .= $alphabet[ random_int( 0, $max ) ];
    }
    return $out;
}

function fcp_must_change_password( $user_id ) {
    return (bool) get_user_meta( $user_id, '_fcp_must_change_password', true );
}

function fcp_handle_change_password() {
    if ( ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_pw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_pw_nonce'] ) ), 'fcp_change_password' ) ) {
        wp_die( 'درخواست معتبر نیست.', 'خطا', array( 'response' => 403 ) );
    }
    $user = wp_get_current_user();
    $old  = isset( $_POST['fcp_old_password'] ) ? (string) wp_unslash( $_POST['fcp_old_password'] ) : '';
    $new  = isset( $_POST['fcp_new_password'] ) ? (string) wp_unslash( $_POST['fcp_new_password'] ) : '';
    $conf = isset( $_POST['fcp_confirm_password'] ) ? (string) wp_unslash( $_POST['fcp_confirm_password'] ) : '';
    if ( ! wp_check_password( $old, $user->user_pass, $user->ID ) ) {
        fcp_redirect_dashboard( 'pw_old', 'security' );
    }
    if ( strlen( $new ) < 10 || $new !== $conf || $new === $old ) {
        fcp_redirect_dashboard( 'pw_weak', 'security' );
    }
    wp_update_user( array( 'ID' => $user->ID, 'user_pass' => $new ) );
    delete_user_meta( $user->ID, '_fcp_must_change_password' );
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );
    fcp_redirect_dashboard( 'pw_ok' );
}

/* ---------- Professor withdraws / deletes an opportunity ---------- */

function fcp_handle_opportunity_action() {
    $id   = isset( $_POST['fcp_post_id'] ) ? absint( $_POST['fcp_post_id'] ) : 0;
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || 'fcp_opportunity' !== $post->post_type || ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_op_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_op_nonce'] ) ), 'fcp_opportunity_action_' . $id ) ) {
        wp_die( 'درخواست معتبر نیست.', 'خطا', array( 'response' => 403 ) );
    }
    $do = isset( $_POST['fcp_do'] ) ? sanitize_key( wp_unslash( $_POST['fcp_do'] ) ) : '';
    if ( 'withdraw' === $do ) {
        wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
        fcp_redirect_dashboard( 'withdrawn' );
    }
    if ( 'delete' === $do ) {
        wp_trash_post( $id );
        fcp_redirect_dashboard( 'deleted' );
    }
    fcp_redirect_dashboard( 'error' );
}
