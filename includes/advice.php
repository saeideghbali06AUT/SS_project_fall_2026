<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advice ("توصیه‌ها"): professors write advice for students.
 *
 * Storage:
 *  - Post type `fcp_advice` (post_author = professor, pending -> publish after admin approval)
 *  - Post meta: _fcp_audience (newcomer|senior), _fcp_topic, _fcp_helpful (cached counter), _fcp_views
 *  - Table {prefix}fcp_advice_votes: one "helpful" vote per visitor per advice (UNIQUE key)
 */

/* ---------- Vocabulary (single source of truth; validated on every write) ---------- */

function fcp_advice_audiences() {
    return array(
        'newcomer' => array(
            'label' => 'دانشجویان نو‌ورود',
            'short' => 'نو‌ورودها',
            'blurb' => 'اولین ترم‌ها، عادت‌های درست و اشتباه‌هایی که می‌شود از آن‌ها گذشت.',
        ),
        'senior'   => array(
            'label' => 'دانشجویان باتجربه‌تر',
            'short' => 'باتجربه‌ترها',
            'blurb' => 'انتخاب زمینه، پایان‌نامه، ادامه تحصیل و مسیر حرفه‌ای.',
        ),
    );
}

/** Topics per audience. A topic is only valid together with its own audience. */
function fcp_advice_topics() {
    return array(
        'newcomer' => array(
            'start'   => 'شروع دانشگاه',
            'study'   => 'درس خواندن',
            'skills'  => 'مهارت‌های پایه',
            'general' => 'عمومی',
        ),
        'senior'   => array(
            'field'   => 'زمینه فعالیت',
            'thesis'  => 'پایان‌نامه',
            'masters' => 'انتخاب رشته ارشد',
            'career'  => 'مسیر حرفه‌ای',
            'general' => 'عمومی',
        ),
    );
}

function fcp_advice_topic_label( $audience, $topic ) {
    $all = fcp_advice_topics();
    return isset( $all[ $audience ][ $topic ] ) ? $all[ $audience ][ $topic ] : '';
}

function fcp_advice_valid_pair( $audience, $topic ) {
    $all = fcp_advice_topics();
    return isset( $all[ $audience ] ) && isset( $all[ $audience ][ $topic ] );
}

function fcp_register_advice() {
    register_post_type( 'fcp_advice', array(
        'labels' => array(
            'name'          => 'توصیه‌ها',
            'singular_name' => 'توصیه',
            'add_new'       => 'افزودن توصیه',
            'add_new_item'  => 'افزودن توصیه جدید',
            'edit_item'     => 'ویرایش توصیه',
            'all_items'     => 'همه توصیه‌ها',
            'menu_name'     => 'توصیه‌ها',
        ),
        'public'              => true,
        'show_in_rest'        => false,
        'show_in_menu'        => 'edit.php?post_type=fcp_opportunity',
        'has_archive'         => false,
        'exclude_from_search' => true,
        'rewrite'             => array( 'slug' => 'advice', 'with_front' => false ),
        'supports'            => array( 'title', 'editor', 'excerpt', 'author' ),
        'map_meta_cap'        => true,
    ) );
}

/* ---------- Votes table helpers ---------- */

function fcp_table_votes() {
    global $wpdb;
    return $wpdb->prefix . 'fcp_advice_votes';
}

/** Anonymous, salted, per-visitor identifier. Never shown to anyone. */
function fcp_voter_hash() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 ) : '';
    return hash( 'sha256', 'advice|' . $ip . '|' . $ua . '|' . wp_salt( 'auth' ) );
}

function fcp_advice_has_voted( $advice_id ) {
    global $wpdb;
    $table = fcp_table_votes();
    return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE advice_id = %d AND voter_hash = %s", $advice_id, fcp_voter_hash() ) );
}

function fcp_advice_helpful_count( $advice_id ) {
    return (int) get_post_meta( $advice_id, '_fcp_helpful', true );
}

/** Recount from the table (source of truth) and refresh the cached counter used for sorting. */
function fcp_advice_sync_helpful( $advice_id ) {
    global $wpdb;
    $table = fcp_table_votes();
    $n     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE advice_id = %d", $advice_id ) );
    update_post_meta( $advice_id, '_fcp_helpful', $n );
    return $n;
}

