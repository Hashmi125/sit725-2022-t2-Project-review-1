<?php
namespace WPHR\HR_MANAGER\HRM;

use WPHR\HR_MANAGER\Framework\Traits\Hooker;

/**
 *  Announcement class HR
 *
 *  Announcement for employees
 *
 *  @since 0.1
 *
 *  @author WPHR <info@wphr.com>
 */
class Announcement {

    use Hooker;

    private $post_type = 'wphr_hr_announcement';
    private $assign_type = array();

    /**
     *  Load autometically all actions
     */
    function __construct() {
        $this->assign_type = array(
            ''                  => __( '-- Select --', 'wphr' ),
            'all_employee'      => __( 'All Employees', 'wphr' ),
            'selected_employee' => __( 'Selected Employee', 'wphr' ),
            'by_department'     => __( 'By Department', 'wphr' ),
            'by_designation'     => __( 'By Designation', 'wphr' )
        );

        $this->action( 'init', 'post_types' ) ;
        $this->action( 'do_meta_boxes', 'do_metaboxes' );
        $this->action( 'save_post', 'save_announcement_meta', 10, 2 );

        $this->filter( 'manage_edit-wphr_hr_announcement_columns', 'add_type_columns' );
        $this->filter( 'manage_wphr_hr_announcement_posts_custom_column', 'assign_type_edit_columns', 10, 2 );

        // $this->filter( 'parent_file', 'fix_parent_file', 999 );
        // $this->filter( 'submenu_file', 'submenu_file', 999 );

        $this->action( 'admin_head', 'filter_admin_sidebar_menu_items' );
    }

    /**
     * Filter admin sidebar menu items
     *
     * Remove HR Announcement items from sidebar generated by register_post_type function.
     * Highlight Parent menu as active item when we are in questionnaire menu page and sub pages.
     *
     * @since 1.1.6
     *
     * @return void
     */
    function filter_admin_sidebar_menu_items() {
        global $menu, $submenu_file, $typenow;

        $hr_menu = array_filter( $menu, function ( $item ) {
            return __( 'HR Announcement', 'wphr' ) === $item[0];
        } );

        $announcement_pages = [
            'post-new.php?post_type=wphr_hr_announcement',
            'edit.php?post_type=wphr_hr_announcement'
        ];

        if ( in_array( $submenu_file , $announcement_pages ) ) {
            $submenu_file = 'edit.php?post_type=wphr_hr_announcement';
            $typenow = null;
            $_SERVER['PHP_SELF'] = 'wphr-hr';

            add_filter( 'parent_file', function () {
                return 'edit.php?post_type=wphr_hr_announcement';
            } );
        }

        $hr_menu_position = key( $hr_menu );

        unset( $menu[ $hr_menu_position ] );
    }

    /**
     * Fix parent file
     *
     * @param  string  $parent_file
     *
     * @return string
     */
    function fix_parent_file( $parent_file ) {
        global $current_screen;

        if ( $current_screen->post_type == $this->post_type ) {
            $parent_file = 'wphr-hr';
        }

        return $parent_file;
    }

    /**
     * Set submenu file
     *
     * @param  string  $submenu_file
     *
     * @return string
     */
    function submenu_file( $submenu_file ) {
        global $current_screen;

        if ( $current_screen->post_type == $this->post_type ) {
            $submenu_file = 'edit.php?post_type=wphr_hr_announcement';
        }

        return $submenu_file;
    }

    /**
     * Register Announcement post type
     *
     * @since 0.1
     *
     * @return void
     */
    function post_types() {
        $capability = 'wphr_hr_manager';

        register_post_type( $this->post_type, array(
            'label'               => __( 'Announcement', 'wphr' ),
            'description'         => '',
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => array( 'slug' => '' ),
            'query_var'           => false,
            'supports'            => array( 'title', 'editor' ),
            'capabilities'        => array(
                'edit_post'          => $capability,
                'read_post'          => $capability,
                'delete_posts'       => $capability,
                'edit_posts'         => $capability,
                'edit_others_posts'  => $capability,
                'publish_posts'      => $capability,
                'read_private_posts' => $capability,
                'create_posts'       => $capability,
                'delete_post'        => $capability,
            ),
            'labels'          => array(
                'name'               => __( 'Announcement', 'wphr' ),
                'singular_name'      => __( 'Announcement', 'wphr' ),
                'menu_name'          => __( 'HR Announcement', 'wphr' ),
                'add_new'            => __( 'Add Announcement', 'wphr' ),
                'add_new_item'       => __( 'Add New Announcement', 'wphr' ),
                'edit'               => __( 'Edit', 'wphr' ),
                'edit_item'          => __( 'Edit Announcement', 'wphr' ),
                'new_item'           => __( 'New Announcement', 'wphr' ),
                'view'               => __( 'View Announcement', 'wphr' ),
                'view_item'          => __( 'View Announcement', 'wphr' ),
                'search_items'       => __( 'Search Announcement', 'wphr' ),
                'not_found'          => __( 'No Announcement Found', 'wphr' ),
                'not_found_in_trash' => __( 'No Announcement found in trash', 'wphr' ),
                'parent'             => __( 'Parent Announcement', 'wphr' )
            ),
        ) );
    }

