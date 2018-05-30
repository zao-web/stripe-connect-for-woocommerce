<?php

class Seller_Statements {

    protected $seller_id;
    protected $db;
    protected $table_name;

    protected $types = [
        'monthly_fee',
        'transactional_fee',
        'transactional_fee',
        'commission',
        'tax',
        'shipping',
        'order'
    ];

    protected $statuses = [
        'due',
        'paid',
        'reversed',
        'pending'
    ];

    public function __construct( $seller_id ) {
        $this->seller_id = $seller_id;
        $this->db        = $GLOBALS['wpdb'];
        $this->_init_db();
    }

    /**
     * Creates custom table for fees for vendors.
     *
     * Similar to commissions, but gives us a bit more flexibility in terms of types of fees, descriptions, etc.
     *
     * @return void
     */
    private function _init_db() {
        $db_version       = get_option( 'stripe_connect_db_version_statements' );
        $current_version  = stripe_connect_for_woocommerce()->version;

        $this->table_name = $this->db->prefix . 'stripe_connect_commissions';

        if ( $current_version === $db_version ) {
            return;
        }


        $sql = "CREATE TABLE $this->table_name (
        id int(24) NOT NULL AUTO_INCREMENT,
        date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        seller_id int(24) NOT NULL,
        order_id int(24),
        description text,
        amount decimal(13,2),
        type varchar(155),
        status varchar(155),
        PRIMARY KEY  (id)
        KEY (seller_id)
        KEY (order_id)
        );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'stripe_connect_db_version_statements', $current_version );
    }

    public function add_fee( $args ) {

        $args = apply_filters( 'stripe_connect_insert_commissions_args', $this->validate_args( $args ) );

        return $this->db->insert(
            $this->table_name,
            $args,
            [ '%s', '%d', '%d', '%f', '%s', '%s', '%s' ]
        );
    }

    public function get_fee( $id ) {
        $args = $this->validate_args( $args );

        $sql = "SELECT * FROM $this->table_name WHERE seller_id = {$args['seller_id']} AND id = {args['id']}";

        return $this->db->get_row( $sql );
    }

    public function update_fee( $args ) {

    }

    public function delete_fee( $args ) {

    }

    public function get_fees( $args ) {
        $args = $this->validate_args( $args );

        $date_query = new WP_Date_Query( $args, $this->table_name . '.date'  );
        $date_sql   = $date_query->get_sql();

    }

    private function validate_args( $args ) {
        $args = wp_parse_args(
            $args, [
                'id'          => 0,
                'date'        => current_time( 'mysql' ),
                'seller_id'   => $this->seller_id,
                'order_id'    => 0,
                'amount'      => 0.00,
                'type'        => '',
                'description' => '',
                'status'      => '',
           ]
        );

        // Validate that order is a real WC Order.
        if ( $args['order_id'] ) {
            $order = wc_get_order( $args['order_id'] );
            if ( ! $order ) {
                $args['order_id'] = 0;
            }
        }

        $args['amount'] = floatval( $args['amount'] );

        if ( ! in_array( $args['status'], $this->statuses, true ) ) {
            $args['status'] = __( 'N/A' );
        }

        if ( ! in_array( $args['type'], $this->types, true ) ) {
            $args['type'] = __( '' );
        }

        $args['description'] = sanitize_text_field( $args['description'] );
        $args['date']        = sanitize_text_field( $args['date'] );

        if ( $id ) {
            $args['id']          = absint( $args['id'] );
        } else {
            unset( $args['id'] );
        }

        return $args;
    }
}