/** Toggle the visitor's vote. Returns array( voted, count ) or WP_Error. */
function fcp_advice_toggle_vote( $advice_id ) {
    global $wpdb;
    $table = fcp_table_votes();
    $hash  = fcp_voter_hash();
    $row   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE advice_id = %d AND voter_hash = %s", $advice_id, $hash ) );
    if ( $row ) {
        $wpdb->delete( $table, array( 'id' => (int) $row ), array( '%d' ) );
        $voted = false;
    } else {
        $wpdb->insert( $table, array(
            'advice_id'  => (int) $advice_id,
            'voter_hash' => $hash,
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s' ) );
        // If a concurrent double-click hit the UNIQUE key the insert fails, but the vote exists anyway.
        $voted = true;
    }
    return array( 'voted' => $voted, 'count' => fcp_advice_sync_helpful( $advice_id ) );
}

/** Remove the votes of an advice that is permanently deleted. */
function fcp_advice_cleanup_votes( $post_id ) {
    global $wpdb;
    if ( 'fcp_advice' === get_post_type( $post_id ) ) {
        $wpdb->delete( fcp_table_votes(), array( 'advice_id' => (int) $post_id ), array( '%d' ) );
    }
}

/* ---------- Queries ---------- */

function fcp_advice_meta( $post_id ) {
    $audience = get_post_meta( $post_id, '_fcp_audience', true );
    $topic    = get_post_meta( $post_id, '_fcp_topic', true );
    return array(
        'audience' => isset( fcp_advice_audiences()[ $audience ] ) ? $audience : 'newcomer',
        'topic'    => $topic,
        'topic_label' => fcp_advice_topic_label( $audience, $topic ),
    );
}

function fcp_count_advice( $audience = '' ) {
    $args = array( 'post_type' => 'fcp_advice', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false );
    if ( $audience && isset( fcp_advice_audiences()[ $audience ] ) ) {
        $args['meta_query'] = array( array( 'key' => '_fcp_audience', 'value' => $audience ) );
    }
    $q = new WP_Query( $args );
    return (int) $q->found_posts;
}

function fcp_count_advice_by_topic( $audience, $topic ) {
    if ( ! fcp_advice_valid_pair( $audience, $topic ) ) {
        return 0;
    }
    $q = new WP_Query( array(
        'post_type'      => 'fcp_advice',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => array(
            'relation' => 'AND',
            array( 'key' => '_fcp_audience', 'value' => $audience ),
            array( 'key' => '_fcp_topic', 'value' => $topic ),
        ),
    ) );
    return (int) $q->found_posts;
}

function fcp_query_advice( $audience, $limit = 60 ) {
    return new WP_Query( array(
        'post_type'           => 'fcp_advice',
        'post_status'         => 'publish',
        'posts_per_page'      => max( 1, min( 100, (int) $limit ) ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'meta_query'          => array( array( 'key' => '_fcp_audience', 'value' => $audience ) ),
    ) );
}

function fcp_reading_minutes( $text ) {
    $words = preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) $text ) ), -1, PREG_SPLIT_NO_EMPTY );
    $n     = is_array( $words ) ? count( $words ) : 0;
    return max( 1, (int) ceil( $n / 180 ) );
}

/* ---------- Safe mini-formatting ----------
 * Professors write plain text. Supported: blank line = paragraph, "## " = heading, "- " = list item.
 * Everything is escaped BEFORE any markup is added, so no HTML can ever be injected.
 */

function fcp_advice_headings( $text ) {
    $out = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        if ( 0 === strpos( $line, '## ' ) ) {
            $title = trim( mb_substr( $line, 3 ) );
            if ( '' !== $title ) {
                $out[] = $title;
            }
        }
    }
    return $out;
}

