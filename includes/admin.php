<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_admin_menu() {
    $parent = 'edit.php?post_type=fcp_opportunity';
    add_submenu_page( $parent, 'کارت‌های دسترسی اساتید', 'کارت‌های دسترسی', 'create_users', 'fcp-cards', 'fcp_render_cards_page' );
    add_submenu_page( $parent, 'درخواست‌های دانشجویان', 'درخواست‌ها', 'manage_options', 'fcp-applications', 'fcp_render_admin_applications' );
    add_submenu_page( $parent, 'تنظیمات سامانه', 'تنظیمات', 'manage_options', 'fcp-settings', 'fcp_render_settings_page' );
}

function fcp_admin_assets( $hook ) {
    if ( false === strpos( (string) $hook, 'fcp-cards' ) ) {
        return;
    }
    wp_enqueue_style( 'fcp-admin-cards', FCP_URL . 'assets/cards-admin.css', array(), FCP_VERSION );
    wp_enqueue_script( 'fcp-qr', FCP_URL . 'assets/qr.js', array(), FCP_VERSION, true );
}

/* ---------- Opportunity list columns ---------- */

function fcp_admin_columns( $cols ) {
    $cols['fcp_views'] = 'بازدید';
    $cols['fcp_apps']  = 'درخواست‌ها';
    return $cols;
}

function fcp_admin_column_value( $col, $post_id ) {
    if ( 'fcp_views' === $col ) {
        echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_fcp_views', true ) ) );
    } elseif ( 'fcp_apps' === $col ) {
        echo esc_html( number_format_i18n( fcp_count_opportunity_applications( $post_id ) ) );
    }
}

/* ---------- Settings ---------- */

function fcp_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    check_admin_referer( 'fcp_save_settings', 'fcp_settings_nonce' );
    $in  = isset( $_POST['fcp'] ) && is_array( $_POST['fcp'] ) ? wp_unslash( $_POST['fcp'] ) : array();
    $new = fcp_default_settings();
    foreach ( array( 'name_fa', 'name_en', 'tagline', 'university', 'contact_phone', 'address' ) as $k ) {
        $new[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $new[ $k ];
    }
    $new['contact_email'] = isset( $in['contact_email'] ) ? sanitize_email( $in['contact_email'] ) : '';
    $new['web_fonts']     = ! empty( $in['web_fonts'] ) ? '1' : '0';
    update_option( 'fcp_settings', $new );
    wp_safe_redirect( add_query_arg( array( 'post_type' => 'fcp_opportunity', 'page' => 'fcp-settings', 'saved' => 1 ), admin_url( 'edit.php' ) ) );
    exit;
}

