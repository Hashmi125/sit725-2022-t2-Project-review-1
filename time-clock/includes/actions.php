<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// this page is for actions


// run action from url
function etimeclockwp_process_actions() {
	if (isset($_REQUEST['etimeclockwp_action'])) {
		do_action('etimeclockwp_' . sanitize_text_field($_REQUEST['etimeclockwp_action']),$_REQUEST);
	}
}
add_action('admin_init','etimeclockwp_process_actions');


// redirect to settings page on install
function etimeclockwp_firstrun() {
	if (!get_option('etimeclockwp_firstrun')) {
		update_option("etimeclockwp_firstrun", "true");
		exit(wp_redirect(admin_url( 'admin.php?page=etimeclockwp_settings_page')));
	}
}
add_action('admin_init', 'etimeclockwp_firstrun');





















// employee timeclock action
function etimeclockwp_timeclock_action_callback() {

	$nonce =	sanitize_text_field($_POST['nonce']);
	$data =		sanitize_text_field($_POST['data']);
	$eid =		sanitize_text_field($_POST['eid']);
	$epw =		sanitize_text_field($_POST['epw']);
	
	// verify nonce
	if (!wp_verify_nonce($nonce,'etimeclock_nonce')) { die( __('Error - Nonce validation failed.','etimeclockwp')); }
	
	
	
	// check to see if login data is valid
	$args = array(
		'post_type'					=> 'etimeclockwp_users',
		'post_status'				=> 'publish',
		'update_post_term_cache'	=> false, // don't retrieve post terms
		'meta_query'			=> array(
		'relation'			=>'and',
			array(
				'key'		=> 'etimeclockwp_id',
				'value'		=> $eid,
				'compare'	=> '=',
			),
			array(
				'key'		=> 'etimeclockwp_pwd',
				'value'		=> $epw,
				'compare'	=> '=',
			)
		)
	);
	
	$posts_array = new WP_Query($args);
	
	foreach ($posts_array->posts as $post) {
		$user_id = $post->ID;
	}	
	
	
	// success - user id and password are correct
	if (!empty($user_id)) {
		
		$wp_date_format = current_time(etimeclockwp_get_option('date-format'));
		$wp_time_format = current_time(etimeclockwp_get_option('time-format'));
		
		
		$now 		= strtotime(current_time('mysql'));
		$now_part 	= current_time('Y-m-d');
		$rand 		= mt_rand(); // default: 0, default: mt_getrandmax() - random numbers are needed because meta key names must be unique
		
		
		// set defaults
		$flag = '0';
		$clock_in = '0';
		
		
		
		// allow users to work past midnight
		if ($data == 'breakon' || $data == 'breakoff' || $data == 'out') {
			
			// check if there is a clock in entry for today
			// check to see if date already in db
			$args_a = array(
				'post_type'						=> 'etimeclockwp_clock',
				'post_status'					=> 'publish',
				'update_post_term_cache'		=> false, // don't retrieve post terms
				'date_query'					=> array(
						'year'	=> current_time('Y'),
						'month'	=> current_time('m'),
						'day'	=> current_time('d'),
				),
				'meta_query'					=> array(
					'relation'					=> 'and',
					array(
						'key'           => 'uid',
						'value'         => $user_id,
						'compare'       => '=',
					),
					array(
						'key'           => 'in',
						'value'         => '1',
						'compare'       => '=',
					),
				),
			);
			
			$posts_array_a = new WP_Query($args_a);
			
			if (!empty($posts_array_a->posts)) {
				
				// user has clocked in for today
				// record event for today
				//echo "record event for today 1";
				
				foreach ($posts_array_a->posts as $post) {
					$post_exists = $post->ID;
				}
				
			} else {
				
				// user has not clocked in today
				// see if there is a clock out event for yesterday
				
				$args_b = array(
					'post_type'						=> 'etimeclockwp_clock',
					'post_status'					=> 'publish',
					'update_post_term_cache'		=> false, // don't retrieve post terms
					'date_query'					=> array(
							'year'	=> date("Y", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )),
							'month'	=> date("m", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )),
							'day'	=> date("d", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )), // yesterday's date
					),
					'meta_query'					=> array(
						'relation'					=> 'and',
						array(
							'key'           => 'uid',
							'value'         => $user_id,
							'compare'       => '=',
						),
						array(
							'key'           => 'out',
							'value'         => '1',
							'compare'       => '=',
						),
					),
				);
				
				$posts_array_b = new WP_Query($args_b);
				
				if (empty($posts_array_b->posts)) {
					
					// no clock out for yesterday
					// see if user worked yesterday
					
					$args_c = array(
						'post_type'						=> 'etimeclockwp_clock',
						'post_status'					=> 'publish',
						'update_post_term_cache'		=> false, // don't retrieve post terms
						'date_query'					=> array(
							'year'	=> date("Y", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )),
							'month'	=> date("m", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )),
							'day'	=> date("d", time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS - 60 * 60 * 24 )), // yesterday's date
						),
						'meta_query'					=> array(
							'relation'					=> 'and',
							array(
								'key'           => 'uid',
								'value'         => $user_id,
								'compare'       => '=',
							),
						),
					);
					
					$posts_array_c = new WP_Query($args_c);
					
					if (empty($posts_array_c->posts)) {
						
						// user did not work yesterday - forgot to clock in today
						// record event for today
						
						//echo "record event for today 2 - forgot to clock in today";
						
						$args_d = array(
							'post_type'						=> 'etimeclockwp_clock',
							'post_status'					=> 'publish',
							'update_post_term_cache'		=> false, // don't retrieve post terms
							'date_query'					=> array(
								'year'	=> current_time('Y'),
								'month'	=> current_time('m'),
								'day'	=> current_time('d'),
							),
							'meta_query'					=> array(
								'relation'					=> 'and',
								array(
									'key'           => 'uid',
									'value'         => $user_id,
									'compare'       => '=',
								),
							),
						);
						
						$posts_array_d = new WP_Query($args_d);
						
						foreach ($posts_array_d->posts as $post) {
							$post_exists = $post->ID;
						}
						
						// add admin review flag
						$flag = '1';
						
						// add clocked in meta key
						$clock_in = '1';
						
						// add admin review flag
						$flag = '1';
						
						
					} else {
						
						// user did work yesterday
						// record event for yesterday
						
						//echo "record event for yesterday 1";
						
						foreach ($posts_array_c->posts as $post) {
							$post_exists = $post->ID;
						}
						
					}
					
				} else {
					
					// clock out for yesterday
					// user forgot to clock in today
					// record event for today
					
					//echo "record event for today 3 - forgot to clock in today";
					
					
					$args_e = array(
						'post_type'						=> 'etimeclockwp_clock',
						'post_status'					=> 'publish',
						'update_post_term_cache'		=> false, // don't retrieve post terms
						'date_query'					=> array(
							'year'	=> current_time('Y'),
							'month'	=> current_time('m'),
							'day'	=> current_time('d'),
						),
						'meta_query'					=> array(
							'relation'					=> 'and',
							array(
								'key'           => 'uid',
								'value'         => $user_id,
								'compare'       => '=',
							),
						),
					);
					
					$posts_array_e = new WP_Query($args_e);
					
					foreach ($posts_array_e->posts as $post) {
						$post_exists = $post->ID;
					}
					
					// add admin review flag
					$flag = '1';
					
				}
			}
			
		} else {
			// clock in event - record event for today - a post for today might already exists if the user is working a double shift, so check to see if a post exists or not before making a new one
			$args_f = array(
				'post_type'						=> 'etimeclockwp_clock',
				'post_status'					=> 'publish',
				'update_post_term_cache'		=> false, // don't retrieve post terms
				'date_query'					=> array(
						'year'	=> current_time('Y'),
						'month'	=> current_time('m'),
						'day'	=> current_time('d'),
				),
				'meta_query'					=> array(
					'relation'					=> 'and',
					array(
						'key'           => 'uid',
						'value'         => $user_id,
						'compare'       => '=',
					),
				),
			);
			
			$posts_array_f = new WP_Query($args_f);
			
			foreach ($posts_array_f->posts as $post) {
				$post_exists = $post->ID;
			}
		}
		
		
		
		if ($data == 'in') {
			$success_msg 	= __('Clock In','etimeclockwp');
			$working_status = '1';
			
		}
		
		if ($data == 'breakon') {
			$success_msg 	= __('Break On','etimeclockwp');
			$working_status = '0';
			
		}
		
		if ($data == 'breakoff') {
			$success_msg 	= __('Break Off','etimeclockwp');
			$working_status = '1';
			
		}
		
		if ($data == 'out') {
			$success_msg 	= __('Clock Out','etimeclockwp');
			$working_status = '0';
			
		}
		
		
		
		
		
		// date is not in db, so insert - record event for today
		
		if (empty($post_exists)) {
			
			$new_post_id = wp_insert_post(
				array(
					'post_title'     => $user_id,
					'post_content'   => '',
					'post_status'    => 'publish',
					'post_author'    => 1,
					'post_type'      => 'etimeclockwp_clock',
				)
			);
			
			
			// today is now in db, so add post meta
			update_post_meta($new_post_id,'etimeclockwp-'.$data.'_'.$rand,$now.'|0');
			
			// update working status
			update_post_meta($new_post_id,'status_'.$now_part,$working_status); // working status needs to have now_part due to reports
			
			// insert user id into post meta
			update_post_meta($new_post_id,'uid',$user_id);
			
			// update count - used to keep track of the order of clock in / out, etc. events
			update_post_meta($new_post_id,'count','1');
			
			// record if the user has clocked in or out today, this is necessary as a wp_query search cannot perform a like search on a key name, which is required for allow users to work past midnight
			if ($data == 'in' || $data == 'out') {
				update_post_meta($new_post_id, $data, true);
			}
			
			// maybe set notices flag
			if ($flag == '1') {
				update_post_meta($new_post_id,'notices', true);
			}
			
			// maybe set clock in flag
			if ($clock_in == '1') {
				update_post_meta($new_post_id, $data, true);
			}
			
			// total time
			etimeclockwp_caculate_total_time($new_post_id);
			
		} else {
			
			// date is already in db - record event for today / yesterday
			
			// get count
			$count	 			= get_post_meta($post_exists,'count', true);
			
			// today is already in db, so add post meta
			update_post_meta($post_exists,'etimeclockwp-'.$data.'_'.$rand,$now.'|'.$count);
			
			// update working status
			update_post_meta($post_exists,'status_'.$now_part,$working_status); // working status needs to have now_part due to reports
			
			// total time
			etimeclockwp_caculate_total_time($post_exists);
			
			// record if the user has clocked in or out today, this is necessary as a wp_query search cannot perform a like search on a key name, which is required for allow users to work past midnight
			if ($data == 'in' || $data == 'out') {
				update_post_meta($post_exists,$data,true);
			}
			
			// update count - used to keep track of the order of clock in / out, etc. events
			$count++;
			update_post_meta($post_exists,'count',$count);
			
			// maybe set notices flag
			if ($flag == '1') {
				update_post_meta($post_exists,'notices', true);
			}
			
			// maybe set clock in flag
			if ($clock_in == '1') {
				update_post_meta($post_exists, $data, true);
			}
			
		}
		
		$message = __('Success','etimeclockwp').' - '. $success_msg.' - '.$wp_date_format.' - '.$wp_time_format;
		$color = 'green';
		
	} else {
		
		$message = __('Incorrect ID or Password.','etimeclockwp');
		$color = 'red';
		
	}
	
	// build response array
	$response = array(
		'message' 	=> $message,
		'color' 	=> $color,
	);
	
	
	// response
	echo json_encode($response);
	
	wp_die();

}
add_action( 'wp_ajax_etimeclockwp_timeclock_action', 'etimeclockwp_timeclock_action_callback' );
add_action( 'wp_ajax_nopriv_etimeclockwp_timeclock_action', 'etimeclockwp_timeclock_action_callback' );