function fcp_format_advice( $text ) {
    $lines = preg_split( '/\r\n|\r|\n/', (string) $text );
    $html  = '';
    $para  = array();
    $list  = array();
    $h     = 0;
    $flush = function () use ( &$html, &$para, &$list ) {
        if ( $para ) {
            $html .= '<p>' . implode( '<br />', $para ) . '</p>';
            $para = array();
        }
        if ( $list ) {
            $html .= '<ul class="fcp-bullets">' . implode( '', $list ) . '</ul>';
            $list = array();
        }
    };
    foreach ( $lines as $line ) {
        $line = rtrim( $line );
        if ( '' === trim( $line ) ) {
            $flush();
        } elseif ( 0 === strpos( $line, '## ' ) ) {
            $flush();
            $h++;
            $html .= '<h3 id="fcp-h' . (int) $h . '">' . esc_html( trim( mb_substr( $line, 3 ) ) ) . '</h3>';
        } elseif ( 0 === strpos( $line, '- ' ) ) {
            if ( $para ) {
                $html .= '<p>' . implode( '<br />', $para ) . '</p>';
                $para = array();
            }
            $list[] = '<li>' . esc_html( trim( mb_substr( $line, 2 ) ) ) . '</li>';
        } else {
            if ( $list ) {
                $html .= '<ul class="fcp-bullets">' . implode( '', $list ) . '</ul>';
                $list = array();
            }
            $para[] = esc_html( $line );
        }
    }
    $flush();
    return $html;
}

/* ---------- Professor handlers (logged in, own content only) ---------- */

function fcp_advice_notices() {
    return array(
        'advice_saved'     => array( 'توصیه ذخیره شد و برای تأیید مدیر ارسال گردید.', 'ok' ),
        'advice_withdrawn' => array( 'توصیه از نمایش عمومی برداشته شد.', 'ok' ),
        'advice_deleted'   => array( 'توصیه حذف شد.', 'ok' ),
        'advice_required'  => array( 'عنوان، مخاطب و موضوع را انتخاب کنید و متن توصیه را بنویسید (حداقل ۱۰۰ نویسه).', 'err' ),
        'advice_limit'     => array( 'هم‌زمان حداکثر ۱۰ توصیه در انتظار تأیید می‌تواند داشته باشید. تا تأیید مدیر صبر کنید.', 'err' ),
    );
}

function fcp_handle_save_advice() {
    if ( ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'اجازه ثبت توصیه را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_advice_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_advice_nonce'] ) ), 'fcp_save_advice' ) ) {
        wp_die( 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.', 'خطای امنیتی', array( 'response' => 403 ) );
    }
    $post_id    = isset( $_POST['fcp_post_id'] ) ? absint( $_POST['fcp_post_id'] ) : 0;
    $old_post   = $post_id ? get_post( $post_id ) : null;
    $current_id = get_current_user_id();
    if ( $post_id && ( ! $old_post || 'fcp_advice' !== $old_post->post_type || ( (int) $old_post->post_author !== $current_id && ! current_user_can( 'manage_options' ) ) ) ) {
        wp_die( 'امکان ویرایش این توصیه وجود ندارد.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
    }

    $title    = isset( $_POST['fcp_title'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['fcp_title'] ) ), 0, 160 ) : '';
    $summary  = isset( $_POST['fcp_summary'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['fcp_summary'] ) ), 0, 280 ) : '';
    $body     = isset( $_POST['fcp_body'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['fcp_body'] ) ), 0, 12000 ) : '';
    $audience = isset( $_POST['fcp_audience'] ) ? sanitize_key( wp_unslash( $_POST['fcp_audience'] ) ) : '';
    $topic    = isset( $_POST['fcp_topic'] ) ? sanitize_key( wp_unslash( $_POST['fcp_topic'] ) ) : '';
    if ( '' === $title || mb_strlen( $body ) < 100 || ! fcp_advice_valid_pair( $audience, $topic ) ) {
        fcp_redirect_dashboard( 'advice_required', 'advice_new' );
    }

    if ( ! $post_id ) {
        $pending = new WP_Query( array( 'post_type' => 'fcp_advice', 'post_status' => 'pending', 'author' => $current_id, 'fields' => 'ids', 'posts_per_page' => 1 ) );
        if ( $pending->found_posts >= 10 ) {
            fcp_redirect_dashboard( 'advice_limit', 'advice' );
        }
    }

    $content = array(
        'post_title'   => $title,
        'post_content' => $body,
        'post_excerpt' => $summary,
        'post_status'  => 'pending',
        'post_type'    => 'fcp_advice',
        'post_author'  => $post_id ? (int) $old_post->post_author : $current_id,
    );
    if ( $post_id ) {
        $content['ID'] = $post_id;
        $saved_id      = wp_update_post( wp_slash( $content ), true );
    } else {
        $saved_id = wp_insert_post( wp_slash( $content ), true );
    }
    if ( is_wp_error( $saved_id ) || ! $saved_id ) {
        fcp_redirect_dashboard( 'error', 'advice' );
    }
    update_post_meta( $saved_id, '_fcp_audience', $audience );
    update_post_meta( $saved_id, '_fcp_topic', $topic );
    fcp_redirect_dashboard( 'advice_saved', 'advice' );
}

