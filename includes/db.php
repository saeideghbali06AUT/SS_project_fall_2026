<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom table: {prefix}fcp_applications  (student applications to opportunities).
 */
function fcp_table_applications() {
    global $wpdb;
    return $wpdb->prefix . 'fcp_applications';
}

function fcp_install_db() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = fcp_table_applications();
    $charset = $wpdb->get_charset_collate();
    $sql     = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  opportunity_id bigint(20) unsigned NOT NULL,
  professor_id bigint(20) unsigned NOT NULL,
  student_name varchar(150) NOT NULL,
  student_email varchar(190) NOT NULL,
  student_number varchar(40) NOT NULL DEFAULT '',
  student_field varchar(150) NOT NULL DEFAULT '',
  message text NOT NULL,
  resume_file varchar(120) NOT NULL DEFAULT '',
  resume_name varchar(190) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'new',
  professor_note text NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY opp_email (opportunity_id,student_email),
  KEY professor_status (professor_id,status),
  KEY created_at (created_at)
) {$charset};";
    dbDelta( $sql );

    $votes = fcp_table_votes();
    $sql2  = "CREATE TABLE {$votes} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  advice_id bigint(20) unsigned NOT NULL,
  voter_hash char(64) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY advice_voter (advice_id,voter_hash),
  KEY created_at (created_at)
) {$charset};";
    dbDelta( $sql2 );
    update_option( 'fcp_db_version', FCP_DB_VERSION );
}

function fcp_application_statuses() {
    return array(
        'new'       => 'جدید',
        'reviewing' => 'در حال بررسی',
        'accepted'  => 'پذیرفته‌شده',
        'rejected'  => 'ردشده',
    );
}

function fcp_insert_application( array $d ) {
    global $wpdb;
    $table = fcp_table_applications();
    $dupe  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE opportunity_id = %d AND student_email = %s", $d['opportunity_id'], $d['student_email'] ) );
    if ( $dupe ) {
        return new WP_Error( 'duplicate', 'برای این فرصت قبلاً با همین ایمیل درخواست ثبت شده است.' );
    }
    $now = current_time( 'mysql' );
    $ok  = $wpdb->insert( $table, array(
        'opportunity_id' => (int) $d['opportunity_id'],
        'professor_id'   => (int) $d['professor_id'],
        'student_name'   => $d['student_name'],
        'student_email'  => $d['student_email'],
        'student_number' => $d['student_number'],
        'student_field'  => $d['student_field'],
        'message'        => $d['message'],
        'resume_file'    => $d['resume_file'],
        'resume_name'    => $d['resume_name'],
        'status'         => 'new',
        'ip_hash'        => $d['ip_hash'],
        'created_at'     => $now,
        'updated_at'     => $now,
    ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
    if ( ! $ok ) {
        return new WP_Error( 'db', 'ثبت درخواست انجام نشد.' );
    }
    return (int) $wpdb->insert_id;
}

function fcp_get_application( $id ) {
    global $wpdb;
    $table = fcp_table_applications();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
}

/**
 * @param array $args professor_id, opportunity_id, status, limit, offset
 */
function fcp_get_applications( array $args = array() ) {
    global $wpdb;
    $table = fcp_table_applications();
    $where = array( '1=1' );
    $vals  = array();
    if ( ! empty( $args['professor_id'] ) ) {
        $where[] = 'professor_id = %d';
        $vals[]  = (int) $args['professor_id'];
    }
    if ( ! empty( $args['opportunity_id'] ) ) {
        $where[] = 'opportunity_id = %d';
        $vals[]  = (int) $args['opportunity_id'];
    }
    if ( ! empty( $args['status'] ) && array_key_exists( $args['status'], fcp_application_statuses() ) ) {
        $where[] = 'status = %s';
        $vals[]  = $args['status'];
    }
    $limit  = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
    $offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
    $sql    = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
    $vals[] = $limit;
    $vals[] = $offset;
    return $wpdb->get_results( $wpdb->prepare( $sql, $vals ) );
}

/** Returns array( status => count, 'all' => total ) for a professor (0 = everyone). */
function fcp_count_applications( $professor_id = 0 ) {
    global $wpdb;
    $table  = fcp_table_applications();
    $counts = array( 'all' => 0 );
    foreach ( fcp_application_statuses() as $key => $label ) {
        $counts[ $key ] = 0;
    }
    if ( $professor_id ) {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS c FROM {$table} WHERE professor_id = %d GROUP BY status", $professor_id ) );
    } else {
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status" );
    }
    foreach ( (array) $rows as $row ) {
        if ( isset( $counts[ $row->status ] ) ) {
            $counts[ $row->status ] = (int) $row->c;
        }
        $counts['all'] += (int) $row->c;
    }
    return $counts;
}

function fcp_total_applications() {
    $c = fcp_count_applications( 0 );
    return $c['all'];
}

function fcp_count_opportunity_applications( $opportunity_id ) {
    global $wpdb;
    $table = fcp_table_applications();
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE opportunity_id = %d", $opportunity_id ) );
}

function fcp_update_application( $id, $status, $note ) {
    global $wpdb;
    if ( ! array_key_exists( $status, fcp_application_statuses() ) ) {
        return false;
    }
    return false !== $wpdb->update(
        fcp_table_applications(),
        array( 'status' => $status, 'professor_note' => $note, 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => (int) $id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
    );
}
