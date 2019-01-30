<?php
/*
Plugin Name: Upcoming Event
Plugin URI: https://
Description: Redirects to the next upcoming event in a given category for The Events Calendar plugin (required).
Author: Andrew Fitzgerald
Author URI: https://
Version: 1.0
License: GNU GENERAL PUBLIC LICENSE, Version 3
Text Domain: upcoming-event
*/


//based on code by 
// Don't call this file directly
if ( ! defined( 'ABSPATH') ) {
  die;
}

class UpcomingEvent
{

  /**
  *   Initial setup: Register the filter and action
  *
  */
  public function __construct() {

    add_action( 'send_headers', array( $this, 'show_upcoming_event' ) );

  }


  /**
  *   Does the actual work
  *
  */
  public function show_upcoming_event() {

    global $show_upcoming_event_query_run;

    // Prevent being triggered again when executing the query
    if ( $show_upcoming_event_query_run == 0 ) {

      $show_upcoming_event_query_run++;

      // Retrieve search criteria from GET query
      // Use sanitized $_GET to be independent from current state of WP Query and possible unavailability of GET parameters.
      if ( ! empty( $_GET['upcoming_event'] ) ) {

        // Can use sanitize_key because only small letters and underscores needed
        $upcoming_event = sanitize_key( $_GET['upcoming_event'] );

        // Set default args
        $args_filter_or_custom = array(
          'fields' => 'ids',
          'post_type' => 'tribe_events',
          'posts_per_page' => 1,
          'orderby' => 'EventStartDate',
          'order' => 'ASC',
          'meta_key'     => '_EventStartDate',
          'meta_value'   => date( "Y-m-d H:i:s" ),
          'meta_compare' => '>',
          'suppress_filters' => true,
          'post_status'         => 'publish'
        );
      
    
        if($upcoming_event == 'all') {
      $args = $args_filter_or_custom;
        } else {
      $args = array_merge($args_filter_or_custom, array('tax_query' => array(array('taxonomy' => 'tribe_events_cat','field' => 'slug','terms' => $upcoming_event))));
    }

        /**
        * Get parameters that don't affect the retrieval of posts and that will be passed through to the final query
        */
        $query_args_pass_through_defaults = array(
          'utm_source', // Google Analytics
          'utm_campaign', // Google Analytics
          'utm_medium' // Google Analytics
        );

        foreach ( $query_args_pass_through_defaults as $value ) {

          if ( isset( $_GET[ $value ] ) ) {

            // Sanitized with sanitize_text_field because some values may be uppercase or spaces
            $query_args_pass_through[ $value ] = sanitize_text_field( $_GET[ $value ] );

          }
        }
      
     

        // caching
        if ( defined( 'UE_RUTP_CACHE' ) ) {

          $caching_time = intval( UE_RUTP_CACHE );

        } else {

          if ( isset( $_GET['cache'] ) ) {

            $caching_time = intval( $_GET['cache'] );

          } else {

            $caching_time = 0;

          }

        }

        // Retrieve the post and redirect to its permalink
        if ( ! empty( $caching_time ) ) {

          $key = md5( serialize( $args ) );

          // We save every $key as own transient so that they can have different lifetimes.
          $post_ids = get_transient( 'ue_rutp_post_ids-' . $key );

          if ( ! empty( $post_ids ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {

            error_log( 'Upcoming Event: We found something useful in the cache for upcoming_event=' . $upcoming_event );

          }

        }

        if ( ! empty( $post_ids ) ) {

        } else {


          wp_reset_postdata();

          /**
          * Remove all hooks that might cancel out our 'orderby'
          */
          remove_all_actions( 'pre_get_posts' );

          /**
          * Reduce the risk of interference from other plugins
          */
          if ( $args_filter_or_custom['suppress_filters'] ) {

            remove_all_filters( 'posts_clauses' );
            remove_all_filters( 'posts_orderby' );
            remove_all_filters( 'posts_where' );
            remove_all_filters( 'posts_join' );
            remove_all_filters( 'posts_groupby' );

          }

          /**
          * WP_Query is supposed to sanitize $args
          */
          $the_query = new WP_Query( $args );

          $post_ids = $the_query->get_posts();

          if ( ! empty( $key ) ) {
            // save transient

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

              error_log( sprintf( 'Upcoming Event: We filled the cache for upcoming_event=%s with a lifetime of %d seconds.', $upcoming_event, $caching_time ) );

            }

            set_transient( 'ue_rutp_post_ids-' . $key, $post_ids, $caching_time );

          }

        }

        if ( ! empty( $post_ids ) ) {

          if ( empty( $post_id ) ) {

            $post_id = reset( $post_ids );

          }

          $permalink = get_permalink( $post_id );

          if ( ! empty( $query_args_pass_through ) ) {

            $permalink = esc_url_raw( add_query_arg( $query_args_pass_through, $permalink ) );

          }

          $this->redirect( $permalink );

        } else {

          // Nothing found, go to post with id as specified by upcoming_event_default, or home

          if ( isset( $_GET['default_upcoming_event'] ) ) {

            $default_upcoming_event = sanitize_key( $_GET['default_upcoming_event'] );

          }

          if ( empty( $default_upcoming_event ) ) {

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

              error_log( 'Upcoming Event: Nothing found, going to site URL' );

            }

            // no default given => go to home page
            $this->redirect( site_url() );

          } else {

            $permalink = get_permalink( $default_upcoming_event );

            if ( $permalink === FALSE ) {

              if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

                error_log( 'Upcoming Event: Nothing found and default_upcoming_event not found, going to site URL.' );

              }

              // default post or page does not exist => go to home page
              $this->redirect( site_url() );

            } else {

              if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

                error_log( 'Upcoming Event: Nothing found, going to default_upcoming_event.' );

              }

              $this->redirect( $permalink );

            }

          }

        }

      }

    }

  }

  /**
  * Redirect to a URL
  *
  * Using own redirection so that we can add a header.
  * Firefox 57 needs to be told not to cache.
  *
  * @param string $link Permalink where to redirect to
  * @return void
  */
  private function redirect( $link ) {

    //wp_redirect( $permalink, 307 );
    /**
    * best experience with code 307 for preventing caching in browser
    * create own redirect to be able to add own header
    */
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'Location: ' . $link, true, 307 );
    exit;

  }



  /**
  * Display some help on plugin activation
  *
  * @param void
  * @return void
  */
  public function on_activation() {



  }


}


/**
* launch the plugin: add actions and filters
*/
$UpcomingEvent = new UpcomingEvent();