    /**
     * Initialize metabox for ERP HR announcement post type
     *
     * @since 0.1
     *
     * @return void
     */
    function do_metaboxes() {
        add_meta_box( 'wphr-hr-announcement-meta-box', __('Announcement Settings', 'wphr'), array( $this, 'meta_boxes_cb' ), $this->post_type, 'advanced', 'high' );
    }

    /**
     * Announcement metabox callback function
     *
     * @param  integer $post_id
     *
     * @return void
     */
    function meta_boxes_cb( $post_id ) {
        global $post;

        $employees    = wphr_hr_get_employees( [ 'number' => -1, 'no_object' => true ] );
        $departments  = wphr_hr_get_departments( [ 'number' => -1, 'no_object' => true ] );
        $designations = wphr_hr_get_designations( [ 'number' => -1, 'no_object' => true ] );

        $announcement_type        = get_post_meta( $post->ID, '_announcement_type', true );
        $announcement_users       = get_post_meta( $post->ID, '_announcement_selected_user', true );
        $announce_departments     = get_post_meta( $post->ID, '_announcement_department', true );
        $announce_designations    = get_post_meta( $post->ID, '_announcement_designation', true );

        $announcement_employee    = ( $announcement_users )    ? $announcement_users    : array();
        $announcement_department  = ( $announce_departments )  ? $announce_departments  : array();
        $announcement_designation = ( $announce_designations ) ? $announce_designations : array();

        ?>
            <table class="form-table wphr-hr-announcement-meta-wrap-table">
                <tr>
                    <th><?php _e( 'Send Announcement To', 'wphr' ); ?></th>
                    <td>
                        <select name="hr_announcement_assign_type" id="hr_announcement_assign_type" style="width:60%">
                            <?php foreach ( $this->assign_type as $key => $type ): ?>
                                <option value="<?php echo $key; ?>" <?php selected( $announcement_type, $key ); ?>><?php echo $type; ?></option>
                            <?php endforeach ?>
                        </select>
                    </td>
                </tr>

                <tr class="selected_employee_field">
                    <th><?php _e( 'Select Employees', 'wphr' ); ?></th>
                    <td>
                        <select name="hr_announcement_assign_employee[]" data-placeholder= '<?php echo __( 'Select Employees...', 'wphr' ); ?>' id="hr_announcement_assign_employee" class="wphr-select2" multiple="multiple">
                            <?php
                            foreach ( $employees as $user ) {
                                if ( $user->user_id == get_current_user_id() ) {
                                    continue;
                                }

                                ?>
                                    <option <?php echo in_array( $user->user_id, $announcement_employee ) ? 'selected="selected"' : ''; ?> value='<?php echo $user->user_id  ?>'><?php echo $user->display_name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr class="by_department_field">
                    <th><?php _e( 'Select Departments', 'wphr' ); ?></th>
                    <td>
                        <select name="hr_announcement_assign_department[]" data-placeholder= '<?php echo __( 'Select Departments...', 'wphr' ); ?>' id="hr_announcement_assign_department" class="wphr-select2" multiple="multiple">
                            <?php
                            foreach ( $departments as $department ) {
                                ?>
                                <option <?php echo in_array( $department->id, $announcement_department ) ? 'selected="selected"' : ''; ?> value='<?php echo $department->id; ?>'><?php echo $department->title; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr class="by_designation_field">
                    <th><?php _e( 'Select Designations', 'wphr' ); ?></th>
                    <td>
                        <select name="hr_announcement_assign_designation[]" data-placeholder= '<?php echo __( 'Select Designations...', 'wphr' ); ?>' id="hr_announcement_assign_designation" class="wphr-select2" multiple="multiple">
                            <?php
                            foreach ( $designations as $designation ) {
                                ?>
                                <option <?php echo in_array( $designation->id, $announcement_designation ) ? 'selected="selected"' : ''; ?> value='<?php echo $designation->id; ?>'><?php echo $designation->title; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <?php do_action( 'hr_announcement_table_last', $post ); ?>

            </table>
            <?php wp_nonce_field( 'hr_announcement_meta_action', 'hr_announcement_meta_action_nonce' ); ?>

            <script>
                (function( $ ){
                    $( document ).ready( function() {

                        // Remove selected value other than the currently active one.
                        switch ( $('select#hr_announcement_assign_type').val() ) {
                            case 'selected_employee':
                                $( 'tr.by_department_field select' ).val(null).trigger('change');
                                $( 'tr.by_designation_field  select' ).val(null).trigger('change');
                                break;

                            case 'by_department':
                                $( 'tr.selected_employee_field  select' ).val(null).trigger('change');
                                $( 'tr.by_designation_field  select' ).val(null).trigger('change');
                                break;

                            case 'by_designation':
                                $( 'tr.selected_employee_field  select' ).val(null).trigger('change');
                                $( 'tr.by_department_field  select' ).val(null).trigger('change');
                        }

                        $('table.wphr-hr-announcement-meta-wrap-table').on( 'change', 'select#hr_announcement_assign_type', function() {
                            var self = $( this );

                            switch ( self.val() ) {
                                case 'all_employee':
                                    $( 'tr.selected_employee_field' ).hide();
                                    $( 'tr.by_department_field' ).hide();
                                    $( 'tr.by_designation_field' ).hide();
                                    break;

                                case 'selected_employee':
                                    $( 'tr.by_department_field' ).hide();
                                    $( 'tr.by_designation_field' ).hide();
                                    $( 'tr.selected_employee_field' ).show();
                                    break;

                                case 'by_department':
                                    $( 'tr.selected_employee_field' ).hide();
                                    $( 'tr.by_department_field' ).show();
                                    $( 'tr.by_designation_field' ).hide();
                                    break;

                                case 'by_designation':
                                    $( 'tr.selected_employee_field' ).hide();
                                    $( 'tr.by_department_field' ).hide();
                                    $( 'tr.by_designation_field' ).show();
                            }
                        });

                        $( 'select#hr_announcement_assign_type' ).trigger( 'change' )
                    });
                })( jQuery );
            </script>
            <style>
                #hr_announcement_assign_employee,
                #hr_announcement_assign_department,
                #hr_announcement_assign_designation {
                    width: 315px;
                }

                tr.selected_employee_field,
                tr.by_department_field,
                tr.by_designation_field {
                    display: none;
                }

                .wphr-hr-announcement-meta-wrap-table .select2-search__field {
                    width: 100% !important;
                }
            </style>
        <?php
    }

    /**
     * Add custom column label
     *
     * @since  0.1
     *
     * @param array $columns
     */
    function add_type_columns( $columns ) {
        unset( $columns['date'] );

        $columns['assign_type'] = __( 'Sent To', 'wphr' );
        $columns['send_type']   = __( 'Type', 'wphr' );
        $columns['date']        = __( 'Date', 'wphr' );

        return $columns;
    }

    /**
     * Render custom column content
     *
     * @since  0.1
     *
     * @param  string $column
     * @param  integer $post_id
     *
     * @return void
     */
    function assign_type_edit_columns( $column, $post_id ) {
        global $post;

        if ( $column == 'assign_type' ) {
            $assign_type = get_post_meta( $post_id, '_announcement_type', true );

            if ( $assign_type ) {
                echo $this->assign_type[$assign_type];
            } else {
                _e( 'No employee assigned!', 'wphr' );
            }
        }

        if ( 'send_type' == $column ) {
            echo '<i class="fa fa-envelope-o fa-lg"></i>';

            do_action( 'hr_announcement_send_type', $column, $post_id );
        }
    }

    /**
     * Save Announcement post meta
     *
     * @since  0.1
     *
     * @param  integer $post_id
     * @param  object $post
     *
     * @return void
     */
    function save_announcement_meta( $post_id, $post ) {

        if ( ! isset( $_POST['hr_announcement_meta_action_nonce'] ) ) {
            return $post_id;
        }

        if ( ! wp_verify_nonce( $_POST['hr_announcement_meta_action_nonce'], 'hr_announcement_meta_action' ) ) {
            return $post_id;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        $post_type = get_post_type_object( $post->post_type );

        if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
            return $post_id;
        }

        if ( !current_user_can( 'wphr_manage_announcement' ) ) {
            return $post_id;
        }

        $type         = ( isset( $_POST['hr_announcement_assign_type'] ) ) ? sanitize_text_field($_POST['hr_announcement_assign_type']) : '';
        $employees    = ( isset( $_POST['hr_announcement_assign_employee'] ) ) ? custom_sanitize_array( $_POST['hr_announcement_assign_employee'] ) : array();
        $departments  = ( isset( $_POST['hr_announcement_assign_department'] ) ) ? custom_sanitize_array ( $_POST['hr_announcement_assign_department'] ) : array();
        $designations = ( isset( $_POST['hr_announcement_assign_designation'] ) ) ? custom_sanitize_array( $_POST['hr_announcement_assign_designation'] ) : array();

        if ( $type == 'by_department' ) {
            $selected = $departments;
        } elseif ( $type == 'by_designation' ) {
            $selected = $designations;
        } else {
            $selected = $employees;
        }

		// Assign / Send announcements to the selected group
        wphr_hr_assign_announcements_to_employees( $post_id, $type, $selected );

        do_action( 'hr_annoucement_save', $post_id, $selected );
    }

}
