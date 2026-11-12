<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_render_login() {
    $state = isset( $_GET['login'] ) ? sanitize_key( wp_unslash( $_GET['login'] ) ) : '';
    echo '<section class="fcp-wrap fcp-auth"><div class="fcp-auth-card">';
    if ( is_user_logged_in() ) {
        echo '<h1>شما وارد شده‌اید</h1>';
        if ( fcp_is_professor() ) {
            echo '<p class="fcp-lead">برای ثبت آگهی و دیدن درخواست‌ها به پنل خود بروید.</p><a class="fcp-btn is-block" href="' . esc_url( fcp_page_url( 'dashboard' ) ) . '">ورود به پنل استاد</a>';
        } else {
            echo '<p class="fcp-lead">این حساب نقش استاد ندارد. برای دریافت دسترسی با مدیر سامانه تماس بگیرید.</p>';
        }
    } else {
        echo '<span class="fcp-gold-badge">★ حساب ویژه استاد</span><h1>ورود اساتید</h1><p class="fcp-lead">شناسه و رمز عبور را از روی کارت دسترسی وارد کنید.</p>';
        if ( 'failed' === $state ) {
            echo '<div class="fcp-notice is-err" role="alert">شناسه یا رمز عبور درست نیست.</div>';
        } elseif ( 'empty' === $state ) {
            echo '<div class="fcp-notice is-err" role="alert">شناسه و رمز عبور را وارد کنید.</div>';
        }
        ?>
        <form class="fcp-form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
            <input type="hidden" name="fcp_login" value="1" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_url( fcp_page_url( 'dashboard' ) ); ?>" />
            <label>شناسه کاربری<input class="fcp-input" name="log" autocomplete="username" dir="ltr" required /></label>
            <label>رمز عبور<input class="fcp-input" type="password" name="pwd" autocomplete="current-password" dir="ltr" required /></label>
            <label class="fcp-check"><input type="checkbox" name="rememberme" value="forever" /> مرا به خاطر بسپار</label>
            <button class="fcp-btn is-block" type="submit">ورود</button>
        </form>
        <p class="fcp-auth-help">حساب را فقط مدیر سامانه می‌سازد. رمز را فراموش کرده‌اید؟ <a href="<?php echo esc_url( wp_lostpassword_url( fcp_page_url( 'login' ) ) ); ?>">بازیابی رمز</a></p>
        <?php
    }
    echo '</div></section>';
}

function fcp_dashboard_notices() {
    return array(
        'saved'      => array( 'آگهی ذخیره شد و برای تأیید مدیر ارسال گردید.', 'ok' ),
        'profile'    => array( 'پروفایل به‌روزرسانی شد.', 'ok' ),
        'app_saved'  => array( 'وضعیت درخواست ذخیره شد.', 'ok' ),
        'withdrawn'  => array( 'آگهی از نمایش عمومی برداشته شد.', 'ok' ),
        'deleted'    => array( 'آگهی حذف شد.', 'ok' ),
        'pw_ok'      => array( 'رمز عبور تغییر کرد.', 'ok' ),
        'required'   => array( 'عنوان، شرح و دسته‌بندی الزامی هستند.', 'err' ),
        'date'       => array( 'مهلت واردشده معتبر نیست.', 'err' ),
        'pw_old'     => array( 'رمز فعلی درست نیست.', 'err' ),
        'pw_weak'    => array( 'رمز جدید باید حداقل ۱۰ نویسه و با تکرارش یکی باشد و با رمز قبلی فرق کند.', 'err' ),
        'error'      => array( 'عملیات انجام نشد. دوباره تلاش کنید.', 'err' ),
    );
}

