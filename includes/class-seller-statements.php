<?php

class Seller_Statements {

    protected $seller_id;
    protected $db;
    protected $table_name;
    protected $start_date = '';
    protected $end_date   = '';

    protected $types = [
        'monthly_fee',
        'stripe_fee',
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
        PRIMARY KEY  (id),
        KEY (seller_id),
        KEY (order_id)
        );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'stripe_connect_db_version_statements', $current_version );
    }

    public function add_fee( $args ) {

        $args         = apply_filters( 'stripe_connect_insert_commissions_args', $this->validate_args( $args ) );
        $args['date'] = current_time( 'mysql' );

        return $this->db->insert(
            $this->table_name,
            $args,
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
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

    public function set_start( $start_timestamp ) {
        $this->start_date = $start_timestamp;
        return $this;
    }

    public function set_end( $end_timestamp ) {
        $this->end_date = $end_timestamp;
        return $this;
    }

    public function get_fees( $args ) {
        $args = $this->validate_args( $args );

        $sql = "SELECT * FROM $this->table_name WHERE seller_id = {$args['seller_id']} ";

        if ( ! isset( $args['date'] ) && isset( $this->start_date ) && isset( $this->end_date ) ) {
            $args['date'] = array(
                'date' => array(
                    'after'  => array(
                        'year'  => date( 'Y', $this->start_date ),
                        'month' => date( 'm', $this->start_date ),
                        'day'   => date( 'd', $this->start_date ),
                    ),
                    'before' => array(
                        'year'  => date( 'Y', $this->end_date ),
                        'month' => date( 'm', $this->end_date ),
                        'day'   => date( 'd', $this->end_date ),
                    )
                ) );
        } else if ( ! isset( $args['date'] ) ) {
            $args['date'] = current_time( 'mysql' );
        }

        if ( ! empty( $args['order_id'] ) ) {
            $sql .= $this->db->prepare( "AND order_id = %d ", $args['order_id'] );
        }

        if ( ! empty( $args['type'] ) ) {
            $sql .= $this->db->prepare( "AND type = %s ", $args['type'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $sql .= $this->db->prepare( "AND status = %s ", $args['status'] );
        }

        $date_query = new WP_Date_Query( $args['date'], $this->table_name . '.date'  );
        $date_sql   = $date_query->get_sql();

        if ( ! empty( $date_sql ) ) {
            $sql .= $date_sql;
        }

        return $this->db->get_results( $sql );
    }

    private function validate_args( $args ) {
        $args = wp_parse_args(
            $args, [
                'id'          => 0,
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
            $args['status'] = __( '' );
        }

        if ( ! in_array( $args['type'], $this->types, true ) ) {
            $args['type'] = __( '' );
        }

        $args['description'] = sanitize_text_field( $args['description'] );

        if ( $id ) {
            $args['id']          = absint( $args['id'] );
        } else {
            unset( $args['id'] );
        }

        return $args;
    }

    public function get_totals( $args ) {
       $all_fees = $this->get_fees( $args );

       $fees = [
            'fees' => [],
            'pending' => [
                'tax'       => [],
                'shipping'    => [],
                'commission' => [],
            ],
            'paid' => [
                'tax'       => [],
                'shipping'    => [],
                'commission' => [],
            ]
       ];

       foreach ( $all_fees as $fee ) {

            $type = $fee->type;

            if ( false !== strpos( $type, '_fee' ) ) {
                $fees['fees'][] = $fee->amount;
            } else {
                $fees[ $fee->status ][ $type ][] = $fee->amount;
            }
       }

       $fees = [
        'pending' => [
            'product'  => array_sum( $fees['pending']['commission'] ) - array_sum( $fees['fees'] ),
            'shipping' => array_sum( $fees['pending']['shipping'] ),
            'taxes '   => array_sum( $fees['pending']['tax'] ),
            'totals'   => array_sum( $fees['pending']['commission'] ) + array_sum( $fees['pending']['tax'] ) + array_sum( $fees['pending']['shipping'] ) - array_sum( $fees['fees'] ),
        ],
        'paid'    => [
            'product'  => array_sum( $fees['paid']['commission'] ) - array_sum( $fees['fees'] ),
            'shipping' => array_sum( $fees['paid']['shipping'] ),
            'taxes '   => array_sum( $fees['paid']['tax'] ),
            'totals'   => array_sum( $fees['paid']['commission'] ) + array_sum( $fees['paid']['tax'] ) + array_sum( $fees['paid']['shipping'] ) - array_sum( $fees['fees'] ),
        ],
       ];

       return $fees;
    }
}