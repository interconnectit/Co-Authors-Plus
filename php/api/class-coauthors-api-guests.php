<?php

/**
 * Class CoAuthors_API_Guests
 */
class CoAuthors_API_Guests extends CoAuthors_API_Controller {

	/**
	 * @var string
	 */
	protected $route = 'guests/';

	/**
	 * @inheritdoc
	 */
	protected function get_args( $context = null ) {

		$args = array(
			'q' => array(
				'contexts' => array( 'get' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'display_name' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'post'     => array( 'required' => true )
			),

			'user_login' => array(
				'contexts' => array( 'post' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_user', 'required' => true )
			),

			'user_email' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_email' ),
				'post'     => array( 'required' => true )
			),

			'first_name' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'last_name' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'linked_account' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_user' )
			),

			'website' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'esc_url_raw' )
			),

			'aim' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'yahooim' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'jabber' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			),

			'description' => array(
				'contexts' => array( 'post', 'put_item' ),
				'common'   => array( 'sanitize_callback' => 'wp_filter_post_kses' )
			),

			'id' => array(
				'contexts' => array( 'get_item', 'put_item', 'delete_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_key' )
			),

			'reassign' => array(
				'contexts' => array( 'delete_item' ),
				'common'   => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( $this, 'validate_reassign' )
				)
			),

			'leave-assigned-to' => array(
				'contexts' => array( 'delete_item' ),
				'common'   => array( 'sanitize_callback' => 'sanitize_text_field' )
			)
		);

		return $this->filter_args( $context, $args );
	}

	/**
	 * @inheritdoc
	 */
	public function create_routes() {

		register_rest_route( $this->get_namespace(), $this->get_route(), array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get' ),
			'permission_callback' => array( $this, 'authorization' ),
			'args'                => $this->get_args( 'get' )
		) );

		register_rest_route( $this->get_namespace(), $this->get_route(), array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'post' ),
			'permission_callback' => array( $this, 'authorization' ),
			'args'                => $this->get_args( 'post' )
		) );

		register_rest_route( $this->get_namespace(), $this->get_route() . '(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_item' ),
			'permission_callback' => array( $this, 'authorization' ),
			'args'                => $this->get_args( 'get_item' )
		) );

		register_rest_route( $this->get_namespace(), $this->get_route() . '(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'put_item' ),
			'permission_callback' => array( $this, 'authorization' ),
			'args'                => $this->get_args( 'put_item' )
		) );

		register_rest_route( $this->get_namespace(), $this->get_route() . '(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_item' ),
			'permission_callback' => array( $this, 'authorization' ),
			'args'                => $this->get_args( 'delete_item' )
		) );
	}

	/**
	 * @inheritdoc
	 */
	public function get( WP_REST_Request $request ) {
		$query = $request->get_param( 'q' );

		$guests = $this->search_guests( $query );

		$data = $this->prepare_data( $guests );

		return $this->send_response( $data );
	}

	/**
	 * @inheritdoc
	 */
	public function post( WP_REST_Request $request ) {
		global $coauthors_plus;

		if ( $this->does_coauthor_exists( $request['user_email'], $request['user_login'] ) ) {
			return new WP_Error( 'rest_guest_invalid_username', __( 'Invalid username or already exists.', 'co-authors-plus' ),
				array( 'status' => self::BAD_REQUEST ) );
		}

		$params = $this->prepare_params_for_database( $request->get_params( 'post' ), 'post' );

		$guest_author_id = $coauthors_plus->guest_authors->create( $params );

		if ( is_wp_error( $guest_author_id ) ) {
			return $guest_author_id;
		}

		update_post_meta( $guest_author_id, '_original_author_login', $request['user_login'] );

		$guest = $coauthors_plus->get_coauthor_by( 'ID', $guest_author_id );
		$data  = $this->prepare_data( array( $guest ) );

		return $this->send_response( $data, self::CREATED );
	}

	/**
	 * @inheritdoc
	 */
	public function get_item( WP_REST_Request $request ) {
		global $coauthors_plus;

		$coauthor_id = (int) sanitize_text_field( $request['id'] );

		$guest_author = $coauthors_plus->guest_authors->get_guest_author_by( 'ID', $coauthor_id );

		if ( ! $guest_author ) {
			return new WP_Error( 'rest_guest_not_found', __( 'Guest not found.', 'co-authors-plus' ),
				array( 'status' => self::NOT_FOUND ) );
		}

		return $this->send_response( array( $guest_author ) );
	}

	/**
	 * @inheritdoc
	 */
	public function put_item( WP_REST_Request $request ) {
		global $coauthors_plus;
		$coauthor_id = (int) sanitize_text_field( $request['id'] );

		$coauthor = $coauthors_plus->get_coauthor_by( 'ID', $coauthor_id );

		if ( ! $coauthor ) {
			return new WP_Error( 'rest_guest_not_found', __( 'Guest not found.', 'co-authors-plus' ),
				array( 'status' => self::NOT_FOUND ) );
		}

		if ( $this->does_coauthor_email_exists( $request['user_email'], $coauthor ) ) {
			return new WP_Error( 'rest_guest_invalid_email', __( 'Invalid email. or already exists.', 'co-authors-plus' ),
				array( 'status' => self::BAD_REQUEST ) );
		}

		clean_post_cache( $coauthor->ID );
		$params = $this->prepare_params_for_database( $request->get_params(), 'put_item' );

		foreach ( $params as $param => $value ) {
			update_post_meta( $coauthor->ID, 'cap-' . $param, $value );
		}

		$coauthors_plus->guest_authors->delete_guest_author_cache( $coauthor->ID );

		$guest = $coauthors_plus->get_coauthor_by( 'ID', $coauthor_id );
		$data  = $this->prepare_data( array( $guest ) );

		return $this->send_response( $data );

	}

	public function delete_item( WP_REST_Request $request ) {
		global $coauthors_plus;

		$coauthor_id = (int) sanitize_text_field( $request['id'] );

		$guest_author = $coauthors_plus->get_coauthor_by( 'ID', $coauthor_id );

		if ( ! $guest_author ) {
			return new WP_Error( 'rest_guest_not_found', __( 'Guest not found.', 'co-authors-plus' ),
				array( 'status' => self::NOT_FOUND ) );
		}

		switch ( $request['reassign'] ) {
			// Leave assigned to the current linked account
			case 'leave-assigned':
				$reassign_to = $guest_author->linked_account;
				break;
			// Reassign to a different user
			case 'reassign-another':
				$user_nicename = sanitize_title( $request['leave-assigned-to'] );
				$reassign_to   = $coauthors_plus->get_coauthor_by( 'user_nicename', $user_nicename );
				if ( ! $reassign_to ) {
					return new WP_Error( 'rest_reassigned_user_not_found', __( 'Reassigned user does not exists.', 'co-authors-plus' ),
						array( 'status' => self::BAD_REQUEST ) );
				}
				$reassign_to = $reassign_to->user_login;
				break;
			// Remove the byline, but don't delete the post
			case 'remove-byline':
				$reassign_to = false;
				break;
		}

		$retval = $coauthors_plus->guest_authors->delete( $guest_author->ID, $reassign_to );

		if ( ! $retval ) {
			return new WP_Error( 'rest_guest_delete_error', __( 'Oh oh, something happened. Guest was not deleted.', 'co-authors-plus' ),
				array( 'status' => self::BAD_REQUEST ) );
		}

		$data = $this->prepare_data( array( $guest_author ) );

		return $this->send_response( $data );
	}

	/**
	 * @param $param
	 * @param WP_REST_Request $request
	 * @param $key
	 *
	 * @return WP_Error
	 */
	public function validate_reassign( $param, WP_REST_Request $request, $key ) {

		$reassign          = sanitize_title( $param );
		$leave_assigned_to = sanitize_title( $request['leave-assigned-to'] );

		if ( 'leave-assigned' !== $reassign && 'reassign-another' !== $reassign && 'remove-byline' !== $reassign ) {
			return new WP_Error( 'rest_guest_reassign_invalid_option', __( 'Invalid reassigned option', 'co-authors-plus' ),
				array( 'status' => self::BAD_REQUEST ) );
		}

		if ( 'reassign-another' === $reassign && ! $leave_assigned_to ) {
			return new WP_Error( 'rest_guest_reassign_invalid_option', __( 'reassign-another requires  "leave-assigned-to" parameter. ', 'co-authors-plus' ),
				array( 'status' => self::BAD_REQUEST ) );
		}

		return true;
	}

	/**
	 * Checks if a coauthor was already added with the same user_email or login.
	 *
	 * @param $email
	 * @param $user_login
	 *
	 * @return bool
	 */
	private function does_coauthor_exists( $email, $user_login ) {
		global $coauthors_plus;

		// Don't allow empty usernames
		if ( ! $user_login ) {
			return false;
		}

		// Guest authors can't be created with the same user_login as a user
		$user = get_user_by( 'slug', $user_login );
		if ( $user && is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
			return true;
		}

		if ( true == $this->does_coauthor_email_exists( $email ) ) {
			return true;
		}

		if ( $coauthors_plus->guest_authors->get_guest_author_by( 'user_login', sanitize_text_field( $user_login ), true ) ) {
			return true;
		}

		return false;
	}


	/**
	 * @param $email
	 * @param $coauthor
	 *
	 * @return bool
	 */
	private function does_coauthor_email_exists( $email, $coauthor = null ) {
		global $coauthors_plus;

		$coauthor_found = $coauthors_plus->guest_authors->get_guest_author_by( 'user_email',
			sanitize_email( $email ), true );

		// If it's the same user's email, it shall pass.
		if ( $coauthor !== null && $coauthor_found->ID == $coauthor->ID ) {
			return false;
		}

		return $coauthor_found;
	}

	/**
	 * Returns an array only with the supported fields from the class args are added to the
	 * create or update data.
	 *
	 * @param $params
	 * @param $context
	 *
	 * @return array
	 */
	private function prepare_params_for_database( $params, $context ) {

		$args = $this->get_args( $context );
		$data = array();

		foreach ( $params as $param => $value ) {
			if ( isset( $args[ $param ] ) ) {
				$data[ $param ] = $value;
			}
		}

		return $data;
	}

	/**
	 * @inheritdoc
	 */
	public function authorization( WP_REST_Request $request ) {
		global $coauthors_plus;

		return current_user_can( $coauthors_plus->guest_authors->list_guest_authors_cap );
	}

	/**
	 * @param array $guests
	 *
	 * @return array
	 */
	protected function prepare_data( $guests ) {

		$data = array();

		foreach ( $guests as $guest ) {
			$data[] = array(
				'id'             => (int) $guest->ID,
				'display_name'   => $guest->display_name,
				'first_name'     => $guest->first_name,
				'last_name'      => $guest->last_name,
				'user_email'     => $guest->user_email,
				'linked_account' => $guest->linked_account,
				'website'        => $guest->website,
				'aim'            => $guest->aim,
				'yahooim'        => $guest->yahooim,
				'jabber'         => $guest->jabber,
				'description'    => $guest->description,
				'user_nicename'  => $guest->user_nicename,
			);
		}

		return $data;
	}

	/**
	 * @param $query
	 *
	 * @return array
	 */
	protected function search_guests( $query ) {
		global $wpdb;

		if ( $query ) {
			$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name like '%%%s%%' AND post_type = 'guest-author'", $wpdb->esc_like( $query ) );
		} else {
			$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s", 'guest-author' );
		}
		$posts = $wpdb->get_results( $query );

		$guests = array();

		if ( count( $posts ) > 0 ) {
			foreach ( $posts as $post ) {
				$guest    = get_coauthors( $post->ID );
				$guests[] = $guest[0];
			}
		}

		return $guests;
	}
}