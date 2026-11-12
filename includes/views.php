<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_name_initial( $name ) {
    $parts = preg_split( '//u', (string) $name, -1, PREG_SPLIT_NO_EMPTY );
    return is_array( $parts ) && ! empty( $parts ) ? $parts[0] : 'ا';
}

function fcp_view_key() {
    if ( is_singular( 'fcp_opportunity' ) ) {
        return 'single';
    }
    if ( is_singular( 'fcp_advice' ) ) {
        return 'single_advice';
    }
    $key = get_post_meta( get_queried_object_id(), '_fcp_page', true );
    return in_array( $key, array( 'home', 'advice', 'professors', 'categories', 'about', 'more', 'login', 'dashboard' ), true ) ? $key : 'home';
}

function fcp_icon( $name ) {
    $icons = array(
        'search' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>',
        'user'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c1.2-3.6 4-5 7.5-5s6.300 1.400 7.500 5"/></svg>',
        'prev'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
        'next'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
        'clip'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.500l-8 8a5 5 0 01-7-7l8.500-8.500a3.500 3.500 0 015 5L10 17.500a2 2 0 01-3-3L14.500 7"/></svg>',
    );
    return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

function fcp_render_header() {
    $view = fcp_view_key();
    $view = 'single_advice' === $view ? 'advice' : $view;
    $menu = array(
        'home'       => 'خانه',
        'advice'     => 'توصیه‌ها',
        'categories' => 'فرصت‌ها',
        'professors' => 'اساتید',
        'about'      => 'درباره ما',
        'more'       => 'سایر',
    );
    $cats = fcp_all_categories();
    ?>
    <header class="fcp-nav" id="fcp-nav">
        <div class="fcp-nav-inner">
            <a class="fcp-logo" href="<?php echo esc_url( fcp_page_url( 'home' ) ); ?>" aria-label="<?php echo esc_attr( fcp_setting( 'name_fa' ) ); ?>"><?php echo fcp_logo_mark( 24 ); ?><?php echo fcp_wordmark(); ?></a>
            <nav class="fcp-nav-links" id="fcp-nav-links" aria-label="منوی اصلی">
                <?php foreach ( $menu as $key => $label ) : ?>
                    <a class="<?php echo $view === $key ? 'is-current' : ''; ?>" href="<?php echo esc_url( fcp_page_url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="fcp-nav-tools">
                <button class="fcp-icon-btn" type="button" data-fcp-search-open aria-label="جست‌وجو"><?php echo fcp_icon( 'search' ); ?></button>
                <?php if ( is_user_logged_in() && fcp_is_professor() ) : ?>
                    <a class="fcp-nav-account" href="<?php echo esc_url( fcp_page_url( 'dashboard' ) ); ?>"><?php echo fcp_icon( 'user' ); ?><span>پنل من</span></a>
                <?php elseif ( ! is_user_logged_in() ) : ?>
                    <a class="fcp-nav-account" href="<?php echo esc_url( fcp_page_url( 'login' ) ); ?>"><?php echo fcp_icon( 'user' ); ?><span>ورود اساتید</span></a>
                <?php else : ?>
                    <a class="fcp-nav-account" href="<?php echo esc_url( wp_logout_url( fcp_page_url( 'home' ) ) ); ?>"><span>خروج</span></a>
                <?php endif; ?>
                <button class="fcp-burger" type="button" data-fcp-menu aria-expanded="false" aria-controls="fcp-nav-links" aria-label="منو"><span></span><span></span></button>
            </div>
        </div>
    </header>

    <div class="fcp-search" id="fcp-search" role="dialog" aria-modal="true" aria-label="جست‌وجو" hidden>
        <div class="fcp-search-inner">
            <form class="fcp-search-form" role="search" method="get" action="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>">
                <?php echo fcp_icon( 'search' ); ?>
                <input id="fcp-search-input" type="search" name="q" placeholder="جست‌وجوی فرصت، توصیه، استاد یا مهارت" autocomplete="off" />
                <button type="button" class="fcp-search-close" data-fcp-search-close>بستن</button>
            </form>
            <p class="fcp-search-hint" id="fcp-search-hint">عنوان فرصت، نام استاد یا زمینه پژوهشی را بنویسید.</p>
            <ul class="fcp-search-results" id="fcp-search-results" aria-live="polite"></ul>
            <div class="fcp-search-quick">
                <span>میان‌بُر</span>
                <?php foreach ( $cats as $term ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'cat', $term->term_id, fcp_page_url( 'categories' ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function fcp_render_footer() {
    $email = fcp_setting( 'contact_email' );
    ?>
    <footer class="fcp-footer">
        <div class="fcp-wrap">
            <p class="fcp-footer-note"><?php echo esc_html( fcp_setting( 'university' ) ); ?>. توصیه‌ها و فرصت‌های پژوهشی و شغلی این سامانه را اساتید منتشر می‌کنند و پیش از نمایش عمومی تأیید می‌شوند.</p>
            <div class="fcp-footer-cols">
                <div><h4>توصیه‌ها</h4><a href="<?php echo esc_url( fcp_advice_url( 'newcomer' ) ); ?>">برای نو‌ورودها</a><a href="<?php echo esc_url( fcp_advice_url( 'senior' ) ); ?>">برای باتجربه‌ترها</a></div>
                <div><h4>فرصت‌ها</h4><a href="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>">همه فرصت‌ها</a><?php foreach ( array_slice( fcp_all_categories(), 0, 4 ) as $term ) : ?><a href="<?php echo esc_url( add_query_arg( 'cat', $term->term_id, fcp_page_url( 'categories' ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endforeach; ?></div>
                <div><h4>اساتید</h4><a href="<?php echo esc_url( fcp_page_url( 'professors' ) ); ?>">فهرست اساتید</a><a href="<?php echo esc_url( fcp_page_url( 'login' ) ); ?>">ورود اساتید</a></div>
                <div><h4>سامانه</h4><a href="<?php echo esc_url( fcp_page_url( 'about' ) ); ?>">درباره ما</a><a href="<?php echo esc_url( fcp_page_url( 'more' ) ); ?>">سؤال‌های پرتکرار</a><?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?></div>
            </div>
            <p class="fcp-footer-legal">© <?php echo esc_html( fcp_current_jyear() ); ?> <?php echo esc_html( fcp_setting( 'name_fa' ) ); ?>. همه حقوق محفوظ است.</p>
        </div>
    </footer>
    <?php
}

function fcp_render_view() {
    switch ( fcp_view_key() ) {
        case 'home': fcp_render_home(); break;
        case 'advice': fcp_render_advice_page(); break;
        case 'single_advice': fcp_render_advice_single(); break;
        case 'professors': fcp_render_professors(); break;
        case 'categories': fcp_render_categories(); break;
        case 'about': fcp_render_about(); break;
        case 'more': fcp_render_more(); break;
        case 'login': fcp_render_login(); break;
        case 'dashboard': fcp_render_dashboard(); break;
        case 'single': fcp_render_single(); break;
    }
}

function fcp_render_page_heading( $title, $description = '', $kicker = '' ) {
    ?>
    <section class="fcp-pagehead"><div class="fcp-wrap">
        <?php if ( $kicker ) : ?><p class="fcp-kicker"><?php echo esc_html( $kicker ); ?></p><?php endif; ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php if ( $description ) : ?><p class="fcp-lead"><?php echo esc_html( $description ); ?></p><?php endif; ?>
    </div></section>
    <?php
}

function fcp_render_card( $post_id ) {
    $term     = fcp_opportunity_category( $post_id );
    $author   = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
    $location = get_post_meta( $post_id, '_fcp_location', true );
    $mode     = get_post_meta( $post_id, '_fcp_work_mode', true );
    $modes    = fcp_mode_options();
    $dl       = fcp_deadline_info( $post_id );
    ?>
    <article class="fcp-tile">
        <div class="fcp-tile-top">
            <span class="fcp-chip"><?php echo $term ? esc_html( $term->name ) : 'فرصت همکاری'; ?></span>
            <?php if ( 'expired' === $dl['state'] ) : ?><span class="fcp-chip is-muted">پایان مهلت</span><?php elseif ( 'soon' === $dl['state'] ) : ?><span class="fcp-chip is-hot"><?php echo esc_html( $dl['note'] ); ?></span><?php endif; ?>
        </div>
        <h3><a class="fcp-stretch" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
        <p class="fcp-tile-sum"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 18, '…' ) ); ?></p>
        <ul class="fcp-facts">
            <li><?php echo esc_html( $author ? $author->display_name : 'دانشکده' ); ?></li>
            <?php if ( $location ) : ?><li><?php echo esc_html( $location ); ?></li><?php endif; ?>
            <?php if ( $mode && isset( $modes[ $mode ] ) ) : ?><li><?php echo esc_html( $modes[ $mode ] ); ?></li><?php endif; ?>
        </ul>
        <div class="fcp-tile-foot"><span><?php echo 'none' === $dl['state'] ? 'بدون مهلت' : 'مهلت ' . esc_html( $dl['label'] ); ?></span><span class="fcp-link">مشاهده</span></div>
    </article>
    <?php
}

function fcp_render_cards_query( $query, $empty_msg = 'در حال حاضر آگهی منتشرشده‌ای وجود ندارد.', $class = 'fcp-grid' ) {
    if ( $query->have_posts() ) {
        echo '<div class="' . esc_attr( $class ) . '">';
        while ( $query->have_posts() ) {
            $query->the_post();
            fcp_render_card( get_the_ID() );
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<div class="fcp-empty"><h3>هنوز موردی ثبت نشده است</h3><p>' . esc_html( $empty_msg ) . '</p></div>';
    }
}

/* ======================= HOME ======================= */

function fcp_render_home() {
    $count     = wp_count_posts( 'fcp_opportunity' );
    $published = isset( $count->publish ) ? (int) $count->publish : 0;
    $profs     = get_users( array( 'role' => 'fcp_professor', 'orderby' => 'display_name', 'number' => 12 ) );
    $prof_n    = count( get_users( array( 'role' => 'fcp_professor', 'fields' => 'ID' ) ) );
    $cats      = fcp_all_categories();
    $apps_n    = fcp_total_applications();
    $advice_n  = fcp_count_advice();
    $blurbs    = fcp_category_blurbs();
    $latest    = new WP_Query( array( 'post_type' => 'fcp_opportunity', 'post_status' => 'publish', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
    $float     = array_slice( $latest->posts, 0, 3 );
    ?>
    <section class="fcp-hero" data-fcp-parallax>
        <div class="fcp-orbs" aria-hidden="true"><i class="o1"></i><i class="o2"></i><i class="o3"></i></div>
        <div class="fcp-wrap fcp-hero-copy">
            <p class="fcp-kicker"><?php echo esc_html( fcp_setting( 'university' ) ); ?></p>
            <h1 class="fcp-display"><span>استاد پیشنهاد می‌دهد.</span> <span class="fcp-dim">تو شروع می‌کنی.</span></h1>
            <p class="fcp-lead">در <?php echo esc_html( fcp_setting( 'name_fa' ) ); ?>، اساتید برای دانشجوها توصیه می‌نویسند؛ از اولین ترم تا پایان‌نامه و ارشد. فرصت‌های پژوهشی و کارآموزی هم همین‌جاست و دانشجوها مستقیم به همان استاد درخواست می‌دهند.</p>
            <div class="fcp-cta-row">
                <a class="fcp-btn" href="<?php echo esc_url( fcp_advice_url() ); ?>">خواندن توصیه‌های اساتید</a>
                <a class="fcp-link fcp-link-lg" href="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>">مشاهده فرصت‌ها</a>
                <a class="fcp-link fcp-link-lg" href="<?php echo esc_url( fcp_page_url( 'login' ) ); ?>">ورود اساتید</a>
            </div>
        </div>
        <div class="fcp-stage" data-fcp-stage>
            <svg class="fcp-stage-mark" viewBox="0 0 400 260" aria-hidden="true">
                <path class="fcp-stage-arc" pathLength="1" d="M60 196C100 14 300 14 340 196"/>
                <circle class="fcp-stage-dot is-a" cx="60" cy="206" r="15"/>
                <circle class="fcp-stage-dot is-b" cx="340" cy="206" r="15"/>
            </svg>
            <?php foreach ( $float as $i => $p ) : $t = fcp_opportunity_category( $p->ID ); $au = get_userdata( (int) $p->post_author ); ?>
                <a class="fcp-float is-<?php echo (int) $i; ?>" href="<?php echo esc_url( get_permalink( $p ) ); ?>">
                    <small><?php echo $t ? esc_html( $t->name ) : 'فرصت'; ?></small>
                    <strong><?php echo esc_html( get_the_title( $p ) ); ?></strong>
                    <span><?php echo esc_html( $au ? $au->display_name : '' ); ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ( ! $float ) : ?><p class="fcp-stage-empty">اولین فرصت منتظر استاد شماست.</p><?php endif; ?>
        </div>
    </section>

    <section class="fcp-statement" data-fcp-words>
        <div class="fcp-wrap"><p>آشنایی با یک استاد، اغلب از یک ایمیل بی‌جواب شروع می‌شود. اینجا هر فرصت، با شرایط و مهلت روشن، منتشر می‌شود و دانشجو می‌داند دقیقاً به چه کسی و برای چه کاری درخواست می‌دهد. پیش از هر درخواستی هم می‌توانی از تجربه‌ی همین اساتید بشنوی.</p></div>
    </section>

    <?php fcp_render_home_advice(); ?>

    <section class="fcp-story" data-fcp-story>
        <div class="fcp-story-pin">
            <div class="fcp-wrap fcp-story-grid">
                <ol class="fcp-steps">
                    <li class="is-active"><h2>استاد فرصت را می‌نویسد</h2><p>عنوان، شرایط، مهلت و راه تماس را در پنل اختصاصی خودش ثبت می‌کند.</p></li>
                    <li><h2>مدیر آن را تأیید می‌کند</h2><p>هر آگهی تازه و هر ویرایش، پیش از نمایش عمومی بررسی می‌شود.</p></li>
                    <li><h2>دانشجو درخواست می‌دهد</h2><p>بدون ساخت حساب؛ یک پیام کوتاه و در صورت دلخواه رزومه کافی است.</p></li>
                    <li><h2>استاد پاسخ می‌دهد</h2><p>وضعیت هر درخواست از «در حال بررسی» تا «پذیرفته‌شده» به دانشجو اطلاع داده می‌شود.</p></li>
                </ol>
                <div class="fcp-story-visual" aria-hidden="true">
                    <div class="fcp-pane is-active"><div class="fcp-mock"><b>فرصت جدید</b><i>دستیار پژوهشی، یادگیری ماشین</i><i class="w60"></i><i class="w80"></i><span class="fcp-mock-btn">ارسال برای تأیید</span></div></div>
                    <div class="fcp-pane"><div class="fcp-mock"><b>در انتظار تأیید</b><div class="fcp-mock-row"><span>دستیار پژوهشی، یادگیری ماشین</span><em class="is-pending">در انتظار</em></div><div class="fcp-mock-row"><span>همین آگهی، پس از تأیید مدیر</span><em class="is-live">منتشر شد</em></div></div></div>
                    <div class="fcp-pane"><div class="fcp-mock"><b>درخواست همکاری</b><i>نام و ایمیل دانشجو</i><i class="w80"></i><span class="fcp-mock-file"><?php echo fcp_icon( 'clip' ); ?> resume.pdf</span><span class="fcp-mock-btn">ارسال درخواست</span></div></div>
                    <div class="fcp-pane"><div class="fcp-mock"><b>درخواست‌های من</b><div class="fcp-mock-row"><span>سارا محمدی</span><em class="is-live">پذیرفته‌شده</em></div><div class="fcp-mock-row"><span>علی رضایی</span><em class="is-pending">در حال بررسی</em></div><div class="fcp-mock-row"><span>نگار کریمی</span><em>جدید</em></div></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="fcp-band">
        <div class="fcp-wrap">
            <div class="fcp-band-head"><h2>تازه‌ترین فرصت‌ها</h2><a class="fcp-link" href="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>">همه فرصت‌ها</a></div>
        </div>
        <div class="fcp-shelf-wrap" data-fcp-shelf>
            <div class="fcp-shelf" tabindex="0" aria-label="فهرست تازه‌ترین فرصت‌ها">
                <?php
                if ( $latest->have_posts() ) {
                    while ( $latest->have_posts() ) {
                        $latest->the_post();
                        fcp_render_card( get_the_ID() );
                    }
                    wp_reset_postdata();
                } else {
                    echo '<div class="fcp-empty"><h3>هنوز آگهی منتشر نشده</h3><p>به‌محض تأیید اولین آگهی، اینجا دیده می‌شود.</p></div>';
                }
                ?>
            </div>
            <div class="fcp-shelf-nav"><button type="button" data-dir="1" aria-label="قبلی"><?php echo fcp_icon( 'prev' ); ?></button><button type="button" data-dir="-1" aria-label="بعدی"><?php echo fcp_icon( 'next' ); ?></button></div>
        </div>
    </section>

    <section class="fcp-wrap fcp-bento-sec">
        <h2 class="fcp-h2">از کجا شروع کنیم؟</h2>
        <div class="fcp-bento">
            <?php foreach ( $cats as $i => $term ) : $desc = $term->description ? $term->description : ( isset( $blurbs[ $term->slug ] ) ? $blurbs[ $term->slug ] : '' ); ?>
                <a class="fcp-bento-tile is-<?php echo (int) ( $i % 4 ); ?>" href="<?php echo esc_url( add_query_arg( 'cat', $term->term_id, fcp_page_url( 'categories' ) ) ); ?>">
                    <span class="fcp-bento-n"><?php echo esc_html( fcp_digits( (int) $term->count ) ); ?></span>
                    <h3><?php echo esc_html( $term->name ); ?></h3>
                    <?php if ( $desc ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fcp-numbers" data-fcp-counters>
        <div class="fcp-wrap fcp-numbers-grid">
            <div><strong data-fcp-count="<?php echo (int) $advice_n; ?>"><?php echo esc_html( fcp_digits( $advice_n ) ); ?></strong><span>توصیه منتشرشده</span></div>
            <div><strong data-fcp-count="<?php echo (int) $published; ?>"><?php echo esc_html( fcp_digits( $published ) ); ?></strong><span>فرصت منتشرشده</span></div>
            <div><strong data-fcp-count="<?php echo (int) $prof_n; ?>"><?php echo esc_html( fcp_digits( $prof_n ) ); ?></strong><span>استاد عضو سامانه</span></div>
            <div><strong data-fcp-count="<?php echo (int) $apps_n; ?>"><?php echo esc_html( fcp_digits( $apps_n ) ); ?></strong><span>درخواست ثبت‌شده</span></div>
            <div><strong data-fcp-count="<?php echo (int) count( $cats ); ?>"><?php echo esc_html( fcp_digits( count( $cats ) ) ); ?></strong><span>دسته‌بندی</span></div>
        </div>
    </section>

    <?php if ( $profs ) : ?>
    <section class="fcp-wrap fcp-profs-sec">
        <div class="fcp-band-head"><h2 class="fcp-h2">اساتید سامانه</h2><a class="fcp-link" href="<?php echo esc_url( fcp_page_url( 'professors' ) ); ?>">همه اساتید</a></div>
        <div class="fcp-prof-row">
            <?php foreach ( $profs as $u ) : ?>
                <a class="fcp-prof" href="<?php echo esc_url( add_query_arg( 'professor', $u->ID, fcp_page_url( 'professors' ) ) ); ?>"><span class="fcp-avatar"><?php echo esc_html( fcp_name_initial( $u->display_name ) ); ?></span><strong><?php echo esc_html( $u->display_name ); ?></strong><small><?php echo esc_html( get_user_meta( $u->ID, '_fcp_department', true ) ); ?></small></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="fcp-wrap fcp-pro">
        <div class="fcp-pro-card">
            <span class="fcp-gold-badge">★ حساب ویژه استاد</span>
            <h2>تجربه یا فرصتی دارید؟ به دانشجوها برسانید.</h2>
            <p>با حساب اختصاصی وارد شوید، توصیه بنویسید، فرصت‌هایتان را منتشر کنید و درخواست‌ها را در یک صفحه مدیریت کنید.</p>
            <a class="fcp-btn is-light" href="<?php echo esc_url( fcp_page_url( 'login' ) ); ?>">ورود به پنل استاد</a>
        </div>
    </section>
    <?php
}

/* ======================= PROFESSORS ======================= */

function fcp_render_professors() {
    $requested_id = isset( $_GET['professor'] ) ? absint( $_GET['professor'] ) : 0;
    if ( ! $requested_id ) {
        fcp_render_page_heading( 'اساتید', 'پروفایل اساتید و فرصت‌هایی که منتشر کرده‌اند.' );
    }
    echo '<section class="fcp-wrap fcp-section">';
    if ( $requested_id ) {
        $user = get_userdata( $requested_id );
        if ( ! $user || ! in_array( 'fcp_professor', (array) $user->roles, true ) ) {
            fcp_render_page_heading( 'استاد پیدا نشد' );
            echo '<div class="fcp-empty"><h3>این پروفایل وجود ندارد</h3><a class="fcp-link" href="' . esc_url( fcp_page_url( 'professors' ) ) . '">بازگشت به فهرست اساتید</a></div>';
        } else {
            $department = get_user_meta( $user->ID, '_fcp_department', true );
            $interests  = get_user_meta( $user->ID, '_fcp_interests', true );
            $bio        = get_user_meta( $user->ID, '_fcp_bio', true );
            ?>
            <div class="fcp-profile">
                <span class="fcp-avatar is-lg"><?php echo esc_html( fcp_name_initial( $user->display_name ) ); ?></span>
                <h1><?php echo esc_html( $user->display_name ); ?></h1>
                <p class="fcp-lead"><?php echo esc_html( $department ? $department : 'عضو هیئت علمی' ); ?></p>
                <?php if ( $interests ) : ?><p class="fcp-profile-tags"><?php foreach ( array_filter( array_map( 'trim', preg_split( '/[,،]/u', $interests ) ) ) as $tag ) : ?><span class="fcp-chip"><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></p><?php endif; ?>
                <?php if ( $bio ) : ?><p class="fcp-profile-bio"><?php echo nl2br( esc_html( $bio ) ); ?></p><?php endif; ?>
            </div>
            <h2 class="fcp-h2">فرصت‌های منتشرشده</h2>
            <?php
            $query = new WP_Query( array( 'post_type' => 'fcp_opportunity', 'post_status' => 'publish', 'author' => $user->ID, 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
            fcp_render_cards_query( $query, 'این استاد هنوز فرصتی منتشر نکرده است.' );
            $adv = new WP_Query( array( 'post_type' => 'fcp_advice', 'post_status' => 'publish', 'author' => $user->ID, 'posts_per_page' => 30, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
            if ( $adv->have_posts() ) {
                echo '<h2 class="fcp-h2 fcp-after-h2">توصیه‌های این استاد</h2><div class="fcp-advice-grid">';
                while ( $adv->have_posts() ) {
                    $adv->the_post();
                    fcp_render_advice_card( get_the_ID() );
                }
                wp_reset_postdata();
                echo '</div>';
            }
        }
    } else {
        $users = get_users( array( 'role' => 'fcp_professor', 'orderby' => 'display_name', 'order' => 'ASC' ) );
        if ( ! $users ) {
            echo '<div class="fcp-empty"><h3>هنوز استادی اضافه نشده است</h3><p>مدیر سایت از «فرصت‌های دانشکده ← کارت‌های دسترسی» استادان را می‌سازد.</p></div>';
        } else {
            echo '<div class="fcp-grid is-prof">';
            foreach ( $users as $user ) {
                $department = get_user_meta( $user->ID, '_fcp_department', true );
                $count      = count_user_posts( $user->ID, 'fcp_opportunity', true );
                ?>
                <a class="fcp-tile fcp-prof-tile" href="<?php echo esc_url( add_query_arg( 'professor', $user->ID, fcp_page_url( 'professors' ) ) ); ?>">
                    <span class="fcp-avatar"><?php echo esc_html( fcp_name_initial( $user->display_name ) ); ?></span>
                    <h3><?php echo esc_html( $user->display_name ); ?></h3>
                    <p><?php echo esc_html( $department ? $department : 'عضو هیئت علمی' ); ?></p>
                    <div class="fcp-tile-foot"><span><?php echo esc_html( fcp_digits( $count ) ); ?> فرصت منتشرشده</span><span class="fcp-link">پروفایل</span></div>
                </a>
                <?php
            }
            echo '</div>';
        }
    }
    echo '</section>';
}

/* ======================= CATEGORIES / SEARCH ======================= */

function fcp_render_categories() {
    fcp_render_page_heading( 'فرصت‌ها', 'پژوهشی، شغلی، کارآموزی یا موضوع پایان‌نامه؛ فیلتر کنید و مستقیم به استاد درخواست بدهید.' );
    $cat_id = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $mode   = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
    $open   = ! empty( $_GET['open'] );
    $page   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
    $terms  = fcp_all_categories();
    $modes  = fcp_mode_options();
    $base   = fcp_page_url( 'categories' );
    ?>
    <section class="fcp-wrap fcp-section">
        <form class="fcp-filters" action="<?php echo esc_url( $base ); ?>" method="get">
            <input class="fcp-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="عنوان یا کلیدواژه" aria-label="جست‌وجو" />
            <select class="fcp-input" name="cat" aria-label="دسته‌بندی"><option value="0">همه دسته‌ها</option><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select>
            <select class="fcp-input" name="mode" aria-label="شیوه همکاری"><option value="">هر شیوه‌ای</option><?php foreach ( $modes as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $mode, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
            <label class="fcp-check"><input type="checkbox" name="open" value="1" <?php checked( $open ); ?> /> فقط مهلت‌دارها</label>
            <button class="fcp-btn" type="submit">اعمال</button>
        </form>
        <div class="fcp-chips-row">
            <a class="<?php echo ! $cat_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>">همه</a>
            <?php foreach ( $terms as $term ) : ?><a class="<?php echo $cat_id === (int) $term->term_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'cat', $term->term_id, $base ) ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endforeach; ?>
        </div>
        <?php
        $args = array( 'post_type' => 'fcp_opportunity', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC', 's' => $search );
        if ( $cat_id && term_exists( $cat_id, 'fcp_category' ) ) {
            $args['tax_query'] = array( array( 'taxonomy' => 'fcp_category', 'field' => 'term_id', 'terms' => array( $cat_id ) ) );
        }
        $meta = array();
        if ( $mode && isset( $modes[ $mode ] ) ) {
            $meta[] = array( 'key' => '_fcp_work_mode', 'value' => $mode );
        }
        if ( $open ) {
            $meta[] = array(
                'relation' => 'OR',
                array( 'key' => '_fcp_deadline', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_fcp_deadline', 'value' => '', 'compare' => '=' ),
                array( 'key' => '_fcp_deadline', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ),
            );
        }
        if ( $meta ) {
            $args['meta_query'] = $meta;
        }
        $query = new WP_Query( $args );
        echo '<p class="fcp-result-count">' . esc_html( fcp_digits( (int) $query->found_posts ) ) . ' فرصت</p>';
        fcp_render_cards_query( $query, 'برای این جست‌وجو موردی پیدا نشد؛ فیلترها را کمتر کنید.' );
        if ( $query->max_num_pages > 1 ) {
            $links = paginate_links( array(
                'base'      => add_query_arg( array( 'pg' => '%#%', 'cat' => $cat_id, 'q' => $search, 'mode' => $mode, 'open' => $open ? 1 : 0 ), $base ),
                'format'    => '',
                'current'   => $page,
                'total'     => $query->max_num_pages,
                'type'      => 'list',
                'prev_text' => 'قبلی',
                'next_text' => 'بعدی',
            ) );
            if ( $links ) {
                echo '<nav class="fcp-pagination" aria-label="صفحه‌بندی">' . wp_kses_post( $links ) . '</nav>';
            }
        }
        ?>
    </section>
    <?php
}

/* ======================= ABOUT / MORE ======================= */

function fcp_render_contact_block() {
    $email = fcp_setting( 'contact_email' );
    $phone = fcp_setting( 'contact_phone' );
    $addr  = fcp_setting( 'address' );
    if ( ! $email && ! $phone && ! $addr ) {
        return;
    }
    echo '<div class="fcp-contact"><h2 class="fcp-h2">ارتباط با ما</h2><ul>';
    if ( $email ) { echo '<li><span>ایمیل</span><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></li>'; }
    if ( $phone ) { echo '<li><span>تلفن</span><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></li>'; }
    if ( $addr ) { echo '<li><span>نشانی</span><em>' . esc_html( $addr ) . '</em></li>'; }
    echo '</ul></div>';
}

function fcp_render_about() {
    fcp_render_page_heading( 'درباره ' . fcp_setting( 'name_fa' ), fcp_setting( 'tagline' ) . '. ' . fcp_setting( 'university' ) . '.' );
    ?>
    <section class="fcp-statement is-static" data-fcp-words><div class="fcp-wrap"><p>«راوی» کسی است که دانش را به دیگران می‌رساند. ما همین نقش را به یک سامانه ساده تبدیل کرده‌ایم: استاد می‌نویسد، دانشجو می‌خواند و میان این دو، یک گفت‌وگوی روشن شکل می‌گیرد.</p></div></section>
    <section class="fcp-wrap fcp-section">
        <div class="fcp-rows">
            <div><h3>توصیه از زبان اساتید</h3><p>اساتید برای نو‌ورودها و دانشجوهای باتجربه‌تر توصیه می‌نویسند؛ از شروع دانشگاه تا پایان‌نامه و ارشد.</p></div>
            <div><h3>انتشار توسط استاد</h3><p>هر استاد با حساب اختصاصی خودش آگهی ثبت می‌کند و مسئول محتوای آن است.</p></div>
            <div><h3>تأیید پیش از نمایش</h3><p>آگهی تازه و هر ویرایش، قبل از دیده‌شدن در سایت توسط مدیر بررسی می‌شود.</p></div>
            <div><h3>درخواست بدون حساب</h3><p>دانشجو برای درخواست نیازی به ثبت‌نام ندارد و پاسخ را از راه ایمیل می‌گیرد.</p></div>
            <div><h3>حریم خصوصی</h3><p>رزومه‌ها در فضای خصوصی ذخیره می‌شوند و فقط استاد مربوطه به آن‌ها دسترسی دارد.</p></div>
        </div>
        <?php fcp_render_contact_block(); ?>
    </section>
    <?php
}

function fcp_render_more() {
    fcp_render_page_heading( 'سایر', 'پیوندهای مفید و پاسخ سؤال‌های پرتکرار.' );
    $faq = array(
        array( 'توصیه‌ها را چه کسی می‌نویسد؟', 'اساتید با حساب اختصاصی‌شان. هر توصیه پیش از انتشار توسط مدیر بررسی می‌شود. دکمه «مفید بود» بدون ثبت‌نام کار می‌کند و به توصیه‌های خوب کمک می‌کند دیده شوند.' ),
        array( 'برای درخواست باید ثبت‌نام کنم؟', 'نه. در صفحه هر فرصت، فرم درخواست را پر می‌کنید و پاسخ استاد به ایمیلتان می‌رسد.' ),
        array( 'رزومه‌ام را چه کسی می‌بیند؟', 'فقط استادی که آگهی را منتشر کرده. فایل‌ها در فضای خصوصی سایت نگهداری می‌شوند و پیوند عمومی ندارند.' ),
        array( 'چرا آگهی من هنوز دیده نمی‌شود؟', 'آگهی‌های تازه و ویرایش‌شده ابتدا توسط مدیر تأیید می‌شوند. وضعیت را در پنل استاد می‌بینید.' ),
        array( 'حساب استاد را چطور بگیرم؟', 'حساب‌ها را فقط مدیر سامانه می‌سازد و به‌صورت کارت دسترسی تحویل می‌دهد. رمز اولیه موقت است و باید در اولین ورود عوض شود.' ),
    );
    ?>
    <section class="fcp-wrap fcp-section">
        <div class="fcp-grid is-links">
            <a class="fcp-tile" href="<?php echo esc_url( fcp_advice_url() ); ?>"><h3>توصیه‌های اساتید</h3><p>راهنمایی برای نو‌ورودها و دانشجوهای باتجربه‌تر.</p></a>
            <a class="fcp-tile" href="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>"><h3>جست‌وجوی فرصت‌ها</h3><p>مرور و فیلتر همه آگهی‌های منتشرشده.</p></a>
            <a class="fcp-tile" href="<?php echo esc_url( fcp_page_url( 'professors' ) ); ?>"><h3>فهرست اساتید</h3><p>پروفایل و فرصت‌های هر استاد.</p></a>
            <a class="fcp-tile" href="<?php echo esc_url( fcp_page_url( 'login' ) ); ?>"><h3>ورود اساتید</h3><p>ثبت آگهی و مدیریت درخواست‌ها.</p></a>
        </div>
        <h2 class="fcp-h2 fcp-faq-title">سؤال‌های پرتکرار</h2>
        <div class="fcp-faq">
            <?php foreach ( $faq as $item ) : ?><details><summary><?php echo esc_html( $item[0] ); ?></summary><p><?php echo esc_html( $item[1] ); ?></p></details><?php endforeach; ?>
        </div>
        <?php fcp_render_contact_block(); ?>
    </section>
    <?php
}

/* ======================= SINGLE ======================= */

function fcp_application_notice( $code ) {
    $map = array(
        'ok'        => array( 'درخواست شما ثبت شد. تأییدیه‌اش را به ایمیلتان فرستادیم و استاد پس از بررسی پاسخ می‌دهد.', 'ok' ),
        'invalid'   => array( 'نام، ایمیل معتبر و پیام (حداقل ۲۰ نویسه) را وارد کنید.', 'err' ),
        'duplicate' => array( 'با این ایمیل قبلاً برای همین فرصت درخواست داده‌اید.', 'err' ),
        'expired'   => array( 'مهلت درخواست برای این فرصت تمام شده است.', 'err' ),
        'file'      => array( 'فایل رزومه باید PDF یا Word و کمتر از ۳ مگابایت باشد.', 'err' ),
        'rate'      => array( 'تعداد درخواست‌های شما در این ساعت زیاد بود. کمی بعد دوباره تلاش کنید.', 'err' ),
        'fast'      => array( 'فرم خیلی سریع ارسال شد. دوباره امتحان کنید.', 'err' ),
        'nonce'     => array( 'اعتبار فرم تمام شده بود. صفحه را تازه کنید و دوباره بفرستید.', 'err' ),
        'error'     => array( 'ثبت انجام نشد. دوباره تلاش کنید.', 'err' ),
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : null;
}

function fcp_render_single() {
    $post_id      = get_queried_object_id();
    $term         = fcp_opportunity_category( $post_id );
    $author       = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
    $mode         = get_post_meta( $post_id, '_fcp_work_mode', true );
    $modes        = fcp_mode_options();
    $location     = get_post_meta( $post_id, '_fcp_location', true );
    $requirements = get_post_meta( $post_id, '_fcp_requirements', true );
    $email        = get_post_meta( $post_id, '_fcp_contact_email', true );
    $apply_url    = get_post_meta( $post_id, '_fcp_apply_url', true );
    $dl           = fcp_deadline_info( $post_id );
    $expired      = 'expired' === $dl['state'];
    $is_live      = 'publish' === get_post_status( $post_id );
    $code         = isset( $_GET['fcp_app'] ) ? sanitize_key( wp_unslash( $_GET['fcp_app'] ) ) : '';
    $notice       = $code ? fcp_application_notice( $code ) : null;
    ?>
    <div class="fcp-localnav" data-fcp-localnav>
        <div class="fcp-wrap fcp-localnav-in"><strong><?php echo esc_html( get_the_title( $post_id ) ); ?></strong><?php if ( $is_live && ! $expired ) : ?><a class="fcp-btn is-sm" href="#apply">درخواست همکاری</a><?php endif; ?></div>
    </div>
    <section class="fcp-pagehead is-left" data-fcp-localnav-anchor><div class="fcp-wrap">
        <a class="fcp-back" href="<?php echo esc_url( fcp_page_url( 'categories' ) ); ?>">همه فرصت‌ها</a>
        <p class="fcp-kicker"><?php echo esc_html( $term ? $term->name : 'فرصت همکاری' ); ?></p>
        <h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
        <p class="fcp-lead">منتشرشده در <?php echo esc_html( fcp_post_jdate( $post_id ) ); ?><?php echo $author ? ' · ' . esc_html( $author->display_name ) : ''; ?></p>
    </div></section>
    <section class="fcp-wrap fcp-section fcp-single-grid">
        <article class="fcp-single-main">
            <h2>شرح فرصت</h2>
            <div class="fcp-prose"><?php echo wpautop( esc_html( get_post_field( 'post_content', $post_id ) ) ); ?></div>
            <?php if ( $requirements ) : ?><h2>شرایط و مهارت‌های موردنیاز</h2><div class="fcp-prose"><?php echo wpautop( esc_html( $requirements ) ); ?></div><?php endif; ?>
        </article>
        <aside class="fcp-single-aside">
            <h3>اطلاعات فرصت</h3>
            <dl>
                <dt>استاد مسئول</dt><dd><?php if ( $author ) : ?><a href="<?php echo esc_url( add_query_arg( 'professor', $author->ID, fcp_page_url( 'professors' ) ) ); ?>"><?php echo esc_html( $author->display_name ); ?></a><?php else : ?>دانشکده<?php endif; ?></dd>
                <dt>دسته‌بندی</dt><dd><?php echo esc_html( $term ? $term->name : 'تعیین نشده' ); ?></dd>
                <dt>محل همکاری</dt><dd><?php echo esc_html( $location ? $location : 'تعیین نشده' ); ?></dd>
                <dt>شیوه همکاری</dt><dd><?php echo esc_html( $mode && isset( $modes[ $mode ] ) ? $modes[ $mode ] : 'تعیین نشده' ); ?></dd>
                <dt>مهلت</dt><dd><?php echo esc_html( $dl['label'] ); ?><?php if ( $dl['note'] ) : ?><br /><small class="fcp-state-<?php echo esc_attr( $dl['state'] ); ?>"><?php echo esc_html( $dl['note'] ); ?></small><?php endif; ?></dd>
            </dl>
            <?php if ( $apply_url ) : ?><a class="fcp-btn is-block" href="<?php echo esc_url( $apply_url ); ?>" target="_blank" rel="noopener noreferrer">صفحه درخواست استاد</a><?php endif; ?>
            <?php if ( $email ) : ?><a class="fcp-btn is-ghost is-block" href="mailto:<?php echo esc_attr( $email ); ?>">ارسال ایمیل</a><?php endif; ?>
        </aside>
    </section>

    <?php if ( $is_live ) : ?>
    <section class="fcp-wrap fcp-apply" id="apply">
        <div class="fcp-apply-card">
            <h2>درخواست همکاری</h2>
            <p class="fcp-lead">نیازی به ساخت حساب نیست. پیام شما مستقیم برای <?php echo esc_html( $author ? $author->display_name : 'استاد' ); ?> فرستاده می‌شود.</p>
            <?php if ( $notice ) : ?><div class="fcp-notice is-<?php echo esc_attr( $notice[1] ); ?>" role="status"><?php echo esc_html( $notice[0] ); ?></div><?php endif; ?>
            <?php if ( $expired ) : ?>
                <div class="fcp-notice is-err">مهلت درخواست برای این فرصت تمام شده است.</div>
            <?php elseif ( 'ok' !== $code ) : ?>
            <form class="fcp-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="fcp_submit_application" />
                <input type="hidden" name="fcp_opportunity_id" value="<?php echo esc_attr( $post_id ); ?>" />
                <input type="hidden" name="fcp_t" value="<?php echo esc_attr( time() ); ?>" />
                <?php wp_nonce_field( 'fcp_submit_application', 'fcp_app_nonce' ); ?>
                <div class="fcp-hp" aria-hidden="true"><label>وب‌سایت<input type="text" name="fcp_website" tabindex="-1" autocomplete="off" /></label></div>
                <div class="fcp-form-grid">
                    <label>نام و نام‌خانوادگی<input class="fcp-input" required maxlength="150" name="fcp_student_name" autocomplete="name" /></label>
                    <label>ایمیل<input class="fcp-input" required type="email" name="fcp_student_email" autocomplete="email" dir="ltr" /></label>
                    <label>شماره دانشجویی<input class="fcp-input" maxlength="40" name="fcp_student_number" dir="ltr" /></label>
                    <label>رشته و مقطع<input class="fcp-input" maxlength="150" name="fcp_student_field" placeholder="مثلاً: کارشناسی کامپیوتر" /></label>
                    <label class="is-full">چرا این فرصت؟<textarea class="fcp-input" required minlength="20" maxlength="3000" rows="5" name="fcp_message" placeholder="مهارت‌ها و انگیزه‌تان را کوتاه بنویسید."></textarea></label>
                    <label class="is-full">رزومه (اختیاری، PDF یا Word، تا ۳ مگابایت)<input class="fcp-input" type="file" name="fcp_resume" accept=".pdf,.doc,.docx" /></label>
                </div>
                <div class="fcp-form-foot"><p>ایمیل و پیام شما فقط برای استاد مربوطه نمایش داده می‌شود.</p><button class="fcp-btn" type="submit">ارسال درخواست</button></div>
            </form>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $related_args = array( 'post_type' => 'fcp_opportunity', 'post_status' => 'publish', 'posts_per_page' => 3, 'post__not_in' => array( $post_id ), 'no_found_rows' => true );
    if ( $term ) {
        $related_args['tax_query'] = array( array( 'taxonomy' => 'fcp_category', 'field' => 'term_id', 'terms' => array( $term->term_id ) ) );
    }
    $related = new WP_Query( $related_args );
    if ( $related->have_posts() ) {
        echo '<section class="fcp-wrap fcp-section"><h2 class="fcp-h2">فرصت‌های مشابه</h2>';
        fcp_render_cards_query( $related );
        echo '</section>';
    }
}