function fcp_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $fields = array(
        'name_fa'       => 'نام فارسی برند',
        'name_en'       => 'نام لاتین برند (مثلاً Raviaut)',
        'tagline'       => 'شعار کوتاه',
        'university'    => 'نام دانشگاه یا دانشکده',
        'contact_email' => 'ایمیل تماس',
        'contact_phone' => 'تلفن تماس',
        'address'       => 'نشانی',
    );
    echo '<div class="wrap" dir="rtl"><h1>تنظیمات سامانه</h1>';
    if ( ! empty( $_GET['saved'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
    }
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fcp_save_settings" />';
    wp_nonce_field( 'fcp_save_settings', 'fcp_settings_nonce' );
    echo '<table class="form-table" role="presentation">';
    foreach ( $fields as $key => $label ) {
        echo '<tr><th scope="row"><label for="fcp_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="fcp_' . esc_attr( $key ) . '" name="fcp[' . esc_attr( $key ) . ']" value="' . esc_attr( fcp_setting( $key ) ) . '" /></td></tr>';
    }
    echo '<tr><th scope="row">فونت وزیرمتن</th><td><label><input type="checkbox" name="fcp[web_fonts]" value="1" ' . checked( fcp_setting( 'web_fonts' ), '1', false ) . ' /> بارگذاری از Google Fonts</label><p class="description">اگر فایل <code>assets/fonts/Vazirmatn.woff2</code> را در افزونه بگذارید، به‌جای آن از همین فایل محلی استفاده می‌شود. این برای شبکه‌هایی که Google Fonts در آن‌ها محدود است بهتر است.</p></td></tr>';
    echo '</table>';
    submit_button( 'ذخیره تنظیمات' );
    echo '</form></div>';
}

/* ---------- Admin: all applications (read-only overview) ---------- */

function fcp_render_admin_applications() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $counts   = fcp_count_applications( 0 );
    $statuses = fcp_application_statuses();
    $rows     = fcp_get_applications( array( 'limit' => 200 ) );
    echo '<div class="wrap" dir="rtl"><h1>درخواست‌های دانشجویان</h1><p>';
    foreach ( $statuses as $k => $label ) {
        echo esc_html( $label ) . ': <strong>' . esc_html( number_format_i18n( $counts[ $k ] ) ) . '</strong> &nbsp; ';
    }
    echo '</p><table class="widefat striped"><thead><tr><th>تاریخ</th><th>دانشجو</th><th>ایمیل</th><th>فرصت</th><th>استاد</th><th>وضعیت</th></tr></thead><tbody>';
    if ( ! $rows ) {
        echo '<tr><td colspan="6">هنوز درخواستی ثبت نشده است.</td></tr>';
    }
    foreach ( $rows as $r ) {
        $prof = get_userdata( (int) $r->professor_id );
        echo '<tr><td>' . esc_html( fcp_jdate( $r->created_at, false ) ) . '</td><td>' . esc_html( $r->student_name ) . '</td><td>' . esc_html( $r->student_email ) . '</td><td><a href="' . esc_url( get_edit_post_link( (int) $r->opportunity_id ) ) . '">' . esc_html( get_the_title( (int) $r->opportunity_id ) ) . '</a></td><td>' . esc_html( $prof ? $prof->display_name : '—' ) . '</td><td>' . esc_html( isset( $statuses[ $r->status ] ) ? $statuses[ $r->status ] : $r->status ) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ---------- Access cards: create / reset professors, print cards ---------- */

function fcp_unique_username( $base ) {
    $base = sanitize_user( strtolower( preg_replace( '/[^a-z0-9._-]/i', '', $base ) ), true );
    if ( strlen( $base ) < 3 ) {
        $base = 'prof' . wp_rand( 100, 999 );
    }
    $name = $base;
    $i    = 1;
    while ( username_exists( $name ) ) {
        $i++;
        $name = $base . $i;
    }
    return $name;
}

function fcp_handle_create_cards() {
    if ( ! current_user_can( 'create_users' ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    check_admin_referer( 'fcp_create_cards', 'fcp_cards_nonce' );
    $raw   = isset( $_POST['fcp_lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fcp_lines'] ) ) : '';
    $lines = preg_split( '/\r\n|\r|\n/', $raw );
    $cards = array();
    $skipped = 0;
    foreach ( array_slice( array_filter( array_map( 'trim', $lines ) ), 0, 100 ) as $line ) {
        // Format: name | email | department | username (optional)
        $parts = array_map( 'trim', explode( '|', $line ) );
        $name  = isset( $parts[0] ) ? $parts[0] : '';
        $email = isset( $parts[1] ) ? sanitize_email( $parts[1] ) : '';
        $dept  = isset( $parts[2] ) ? $parts[2] : '';
        if ( '' === $name || ! is_email( $email ) || email_exists( $email ) ) {
            $skipped++;
            continue;
        }
        $login = fcp_unique_username( ! empty( $parts[3] ) ? $parts[3] : strstr( $email, '@', true ) );
        $pass  = fcp_generate_password();
        $uid   = wp_insert_user( array(
            'user_login'   => $login,
            'user_pass'    => $pass,
            'user_email'   => $email,
            'display_name' => $name,
            'first_name'   => $name,
            'role'         => 'fcp_professor',
        ) );
        if ( is_wp_error( $uid ) ) {
            $skipped++;
            continue;
        }
        update_user_meta( $uid, '_fcp_department', $dept );
        update_user_meta( $uid, '_fcp_must_change_password', 1 );
        $cards[] = array( 'name' => $name, 'dept' => $dept, 'login' => $login, 'pass' => $pass );
    }
    fcp_store_cards_and_redirect( $cards, $skipped );
}

function fcp_handle_reset_card() {
    if ( ! current_user_can( 'edit_users' ) ) {
        wp_die( 'دسترسی غیرمجاز.', 'خطا', array( 'response' => 403 ) );
    }
    $uid = isset( $_POST['fcp_user_id'] ) ? absint( $_POST['fcp_user_id'] ) : 0;
    check_admin_referer( 'fcp_reset_card_' . $uid, 'fcp_reset_nonce' );
    $user = get_userdata( $uid );
    if ( ! $user || ! in_array( 'fcp_professor', (array) $user->roles, true ) ) {
        wp_die( 'استاد پیدا نشد.', 'خطا', array( 'response' => 404 ) );
    }
    $pass = fcp_generate_password();
    wp_set_password( $pass, $uid );
    update_user_meta( $uid, '_fcp_must_change_password', 1 );
    $cards = array( array( 'name' => $user->display_name, 'dept' => get_user_meta( $uid, '_fcp_department', true ), 'login' => $user->user_login, 'pass' => $pass ) );
    fcp_store_cards_and_redirect( $cards, 0 );
}

function fcp_store_cards_and_redirect( array $cards, $skipped ) {
    $args = array( 'post_type' => 'fcp_opportunity', 'page' => 'fcp-cards', 'skipped' => (int) $skipped );
    if ( $cards ) {
        // Plain passwords live only in a 15-minute transient, owned by the creating admin; they are never stored in the DB.
        $token = wp_generate_password( 20, false, false );
        set_transient( 'fcp_cards_' . $token, array( 'owner' => get_current_user_id(), 'cards' => $cards ), 15 * MINUTE_IN_SECONDS );
        $args['batch'] = $token;
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
    exit;
}

function fcp_render_card_back( array $c, $login_url ) {
    ?>
    <div class="fcp-card-sheet">
        <div class="fcp-cb-top">
            <span class="fcp-cb-brand"><?php echo fcp_logo_mark( 18 ); ?> <?php echo fcp_wordmark(); ?></span>
            <span class="fcp-cb-badge">★ حساب ویژه استاد</span>
        </div>
        <div class="fcp-cb-welcome"><small>خوش آمدید</small><strong><?php echo esc_html( $c['name'] ); ?></strong><?php if ( $c['dept'] ) : ?><em><?php echo esc_html( $c['dept'] ); ?></em><?php endif; ?></div>
        <div class="fcp-cb-bottom">
            <dl>
                <div><dt>شناسه کاربری</dt><dd dir="ltr"><?php echo esc_html( $c['login'] ); ?></dd></div>
                <div><dt>رمز عبور موقت</dt><dd dir="ltr"><?php echo esc_html( $c['pass'] ); ?></dd></div>
            </dl>
            <div class="fcp-cb-qr"><span data-fcp-qr="<?php echo esc_attr( $login_url ); ?>" data-size="84"></span><small>برای ورود اسکن کنید</small></div>
        </div>
    </div>
    <?php
}

function fcp_render_cards_page() {
    if ( ! current_user_can( 'create_users' ) ) {
        return;
    }
    $login_url = fcp_page_url( 'login' );
    $batch     = isset( $_GET['batch'] ) ? sanitize_key( wp_unslash( $_GET['batch'] ) ) : '';
    $data      = $batch ? get_transient( 'fcp_cards_' . $batch ) : false;
    echo '<div class="wrap fcp-admin-cards" dir="rtl"><h1>کارت‌های دسترسی اساتید</h1>';
    if ( isset( $_GET['skipped'] ) && (int) $_GET['skipped'] > 0 ) {
        echo '<div class="notice notice-warning"><p>' . esc_html( number_format_i18n( (int) $_GET['skipped'] ) ) . ' سطر رد شد (ایمیل نامعتبر، تکراری یا نام خالی).</p></div>';
    }
    if ( $data && (int) $data['owner'] === get_current_user_id() ) {
        echo '<div class="notice notice-info fcp-no-print"><p><strong>رمزها فقط در همین صفحه و تا ۱۵ دقیقه دیده می‌شوند و جایی ذخیره نمی‌شوند.</strong> الان کارت‌ها را چاپ کنید یا PDF بگیرید. هر استاد در اولین ورود باید رمز را عوض کند.</p><p><button type="button" class="button button-primary" onclick="window.print()">چاپ کارت‌ها</button></p></div>';
        echo '<div class="fcp-card-print-area">';
        foreach ( $data['cards'] as $c ) {
            fcp_render_card_back( $c, $login_url );
        }
        echo '</div>';
    } elseif ( $batch ) {
        echo '<div class="notice notice-error"><p>مهلت نمایش این کارت‌ها تمام شده است. برای دریافت رمز تازه از فهرست پایین «رمز جدید و کارت» را بزنید.</p></div>';
    }
    echo '<div class="fcp-no-print">';
    echo '<h2>ساخت حساب و کارت</h2><p>هر استاد در یک سطر، با این قالب: <code>نام و عنوان | ایمیل | گروه آموزشی | نام کاربری (اختیاری)</code></p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fcp_create_cards" />';
    wp_nonce_field( 'fcp_create_cards', 'fcp_cards_nonce' );
    echo '<textarea name="fcp_lines" rows="6" class="large-text" dir="auto" placeholder="دکتر سارا احمدی | s.ahmadi@example.edu | مهندسی کامپیوتر"></textarea><p>';
    submit_button( 'ساخت حساب‌ها و کارت‌ها', 'primary', 'submit', false );
    echo '</p></form>';
    $profs = get_users( array( 'role' => 'fcp_professor', 'orderby' => 'display_name' ) );
    echo '<h2>اساتید فعلی</h2><table class="widefat striped"><thead><tr><th>نام</th><th>شناسه</th><th>ایمیل</th><th>گروه</th><th></th></tr></thead><tbody>';
    if ( ! $profs ) {
        echo '<tr><td colspan="5">هنوز استادی ثبت نشده است.</td></tr>';
    }
    foreach ( $profs as $u ) {
        echo '<tr><td>' . esc_html( $u->display_name ) . '</td><td dir="ltr">' . esc_html( $u->user_login ) . '</td><td dir="ltr">' . esc_html( $u->user_email ) . '</td><td>' . esc_html( get_user_meta( $u->ID, '_fcp_department', true ) ) . '</td><td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'رمز فعلی این استاد باطل می‌شود. ادامه می‌دهید؟\')"><input type="hidden" name="action" value="fcp_reset_card" /><input type="hidden" name="fcp_user_id" value="' . esc_attr( $u->ID ) . '" />';
        wp_nonce_field( 'fcp_reset_card_' . $u->ID, 'fcp_reset_nonce' );
        echo '<button class="button" type="submit">رمز جدید و کارت</button></form></td></tr>';
    }
    echo '</tbody></table></div></div>';
}