function fcp_render_dashboard() {
    if ( ! is_user_logged_in() ) {
        fcp_render_login();
        return;
    }
    if ( ! fcp_is_professor() ) {
        fcp_render_page_heading( 'دسترسی محدود', 'این حساب امکان ثبت آگهی ندارد. با مدیر سامانه تماس بگیرید.' );
        return;
    }
    $user     = wp_get_current_user();
    $must     = fcp_must_change_password( $user->ID );
    $notice   = isset( $_GET['fcp_notice'] ) ? sanitize_key( wp_unslash( $_GET['fcp_notice'] ) ) : '';
    $notices  = array_merge( fcp_dashboard_notices(), fcp_advice_notices() );
    $edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    $editing  = $edit_id ? get_post( $edit_id ) : null;
    if ( $editing && ( 'fcp_opportunity' !== $editing->post_type || ( (int) $editing->post_author !== $user->ID && ! current_user_can( 'manage_options' ) ) ) ) {
        $editing = null;
    }
    $adv_edit_id = isset( $_GET['edit_advice'] ) ? absint( $_GET['edit_advice'] ) : 0;
    $adv_editing = $adv_edit_id ? get_post( $adv_edit_id ) : null;
    if ( $adv_editing && ( 'fcp_advice' !== $adv_editing->post_type || ( (int) $adv_editing->post_author !== $user->ID && ! current_user_can( 'manage_options' ) ) ) ) {
        $adv_editing = null;
    }
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'advice';
    if ( ! in_array( $tab, array( 'advice', 'advice_new', 'list', 'applications', 'new', 'profile', 'security' ), true ) ) {
        $tab = 'advice';
    }
    if ( $editing ) {
        $tab = 'new';
    }
    if ( $adv_editing ) {
        $tab = 'advice_new';
    }
    if ( $must ) {
        $tab = 'security';
    }
    $posts = get_posts( array( 'post_type' => 'fcp_opportunity', 'post_status' => array( 'publish', 'pending', 'draft' ), 'author' => $user->ID, 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
    $n_pub = 0; $n_pend = 0; $views = 0;
    foreach ( $posts as $p ) {
        if ( 'publish' === $p->post_status ) { $n_pub++; }
        if ( 'pending' === $p->post_status ) { $n_pend++; }
        $views += (int) get_post_meta( $p->ID, '_fcp_views', true );
    }
    $advice_posts = get_posts( array( 'post_type' => 'fcp_advice', 'post_status' => array( 'publish', 'pending', 'draft' ), 'author' => $user->ID, 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
    $adv_pub      = 0;
    foreach ( $advice_posts as $ap ) {
        if ( 'publish' === $ap->post_status ) { $adv_pub++; }
    }
    $adv_helpful = fcp_professor_helpful_total( $user->ID );
    $counts = fcp_count_applications( $user->ID );
    $dash   = fcp_page_url( 'dashboard' );
    $tabs   = array(
        'advice'       => 'توصیه‌ها',
        'advice_new'   => 'توصیه جدید',
        'list'         => 'آگهی‌ها',
        'applications' => 'درخواست‌ها' . ( $counts['new'] ? ' (' . fcp_digits( $counts['new'] ) . ')' : '' ),
        'new'          => 'آگهی جدید',
        'profile'      => 'پروفایل',
        'security'     => 'امنیت',
    );
    ?>
    <section class="fcp-wrap fcp-dash">
        <header class="fcp-dash-head">
            <span class="fcp-gold-badge">★ حساب ویژه استاد</span>
            <h1>سلام، <?php echo esc_html( $user->display_name ); ?></h1>
            <p class="fcp-lead"><a class="fcp-link" href="<?php echo esc_url( add_query_arg( 'professor', $user->ID, fcp_page_url( 'professors' ) ) ); ?>">پروفایل عمومی</a> · <a class="fcp-link" href="<?php echo esc_url( wp_logout_url( fcp_page_url( 'home' ) ) ); ?>">خروج</a></p>
        </header>
        <?php if ( $must ) : ?><div class="fcp-notice is-warn">برای ادامه، رمز موقت کارت دسترسی را عوض کنید.</div><?php endif; ?>
        <?php if ( $notice && isset( $notices[ $notice ] ) ) : ?><div class="fcp-notice is-<?php echo esc_attr( $notices[ $notice ][1] ); ?>" role="status"><?php echo esc_html( $notices[ $notice ][0] ); ?></div><?php endif; ?>
        <div class="fcp-stats">
            <div class="is-hl"><strong><?php echo esc_html( fcp_digits( $adv_pub ) ); ?></strong><span>توصیه منتشرشده</span></div>
            <div class="is-hl"><strong><?php echo esc_html( fcp_digits( $adv_helpful ) ); ?></strong><span>نظر «مفید بود»</span></div>
            <div><strong><?php echo esc_html( fcp_digits( $n_pub ) ); ?></strong><span>آگهی منتشرشده</span></div>
            <div><strong><?php echo esc_html( fcp_digits( $n_pend ) ); ?></strong><span>در انتظار تأیید</span></div>
            <div><strong><?php echo esc_html( fcp_digits( $counts['all'] ) ); ?></strong><span>درخواست دریافتی</span></div>
            <div><strong><?php echo esc_html( fcp_digits( $views ) ); ?></strong><span>بازدید آگهی‌ها</span></div>
        </div>
        <?php if ( ! $must ) : ?>
        <nav class="fcp-seg" aria-label="بخش‌های پنل">
            <?php foreach ( $tabs as $key => $label ) : ?><a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( 'list' === $key ? $dash : add_query_arg( 'tab', $key, $dash ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <div class="fcp-panel">
            <?php
            if ( 'security' === $tab ) {
                fcp_render_password_form();
            } elseif ( 'advice_new' === $tab ) {
                fcp_render_advice_form( $adv_editing );
            } elseif ( 'advice' === $tab ) {
                fcp_render_advice_list( $advice_posts, $dash );
            } elseif ( 'new' === $tab ) {
                fcp_render_opportunity_form( $editing );
            } elseif ( 'profile' === $tab ) {
                fcp_render_profile_form( $user );
            } elseif ( 'applications' === $tab ) {
                fcp_render_applications_panel( $user );
            } else {
                fcp_render_my_list( $posts, $dash );
            }
            ?>
        </div>
    </section>
    <?php
}

function fcp_render_my_list( $posts, $dash ) {
    ?>
    <div class="fcp-panel-head"><h2>آگهی‌های من</h2><a class="fcp-btn is-sm" href="<?php echo esc_url( add_query_arg( 'tab', 'new', $dash ) ); ?>">آگهی جدید</a></div>
    <?php if ( ! $posts ) : ?>
        <div class="fcp-empty"><h3>هنوز آگهی ثبت نکرده‌اید</h3><p>اولین فرصت را معرفی کنید تا بعد از تأیید مدیر، همه دانشجوها ببینند.</p></div>
    <?php else : ?>
        <ul class="fcp-list">
        <?php foreach ( $posts as $p ) :
            $status = $p->post_status;
            $apps   = fcp_count_opportunity_applications( $p->ID );
            ?>
            <li>
                <div class="fcp-list-main"><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong><small><?php echo esc_html( fcp_post_jdate( $p->ID ) ); ?> · <?php echo esc_html( fcp_digits( $apps ) ); ?> درخواست · <?php echo esc_html( fcp_digits( (int) get_post_meta( $p->ID, '_fcp_views', true ) ) ); ?> بازدید</small></div>
                <span class="fcp-status is-<?php echo esc_attr( $status ); ?>"><?php echo 'publish' === $status ? 'منتشرشده' : ( 'pending' === $status ? 'در انتظار تأیید' : 'پیش‌نویس' ); ?></span>
                <div class="fcp-list-actions">
                    <a class="fcp-link" href="<?php echo esc_url( add_query_arg( 'edit', $p->ID, $dash ) ); ?>">ویرایش</a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('مطمئن هستید؟')">
                        <input type="hidden" name="action" value="fcp_opportunity_action" /><input type="hidden" name="fcp_post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
                        <?php wp_nonce_field( 'fcp_opportunity_action_' . $p->ID, 'fcp_op_nonce' ); ?>
                        <?php if ( 'publish' === $status ) : ?><button class="fcp-link-btn" name="fcp_do" value="withdraw">برداشتن</button><?php endif; ?>
                        <button class="fcp-link-btn is-danger" name="fcp_do" value="delete">حذف</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif;
}

function fcp_render_applications_panel( $user ) {
    $statuses = fcp_application_statuses();
    $filter   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $filter   = array_key_exists( $filter, $statuses ) ? $filter : '';
    $counts   = fcp_count_applications( $user->ID );
    $rows     = fcp_get_applications( array( 'professor_id' => $user->ID, 'status' => $filter, 'limit' => 100 ) );
    $base     = add_query_arg( 'tab', 'applications', fcp_page_url( 'dashboard' ) );
    ?>
    <div class="fcp-panel-head"><h2>درخواست‌های دانشجویان</h2></div>
    <div class="fcp-chips-row">
        <a class="<?php echo '' === $filter ? 'is-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>">همه (<?php echo esc_html( fcp_digits( $counts['all'] ) ); ?>)</a>
        <?php foreach ( $statuses as $k => $label ) : ?><a class="<?php echo $filter === $k ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'status', $k, $base ) ); ?>"><?php echo esc_html( $label ); ?> (<?php echo esc_html( fcp_digits( $counts[ $k ] ) ); ?>)</a><?php endforeach; ?>
    </div>
    <?php if ( ! $rows ) : ?>
        <div class="fcp-empty"><h3>درخواستی نیست</h3><p>وقتی دانشجویی برای آگهی‌هایتان درخواست بدهد، اینجا می‌بینید و ایمیل هم می‌گیرید.</p></div>
    <?php else : ?>
        <div class="fcp-apps">
        <?php foreach ( $rows as $r ) : ?>
            <details class="fcp-app">
                <summary>
                    <span class="fcp-app-who"><strong><?php echo esc_html( $r->student_name ); ?></strong><small><?php echo esc_html( get_the_title( (int) $r->opportunity_id ) ); ?></small></span>
                    <span class="fcp-app-date"><?php echo esc_html( fcp_jdate( $r->created_at ) ); ?></span>
                    <span class="fcp-status is-app-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( isset( $statuses[ $r->status ] ) ? $statuses[ $r->status ] : $r->status ); ?></span>
                </summary>
                <div class="fcp-app-body">
                    <dl>
                        <dt>ایمیل</dt><dd dir="ltr"><a href="mailto:<?php echo esc_attr( $r->student_email ); ?>"><?php echo esc_html( $r->student_email ); ?></a></dd>
                        <?php if ( $r->student_number ) : ?><dt>شماره دانشجویی</dt><dd dir="ltr"><?php echo esc_html( $r->student_number ); ?></dd><?php endif; ?>
                        <?php if ( $r->student_field ) : ?><dt>رشته و مقطع</dt><dd><?php echo esc_html( $r->student_field ); ?></dd><?php endif; ?>
                    </dl>
                    <p class="fcp-app-msg"><?php echo nl2br( esc_html( $r->message ) ); ?></p>
                    <?php if ( $r->resume_file ) : ?><a class="fcp-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fcp_download_resume', 'id' => $r->id ), admin_url( 'admin-post.php' ) ), 'fcp_resume_' . $r->id ) ); ?>">دریافت رزومه (<?php echo esc_html( $r->resume_name ); ?>)</a><?php endif; ?>
                    <form class="fcp-form fcp-app-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="fcp_update_application" /><input type="hidden" name="fcp_app_id" value="<?php echo esc_attr( $r->id ); ?>" />
                        <?php wp_nonce_field( 'fcp_update_application_' . $r->id, 'fcp_update_nonce' ); ?>
                        <div class="fcp-form-grid">
                            <label>وضعیت<select class="fcp-input" name="fcp_status"><?php foreach ( $statuses as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r->status, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                            <label class="fcp-check"><input type="checkbox" name="fcp_notify" value="1" /> اطلاع‌رسانی به دانشجو با ایمیل</label>
                            <label class="is-full">پیام یا یادداشت<textarea class="fcp-input" rows="3" maxlength="1500" name="fcp_note"><?php echo esc_textarea( (string) $r->professor_note ); ?></textarea></label>
                        </div>
                        <div class="fcp-form-foot"><span></span><button class="fcp-btn is-sm" type="submit">ذخیره</button></div>
                    </form>
                </div>
            </details>
        <?php endforeach; ?>
        </div>
    <?php endif;
}

function fcp_render_password_form() {
    ?>
    <div class="fcp-panel-head"><h2>تغییر رمز عبور</h2></div>
    <form class="fcp-form fcp-narrow" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="fcp_change_password" /><?php wp_nonce_field( 'fcp_change_password', 'fcp_pw_nonce' ); ?>
        <label>رمز فعلی<input class="fcp-input" type="password" name="fcp_old_password" autocomplete="current-password" dir="ltr" required /></label>
        <label>رمز جدید (حداقل ۱۰ نویسه)<input class="fcp-input" type="password" name="fcp_new_password" autocomplete="new-password" minlength="10" dir="ltr" required /></label>
        <label>تکرار رمز جدید<input class="fcp-input" type="password" name="fcp_confirm_password" autocomplete="new-password" minlength="10" dir="ltr" required /></label>
        <button class="fcp-btn" type="submit">ذخیره رمز</button>
    </form>
    <?php
}

function fcp_render_opportunity_form( $post = null ) {
    $id       = $post ? (int) $post->ID : 0;
    $meta     = function ( $key ) use ( $id ) { return $id ? get_post_meta( $id, '_fcp_' . $key, true ) : ''; };
    $assigned = $id ? wp_get_object_terms( $id, 'fcp_category', array( 'fields' => 'ids' ) ) : array();
    $selected = ! is_wp_error( $assigned ) && $assigned ? (int) $assigned[0] : 0;
    ?>
    <div class="fcp-panel-head"><h2><?php echo $post ? 'ویرایش آگهی' : 'آگهی جدید'; ?></h2></div>
    <p class="fcp-muted">بعد از ارسال، آگهی منتظر تأیید مدیر می‌ماند. ویرایش آگهی منتشرشده هم دوباره تأیید می‌خواهد.</p>
    <form class="fcp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="fcp_save_opportunity" /><input type="hidden" name="fcp_post_id" value="<?php echo esc_attr( $id ); ?>" /><?php wp_nonce_field( 'fcp_save_opportunity', 'fcp_nonce' ); ?>
        <div class="fcp-form-grid">
            <label class="is-full">عنوان فرصت<input class="fcp-input" required maxlength="200" id="fcp_title" name="fcp_title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" placeholder="مثلاً: دستیار پژوهشی در زمینه هوش مصنوعی" /></label>
            <label>دسته‌بندی<select class="fcp-input" name="fcp_category" required><option value="">انتخاب کنید</option><?php foreach ( fcp_all_categories() as $term ) : ?><option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></label>
            <label>محل همکاری<input class="fcp-input" maxlength="150" name="fcp_location" value="<?php echo esc_attr( $meta( 'location' ) ); ?>" placeholder="مثلاً: آزمایشگاه دانشکده" /></label>
            <label>شیوه همکاری<select class="fcp-input" name="fcp_work_mode"><option value="">انتخاب کنید</option><?php foreach ( fcp_mode_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $meta( 'work_mode' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label>مهلت درخواست<input class="fcp-input" type="date" name="fcp_deadline" value="<?php echo esc_attr( $meta( 'deadline' ) ); ?>" /></label>
            <label class="is-full">شرح فرصت<textarea class="fcp-input" required rows="7" name="fcp_description" placeholder="هدف، شرح کار و موضوع همکاری را توضیح دهید."><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea></label>
            <label class="is-full">شرایط و مهارت‌های موردنیاز<textarea class="fcp-input" rows="4" name="fcp_requirements"><?php echo esc_textarea( $meta( 'requirements' ) ); ?></textarea></label>
            <label>ایمیل تماس (نمایش عمومی)<input class="fcp-input" type="email" name="fcp_contact_email" dir="ltr" value="<?php echo esc_attr( $meta( 'contact_email' ) ); ?>" /></label>
            <label>لینک درخواست خارجی (اختیاری)<input class="fcp-input" type="url" name="fcp_apply_url" dir="ltr" value="<?php echo esc_attr( $meta( 'apply_url' ) ); ?>" placeholder="https://" /></label>
        </div>
        <div class="fcp-form-foot"><p>فرم درخواست خود سامانه همیشه فعال است؛ لینک خارجی فقط یک راه اضافه است.</p><button class="fcp-btn" type="submit">ارسال برای تأیید</button></div>
    </form>
    <?php
}

function fcp_render_profile_form( $user ) {
    ?>
    <div class="fcp-panel-head"><h2>پروفایل عمومی</h2></div>
    <form class="fcp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="fcp_save_profile" /><?php wp_nonce_field( 'fcp_save_profile', 'fcp_profile_nonce' ); ?>
        <div class="fcp-form-grid">
            <label>نام نمایشی<input class="fcp-input" name="fcp_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" /></label>
            <label>گروه آموزشی<input class="fcp-input" name="fcp_department" value="<?php echo esc_attr( get_user_meta( $user->ID, '_fcp_department', true ) ); ?>" placeholder="مثلاً: مهندسی کامپیوتر" /></label>
            <label class="is-full">زمینه‌های تخصصی (با ویرگول جدا کنید)<input class="fcp-input" name="fcp_interests" value="<?php echo esc_attr( get_user_meta( $user->ID, '_fcp_interests', true ) ); ?>" placeholder="یادگیری ماشین، پردازش زبان" /></label>
            <label class="is-full">معرفی کوتاه<textarea class="fcp-input" rows="5" name="fcp_bio"><?php echo esc_textarea( get_user_meta( $user->ID, '_fcp_bio', true ) ); ?></textarea></label>
        </div>
        <div class="fcp-form-foot"><span></span><button class="fcp-btn" type="submit">ذخیره پروفایل</button></div>
    </form>
    <?php
}
