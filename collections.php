<?php

/*
Plugin Name: Basic Collections
Description: Create sets (taxonomy) and collections (post type).
Version: 1.0.0
Author: Jordy Meow
Author URI: http://www.meow.fr
*/

class Meow_WPLR_Sync_Plugin_Collections {

  public function __construct() {

    // Init
    add_action( 'init', array( $this, 'init' ), 10, 0 );

    // Reset
    add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );

    // Move
    add_action( "wplr_move_folder", array( $this, 'move_collection' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );
    add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
  }

  // Create the "Collections" post type.
  function init() {
    $collections = array(
      'name'               => _x( 'Collections', 'post type general name', 'wplr-sync-collections' ),
      'singular_name'      => _x( 'Collection', 'post type singular name', 'wplr-sync-collections' ),
      'menu_name'          => _x( 'Collections', 'admin menu', 'wplr-sync-collections' ),
      'name_admin_bar'     => _x( 'Collection', 'add new on admin bar', 'wplr-sync-collections' ),
      'add_new'            => _x( 'Add New', 'collection', 'wplr-sync-collections' ),
      'add_new_item'       => __( 'Add New Collection', 'wplr-sync-collections' ),
      'new_item'           => __( 'New Collection', 'wplr-sync-collections' ),
      'edit_item'          => __( 'Edit Collection', 'wplr-sync-collections' ),
      'view_item'          => __( 'View Collection', 'wplr-sync-collections' ),
      'all_items'          => __( 'All Collections', 'wplr-sync-collections' ),
      'search_items'       => __( 'Search Collections', 'wplr-sync-collections' ),
      'parent_item_colon'  => __( 'Parent Collections:', 'wplr-sync-collections' ),
      'not_found'          => __( 'No collections found.', 'wplr-sync-collections' ),
      'not_found_in_trash' => __( 'No collections found in Trash.', 'wplr-sync-collections' )
    );
    $args = array(
      'labels'             		=> $collections,
      'public'             		=> true,
      'publicly_queryable' 		=> true,
      'show_ui'            		=> true,
      'show_in_menu'       		=> true,
      'query_var'          		=> true,
      'rewrite'            		=> array( 'slug' => 'collection' ),
      'has_archive'        		=> true,
      'hierarchical'       		=> true,
      'capability_type'		 		=> 'post',
      'map_meta_cap'			 		=> true,
      'menu_position'      		=> 10,
      'menu_icon'             => 'dashicons-camera',
      'supports'							=> array( 'title', 'editor' )
    );
    register_post_type( 'collection', $args );
  }

  function reset() {
    global $wpdb;
  	$wpdb->query( "DELETE p FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID = m.meta_value WHERE m.meta_key = \"lrid_to_id\"" );
  	$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = \"lrid_to_id\"" );
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {

    // If exists already, avoid re-creating
    if ( !empty( get_post_meta( $collectionId, 'lrid_to_id', true ) ) )
      return;

    // Get the ID of the parent collection (if any) - check the end of this function for more explanation.
    $post_parent = null;
    if ( !empty( $inFolderId ) )
      $post_parent = get_post_meta( $inFolderId, 'lrid_to_id', true );

    // Create the collection.
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_content'  => $isFolder ? '' : '[gallery ids=""]', // if folder, nothing, if collection, let's start a gallery
      'post_status'   => 'publish',
      'post_type'     => 'collection',
      'post_parent'   => $post_parent
    );
    $id = wp_insert_post( $post );

    // Let's trick this meta. Instead of using the post as reference, we use the ID from LR. Makes the get_post_meta cleaner.
    add_post_meta( $collectionId, 'lrid_to_id', $id, true );
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    // Well, we can say that a folder is a collection (we could have use a taxonomy for that too)
    // Let's keep it simple and re-use the create_collection with an additional parameter to avoid having content.
    $this->create_collection( $folderId, $inFolderId, $folder, true );
  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    $id = get_post_meta( $collectionId, 'lrid_to_id', true );
    $post = array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $collection['name'] ) );
    wp_update_post( $post );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    $this->update_collection( $folderId, $folder );
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    $post_parent = null;
    if ( !empty( $folderId ) )
      $post_parent = get_post_meta( $folderId, 'lrid_to_id', true );
    $id = get_post_meta( $collectionId, 'lrid_to_id', true );
    $post = array( 'ID' => $id, 'post_parent' => $post_parent );
    wp_update_post( $post );
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    $id = get_post_meta( $collectionId, 'lrid_to_id', true );
    $content = get_post_field( 'post_content', $id );
    preg_match_all( '/\[gallery.*ids="([0-9,]*)"\]/', $content, $results );
    if ( !empty( $results ) && !empty( $results[1] ) ) {
      $str = $results[1][0];
      $ids = !empty( $str ) ? explode( ',', $str ) : array();
      $index = array_search( $mediaId, $ids, false );
      if ( $isRemove ) {
        if ( $index !== FALSE )
          unset( $ids[$index] );
      }
      else {
        // If mediaId already there then exit.
        if ( $index !== FALSE )
          return;
        array_push( $ids, $mediaId );
      }
      // Replace the array within the gallery shortcode.
      $content = str_replace( 'ids="' . $str, 'ids="' . implode( ',', $ids ), $content );
      $post = array( 'ID' => $id, 'post_content' => $content );
      wp_update_post( $post );
    }
  }

  // Remove media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    $this->add_media_to_collection( $mediaId, $collectionId, true );
  }

  // The media was physically deleted.
  function remove_media( $mediaId ) {
    // No need to do anything.
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    $id = get_post_meta( $collectionId, 'lrid_to_id', true );
    wp_delete_post( $id, true );
    delete_post_meta( $collectionId, 'lrid_to_id' );
  }
}

new Meow_WPLR_Sync_Plugin_Collections;

?>
