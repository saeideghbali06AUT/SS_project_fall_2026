<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_register_content() {
    fcp_register_advice();
    register_taxonomy( 'fcp_category', array( 'fcp_opportunity' ), array(
        'labels'       => array(
            'name'          => 'دسته‌بندی فرصت‌ها',
            'singular_name' => 'دسته‌بندی فرصت',
            'add_new_item'  => 'افزودن دسته‌بندی',
        ),
        'public'       => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'opportunity-category', 'with_front' => false ),
    ) );

    register_post_type( 'fcp_opportunity', array(
        'labels' => array(
            'name'          => 'فرصت‌ها',
            'singular_name' => 'فرصت',
            'add_new'       => 'افزودن فرصت',
            'add_new_item'  => 'افزودن فرصت جدید',
            'edit_item'     => 'ویرایش فرصت',
            'all_items'     => 'همه فرصت‌ها',
            'menu_name'     => 'فرصت‌های دانشکده',
        ),
        'public'        => true,
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-welcome-learn-more',
        'menu_position' => 23,
        'has_archive'   => false,
        'rewrite'       => array( 'slug' => 'opportunity', 'with_front' => false ),
        'supports'      => array( 'title', 'editor', 'excerpt', 'author' ),
        'taxonomies'    => array( 'fcp_category' ),
        'map_meta_cap'  => true,
    ) );
}

function fcp_page_id( $key ) {
    $pages = get_option( 'fcp_pages', array() );
    return isset( $pages[ $key ] ) ? absint( $pages[ $key ] ) : 0;
}

function fcp_page_url( $key ) {
    $id = fcp_page_id( $key );
    return $id && get_post_status( $id ) ? get_permalink( $id ) : home_url( '/' );
}

function fcp_ensure_pages() {
    $page_titles = array(
        'home'       => array( 'خانه', 'faculty-home' ),
        'advice'     => array( 'توصیه‌ها', 'faculty-advice' ),
        'professors' => array( 'اساتید', 'faculty-professors' ),
        'categories' => array( 'دسته‌بندی‌ها', 'faculty-categories' ),
        'about'      => array( 'درباره ما', 'faculty-about' ),
        'more'       => array( 'سایر', 'faculty-more' ),
        'login'      => array( 'ورود اساتید', 'faculty-login' ),
        'dashboard'  => array( 'پنل استاد', 'faculty-dashboard' ),
    );

    $pages = get_option( 'fcp_pages', array() );
    foreach ( $page_titles as $key => $data ) {
        $existing = isset( $pages[ $key ] ) ? get_post( absint( $pages[ $key ] ) ) : null;
        if ( $existing && 'page' === $existing->post_type ) {
            $id = $existing->ID;
        } else {
            $existing_page = get_page_by_path( $data[1], OBJECT, 'page' );
            if ( $existing_page && get_post_meta( $existing_page->ID, '_fcp_page', true ) === $key ) {
                $id = $existing_page->ID;
            } else {
                $id = wp_insert_post( array(
                    'post_title'   => $data[0],
                    'post_name'    => $data[1],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '',
                ) );
            }
        }
        if ( ! is_wp_error( $id ) && $id ) {
            update_post_meta( $id, '_fcp_page', $key );
            $pages[ $key ] = (int) $id;
        }
    }
    update_option( 'fcp_pages', $pages );
    return $pages;
}

function fcp_activate() {
    fcp_register_content();
    add_role( 'fcp_professor', 'استاد دانشکده', array( 'read' => true ) );
    fcp_install_db();
    fcp_ensure_private_dir();
    if ( false === get_option( 'fcp_settings', false ) ) {
        add_option( 'fcp_settings', fcp_default_settings() );
    }

    $pages = fcp_ensure_pages();

    $defaults = array(
        'research'   => 'پژوهشی',
        'employment' => 'شغلی',
        'internship' => 'کارآموزی',
        'thesis'     => 'پروژه و پایان‌نامه',
    );
    foreach ( $defaults as $slug => $name ) {
        if ( ! term_exists( $slug, 'fcp_category' ) ) {
            wp_insert_term( $name, 'fcp_category', array( 'slug' => $slug ) );
        }
    }

    if ( ! get_option( 'fcp_previous_front_page' ) ) {
        update_option( 'fcp_previous_front_page', array(
            'show_on_front' => get_option( 'show_on_front' ),
            'page_on_front' => get_option( 'page_on_front' ),
        ) );
    }
    if ( ! empty( $pages['home'] ) ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', (int) $pages['home'] );
    }
    flush_rewrite_rules();
}

