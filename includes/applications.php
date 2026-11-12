<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------- Private resume storage ---------- */

function fcp_private_dir() {
    $u = wp_upload_dir();
    return trailingslashit( $u['basedir'] ) . 'fcp-private/';
}

function fcp_ensure_private_dir() {
    $dir = fcp_private_dir();
    if ( ! wp_mkdir_p( $dir ) ) {
        return;
    }
    if ( ! file_exists( $dir . '.htaccess' ) ) {
        file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" );
    }
    if ( ! file_exists( $dir . 'index.php' ) ) {
        file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
    }
}

function fcp_application_redirect( $post_id, $code ) {
    $url = add_query_arg( 'fcp_app', $code, get_permalink( $post_id ) ) . '#apply';
    wp_safe_redirect( $url );
    exit;
}

function fcp_client_ip_hash() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    return hash( 'sha256', $ip . wp_salt( 'auth' ) );
}

/* ---------- Student submits an application (no login needed) ---------- */

function fcp_handle_submit_application() {
    $post_id = isset( $_POST['fcp_opportunity_id'] ) ? absint( $_POST['fcp_opportunity_id'] ) : 0;
    $post    = $post_id ? get_post( $post_id ) : null;
    if ( ! $post || 'fcp_opportunity' !== $post->post_type || 'publish' !== $post->post_status ) {
        wp_die( 'این فرصت در دسترس نیست.', 'خطا', array( 'response' => 404 ) );
    }
    if ( ! isset( $_POST['fcp_app_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_app_nonce'] ) ), 'fcp_submit_application' ) ) {
        fcp_application_redirect( $post_id, 'nonce' );
    }
    // Honeypot + minimum fill time (bots).
    if ( ! empty( $_POST['fcp_website'] ) ) {
        fcp_application_redirect( $post_id, 'ok' );
    }
    $started = isset( $_POST['fcp_t'] ) ? absint( $_POST['fcp_t'] ) : 0;
    if ( $started && ( time() - $started ) < 4 ) {
        fcp_application_redirect( $post_id, 'fast' );
    }
    if ( fcp_is_expired( $post_id ) ) {
        fcp_application_redirect( $post_id, 'expired' );
    }
    // Rate limit: 5 applications per hour per visitor.
    $rate_key = 'fcp_rate_' . substr( fcp_client_ip_hash(), 0, 24 );
    $rate     = (int) get_transient( $rate_key );
    if ( $rate >= 5 ) {
        fcp_application_redirect( $post_id, 'rate' );
    }

    $name    = isset( $_POST['fcp_student_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_student_name'] ) ) : '';
    $email   = isset( $_POST['fcp_student_email'] ) ? sanitize_email( wp_unslash( $_POST['fcp_student_email'] ) ) : '';
    $number  = isset( $_POST['fcp_student_number'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_student_number'] ) ) : '';
    $field   = isset( $_POST['fcp_student_field'] ) ? sanitize_text_field( wp_unslash( $_POST['fcp_student_field'] ) ) : '';
    $message = isset( $_POST['fcp_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fcp_message'] ) ) : '';
    if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 20 ) {
        fcp_application_redirect( $post_id, 'invalid' );
    }
    $name    = mb_substr( $name, 0, 150 );
    $number  = mb_substr( $number, 0, 40 );
    $field   = mb_substr( $field, 0, 150 );
    $message = mb_substr( $message, 0, 3000 );

    // Optional resume (PDF / Word, max 3 MB), stored outside the public uploads URL space.
    $stored = '';
    $shown  = '';
    if ( ! empty( $_FILES['fcp_resume']['name'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['fcp_resume']['error'] ) {
        $file = $_FILES['fcp_resume']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if ( UPLOAD_ERR_OK !== (int) $file['error'] || $file['size'] > 3 * MB_IN_BYTES || ! is_uploaded_file( $file['tmp_name'] ) ) {
            fcp_application_redirect( $post_id, 'file' );
        }
        $allowed = array(
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
        if ( empty( $check['ext'] ) || ! isset( $allowed[ $check['ext'] ] ) ) {
            fcp_application_redirect( $post_id, 'file' );
        }
        fcp_ensure_private_dir();
        $stored = bin2hex( random_bytes( 16 ) ) . '.' . $check['ext'];
        if ( ! move_uploaded_file( $file['tmp_name'], fcp_private_dir() . $stored ) ) {
            fcp_application_redirect( $post_id, 'file' );
        }
        $shown = mb_substr( sanitize_file_name( $file['name'] ), 0, 180 );
    }

    $id = fcp_insert_application( array(
        'opportunity_id' => $post_id,
        'professor_id'   => (int) $post->post_author,
        'student_name'   => $name,
        'student_email'  => $email,
        'student_number' => $number,
        'student_field'  => $field,
        'message'        => $message,
        'resume_file'    => $stored,
        'resume_name'    => $shown,
        'ip_hash'        => fcp_client_ip_hash(),
    ) );
    if ( is_wp_error( $id ) ) {
        if ( $stored && file_exists( fcp_private_dir() . $stored ) ) {
            wp_delete_file( fcp_private_dir() . $stored );
        }
        fcp_application_redirect( $post_id, 'duplicate' === $id->get_error_code() ? 'duplicate' : 'error' );
    }
    set_transient( $rate_key, $rate + 1, HOUR_IN_SECONDS );
    fcp_notify_new_application( $id );
    fcp_application_redirect( $post_id, 'ok' );
}

function fcp_notify_new_application( $id ) {
    $app = fcp_get_application( $id );
    if ( ! $app ) {
        return;
    }
    $prof  = get_userdata( (int) $app->professor_id );
    $title = get_the_title( (int) $app->opportunity_id );
    $brand = fcp_setting( 'name_fa' );
    if ( $prof && $prof->user_email ) {
        $body  = "سلام {$prof->display_name}،\n\n";
        $body .= "یک درخواست تازه برای «{$title}» ثبت شد.\n";
        $body .= "نام: {$app->student_name}\nایمیل: {$app->student_email}\n\n";
        $body .= 'برای دیدن جزئیات وارد پنل خود شوید: ' . add_query_arg( 'tab', 'applications', fcp_page_url( 'dashboard' ) ) . "\n";
        wp_mail( $prof->user_email, "[{$brand}] درخواست تازه برای {$title}", $body );
    }
    $body  = "سلام {$app->student_name}،\n\n";
    $body .= "درخواست شما برای «{$title}» ثبت شد. استاد پس از بررسی با شما تماس می‌گیرد.\n\n{$brand}";
    wp_mail( $app->student_email, "[{$brand}] درخواست شما ثبت شد", $body );
}

/* ---------- Professor updates status / note ---------- */

function fcp_user_can_manage_application( $app ) {
    return $app && is_user_logged_in() && fcp_is_professor() && ( (int) $app->professor_id === get_current_user_id() || current_user_can( 'manage_options' ) );
}

function fcp_handle_update_application() {
    $id  = isset( $_POST['fcp_app_id'] ) ? absint( $_POST['fcp_app_id'] ) : 0;
    $app = $id ? fcp_get_application( $id ) : null;
    if ( ! fcp_user_can_manage_application( $app ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_update_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_update_nonce'] ) ), 'fcp_update_application_' . $id ) ) {
        wp_die( 'درخواست معتبر نیست.', 'خطا', array( 'response' => 403 ) );
    }
    $status = isset( $_POST['fcp_status'] ) ? sanitize_key( wp_unslash( $_POST['fcp_status'] ) ) : '';
    $note   = isset( $_POST['fcp_note'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['fcp_note'] ) ), 0, 1500 ) : '';
    if ( ! fcp_update_application( $id, $status, $note ) ) {
        fcp_redirect_dashboard( 'error', 'applications' );
    }
    if ( ! empty( $_POST['fcp_notify'] ) && $status !== $app->status && in_array( $status, array( 'accepted', 'rejected', 'reviewing' ), true ) ) {
        $labels = fcp_application_statuses();
        $title  = get_the_title( (int) $app->opportunity_id );
        $body   = "سلام {$app->student_name}،\n\nوضعیت درخواست شما برای «{$title}» به «{$labels[ $status ]}» تغییر کرد.\n";
        if ( $note ) {
            $body .= "\nپیام استاد:\n{$note}\n";
        }
        wp_mail( $app->student_email, '[' . fcp_setting( 'name_fa' ) . '] به‌روزرسانی درخواست', $body );
    }
    fcp_redirect_dashboard( 'app_saved', 'applications' );
}

/* ---------- Authenticated resume download ---------- */

function fcp_handle_download_resume() {
    $id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $app = $id ? fcp_get_application( $id ) : null;
    if ( ! fcp_user_can_manage_application( $app ) || ! $app->resume_file ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fcp_resume_' . $id ) ) {
        wp_die( 'پیوند معتبر نیست.', 'خطا', array( 'response' => 403 ) );
    }
    $path = fcp_private_dir() . basename( $app->resume_file );
    if ( ! file_exists( $path ) ) {
        wp_die( 'فایل پیدا نشد.', 'خطا', array( 'response' => 404 ) );
    }
    $ext   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    $types = array( 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
    nocache_headers();
    header( 'Content-Type: ' . ( isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream' ) );
    header( 'Content-Disposition: attachment; filename="resume-' . $id . '.' . $ext . '"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Length: ' . filesize( $path ) );
    readfile( $path );
    exit;
