<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

class Membership_Notes {

  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';
  private $membership_notes_cpt_slug = '';

  public function __construct() {
      add_action( 'save_post', array( $this, 'add_mship_note_on_post_save' ), 10, 3 );
      add_action( 'add_meta_boxes', array( $this, 'add_membership_notes_meta_box') );
      add_action( 'save_post', array( $this, 'save_membership_note') );
      add_action( 'admin_footer', array( $this, 'add_membership_notes_ajax_script') );
      add_action( 'wp_ajax_add_membership_note_ajax', array( $this, 'handle_add_membership_note_ajax') );
      add_action( 'wp_ajax_delete_membership_note_ajax', array( $this, 'handle_delete_membership_note_ajax' ) );

      $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
      $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
      $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
      $this->membership_notes_cpt_slug = Helper::get_membership_notes_cpt_slug();

  }

  public function add_membership_notes_meta_box() {
    add_meta_box(
        'membership_notes_meta_box',
        'Membership Notes',
        [$this, 'display_membership_notes_meta_box'],
        $this->membership_cpt_slug,
        'side'
    );
  }

  public function display_membership_notes_meta_box( $post ) {
    $membership_notes_manager = new Membership_Notes();
    $notes = $membership_notes_manager->get_notes_for_post( $post->ID );
    if ( !empty( $notes ) ) {
        echo '<h3>Membership Notes</h3>';
        echo '<ul>';
        foreach ( $notes as $note ) {
          echo '<li><em>' . esc_html( $note['note_content'] ) . '</em><br />';
          echo $note['note_attribute'] . '<br />';
          echo ' <span style="color:red">' . $note['delete_link'] . '</span></small></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No notes found for this membership.</p>';
    }
    ?>
    <h3>Add Note</h3>
    <textarea name="membership_note_content" rows="4" style="width:100%;" placeholder="Enter your note here..."></textarea>
    <p>
        <button type="button" id="add_membership_note" class="button">Add Note</button>
    </p>
    <?php
  }

  public function save_membership_note( $post_id ) {
    if ( 'memberships' !== get_post_type( $post_id ) || !isset( $_POST['membership_note_content'] ) ) {
        return;
    }
    $note_content = sanitize_text_field( $_POST['membership_note_content'] );
    if ( !empty( $note_content ) ) {
        $membership_notes_manager = new Membership_Notes();
        $membership_notes_manager->add_admin_note( $post_id, $note_content );
    }
  }

  public function add_membership_notes_ajax_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#add_membership_note').on('click', function() {
                var noteContent = $('textarea[name="membership_note_content"]').val();
                var postId = <?php echo get_the_ID(); ?>;
                if ( noteContent.trim() === '' ) {
                    alert('Please enter a note.');
                    return;
                }
                $.post(
                    '<?php echo admin_url('admin-ajax.php'); ?>',
                    {
                        action: 'add_membership_note_ajax',
                        note_content: noteContent,
                        post_id: postId,
                    },
                    function(response) {
                        if ( response.success ) {
                            alert('Note added successfully!');
                            location.reload();
                        } else {
                            alert('Failed to add note.');
                        }
                    }
                );
            });
            $('.delete-note-link').on('click', function(e) {
                e.preventDefault();

                var noteId = $(this).data('note-id');
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: {
                        action: 'delete_membership_note_ajax',
                        note_id: noteId,
                    },
                    success: function(response) {
                        if (response.success) {
                            $('a[data-note-id="' + noteId + '"]').closest('.note').fadeOut();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while trying to delete the note.');
                    }
                });
            });
        });
    </script>
    <?php
  }

  public function handle_delete_membership_note_ajax() {
    if ( ! current_user_can( 'delete_posts' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to delete this note.' ) );
    }
    if ( isset( $_POST['note_id'] ) && is_numeric( $_POST['note_id'] ) ) {
        $note_id = (int) $_POST['note_id'];
        $note_post = get_post( $note_id );

        if ( $note_post && $note_post->post_type === $this->membership_notes_cpt_slug ) {
            wp_delete_post( $note_id, true ); // true to force deletion, skip trash
            wp_send_json_success( array( 'message' => 'Note deleted successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Note not found or invalid.' ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'Invalid note ID.' ) );
    }
  }

  function handle_add_membership_note_ajax() {
    if ( !isset( $_POST['note_content'] ) || !isset( $_POST['post_id'] ) ) {
        wp_send_json_error();
        return;
    }

    $note_content = sanitize_text_field( $_POST['note_content'] );
    $post_id = intval( $_POST['post_id'] );
    $membership_notes_manager = new Membership_Notes();
    $membership_notes_manager->add_mship_note( $post_id, $note_content );
    wp_send_json_success();
  }

  /**
   * Adds an admin note when a Membership post is saved (or can be triggered manually).
   */
  public function add_mship_note_on_post_save( $post_id, $post, $update ) {
      if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
      if ( $this->membership_cpt_slug !== $post->post_type ) return $post_id; 

      if ( !$update ) {
          $this->add_mship_note( $post_id, 'Membership created' );
      } else {
          $this->add_mship_note( $post_id, 'Membership updated' );
      }
  }

  /**
   * Add an admin note for a specific Membership post.
   * 
   * @param int $post_id
   * @param string $note_content
   */
  public function add_mship_note( $post_id, $note_content ) {
      $user = wp_get_current_user();
      $timestamp = current_time( 'mysql' );
      $note_data = array(
          'post_type'    => $this->membership_notes_cpt_slug,
          'post_title'   => 'Note for Membership ID: ' . $post_id,
          'post_content' => $note_content,
          'post_status'  => 'publish',
          'post_author'  => $user->ID,
          'meta_input'   => array(
              '_associated_post_id' => $post_id,
              '_associated_post_type' => $this->membership_cpt_slug,
              '_note_timestamp'     => $timestamp,
          ),
      );
      wp_insert_post( $note_data );
  }

  /**
   * Get the notes for a specific Membership post.
   * 
   * @param int $post_id 
   * @return array 
   */
  public function get_notes_for_post( $post_id ) {
      wp_reset_postdata();
      $args = array(
          'post_type'      => $this->membership_notes_cpt_slug,
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'   => '_associated_post_id',
                  'value' => $post_id,
                  'compare' => '=',
              ),
              array(
                  'key'   => '_associated_post_type',
                  'value' => $this->membership_cpt_slug,
                  'compare' => '=',
              ),
          ),
      );

      $notes_query = new \WP_Query( $args );
      $notes = array();
      if ( $notes_query->have_posts() ) {
        foreach ( $notes_query->posts as $note_post ) {
          $note_id = $note_post->ID;
          $note_content = get_the_content( null, false, $note_post );
          $author = get_the_author_meta( 'display_name', $note_post->post_author );
          $timestamp = get_post_meta( $note_id, '_note_timestamp', true );
          $notes[] = array(
              'note_content' => $note_content,
              'note_attribute' => $author . " on " . $timestamp,
              'author'       => $author,
              'timestamp'    => $timestamp,
              'delete_link'  => '<a href="#" class="delete-note-link" data-note-id="' . $note_id . '">Delete</a>',
          );
        }
      }
      wp_reset_postdata();
      return $notes;
  }

  /**
   * Get a specific note by its ID.
   * 
   * @param int $note_id The ID of the note to retrieve.
   * @return array|null The note data or null if not found.
   */
  public function get_note_by_id( $note_id ) {
      $note = get_post( $note_id );

      if ( $note && $this->membership_notes_cpt_slug === $note->post_type ) {
          return array(
              'note_content' => $note->post_content,
              'author'       => get_the_author_meta( 'display_name', $note->post_author ),
              'timestamp'    => get_post_meta( $note_id, '_note_timestamp', true ),
          );
      }

      return null;
  }
}

/** Add Note for Membership by Post ID

$membership_notes_manager = new Membership_Notes();
$membership_notes_manager->add_mship_note( 123, 'This is a note related to Membership 123.' );

**/

/** Get Notes for Membership by Post ID

$notes = $membership_notes_manager->get_notes_for_post( 123 );
foreach ( $notes as $note ) {
    echo '<p>' . esc_html( $note['note_content'] ) . '</p>';
    echo '<p>By: ' . esc_html( $note['author'] ) . ' on ' . esc_html( $note['timestamp'] ) . '</p>';
}

 */