function fcp_deactivate() {
    flush_rewrite_rules();
    // Do not remove pages, professor accounts, opportunities, or their metadata.
}

function fcp_is_professor( $user = null ) {
    $user = $user ? $user : wp_get_current_user();
    return $user instanceof WP_User && $user->exists()
        && ( in_array( 'fcp_professor', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) );
}

function fcp_is_portal_request() {
    if ( is_singular( array( 'fcp_opportunity', 'fcp_advice' ) ) ) {
        return true;
    }
    return is_page() && (bool) get_post_meta( get_queried_object_id(), '_fcp_page', true );
}

function fcp_template_include( $template ) {
    if ( fcp_is_portal_request() ) {
        return FCP_DIR . 'templates/portal.php';
    }
    return $template;
}

function fcp_enqueue_assets() {
    if ( ! fcp_is_portal_request() ) {
        return;
    }
    wp_enqueue_style( 'fcp-portal-style', FCP_URL . 'assets/portal.css', array(), FCP_VERSION );
    $local_font = FCP_DIR . 'assets/fonts/Vazirmatn.woff2';
    if ( file_exists( $local_font ) ) {
        wp_add_inline_style( 'fcp-portal-style', '@font-face{font-family:"Vazirmatn";src:url("' . esc_url( FCP_URL . 'assets/fonts/Vazirmatn.woff2' ) . '") format("woff2");font-weight:100 900;font-display:swap}' );
    } elseif ( fcp_setting( 'web_fonts' ) ) {
        wp_enqueue_style( 'fcp-web-fonts', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&family=Sora:wght@600;700&display=swap', array(), null );
    }
    wp_enqueue_script( 'fcp-portal-script', FCP_URL . 'assets/portal.js', array(), FCP_VERSION, true );
    wp_localize_script( 'fcp-portal-script', 'FCP', array(
        'search'  => esc_url_raw( rest_url( 'fcp/v1/search' ) ),
        'vote'    => esc_url_raw( rest_url( 'fcp/v1/advice/' ) ),
        'voteErr' => 'ثبت نظر انجام نشد. کمی بعد دوباره تلاش کنید.',
        'copied'  => 'پیوند کپی شد',
        'saved'   => 'به ذخیره‌شده‌ها اضافه شد',
        'unsaved' => 'از ذخیره‌شده‌ها برداشته شد',
        'noAdvice'=> 'توصیه‌ای با این فیلتر پیدا نشد.',
        'noMatch' => 'نتیجه‌ای پیدا نشد. عبارت دیگری را امتحان کنید.',
        'hint'    => 'عنوان فرصت یا توصیه، نام استاد یا زمینه پژوهشی را بنویسید.',
        'error'   => 'جست‌وجو انجام نشد. دوباره تلاش کنید.',
    ) );
}

function fcp_plugin_action_links( $links ) {
    array_unshift( $links, '<a href="' . esc_url( fcp_page_url( 'home' ) ) . '">مشاهده سامانه</a>' );
    return $links;
}

function fcp_mode_options() {
    return array(
        'onsite'  => 'حضوری',
        'remote'  => 'دورکاری',
        'hybrid'  => 'ترکیبی',
        'flexible'=> 'توافقی',
    );
}

function fcp_deadline_label( $post_id ) {
    $info = fcp_deadline_info( $post_id );
    return $info['label'];
}

function fcp_all_categories() {
    $terms = get_terms( array( 'taxonomy' => 'fcp_category', 'hide_empty' => false, 'orderby' => 'name' ) );
    return is_wp_error( $terms ) ? array() : $terms;
}

function fcp_opportunity_category( $post_id ) {
    $terms = get_the_terms( $post_id, 'fcp_category' );
    return ! is_wp_error( $terms ) && $terms ? reset( $terms ) : null;
}

function fcp_add_admin_meta_box() {
    add_meta_box( 'fcp_details_box', 'جزئیات فرصت', 'fcp_render_admin_meta_box', 'fcp_opportunity', 'normal', 'high' );
}

function fcp_render_admin_meta_box( $post ) {
    wp_nonce_field( 'fcp_admin_meta_action', 'fcp_admin_meta_nonce' );
    $fields = array(
        '_fcp_location'       => array( 'محل فعالیت', 'text' ),
        '_fcp_work_mode'      => array( 'شیوه همکاری (onsite / remote / hybrid / flexible)', 'text' ),
        '_fcp_deadline'       => array( 'مهلت (YYYY-MM-DD)', 'date' ),
        '_fcp_requirements'   => array( 'شرایط و مهارت‌های موردنیاز', 'textarea' ),
        '_fcp_contact_email'  => array( 'ایمیل تماس', 'email' ),
        '_fcp_apply_url'      => array( 'لینک درخواست', 'url' ),
    );
    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br />';
        if ( 'textarea' === $field[1] ) {
            echo '<textarea style="width:100%;min-height:85px" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input style="width:100%" id="' . esc_attr( $key ) . '" type="' . esc_attr( $field[1] ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
        }
        echo '</p>';
    }
}

function fcp_save_admin_meta_box( $post_id ) {
    if ( ! isset( $_POST['fcp_admin_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcp_admin_meta_nonce'] ) ), 'fcp_admin_meta_action' ) ) {
        return;
    }
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $fields = array( '_fcp_location', '_fcp_work_mode', '_fcp_deadline', '_fcp_requirements', '_fcp_contact_email', '_fcp_apply_url' );
    foreach ( $fields as $key ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            continue;
        }
        $value = wp_unslash( $_POST[ $key ] );
        if ( '_fcp_contact_email' === $key ) {
            $value = sanitize_email( $value );
        } elseif ( '_fcp_apply_url' === $key ) {
            $value = esc_url_raw( $value );
        } elseif ( '_fcp_requirements' === $key ) {
            $value = sanitize_textarea_field( $value );
        } elseif ( '_fcp_work_mode' === $key ) {
            $value = array_key_exists( $value, fcp_mode_options() ) ? $value : '';
        } else {
            $value = sanitize_text_field( $value );
        }
        update_post_meta( $post_id, $key, $value );
    }
}

