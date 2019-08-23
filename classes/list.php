<?php
/**
 * Class DVF_List
 * display list from Dokan Vendors Filter
 *
 * @since 1.0.0
 */

class DVF_List {

	/**
	 * Maximum elements on one page
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $limit = 1;

	/**
	 * Current page
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $page = 1;

	/**
	 * Count pages left and right from current page
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $pages_lenght = 4;

	/**
	 * DokanVendorsFilterList constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_dokan_vendors_ajax_list', array( $this, 'dokan_vendors_ajax_list' ), 99 );
		add_action( 'wp_ajax_nopriv_dokan_vendors_ajax_list', array( $this, 'dokan_vendors_ajax_list' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		add_shortcode( 'dvf-list', array( $this, 'show_list' ) );
	}

	/**
	 * Load styles and scripts
	 *
	 * @since  1.0.0
	 */
	public function add_scripts() {
		wp_enqueue_style(
			'dokan-vendors-style',
			DOKAN_VF_PLUGIN_URL . 'assets/style.css',
			array(),
			DOKAN_VF_VERSION
		);
		wp_enqueue_script(
			'dokan-vendors-script',
			DOKAN_VF_PLUGIN_URL .
			'assets/scripts.js',
			array( 'jquery' ),
			DOKAN_VF_VERSION,
			true
		);

		wp_localize_script(
			'dokan-vendors-script',
			'DokanVendorsFilter',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'pluginUrl' => DOKAN_VF_PLUGIN_URL
			)
		);
	}

	/**
	 * Display list
	 *
	 * @since 1.0.0
	 *
	 * @param $attrs
	 *
	 * @return string
	 */
	public function show_list( $attrs ) {
		$html    = '<div class="dvf-wrapper">';
		$html    = $this->get_header( $html );
		$html    = $this->get_filters( $html );
		$vendors = $this->get_vendors();

		if ( count( $vendors ) ) {
			$html .= '	<section class="dvf-items">';

			foreach ( $vendors as $vendor ) {
				$html .= $this->get_vendor_item( $vendor );
			}

			$html .= '	</section>';
		}

		$html = $this->get_footer( $html );
		$html .= '</div > ';

		return $html;
	}

	/**
	 * Make list header
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_header( $html ) {
		$html .= '	<section class="dvf-head" >
						<input type="hidden" name="dokan_per_page" value="' . $this->limit . '" >';

		$html .= $this->get_pages();

		$html .= $this->get_paginations();

		$html .= '		<input type="hidden" name="dokan_vendors_page" value="' . $this->page . '" >';

		$filters = DVF_Params::get_parameter( 'filters' );
		if ( count( $filters ) ) {
			$html .= '		<div class="dvf-filter-button" >';
			$html .= '			<a href = "#" > Filters</a >';
			$html .= '		</div >';
		}

		$html .= '	</section > ';

		return $html;
	}

	/**
	 * Get filters for list
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_filters( $html ) {

		$html .= '	<section class="dvf-filter-section" >
						<form id="dokan-vendors-filters-form">';

		$filters = DVF_Params::get_parameter( 'filters' );
		foreach ( DVF_Params::$fields as $key => $field ) {
			if ( ! isset( $filters[ $key ] ) || $filters[ $key ] != DVF_Params::ACTIVE ) {
				continue;
			}

			$meta_values = $this->get_meta_values( DVF_Params::SLUG . $key );

			if ( count( $meta_values ) ) {
				$html .= '	<div class="dvf-dropdown" >
			                <div class="dvf-dropdown-title" >' . $field . '</div >
			                <div class="dvf-dropdown-preview" >' . ( count( $meta_values ) > 1
						? 'All ' . $field : $meta_values[0]['title'] ) . ' <i class="arrow down"></i ></div >
			                <div class="dvf-dropdown-list" >';

				if ( count( $meta_values ) > 1 ) {
					$html .= '  <input type="checkbox" value="all" 
									name="' . DVF_Params::SLUG . $key . '[0]" 
									id="' . $key . '_all" >
								<label for="' . $key . '_all" >All ' . $field . ' </label >';
				}

				$i = 1;
				foreach ( $meta_values as $meta_value ) {
					$html .= '  <input type="checkbox" value="' . $meta_value['value'] . '" 
									name="' . DVF_Params::SLUG . $key . '[' . $i . ']" 
									id="' . $key . '_' . $i . '" >
								<label for="' . $key . '_' . $i . '" >' . $meta_value['title'] . '</label >';
					$i ++;
				}

				$html .= '	    </div>
	                    </div>';
			}

		}

		$html .= '		</form>
						<div class="clear" ></div >
					</section>';

		return $html;
	}

	/**
	 * Make list footer
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_footer( $html ) {
		$html .= '<section class="dvf-footer" >';

		$html .= $this->get_pages();

		$html .= $this->get_paginations();

		$html .= '</section>';

		return $html;
	}

	/**
	 * Get vendors from dokan by current filters
	 *
	 * @since 1.0.0
	 *
	 * @param array $postdata
	 *
	 * @return mixed|array
	 */
	private function get_vendors( $postdata = array() ) {
		$args   = array();
		$limit  = $this->limit;
		$offset = 1;

		if ( count( $postdata ) ) {
			$args               = array( 'meta_query' );
			$args['meta_query'] = [];
			if ( count( $postdata ) > 1 ) {
				$args['meta_query']['relation'] = 'AND';
			}

			foreach ( $postdata as $key => $value ) {
				if ( $value[0] != 'all' && $key != 'limit' && $key != 'page' ) {
					$args['meta_query'][] = array(
						'key'     => $key,
						'value'   => $value,
						'compare' => 'IN',
					);
				}
			}
		}

		$results        = [];
		$args['number'] = $this->limit;
		$args['offset'] = ( $this->page - 1 ) * $this->limit;
		$vendors        = dokan_get_sellers( $args );

		foreach ( $vendors['users'] as $seller ) {
			$vendor      = dokan()->vendor->get( $seller->ID );
			$store_info  = dokan_get_store_info( $vendor->data->ID );
			$description = get_user_meta( $vendor->data->ID, 'description', true );

			if ( strlen( $description ) > 65 ) {
				$description = substr( $description, 0, 65 ) . '...';
			}

			$store_banner_id  = $vendor->get_banner_id();
			$store_banner_url = $store_banner_id ?
				wp_get_attachment_image_src( $store_banner_id, 'kas_vendor_image' ) :
				DOKAN_PLUGIN_ASSEST . '/images/default-store-banner.png';

			$results[] = array(
				'store_id'    => $vendor->data->ID,
				'store_url'   => dokan_get_store_url( $vendor->data->ID ),
				'store_name'  => $store_info['store_name'],
				'description' => $description,
				'phone'       => $store_info['phone'],
				'banner'      => ( is_array( $store_banner_url ) ?
					esc_attr( $store_banner_url[0] ) : esc_attr( $store_banner_url ) ),
			);
		}

		return $results;
	}

	/**
	 * Make vendor item HTML block
	 *
	 * @since 1.0.0
	 *
	 * @param $vendor
	 *
	 * @return string
	 */
	private function get_vendor_item( $vendor ) {
		$html = '	<div class="dvf-item" >
			            <a href="' . $vendor['store_url'] . '" class="dvf-thumb" >
			            	<span class="dvf-show-more" > More details </span >
			            	<img src="' . $vendor['banner'] . '" />
			            </a >
			            <div class="dvf-item-description" >
			                <a href="' . $vendor['store_url'] . '" class="dvf-item-title" > 
			                    ' . $vendor['store_name'] . '
		                    </a >
			                <div class="dvf_item_address" >' . $vendor['description'] . '</div >
			                <div class="dvf-item-phone" >' . $vendor['phone'] . '</div >
			            </div >
		            </div >';

		return $html;
	}

	/**
	 * Get all isset meta values
	 *
	 * @since 1.0.0
	 *
	 * @param string $key
	 *
	 * @return array|void
	 */
	private function get_meta_values( $key = '' ) {
		if ( empty( $key ) ) {
			return;
		}

		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare( "SELECT um.meta_value FROM {$wpdb->usermeta} um WHERE um.meta_key = %s", $key )
		);

		return $this->prepare_meta_titles( array_unique( $results ), $key );
	}

	/**
	 * Add titles with country names and other
	 *
	 * @since 1.0.0
	 *
	 * @param $meta_values
	 * @param $key
	 *
	 * @return array
	 */
	private function prepare_meta_titles( $meta_values, $key ) {
		$results = [];
		$titles  = [];

		if ( $key == DVF_Params::SLUG . DVF_Params::FIELD_COUNTRY ) {
			$titles = WC()->countries->get_allowed_countries();
		}

		foreach ( $meta_values as $meta_value ) {
			$results[] = array(
				'value' => $meta_value,
				'title' => isset( $titles[ $meta_value ] ) ? $titles[ $meta_value ] : $meta_value,
			);
		}

		return $results;
	}

	/**
	 * Make pagination block
	 *
	 * @since 1.0.0
	 *
	 * @param array $postdata
	 * @param string $ul
	 *
	 * @return string
	 */
	private function get_paginations( $postdata = array(), $ul = 'show' ) {
		$html          = '';
		$count_vendors = $this->get_count_vendors( $postdata );
		$pages         = $count_vendors / $this->limit;

		if ( $pages > 1 ) {
			if ( 'show' == $ul ) {
				$html .= '<ul class="dvf-pagination" >';
			}

			if ( 1 < $this->page ) {
				$html .= '<li ><a href="#" data-page="' . ( $this->page - 1 ) . '" ><span ><</span ></a ></li >';
			}

			$pages_left = $this->page - $this->pages_lenght;
			if ( 1 > $pages_left ) {
				$pages_left = 1;
			}

			for ( $i = $pages_left; $i < $this->page; $i ++ ) {
				$html .= '<li ><a href="#" data-page="' . $i . '"><span >' . $i . '</span ></a ></li >';
			}

			$html .= '<li ><a href="#" data-page="' . $this->page
			         . '" class="active" ><span>' . $this->page . '</span ></a ></li >';

			$pages_right = $this->page + $this->pages_lenght;
			if ( $pages_right > $pages ) {
				$pages_right = $pages;
			}

			for ( $i = $this->page + 1; $i <= $pages_right; $i ++ ) {
				$html .= '<li ><a href="#" data-page="' . $i . '"><span >' . $i . '</span ></a ></li >';
			}

			if ( $pages > $this->page ) {
				$html .= '<li ><a href="#" data-page="' . ( $this->page + 1 ) . '" ><span >></span ></a ></li >';
			}

			if ( 'show' == $ul ) {
				$html .= '</ul >';
			}
		}

		return $html;
	}

	private function get_pages( $postdata = array(), $ul = 'show' ) {
		$count_vendors = $this->get_count_vendors( $postdata );
		$pages         = $count_vendors / $this->limit;

		$html = '';

		if ( 'show' == $ul ) {
			$html .= '	<ul class="dvf-pages" >';
		}

		$html .= '		<li ><span > Show</span ></li >';

		foreach ( DVF_Params::$limits as $limit ) {
			if ( $pages >= $limit ) {
				$html .= '<li ><a href = "" data-per_page="' . $limit . '" ' .
				         ( ( $limit == $this->limit ) ? 'class="active"' : '' ) . ' ><span > '
				         . $limit . '</span ></a ></li >';
			}
		}

		if ( 'show' == $ul ) {
			$html .= '	</ul >';
		}

		return $html;
	}

	/**
	 * Get count vendors by filters or default
	 *
	 * @since 1.0.0
	 *
	 * @param array $postdata
	 *
	 * @return mixed
	 */
	private function get_count_vendors( $postdata = array() ) {
		$args = array();

		if ( count( $postdata ) ) {
			$args               = array( 'meta_query' );
			$args['meta_query'] = [];
			if ( count( $postdata ) > 1 ) {
				$args['meta_query']['relation'] = 'AND';
			}

			foreach ( $postdata as $key => $value ) {
				if ( $value[0] != 'all' ) {
					$args['meta_query'][] = array(
						'key'     => $key,
						'value'   => $value,
						'compare' => 'IN',
					);
				}
			}
		}

		$vendors = dokan_get_sellers( $args );

		//wp_send_json_success( $args );

		return $vendors['count'];
	}

	/**
	 * Return vendors list by filtering and pagination
	 *
	 * @since 1.0.0
	 */
	public function dokan_vendors_ajax_list() {
		ob_clean();

		parse_str( $_POST['data'], $postdata );

		if ( isset( $postdata['limit'] ) ) {
			$this->limit = $postdata['limit'];
			unset($postdata['limit']);
		}

		if ( isset( $postdata['page'] ) ) {
			$this->page = $postdata['page'];
			unset($postdata['page']);
		}

		$vendors = $this->get_vendors( $postdata );

		$items = '';

		if ( count( $vendors ) == 0 ) {
			$items = 'No results';
		}

		foreach ( $vendors as $vendor ) {
			$items .= $this->get_vendor_item( $vendor );
		}

		$answer = array(
			'items'       => $items,
			'paginations' => $this->get_paginations( $postdata, 'hide' ),
			'pages'       => $this->get_pages( $postdata, 'hide' ),
		);

		wp_send_json_success( $answer );

		wp_die();
	}
}

/**
 * Run DokanVendorsFilterAdmin class
 *
 * @since 1.0.0
 *
 * @return DVF_List
 */
function dokan_vendors_filter_list_runner() {
	return new DVF_List();
}

dokan_vendors_filter_list_runner();