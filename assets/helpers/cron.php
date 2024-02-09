<?php

function add_fac_shedule_event( $schedules ) {
    // add a 'weekly' schedule to the existing set
    $schedules['every-1-day'] = array(
        'interval' => 86400,
        'display' => __('Every 24 Hours')
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'add_fac_shedule_event' );