function fcp_handle_advice_action() {
    $id   = isset( $_POST['fcp_post_id'] ) ? absint( $_POST['fcp_post_id'] ) : 0;
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || 'fcp_advice' !== $post->post_type || ! is_user_logged_in() || ! fcp_is_professor() ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    if ( ! isset( $_POST['fcp_adv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_adv_nonce'] ) ), 'fcp_advice_action_' . $id ) ) {
        wp_die( 'درخواست معتبر نیست.', 'خطا', array( 'response' => 403 ) );
    }
    $do = isset( $_POST['fcp_do'] ) ? sanitize_key( wp_unslash( $_POST['fcp_do'] ) ) : '';
    if ( 'withdraw' === $do ) {
        wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
        fcp_redirect_dashboard( 'advice_withdrawn', 'advice' );
    }
    if ( 'delete' === $do ) {
        wp_trash_post( $id );
        fcp_redirect_dashboard( 'advice_deleted', 'advice' );
    }
    fcp_redirect_dashboard( 'error', 'advice' );
}

/* ---------- Admin: meta box, list columns ---------- */

function fcp_add_advice_meta_box() {
    add_meta_box( 'fcp_advice_box', 'مخاطب و موضوع توصیه', 'fcp_render_advice_meta_box', 'fcp_advice', 'side', 'high' );
}

function fcp_render_advice_meta_box( $post ) {
    wp_nonce_field( 'fcp_advice_meta_action', 'fcp_advice_meta_nonce' );
    $audience = get_post_meta( $post->ID, '_fcp_audience', true );
    $topic    = get_post_meta( $post->ID, '_fcp_topic', true );
    echo '<p><label for="fcp_audience"><strong>مخاطب</strong></label><br /><select id="fcp_audience" name="fcp_audience" style="width:100%">';
    foreach ( fcp_advice_audiences() as $key => $a ) {
        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $audience, $key, false ) . '>' . esc_html( $a['label'] ) . '</option>';
    }
    echo '</select></p><p><label for="fcp_topic"><strong>موضوع</strong></label><br /><select id="fcp_topic" name="fcp_topic" style="width:100%">';
    foreach ( fcp_advice_topics() as $aud => $topics ) {
        foreach ( $topics as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $audience === $aud && $topic === $key, true, false ) . '>' . esc_html( fcp_advice_audiences()[ $aud ]['short'] . ' — ' . $label ) . '</option>';
        }
    }
    echo '</select></p><p class="description">موضوع باید با مخاطب هم‌خوان باشد؛ در غیر این صورت ذخیره نمی‌شود.</p>';
}

function fcp_save_advice_meta_box( $post_id ) {
    if ( ! isset( $_POST['fcp_advice_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_advice_meta_nonce'] ) ), 'fcp_advice_meta_action' ) ) {
        return;
    }
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $audience = isset( $_POST['fcp_audience'] ) ? sanitize_key( wp_unslash( $_POST['fcp_audience'] ) ) : '';
    $topic    = isset( $_POST['fcp_topic'] ) ? sanitize_key( wp_unslash( $_POST['fcp_topic'] ) ) : '';
    if ( fcp_advice_valid_pair( $audience, $topic ) ) {
        update_post_meta( $post_id, '_fcp_audience', $audience );
        update_post_meta( $post_id, '_fcp_topic', $topic );
    }
}