/* ---------- Settings & branding ---------- */

function fcp_default_settings() {
    return array(
        'name_fa'       => 'راویان',
        'name_en'       => 'Raviaut',
        'tagline'       => 'پلی میان استاد و دانشجو',
        'university'    => 'دانشگاه صنعتی امیرکبیر',
        'contact_email' => '',
        'contact_phone' => '',
        'address'       => '',
        'web_fonts'     => '1',
    );
}

function fcp_setting( $key ) {
    $saved    = get_option( 'fcp_settings', array() );
    $settings = wp_parse_args( is_array( $saved ) ? $saved : array(), fcp_default_settings() );
    return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
}

function fcp_logo_mark( $size = 26 ) {
    return '<svg class="fcp-mark" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M8 33C12 9 36 9 40 33" fill="none" stroke="currentColor" stroke-width="3.6" stroke-linecap="round"/><circle cx="8" cy="36" r="4.6" fill="currentColor"/><circle cx="40" cy="36" r="4.6" class="fcp-mark-accent"/></svg>';
}

function fcp_wordmark() {
    $en = (string) fcp_setting( 'name_en' );
    if ( preg_match( '/^(.+)(aut)$/i', $en, $m ) ) {
        return '<span class="fcp-wordmark" dir="ltr">' . esc_html( $m[1] ) . '<b>' . esc_html( $m[2] ) . '</b></span>';
    }
    return '<span class="fcp-wordmark" dir="ltr">' . esc_html( $en ) . '</span>';
}

/* ---------- Persian digits & Jalali calendar ---------- */

