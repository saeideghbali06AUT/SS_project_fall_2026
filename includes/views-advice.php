<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fcp_advice_icon( $name ) {
    $a = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
    $icons = array(
        'heart'    => '<svg ' . $a . ' width="18" height="18"><path d="M12 20.5s-7.5-4.6-9.2-9.4C1.700 7.800 3.700 4.500 7 4.500c2 0 3.500 1.100 5 3 1.500-1.900 3-3 5-3 3.300 0 5.300 3.300 4.200 6.600-1.700 4.800-9.200 9.400-9.200 9.400z"/></svg>',
        'bookmark' => '<svg ' . $a . ' width="18" height="18"><path d="M6 4h12v17l-6-4.200L6 21z"/></svg>',
        'link'     => '<svg ' . $a . ' width="18" height="18"><path d="M10 14a4 4 0 005.700 0l3-3a4 4 0 00-5.700-5.700l-1 1"/><path d="M14 10a4 4 0 00-5.700 0l-3 3a4 4 0 005.700 5.700l1-1"/></svg>',
        'clock'    => '<svg ' . $a . ' width="15" height="15"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'seed'     => '<svg ' . $a . ' width="34" height="34"><path d="M12 21v-9"/><path d="M12 12C12 7.500 8.500 5 4 5c0 4.500 3 7 8 7z"/><path d="M12 14c0-3.500 2.500-6 7-6 0 3.500-2.500 6-7 6z"/></svg>',
        'compass'  => '<svg ' . $a . ' width="34" height="34"><circle cx="12" cy="12" r="9"/><path d="M15.500 8.500l-2 5-5 2 2-5z"/></svg>',
        'spark'    => '<svg ' . $a . ' width="16" height="16"><path d="M12 3l1.800 5.200L19 10l-5.200 1.800L12 17l-1.800-5.200L5 10l5.200-1.800z"/></svg>',
    );
    return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

function fcp_advice_url( $audience = '', $topic = '' ) {
    $url = fcp_page_url( 'advice' );
    if ( $audience && isset( fcp_advice_audiences()[ $audience ] ) ) {
        $url = add_query_arg( 'for', $audience, $url );
        if ( $topic && fcp_advice_valid_pair( $audience, $topic ) ) {
            $url = add_query_arg( 'topic', $topic, $url );
        }
    }
    return $url;
}

function fcp_render_save_button( $post_id ) {
    echo '<button type="button" class="fcp-save" data-fcp-save="' . esc_attr( $post_id ) . '" aria-pressed="false" aria-label="ذخیره برای بعد" title="ذخیره برای بعد">' . fcp_advice_icon( 'bookmark' ) . '</button>';
}

function fcp_render_helpful_button( $post_id, $voted, $count, $big = false ) {
    ?>
    <button type="button" class="fcp-helpful<?php echo $big ? ' is-big' : ''; ?><?php echo $voted ? ' is-on' : ''; ?>" data-fcp-helpful="<?php echo esc_attr( $post_id ); ?>" aria-pressed="<?php echo $voted ? 'true' : 'false'; ?>">
        <span class="fcp-helpful-ico"><?php echo fcp_advice_icon( 'heart' ); ?></span>
        <span class="fcp-helpful-txt">مفید بود</span>
        <b data-fcp-helpful-n><?php echo esc_html( fcp_digits( (int) $count ) ); ?></b>
        <span class="fcp-burst" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
    </button>
    <?php
}

function fcp_render_advice_card( $post_id ) {
    $m       = fcp_advice_meta( $post_id );
    $author  = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
    $name    = $author ? $author->display_name : 'دانشکده';
    $title   = get_the_title( $post_id );
    $helpful = fcp_advice_helpful_count( $post_id );
    $mins    = fcp_reading_minutes( get_post_field( 'post_content', $post_id ) );
    ?>
    <article class="fcp-advice-card is-<?php echo esc_attr( $m['audience'] ); ?>" data-fcp-glow data-id="<?php echo esc_attr( $post_id ); ?>" data-topic="<?php echo esc_attr( $m['topic'] ); ?>" data-helpful="<?php echo esc_attr( $helpful ); ?>" data-time="<?php echo esc_attr( (int) get_post_time( 'U', true, $post_id ) ); ?>" data-title="<?php echo esc_attr( mb_strtolower( $title . ' ' . $name . ' ' . $m['topic_label'] ) ); ?>">
        <div class="fcp-advice-top">
            <span class="fcp-chip"><?php echo esc_html( $m['topic_label'] ? $m['topic_label'] : 'توصیه' ); ?></span>
            <?php fcp_render_save_button( $post_id ); ?>
        </div>
        <h3><a class="fcp-stretch" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( $title ); ?></a></h3>
        <p class="fcp-advice-sum"><?php echo esc_html( fcp_advice_summary( $post_id ) ); ?></p>
        <div class="fcp-advice-foot">
            <span class="fcp-advice-by"><span class="fcp-avatar is-xs"><?php echo esc_html( fcp_name_initial( $name ) ); ?></span><span><?php echo esc_html( $name ); ?></span></span>
            <span class="fcp-advice-stats"><span><?php echo fcp_advice_icon( 'clock' ); ?> <?php echo esc_html( fcp_digits( $mins ) ); ?> دقیقه</span><span class="fcp-advice-heart"><?php echo fcp_advice_icon( 'heart' ); ?> <?php echo esc_html( fcp_digits( $helpful ) ); ?></span></span>
        </div>
    </article>
    <?php
}

/* ======================= LISTING PAGE ======================= */

function fcp_render_advice_page() {
    $audiences = fcp_advice_audiences();
    $topics    = fcp_advice_topics();
    $active    = isset( $_GET['for'] ) ? sanitize_key( wp_unslash( $_GET['for'] ) ) : 'newcomer';
    $active    = isset( $audiences[ $active ] ) ? $active : 'newcomer';
    $init_topic = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '';
    $init_topic = fcp_advice_valid_pair( $active, $init_topic ) ? $init_topic : 'all';
    $icons     = array( 'newcomer' => 'seed', 'senior' => 'compass' );
    fcp_render_page_heading( 'توصیه‌های اساتید', 'تجربه و راهنمایی کسانی که این مسیر را رفته‌اند؛ برای شروع دانشگاه و برای قدم‌های بعدی.', 'از استاد بشنو' );
    ?>
    <section class="fcp-wrap fcp-section fcp-advice" data-fcp-advice data-active="<?php echo esc_attr( $active ); ?>" data-topic="<?php echo esc_attr( $init_topic ); ?>" data-base="<?php echo esc_url( fcp_page_url( 'advice' ) ); ?>">
        <div class="fcp-tabs" role="tablist" aria-label="مخاطب توصیه‌ها" data-fcp-tabs>
            <span class="fcp-tabs-thumb" aria-hidden="true"></span>
            <?php foreach ( $audiences as $key => $a ) : ?>
                <a class="fcp-tab<?php echo $active === $key ? ' is-active' : ''; ?>" role="tab" id="fcp-tab-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo $active === $key ? 'true' : 'false'; ?>" aria-controls="fcp-panel-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( fcp_advice_url( $key ) ); ?>" data-fcp-tab="<?php echo esc_attr( $key ); ?>">
                    <?php echo fcp_advice_icon( $icons[ $key ] ); ?>
                    <span><?php echo esc_html( $a['label'] ); ?></span>
                    <small><?php echo esc_html( fcp_digits( fcp_count_advice( $key ) ) ); ?></small>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="fcp-adv-tools fcp-jsonly">
            <label class="fcp-adv-search"><?php echo fcp_icon( 'search' ); ?><input type="search" data-fcp-adv-search placeholder="جست‌وجو در توصیه‌ها" aria-label="جست‌وجو در توصیه‌ها" maxlength="60" /></label>
            <div class="fcp-sort" role="group" aria-label="مرتب‌سازی">
                <button type="button" class="is-active" data-fcp-sort="new">تازه‌ترین</button>
                <button type="button" data-fcp-sort="helpful">مفیدترین</button>
            </div>
            <button type="button" class="fcp-saved-toggle" data-fcp-saved-toggle aria-pressed="false"><?php echo fcp_advice_icon( 'bookmark' ); ?> ذخیره‌شده‌ها <b data-fcp-saved-n>۰</b></button>
        </div>

        <?php foreach ( $audiences as $key => $a ) :
            $query = fcp_query_advice( $key, 60 );
            ?>
            <div class="fcp-adv-panel" role="tabpanel" id="fcp-panel-<?php echo esc_attr( $key ); ?>" aria-labelledby="fcp-tab-<?php echo esc_attr( $key ); ?>" data-fcp-panel="<?php echo esc_attr( $key ); ?>" <?php echo $active === $key ? '' : 'hidden'; ?>>
                <p class="fcp-adv-blurb"><?php echo esc_html( $a['blurb'] ); ?></p>
                <div class="fcp-chips-row fcp-jsonly" data-fcp-topics>
                    <button type="button" class="<?php echo ( $active === $key && 'all' === $init_topic ) ? 'is-active' : ''; ?>" data-fcp-topic="all">همه</button>
                    <?php foreach ( $topics[ $key ] as $slug => $label ) : ?>
                        <button type="button" class="<?php echo ( $active === $key && $init_topic === $slug ) ? 'is-active' : ''; ?>" data-fcp-topic="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if ( $query->have_posts() ) : ?>
                    <div class="fcp-advice-grid" data-fcp-grid>
                        <?php
                        while ( $query->have_posts() ) {
                            $query->the_post();
                            fcp_render_advice_card( get_the_ID() );
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                <?php else : ?>
                    <div class="fcp-empty"><h3>هنوز توصیه‌ای برای این گروه منتشر نشده</h3><p>به‌محض تأیید اولین توصیه استاد، همین‌جا دیده می‌شود.</p></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="fcp-empty fcp-adv-empty" data-fcp-adv-empty hidden><h3>موردی پیدا نشد</h3><p>جست‌وجو یا فیلتر را تغییر دهید.</p></div>
    </section>
    <?php
}

/* ======================= SINGLE ADVICE ======================= */

function fcp_render_advice_single() {
    $post_id  = get_queried_object_id();
    $m        = fcp_advice_meta( $post_id );
    $aud      = fcp_advice_audiences()[ $m['audience'] ];
    $author   = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
    $content  = (string) get_post_field( 'post_content', $post_id );
    $heads    = fcp_advice_headings( $content );
    $mins     = fcp_reading_minutes( $content );
    $is_live  = 'publish' === get_post_status( $post_id );
    $count    = fcp_advice_helpful_count( $post_id );
    $voted    = $is_live ? fcp_advice_has_voted( $post_id ) : false;
    $dept     = $author ? get_user_meta( $author->ID, '_fcp_department', true ) : '';
    ?>
    <div class="fcp-progress" data-fcp-progress aria-hidden="true"><i></i></div>
    <section class="fcp-pagehead is-left fcp-advice-head"><div class="fcp-wrap">
        <a class="fcp-back" href="<?php echo esc_url( fcp_advice_url( $m['audience'] ) ); ?>">همه توصیه‌ها</a>
        <p class="fcp-kicker"><?php echo esc_html( $aud['label'] ); ?><?php echo $m['topic_label'] ? ' · ' . esc_html( $m['topic_label'] ) : ''; ?></p>
        <h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
        <p class="fcp-lead"><?php echo $author ? esc_html( $author->display_name ) . ' · ' : ''; ?><?php echo esc_html( fcp_post_jdate( $post_id ) ); ?> · <?php echo esc_html( fcp_digits( $mins ) ); ?> دقیقه مطالعه</p>
    </div></section>

    <section class="fcp-wrap fcp-section fcp-single-grid fcp-advice-single">
        <article class="fcp-single-main" data-fcp-article>
            <?php $sum = trim( (string) get_post_field( 'post_excerpt', $post_id ) ); ?>
            <?php if ( '' !== $sum ) : ?><p class="fcp-advice-intro"><?php echo esc_html( $sum ); ?></p><?php endif; ?>
            <div class="fcp-prose fcp-advice-prose"><?php echo fcp_format_advice( $content ); // Escaped inside the formatter. ?></div>
            <?php if ( $is_live ) : ?>
            <div class="fcp-helpful-box" data-fcp-glow>
                <div><strong>این توصیه به کارت آمد؟</strong><span>نظر شما به دانشجوهای دیگر کمک می‌کند مفیدترین‌ها را زودتر پیدا کنند.</span></div>
                <?php fcp_render_helpful_button( $post_id, $voted, $count, true ); ?>
            </div>
            <?php endif; ?>
        </article>
        <aside class="fcp-single-aside fcp-advice-aside">
            <?php if ( $author ) : ?>
            <a class="fcp-adv-prof" href="<?php echo esc_url( add_query_arg( 'professor', $author->ID, fcp_page_url( 'professors' ) ) ); ?>">
                <span class="fcp-avatar"><?php echo esc_html( fcp_name_initial( $author->display_name ) ); ?></span>
                <span><strong><?php echo esc_html( $author->display_name ); ?></strong><small><?php echo esc_html( $dept ? $dept : 'عضو هیئت علمی' ); ?></small></span>
            </a>
            <?php endif; ?>
            <?php if ( count( $heads ) > 1 ) : ?>
            <nav class="fcp-toc" aria-label="فهرست مطلب" data-fcp-toc>
                <h3>در این توصیه</h3>
                <ol>
                    <?php foreach ( $heads as $i => $h ) : ?><li><a href="#fcp-h<?php echo (int) ( $i + 1 ); ?>"><?php echo esc_html( $h ); ?></a></li><?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
            <div class="fcp-adv-actions">
                <?php if ( $is_live ) : ?><button type="button" class="fcp-act" data-fcp-save="<?php echo esc_attr( $post_id ); ?>" aria-pressed="false"><?php echo fcp_advice_icon( 'bookmark' ); ?> <span>ذخیره برای بعد</span></button><?php endif; ?>
                <button type="button" class="fcp-act fcp-jsonly" data-fcp-copy="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo fcp_advice_icon( 'link' ); ?> <span>کپی پیوند</span></button>
            </div>
        </aside>
    </section>
    <?php
    $rel = new WP_Query( array(
        'post_type'      => 'fcp_advice',
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'post__not_in'   => array( $post_id ),
        'no_found_rows'  => true,
        'meta_query'     => array( array( 'key' => '_fcp_audience', 'value' => $m['audience'] ) ),
    ) );
    if ( $rel->have_posts() ) {
        echo '<section class="fcp-wrap fcp-section"><h2 class="fcp-h2">توصیه‌های بیشتر برای ' . esc_html( $aud['short'] ) . '</h2><div class="fcp-advice-grid">';
        while ( $rel->have_posts() ) {
            $rel->the_post();
            fcp_render_advice_card( get_the_ID() );
        }
        wp_reset_postdata();
        echo '</div></section>';
    }
}

/* ======================= HOME SECTION ======================= */

function fcp_render_home_advice() {
    $audiences = fcp_advice_audiences();
    $counts    = fcp_advice_topic_counts();
    $totals    = array( 'newcomer' => fcp_count_advice( 'newcomer' ), 'senior' => fcp_count_advice( 'senior' ) );
    $stages    = array(
        array( 'newcomer', 'start', 'ورود به دانشگاه', 'ترم‌های اول چطور شروع شود؟ از انتخاب واحد و آشنایی با استادها تا عادت‌هایی که ارزش دارند.' ),
        array( 'newcomer', 'study', 'درس و مهارت', 'چطور درس بخوانیم، از کلاس بیشتر یاد بگیریم و مهارت‌هایی بسازیم که به درد بعدها می‌خورند.' ),
        array( 'senior', 'field', 'زمینه فعالیت', 'میان این‌همه گرایش، چطور زمینه‌ای را پیدا کنیم که هم دوستش داریم و هم آینده دارد؟' ),
        array( 'senior', 'thesis', 'پایان‌نامه', 'انتخاب موضوع و استاد راهنما، برنامه‌ریزی نوشتن و اشتباه‌های رایج در این مسیر.' ),
        array( 'senior', 'masters', 'ارشد', 'برای ادامه تحصیل چه بخوانیم، کجا و با چه آمادگی‌ای؟ نظر استادانی که این راه را دیده‌اند.' ),
        array( 'senior', 'career', 'مسیر حرفه‌ای', 'از دانشگاه تا بازار کار و پژوهش؛ چه چیزهایی را باید از همین حالا ساخت.' ),
    );
    $latest = new WP_Query( array( 'post_type' => 'fcp_advice', 'post_status' => 'publish', 'posts_per_page' => 8, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
    ?>
    <section class="fcp-wrap fcp-adv-home" id="advice">
        <div class="fcp-adv-home-head">
            <p class="fcp-kicker">توصیه‌های اساتید</p>
            <h2 class="fcp-h2">از کسانی بشنو که این مسیر را رفته‌اند.</h2>
        </div>
        <div class="fcp-aud-grid">
            <?php foreach ( array( 'newcomer' => 'seed', 'senior' => 'compass' ) as $key => $icon ) : $a = $audiences[ $key ]; ?>
                <a class="fcp-aud-card is-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( fcp_advice_url( $key ) ); ?>" data-fcp-tilt data-fcp-glow>
                    <span class="fcp-aud-ico"><?php echo fcp_advice_icon( $icon ); ?></span>
                    <h3><?php echo esc_html( $a['label'] ); ?></h3>
                    <p><?php echo esc_html( $a['blurb'] ); ?></p>
                    <span class="fcp-aud-foot"><em><?php echo $totals[ $key ] ? esc_html( fcp_digits( $totals[ $key ] ) ) . ' توصیه' : 'به‌زودی'; ?></em><span class="fcp-link">خواندن توصیه‌ها</span></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="fcp-path" data-fcp-path>
            <h3 class="fcp-path-title">مسیر یک دانشجو؛ روی هر مرحله بزن</h3>
            <div class="fcp-path-rail">
                <div class="fcp-path-line" aria-hidden="true"><i data-fcp-path-fill></i></div>
                <ol class="fcp-path-nodes" role="tablist" aria-label="مراحل مسیر دانشجو">
                    <?php foreach ( $stages as $i => $st ) : ?>
                        <li><button type="button" role="tab" id="fcp-node-<?php echo (int) $i; ?>" aria-controls="fcp-pane-<?php echo (int) $i; ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" class="fcp-node<?php echo 0 === $i ? ' is-active' : ''; ?>" data-fcp-node="<?php echo (int) $i; ?>"><span class="fcp-node-dot"><?php echo esc_html( fcp_digits( $i + 1 ) ); ?></span><span class="fcp-node-label"><?php echo esc_html( $st[2] ); ?></span></button></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <div class="fcp-path-detail">
                <?php foreach ( $stages as $i => $st ) :
                    $n = isset( $counts[ $st[0] ][ $st[1] ] ) ? $counts[ $st[0] ][ $st[1] ] : 0;
                    ?>
                    <div class="fcp-path-pane<?php echo 0 === $i ? ' is-active' : ''; ?>" role="tabpanel" id="fcp-pane-<?php echo (int) $i; ?>" aria-labelledby="fcp-node-<?php echo (int) $i; ?>" data-fcp-pane="<?php echo (int) $i; ?>">
                        <span class="fcp-chip"><?php echo esc_html( $audiences[ $st[0] ]['short'] ); ?></span>
                        <h3><?php echo esc_html( $st[2] ); ?></h3>
                        <p><?php echo esc_html( $st[3] ); ?></p>
                        <a class="fcp-link" href="<?php echo esc_url( fcp_advice_url( $st[0], $st[1] ) ); ?>"><?php echo $n ? esc_html( fcp_digits( $n ) ) . ' توصیه در این مرحله' : 'دیدن توصیه‌ها'; ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ( $latest->have_posts() ) : ?>
    <section class="fcp-band fcp-band-advice">
        <div class="fcp-wrap"><div class="fcp-band-head"><h2>تازه‌ترین توصیه‌ها</h2><a class="fcp-link" href="<?php echo esc_url( fcp_advice_url() ); ?>">همه توصیه‌ها</a></div></div>
        <div class="fcp-shelf-wrap" data-fcp-shelf>
            <div class="fcp-shelf" tabindex="0" aria-label="تازه‌ترین توصیه‌ها">
                <?php
                while ( $latest->have_posts() ) {
                    $latest->the_post();
                    fcp_render_advice_card( get_the_ID() );
                }
                wp_reset_postdata();
                ?>
            </div>
            <div class="fcp-shelf-nav"><button type="button" data-dir="1" aria-label="قبلی"><?php echo fcp_icon( 'prev' ); ?></button><button type="button" data-dir="-1" aria-label="بعدی"><?php echo fcp_icon( 'next' ); ?></button></div>
        </div>
    </section>
    <?php endif;
}

/* ======================= PROFESSOR DASHBOARD ======================= */

function fcp_render_advice_list( $posts, $dash ) {
    $audiences = fcp_advice_audiences();
    ?>
    <div class="fcp-panel-head"><h2>توصیه‌های من</h2><a class="fcp-btn is-sm" href="<?php echo esc_url( add_query_arg( 'tab', 'advice_new', $dash ) ); ?>">توصیه جدید</a></div>
    <?php if ( ! $posts ) : ?>
        <div class="fcp-empty"><h3>هنوز توصیه‌ای ننوشته‌اید</h3><p>یک تجربه، یک اشتباه که ای‌کاش می‌دانستید یا یک راهنمایی درباره پایان‌نامه؛ دانشجوها منتظرند.</p><p><a class="fcp-link" href="<?php echo esc_url( add_query_arg( 'tab', 'advice_new', $dash ) ); ?>">نوشتن اولین توصیه</a></p></div>
    <?php else : ?>
        <ul class="fcp-list">
        <?php foreach ( $posts as $p ) :
            $status = $p->post_status;
            $m      = fcp_advice_meta( $p->ID );
            ?>
            <li>
                <div class="fcp-list-main"><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong><small><?php echo esc_html( $audiences[ $m['audience'] ]['short'] ); ?><?php echo $m['topic_label'] ? ' · ' . esc_html( $m['topic_label'] ) : ''; ?> · <?php echo esc_html( fcp_post_jdate( $p->ID ) ); ?> · <?php echo esc_html( fcp_digits( fcp_advice_helpful_count( $p->ID ) ) ); ?> مفید · <?php echo esc_html( fcp_digits( (int) get_post_meta( $p->ID, '_fcp_views', true ) ) ); ?> بازدید</small></div>
                <span class="fcp-status is-<?php echo esc_attr( $status ); ?>"><?php echo 'publish' === $status ? 'منتشرشده' : ( 'pending' === $status ? 'در انتظار تأیید' : 'پیش‌نویس' ); ?></span>
                <div class="fcp-list-actions">
                    <a class="fcp-link" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'advice_new', 'edit_advice' => $p->ID ), $dash ) ); ?>">ویرایش</a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('مطمئن هستید؟')">
                        <input type="hidden" name="action" value="fcp_advice_action" /><input type="hidden" name="fcp_post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
                        <?php wp_nonce_field( 'fcp_advice_action_' . $p->ID, 'fcp_adv_nonce' ); ?>
                        <?php if ( 'publish' === $status ) : ?><button class="fcp-link-btn" name="fcp_do" value="withdraw">برداشتن</button><?php endif; ?>
                        <button class="fcp-link-btn is-danger" name="fcp_do" value="delete">حذف</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif;
}

function fcp_render_advice_form( $post = null ) {
    $id        = $post ? (int) $post->ID : 0;
    $m         = $id ? fcp_advice_meta( $id ) : array( 'audience' => 'newcomer', 'topic' => '' );
    $audiences = fcp_advice_audiences();
    $topics    = fcp_advice_topics();
    $icons     = array( 'newcomer' => 'seed', 'senior' => 'compass' );
    $prompts   = array(
        'newcomer' => 'چه چیزی را ای‌کاش در ترم اول می‌دانستید؟ چه عادتی بیشترین فایده را داشت؟ چه اشتباهی زیاد دیده‌اید؟',
        'senior'   => 'چطور زمینه یا موضوع پایان‌نامه‌تان را انتخاب کردید؟ برای ارشد به چه چیزهایی توجه کنند؟ چه مهارتی بیشتر از همه کمک می‌کند؟',
    );
    ?>
    <div class="fcp-panel-head"><h2><?php echo $post ? 'ویرایش توصیه' : 'توصیه جدید'; ?></h2></div>
    <p class="fcp-muted">توصیه بعد از ارسال منتظر تأیید مدیر می‌ماند. ویرایش توصیه منتشرشده هم دوباره تأیید می‌خواهد.</p>
    <form class="fcp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-fcp-advice-form>
        <input type="hidden" name="action" value="fcp_save_advice" /><input type="hidden" name="fcp_post_id" value="<?php echo esc_attr( $id ); ?>" /><?php wp_nonce_field( 'fcp_save_advice', 'fcp_advice_nonce' ); ?>
        <fieldset class="fcp-aud-pick">
            <legend>این توصیه برای کیست؟</legend>
            <?php foreach ( $audiences as $key => $a ) : ?>
                <label class="fcp-aud-opt" data-prompt="<?php echo esc_attr( $prompts[ $key ] ); ?>">
                    <input type="radio" name="fcp_audience" value="<?php echo esc_attr( $key ); ?>" <?php checked( $m['audience'], $key ); ?> required />
                    <span class="fcp-aud-opt-in"><?php echo fcp_advice_icon( $icons[ $key ] ); ?><strong><?php echo esc_html( $a['label'] ); ?></strong><small><?php echo esc_html( $a['blurb'] ); ?></small></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="fcp-prompt" data-fcp-prompt><?php echo fcp_advice_icon( 'spark' ); ?> <span><?php echo esc_html( $prompts[ $m['audience'] ] ); ?></span></p>
        <div class="fcp-form-grid">
            <label class="is-full">عنوان توصیه<input class="fcp-input" required maxlength="160" name="fcp_title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" placeholder="مثلاً: چطور موضوع پایان‌نامه را انتخاب کنیم؟" /></label>
            <label>موضوع
                <select class="fcp-input" name="fcp_topic" required data-fcp-topic-select>
                    <option value="">انتخاب کنید</option>
                    <?php foreach ( $topics as $aud => $list ) : ?>
                        <optgroup label="<?php echo esc_attr( $audiences[ $aud ]['short'] ); ?>" data-aud="<?php echo esc_attr( $aud ); ?>">
                            <?php foreach ( $list as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" data-aud="<?php echo esc_attr( $aud ); ?>" <?php selected( $m['audience'] === $aud && $m['topic'] === $slug, true ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>خلاصه (اختیاری، تا ۲۸۰ نویسه)<textarea class="fcp-input" rows="3" maxlength="280" name="fcp_summary" data-fcp-count-for="summary"><?php echo esc_textarea( $post ? $post->post_excerpt : '' ); ?></textarea><small class="fcp-counter" data-fcp-counter="summary" data-max="280"></small></label>
            <label class="is-full">متن توصیه
                <textarea class="fcp-input" required rows="12" minlength="100" maxlength="12000" name="fcp_body" data-fcp-count-for="body" data-fcp-preview-src placeholder="بنویسید…"><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea>
                <small class="fcp-counter" data-fcp-counter="body" data-max="12000" data-min="100"></small>
            </label>
        </div>
        <p class="fcp-muted fcp-fmt-hint">قالب‌بندی ساده: یک خط خالی بین بندها بگذارید. برای تیتر، خط را با <code dir="ltr">## </code> شروع کنید و برای فهرست با <code dir="ltr">- </code>.</p>
        <details class="fcp-preview" data-fcp-preview><summary>پیش‌نمایش زنده</summary><div class="fcp-prose fcp-advice-prose" data-fcp-preview-out></div></details>
        <div class="fcp-form-foot"><p>پس از تأیید مدیر در بخش «توصیه‌ها» دیده می‌شود.</p><button class="fcp-btn" type="submit">ارسال برای تأیید</button></div>
    </form>
    <?php
}