function fcp_advice_admin_columns( $cols ) {
    $cols['fcp_aud']     = 'مخاطب';
    $cols['fcp_topic']   = 'موضوع';
    $cols['fcp_helpful'] = 'مفید';
    return $cols;
}

function fcp_advice_admin_column_value( $col, $post_id ) {
    $m = fcp_advice_meta( $post_id );
    if ( 'fcp_aud' === $col ) {
        echo esc_html( fcp_advice_audiences()[ $m['audience'] ]['short'] );
    } elseif ( 'fcp_topic' === $col ) {
        echo esc_html( $m['topic_label'] ? $m['topic_label'] : '—' );
    } elseif ( 'fcp_helpful' === $col ) {
        echo esc_html( number_format_i18n( fcp_advice_helpful_count( $post_id ) ) );
    }
}

/* ---------- REST: "helpful" vote ---------- */

function fcp_register_advice_rest() {
    register_rest_route( 'fcp/v1', '/advice/(?P<id>\d+)/helpful', array(
        'methods'             => 'POST',
        'callback'            => 'fcp_rest_advice_helpful',
        'permission_callback' => 'fcp_rest_vote_permission',
        'args'                => array(
            'id' => array( 'validate_callback' => function ( $v ) { return is_numeric( $v ) && (int) $v > 0; } ),
        ),
    ) );
}

/** Same-site requests only (cheap CSRF defence for an anonymous endpoint). */
function fcp_rest_vote_permission( WP_REST_Request $request ) {
    $origin = $request->get_header( 'origin' );
    if ( $origin ) {
        $host_o = wp_parse_url( $origin, PHP_URL_HOST );
        $host_s = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! $host_o || ! $host_s || strtolower( $host_o ) !== strtolower( $host_s ) ) {
            return new WP_Error( 'fcp_origin', 'درخواست نامعتبر است.', array( 'status' => 403 ) );
        }
    }
    return true;
}

function fcp_rest_advice_helpful( WP_REST_Request $request ) {
    $id   = absint( $request['id'] );
    $post = get_post( $id );
    if ( ! $post || 'fcp_advice' !== $post->post_type || 'publish' !== $post->post_status ) {
        return new WP_Error( 'fcp_not_found', 'این توصیه در دسترس نیست.', array( 'status' => 404 ) );
    }
    // Rate limit: 60 vote toggles per hour per visitor.
    $key  = 'fcp_vrate_' . substr( fcp_voter_hash(), 0, 24 );
    $rate = (int) get_transient( $key );
    if ( $rate >= 60 ) {
        return new WP_Error( 'fcp_rate', 'تعداد درخواست‌ها زیاد بود. کمی بعد دوباره تلاش کنید.', array( 'status' => 429 ) );
    }
    set_transient( $key, $rate + 1, HOUR_IN_SECONDS );
    $result = fcp_advice_toggle_vote( $id );
    return rest_ensure_response( $result );
}

/** One grouped query for all topic counters (home "path" map). Returns [audience][topic] => count. */
function fcp_advice_topic_counts() {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.meta_value AS aud, t.meta_value AS topic, COUNT(*) AS c
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = %s
         JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = %s
         WHERE p.post_type = %s AND p.post_status = %s
         GROUP BY a.meta_value, t.meta_value",
        '_fcp_audience', '_fcp_topic', 'fcp_advice', 'publish'
    ) );
    $out = array();
    foreach ( (array) $rows as $r ) {
        if ( fcp_advice_valid_pair( $r->aud, $r->topic ) ) {
            $out[ $r->aud ][ $r->topic ] = (int) $r->c;
        }
    }
    return $out;
}

function fcp_advice_summary( $post_id, $words = 26 ) {
    $excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
    if ( '' !== $excerpt ) {
        return $excerpt;
    }
    $plain = preg_replace( '/^(## |- )/m', '', (string) get_post_field( 'post_content', $post_id ) );
    return wp_trim_words( wp_strip_all_tags( $plain ), $words, '…' );
}

/** Total «مفید بود» votes received by a professor's published advice. */
function fcp_professor_helpful_total( $user_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(CAST(m.meta_value AS UNSIGNED)),0) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = %s AND p.post_author = %d",
        '_fcp_helpful', 'fcp_advice', 'publish', $user_id
    ) );
}