function fcp_digits( $value ) {
    return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

function fcp_g2j( $gy, $gm, $gd ) {
    $g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
    $gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
    $days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
    $jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
    $days %= 12053;
    $jy   += 4 * (int) ( $days / 1461 );
    $days %= 1461;
    if ( $days > 365 ) {
        $jy  += (int) ( ( $days - 1 ) / 365 );
        $days = ( $days - 1 ) % 365;
    }
    if ( $days < 186 ) {
        $jm = 1 + (int) ( $days / 31 );
        $jd = 1 + ( $days % 31 );
    } else {
        $jm = 7 + (int) ( ( $days - 186 ) / 30 );
        $jd = 1 + ( ( $days - 186 ) % 30 );
    }
    return array( $jy, $jm, $jd );
}

function fcp_jdate( $ymd, $long = true ) {
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', (string) $ymd, $m ) ) {
        return '';
    }
    $j = fcp_g2j( (int) $m[1], (int) $m[2], (int) $m[3] );
    if ( ! $long ) {
        return fcp_digits( sprintf( '%04d/%02d/%02d', $j[0], $j[1], $j[2] ) );
    }
    $months = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
    return fcp_digits( $j[2] ) . ' ' . $months[ $j[1] - 1 ] . ' ' . fcp_digits( $j[0] );
}

function fcp_post_jdate( $post_id ) {
    return fcp_jdate( get_post_time( 'Y-m-d', false, $post_id ) );
}

function fcp_current_jyear() {
    $j = fcp_g2j( (int) current_time( 'Y' ), (int) current_time( 'n' ), (int) current_time( 'j' ) );
    return fcp_digits( $j[0] );
}

function fcp_deadline_info( $post_id ) {
    $none  = array( 'state' => 'none', 'label' => 'بدون مهلت مشخص', 'note' => '', 'days' => null );
    $value = get_post_meta( $post_id, '_fcp_deadline', true );
    if ( ! $value ) {
        return $none;
    }
    $due = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
    if ( ! $due ) {
        return $none;
    }
    $today = DateTimeImmutable::createFromFormat( '!Y-m-d', current_time( 'Y-m-d' ) );
    $days  = (int) $today->diff( $due )->format( '%r%a' );
    $label = fcp_jdate( $value );
    if ( $days < 0 ) {
        return array( 'state' => 'expired', 'label' => $label, 'note' => 'مهلت تمام شده', 'days' => $days );
    }
    if ( 0 === $days ) {
        return array( 'state' => 'soon', 'label' => $label, 'note' => 'امروز آخرین روز است', 'days' => 0 );
    }
    return array( 'state' => $days <= 7 ? 'soon' : 'open', 'label' => $label, 'note' => fcp_digits( $days ) . ' روز مانده', 'days' => $days );
}

function fcp_is_expired( $post_id ) {
    $info = fcp_deadline_info( $post_id );
    return 'expired' === $info['state'];
}

function fcp_category_blurbs() {
    return array(
        'research'   => 'دستیار پژوهشی، آزمایشگاه و پروژه‌های علمی',
        'employment' => 'همکاری حرفه‌ای با دانشکده و پروژه‌های صنعتی',
        'internship' => 'تجربه‌ی واقعی کار در کنار اساتید',
        'thesis'     => 'موضوع‌های پیشنهادی برای پروژه و پایان‌نامه',
    );
}

function fcp_after_setup_theme() {
    add_theme_support( 'title-tag' );
}

/* ---------- Upgrades ---------- */

function fcp_maybe_upgrade() {
    if ( get_option( 'fcp_db_version' ) !== FCP_DB_VERSION ) {
        fcp_install_db();
        fcp_ensure_private_dir();
        if ( ! get_role( 'fcp_professor' ) ) {
            add_role( 'fcp_professor', 'استاد دانشکده', array( 'read' => true ) );
        }
        if ( false === get_option( 'fcp_settings', false ) ) {
            add_option( 'fcp_settings', fcp_default_settings() );
        }
        // New pages (e.g. «توصیه‌ها») and the new advice permalinks for sites upgraded from an older version.
        fcp_ensure_pages();
        flush_rewrite_rules();
    }
}

/* ---------- View counter ---------- */

function fcp_track_view() {
    if ( ! is_singular( array( 'fcp_opportunity', 'fcp_advice' ) ) || is_user_logged_in() ) {
        return;
    }
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
    if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview/', $ua ) ) {
        return;
    }
    $id     = get_queried_object_id();
    $cookie = 'fcp_v_' . $id;
    if ( isset( $_COOKIE[ $cookie ] ) || 'publish' !== get_post_status( $id ) ) {
        return;
    }
    update_post_meta( $id, '_fcp_views', (int) get_post_meta( $id, '_fcp_views', true ) + 1 );
    setcookie( $cookie, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}
