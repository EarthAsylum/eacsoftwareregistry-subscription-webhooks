<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server - {eac}SoftwareRegistry Subscriptions for WooCommerce
 *
 * Adds a custom WebHook to WooCommerce > Settings > Advanced > Webhooks for subscription updates.
 * Extends the WooCommerce 'order.*' WebHooks by adding subscription data to orders with subscriptions.
 * Adds product meta-data to subscriptions and/or orders.
 *
 * Uses subscriptions in WooCommerce Payments and assumed to work with WooCommerce Subscriptions
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry\Webhook
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 *
 * @wordpress-plugin
 * Plugin Name:				{eac}SoftwareRegistry Subscription WebHooks
 * Description:				Software Registration Server Subscription Webhooks for WooCommerce - adds a custom Webhook topic for subscription updates to WooCommerce Webhooks.
 * Version:					2.1.4
 * Requires at least:		5.8
 * Tested up to:			6.8
 * Requires PHP:			7.4
 * Requires Plugins: 		woocommerce
 * WC requires at least: 	7.0
 * WC tested up to: 		9.8
 * Plugin URI:        		https://swregistry.earthasylum.com/subscriptions-for-woocommerce/
 * Author:					EarthAsylum Consulting
 * Author URI:				http://www.earthasylum.com
 * License: 				GPLv3 or later
 * License URI: 			https://www.gnu.org/licenses/gpl.html
 * Text Domain:				eacsoftwareregistry-subscription-webhooks
 * Domain Path:				/languages
 */

namespace EarthAsylumConsulting;

class eacSoftwareRegistry_Subscription_Webhooks
{
	/**
	 * @var string default verion
	 */
	private $debugging_enabled	= true;

	/**
	 * constructor method
	 *
	 * Add filters and actions for our custom webhook
	 *
	 * @return	void
	 */
	public function __construct()
	{
		// declare compatibility with WooCommerce 9.0/HPOS
		add_action('before_woocommerce_init', function()
		{
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		});

		add_action('admin_init', function()
		{
		 	// on plugin_action_links_ filter, add 'Settings' link
	 		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
			 												array( $this, 'plugin_action_links' ), 20, 3 );

			// add our order webhook options
			add_action( 'woocommerce_webhook_options',		array( $this, 'add_webhook_options' ));

			// save our order webhook options
			add_action( 'woocommerce_webhook_options_save',	array( $this, 'save_webhook_options' ));
		});

		add_action('init', function()
		{
			$this->debugging_enabled = apply_filters('subscription_webhooks_debugging',$this->debugging_enabled);
		});

		add_action( 'plugins_loaded', 						array( $this, 'set_actions_and_filters' ) );

		// add our custom webhook topic
		add_filter( 'woocommerce_webhook_topics',			array( $this, 'add_webhook_topic' ));
	}


	/**
	 * add actions/filters for subscriptions
	 *
	 * Add filters and actions for our custom webhook
	 *
	 * @return	void
	 */
	public function set_actions_and_filters()
	{
		// trigger our webhook from wc subscription hook
		if ( function_exists('\wcs_get_subscription') )
		{
			add_action( 'woocommerce_subscription_status_updated',	array( $this, 'subscription_updated_wc' ), 10, 3 );
		}

		// trigger our webhooks from sumo subscription hook(s)
		if ( class_exists('\SUMOSubscriptions',false) )
		{
			add_action( 'sumosubscriptions_subscription_created',	function($sub_id)
			{
				remove_action( 'sumosubscriptions_active_subscription', array($this,'subscription_updated_sumo'), 20,1 );
				$this->subscription_updated_sumo($sub_id);
			}, 20, 1 );
			add_action( 'sumosubscriptions_subscription_resumed',	function($sub_id)
			{
				remove_action( 'sumosubscriptions_active_subscription', array($this,'subscription_updated_sumo'), 20,1 );
				$this->subscription_updated_sumo($sub_id);
			}, 20, 1 );
			add_action( 'sumosubscriptions_subscription_is_switched', function($order, $subscription, $switched_subscription_product)
			{
				// new order, new subscription replaces old order/subscription
				remove_action( 'sumosubscriptions_active_subscription', array($this,'subscription_updated_sumo'), 20,1 );
				$switched = get_post_meta( $subscription->get_id(), 'sumo_previous_parent_order', true );
				$this->subscription_updated_sumo($subscription->get_id(),$switched);
			},10,3 );
		//	add_action( 'sumosubscriptions_subscription_paused',	array( $this, 'subscription_updated_sumo' ), 20, 1 );
		//	add_action( 'sumosubscriptions_subscription_cancelled',	array( $this, 'subscription_updated_sumo' ), 20, 1 );
			add_action( 'sumosubscriptions_subscription_expired',	array( $this, 'subscription_updated_sumo' ), 20, 1 );

			add_action( 'sumosubscriptions_active_subscription',	array( $this, 'subscription_updated_sumo' ), 20, 1 );
			add_action( 'sumosubscriptions_pause_subscription',		array( $this, 'subscription_updated_sumo' ), 20, 1 );
			add_action( 'sumosubscriptions_cancel_subscription',	array( $this, 'subscription_updated_sumo' ), 20, 1 );
		}

		// filter the webhook payload for our's and for any other order webhooks
		add_filter( 'woocommerce_webhook_payload',				array( $this, 'get_webhook_payload' ), 10, 4 );

		// get webhook delivery response - $http_args, $response, $duration, $arg, $this->get_id() );
		add_action( 'woocommerce_webhook_delivery', 			array( $this, 'get_webhook_response' ), 10, 5 );
	}


	/**
	 * on plugin_action_links_ filter, add 'Settings' & 'Support' link
	 *
	 * @param  array $pluginLinks Default links
	 * @return array with added link
	 */
	public function plugin_action_links( $pluginLinks, $pluginFile, $pluginData )
	{
		if (empty($pluginData) || empty($pluginData['Name'])) return $pluginLinks;
		$pluginData['slug'] = dirname(plugin_basename($pluginFile));

		$query = array(
			'page'    => 'wc-settings',
			'tab'     => 'advanced',
			'section' => 'webhooks',
		);
		$settings_url = sprintf(
				'<a href="%s" title="%s">%s</a>',
				 esc_url( add_query_arg( $query,self_admin_url( 'admin.php' ) ) ),
				 /* translators: %s: WooCommerce */
				 esc_attr( sprintf( __( "%s Webhooks", 'eacsoftwareregistry-subscription-webhooks' ),'WooCommerce' ) ),
				 __( 'Settings', 'eacsoftwareregistry-subscription-webhooks' )
        );
		$website_url = sprintf(
				'<a href="%s" title="%s">%s</a>',
		 		esc_url( "https://swregistry.earthasylum.com/{$pluginData['slug']}" ),
				 /* translators: %s: this Plugin name */
		 		esc_attr( sprintf( __( "%s Documentation", 'eacsoftwareregistry-subscription-webhooks' ), $pluginData['Name'] ) ),
		 		__( 'Docs', 'eacsoftwareregistry-subscription-webhooks' )
		);
		$support_url = sprintf(
				 /* translators: %s: support url, %s: title, %s: link display */
				'<a href="%s" title="%s">%s</a>',
				 esc_url( "https://wordpress.org/support/plugin/{$pluginData['slug']}" ),
				 /* translators: %s: this Plugin name */
				 esc_attr( sprintf( __( "%s Support", 'eacsoftwareregistry-subscription-webhooks' ), $pluginData['Name'] ) ),
				 __( 'Support', 'eacsoftwareregistry-subscription-webhooks' )
        );

		return array_merge( [$settings_url, $website_url, $support_url], $pluginLinks );
	}


	/**
	 * Add webhook options to admin page
	 *
	 * @return void
	 */
	public function add_webhook_options( $topics )
	{
		$webhook_id = absint( $_GET['edit-webhook'] );
		$sub_options = \get_option('eacSoftwareRegistry_subscription_options_'.$webhook_id,[]);
		$options = [
			[
				'NEWORDER',
				__('Append related subscriptions to orders','eacsoftwareregistry-subscription-webhooks'),
				in_array('NEWORDER',$sub_options) ? ' checked=checked' : '',
			],
			[
				'RENEWAL',
				__('Append related subscriptions to renewals','eacsoftwareregistry-subscription-webhooks'),
				in_array('RENEWAL',$sub_options) ? ' checked=checked' : '',
			],
			[
				'METADATA',
				__('Append product meta data to all orders','eacsoftwareregistry-subscription-webhooks'),
				in_array('METADATA',$sub_options) ? ' checked=checked' : '',
			],
		]
		?>
		<div id="webhook-subscription-options" style="display: none;"">
		<!-- <h4><?php esc_html_e( '{eac}SoftwareRegistry Subscription WebHooks', 'woocommerce' ); ?></h4> -->
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="eacSoftwareRegistry_subscription_options">
							<abbr title='<?php esc_html_e( 'Provided by {eac}SoftwareRegistry Subscription WebHooks', 'eacsoftwareregistry-subscription-webhooks' ); ?>'>
							<?php esc_html_e( 'Order Options', 'eacsoftwareregistry-subscription-webhooks' ); ?></abbr>
							<?php echo wc_help_tip( __( 'Append subscription record(s) to orders with related subscriptions. Append custom fields and attributes from products ordered to order records;', 'eacsoftwareregistry-subscription-webhooks' ) ); ?>
						</label>
					</th>
					<td class="forminp">
					<?php
						foreach ($options as $x => $option) {
							echo "<span style='display: block;'><input type='checkbox' name='eacSoftwareRegistry_subscription_options[]' id='eacSoftwareRegistry_subscription_options_{$x}' value='{$option[0]}'{$option[2]}><label for='eacSoftwareRegistry_subscription_options_{$x}'>{$option[1]}</label></span>\n";
						}
					?>
					</td>
				</tr>
			</tbody>
		</table>
		<script type="text/javascript">
			jQuery( function ( $ ) {
				$( '#webhook-options' ).find( '#webhook_topic' ).on( 'change', function() {
					var current = $( this ).val(),
						subscriptions_field = $( '#webhook-options' ).find( '#webhook-subscription-options' );
					subscriptions_field.hide();
					if ( current.match(/^order\.(created|updated|restored)$/) ) {
						subscriptions_field.show();
					}
				}).trigger( 'change' );
			});
		</script>
		</div>
		<?php
	}


	/**
	 * save webhook options to admin page
	 *
	 * @param int $webhook_id
	 * @return void
	 */
	public function save_webhook_options( $webhook_id )
	{
		$webhook = wc_get_webhook( $webhook_id );
		$topic = $webhook->get_topic();
		if (preg_match('/^order\.(created|updated|restored)$/',$topic))
		{
			$options = $_POST['eacSoftwareRegistry_subscription_options'] ?? null;
			if ( !empty($options) && is_array($options) )
			{
				$options = array_filter($options, function($value)
					{
						return (in_array($value,['NEWORDER','RENEWAL','METADATA']));
					}
				);
				\update_option('eacSoftwareRegistry_subscription_options_'.$webhook_id,$options);
			}
			else
			{
				\delete_option('eacSoftwareRegistry_subscription_options_'.$webhook_id);
			}
		}
	}


	/**
	 * Add webhook topic to drop-down list.
	 * Custom topics are prefixed with woocommerce_ or wc_ are valid.
	 *
	 * @param array $topics array of current topics
	 * @return array $topics
	 */
	public function add_webhook_topic( $topics )
	{
		// using WooCommerce Subscriptions
		if ( function_exists('\wcs_get_subscription') )
		{
			$topics['action.wc_eacswregistry_subscription'] = __('{eac}SoftwareRegistry WC Subscription','eacsoftwareregistry-subscription-webhooks');
		}
		// Using SUMO Subscriptions
		if ( class_exists('\SUMOSubscriptions',false) )
		{
			$topics['action.wc_eacswregistry_sumosub'] = __('{eac}SoftwareRegistry Sumo Subscription','eacsoftwareregistry-subscription-webhooks');
		}
		return $topics;
	}


	/**
	 * subscription updated (wc)
	 *
	 * triggered by woocommerce_subscription_status_updated
	 * triggers custom webhook wc_eacswregistry_subscription
	 *
	 * @param object $subscription WC_Subscription
	 * @param string $newStatus new status
	 * @param string $oldStatus old status
	 * @return void
	 */
	public function subscription_updated_wc( $subscription, $newStatus=null, $oldStatus=null )
	{
		$this->logDebug($subscription,__METHOD__.' '.current_action());
		do_action('wc_eacswregistry_subscription', [$subscription->get_id(),current_action(),0] );
	}


	/**
	 * subscription updated (sumo)
	 *
	 * triggered by ***
	 * triggers custom webhook wc_eacswregistry_sumosub
	 *
	 * @param string $sub_id subscription post id
	 * @return void
	 */
	public function subscription_updated_sumo( $sub_id, $switched=0 )
	{
		$this->logDebug($sub_id,__METHOD__.' '.current_action());
		do_action('wc_eacswregistry_sumosub', [$sub_id,current_action(),$switched] );
	}


	/**
	 * Get the webhook response
	 *
	 * @param array $http_args
	 * @param array $response
	 * @param float $duration
	 * @param string $post_id
	 * @param int $webhook_id
	 */
	public function get_webhook_response( $http_args, $response, $duration, $post_id, $webhook_id )
	{
		$this->logDebug([$response['response'],json_decode($response['body'])],__METHOD__.' '.current_action());
	}


	/**
	 * Get the payload data for our webhook
	 *
	 * WebHook topic header = 'X-Wc-Webhook-Topic: action.wc_eacswregistry_subscription',
	 * WebHook topic header = 'X-Wc-Webhook-Topic: action.wc_eacswregistry_sumosub',
	 *
	 * @param array $payload ['action'=>,'arg'=>]
	 * @param string $resource 'action', 'order'
	 * @param int $resource_id subscription or order id
	 * @param int $webhook_id
	 * @return array $payload
	 */
	public function get_webhook_payload( $payload, $resource, $resource_id, $webhook_id )
	{
		$webhook = wc_get_webhook( $webhook_id );

		// switch to the user who created the webhook
		$current_user = get_current_user_id();
		wp_set_current_user( $webhook->get_user_id() );

		$version = str_replace( 'wp_api_', '', $webhook->get_api_version() );

		/*
		 * custom subscription actions: wc_eacswregistry_subscription, wc_eacswregistry_sumosub
		 */
		if ( $resource == 'action' && is_array($resource_id) )
		{
			list ($resource_id,$current_action,$switched) = $resource_id;
			if ( $payload['action'] == 'wc_eacswregistry_subscription' )
			// wc subscription update - get subscription order and overlay additional subscription data
			{
				$subscription = new \WC_Subscription($resource_id);
				$payload = array_merge(
					$this->get_endpoint_data( $version, 'order', $resource_id ),
					$this->get_subscription_data_wc($subscription)
				);
				$payload['current_action'] 		= $current_action;
				$payload['related_orders'] 		= $this->get_related_orders_wc($subscription);
			}
			else if ( $payload['action'] == 'wc_eacswregistry_sumosub' )
			// sumo subscription update - get subscription order and overlay additional subscription data
			{
				$subscription 	= get_post($resource_id); //new \sumo_get_subscription($resource_id);
				// initial order (created subscription)
				$parent 		= get_post_meta( $subscription->ID, 'sumo_get_parent_order_id', true );
				// last renewal order (or parent)
				$renewal 		= get_post_meta( $subscription->ID, 'sumo_get_renewal_id', true ) ?: $parent;
				// endpoint renewal order
				$order = $this->get_endpoint_data( $version, 'order', $renewal );
				if (empty($order['parent_id'])) $order['parent_id'] = $order['id'];
				$payload = array_merge(
					$order,
					$this->get_subscription_data_sumo($subscription,$renewal)
				);
				// trigger update (renew/revise) by setting post_id
				if (!strpos($current_action,'_created')) {
					$payload['post_id'] 		= $order['id'];
				}
				$payload['current_action'] 		= $current_action;
				$payload['related_orders'] 		= $this->get_related_orders_sumo($subscription);
				// only pass switched if the subscription was switched to a new original order
				if ($switched) {
					$payload['related_orders'][$switched] = 'switch';
					$payload['switched_order'] 	= $switched;
				}
			}
		}
		/*
		 * Woo order hook(s): order.created, order.updated, order.deleted, order.restored
		 */
		else if ( $resource == 'order' && is_numeric($resource_id) )
		{
			$sub_options = \get_option('eacSoftwareRegistry_subscription_options_'.$webhook_id,[]);
			if (in_array('RENEWAL',$sub_options))			// append subscription to renewal order
			{
				if (function_exists('\wcs_order_contains_renewal') && \wcs_order_contains_renewal($resource_id))
				{
					$payload['subscriptions'] 	= $this->get_wc_subscriptions( $resource_id, 'RENEWAL' );
				}
				else if (function_exists('\sumosubs_is_renewal_order') && \sumosubs_is_renewal_order($resource_id))
				{
					$payload['post_id'] 		= $payload['id'];
					$payload['created_via'] 	= 'subscription';
					$payload['subscriptions'] 	= $this->get_sumo_subscriptions( $resource_id, 'RENEWAL');
				}
			}
			else if (in_array('NEWORDER',$sub_options))		// append subscription to new order
			{
				if (function_exists('\wcs_order_contains_subscription') && \wcs_order_contains_subscription($resource_id))
				{
					$payload['subscriptions'] 	= $this->get_wc_subscriptions( $resource_id, 'NEWORDER' );
				}
				else if (function_exists('\sumo_order_contains_subscription') && \sumo_order_contains_subscription($resource_id))
				{
					unset($payload['post_id']);
					$payload['subscriptions'] 	= $this->get_sumo_subscriptions( $resource_id, 'NEWORDER');
				}
			}
			if (in_array('METADATA',$sub_options))			// append order productt(s) meta-data
			{
				$payload['product_meta']		= $this->get_product_meta(wc_get_order($resource_id));
			}
		}

		// Restore the current user.
		wp_set_current_user( $current_user );

		$this->logDebug(
			[
				'resource' 		=> $resource,
				'resource id' 	=> $resource_id,
				'Webhook id' 	=> $webhook_id,
			//	'payload' 		=> $payload
			],
			__METHOD__.' '.$resource.' '.current_action());
		return $payload;
	}


	/**
	 * Get payload data (support legacy api)
	 *
	 * @param string $version API version
	 * @param string $resource 'order'
	 * @param string $resource_id order id
	 * @return array endpoint version of order record
	 */
	private function get_endpoint_data( $version, $resource, $resource_id )
	{
		$RestApiUtil = "\Automattic\WooCommerce\Utilities\RestApiUtil";
		if (class_exists($RestApiUtil))
		{
			try {
				$RestApiUtil = new $RestApiUtil();
				return $RestApiUtil->get_endpoint_data("/wc/{$version}/{$resource}s/{$resource_id}" );
			} catch (\Throwable $e) {$this->logDebug($e);}
		}

		if ( ! is_null( wc()->api ) )		// legacy api
		{
			try {
				return wc()->api->get_endpoint_data( "/wc/{$version}/{$resource}s/{$resource_id}" );
			} catch (\Throwable $e) {$this->logDebug($e);}
		}
	}


	/**
	 * Get subscription records  (wc)
	 *
	 * @param array $resource_id order id
	 * @param string $type NEWORDER | RENEWAL
	 * @return array subscription records array
	 */
	private function get_wc_subscriptions( $resource_id, $type )
	{
		$subs = ($type == 'NEWORDER')
			? \wcs_get_subscriptions_for_order($resource_id)
			: \wcs_get_subscriptions_for_renewal_order($resource_id);
		$payload = array();
		foreach ($subs as $sub)
		{
			$payload[ $sub->get_id() ] = array_merge(
				$this->get_endpoint_data( $version, 'order', $sub->get_id() ),
				$this->get_subscription_data_wc($sub)
			);
		}
		return $payload;
	}


	/**
	 * Get subscription records  (sumo)
	 *
	 * @param array $resource_id order id
	 * @param string $type NEWORDER | RENEWAL
	 * @return array subscription records array
	 */
	private function get_sumo_subscriptions( $resource_id, $type )
	{
		$order 		= wc_get_order($resource_id);
		$parent 	= $order->get_parent_id();
		$parent 	= ($parent) ? wc_get_order($parent) : $order;
		$payload 	= array();
		$related 	= $parent->get_meta('sumo_subsc_get_available_postids_from_parent_order');
		if (!empty($related))
		{
			foreach ($related as $id) {
				$sub = get_post($id);
				$payload[$id] = array_merge(
					(array)$sub,
					$this->get_subscription_data_sumo($sub,$order)
				);
			}
		}
		return $payload;
	}


	/**
	 * Get subscription data to add to the payload (wc)
	 *
	 * @param array $subscription WC_Subscription
	 * @return array subscription data array
	 */
	private function get_subscription_data_wc( $subscription )
	{
		return	[	// dates are UTC/GMT
					'status'					=> strtolower($subscription->get_status()),
					'date_created'				=> $this->dateFormat( $subscription->get_date('date_created') ),
					'date_modified'				=> $this->dateFormat( $subscription->get_date('date_modified') ),
					'date_paid'					=> $this->dateFormat( $subscription->get_date('date_paid') ),
					'date_completed'			=> $this->dateFormat( $subscription->get_date('date_completed') ),
					'last_order_id'				=> $subscription->get_last_order(),
					'last_order_date_created'	=> $this->dateFormat( $subscription->get_date('last_order_date_created') ),
					'last_order_date_paid'		=> $this->dateFormat( $subscription->get_date('last_order_date_paid') ),
					'last_order_date_completed'	=> $this->dateFormat( $subscription->get_date('last_order_date_completed') ),
					'schedule_trial_end' 		=> $this->dateFormat( $subscription->get_date('schedule_trial_end') ),
					'schedule_start' 			=> $this->dateFormat( $subscription->get_date('schedule_start') ),
					'schedule_end' 				=> $this->dateFormat( $subscription->get_date('schedule_end') ),
					'schedule_cancelled' 		=> $this->dateFormat( $subscription->get_date('schedule_cancelled') ),
					'schedule_next_payment' 	=> $this->dateFormat( $subscription->get_date('schedule_next_payment') ),
					'schedule_payment_retry' 	=> $this->dateFormat( $subscription->get_date('schedule_payment_retry') ),
					'billing_period'			=> $subscription->get_billing_period(),
					'billing_interval'			=> $subscription->get_billing_interval(),
					'sign_up_fee'				=> $subscription->get_sign_up_fee(),
					'product_meta'				=> $this->get_product_meta($subscription),
		];
	}


	/**
	 * Get subscription data to add to the payload (sumo)
	 *
	 * @param array $subscription SUMO subscription
	 * @param array $order WC order
	 * @return array subscription data array
	 */
	private function get_subscription_data_sumo( $subscription, $order )
	{
		$order 		= wc_get_order($order);
		$parent 	= $order->get_parent_id();
		$parent 	= ($parent) ? wc_get_order($parent) : $order;
		$plan 		= \sumo_get_subscription_plan($subscription->ID);
		$period 	= $plan['subscription_duration'];
		switch ($period) {
			case 'D':
				$period = 'day';
				break;
			case 'W':
				$period = 'week';
				break;
			case 'M':
				$period = 'month';
				break;
			case 'Y':
				$period = 'year';
				break;
		}
		$interval 	= $plan['subscription_duration_value'];


		$next 		= get_post_meta( $subscription->ID,'sumo_get_next_payment_date',true);
		$end 		= get_post_meta( $subscription->ID,'sumo_get_sub_end_date',true) ?: $next;
		$start 		= get_post_meta( $subscription->ID,'sumo_get_sub_start_date',true);
		if (empty($start)) {
			try {
				$start 	= new \DateTimeImmutable($end);
				$start 	= $start->modify("-{$interval} {$period}")->format('Y-m-d\TH:i:s');
			} catch (\Throwable $e) {$end = $start = '';} // when paused
		}
		$status 	= strtolower(get_post_meta( $subscription->ID,'sumo_get_status',true));
		$cancelled	= ($status == 'cancelled') ? $end : '';
		return	[	// dates are UTC/GMT
					'id'						=> $subscription->ID, // override order id
					'status'					=> $status,
					'date_created'				=> $this->dateFormat( $parent->get_date_created() ),
					'date_modified'				=> $this->dateFormat( $order->get_date_modified() ),
					'date_paid'					=> $this->dateFormat( get_post_meta( $subscription->ID,'sumo_get_last_payment_date',true) ),
					'date_completed'			=> $this->dateFormat( $order->get_date_completed() ),
					'last_order_id'				=> $order->get_id(),
					'last_order_date_created'	=> $this->dateFormat( $order->get_date_created() ),
					'last_order_date_paid'		=> $this->dateFormat( $order->get_date_paid() ),
					'last_order_date_completed'	=> $this->dateFormat( $order->get_date_completed() ),
					'schedule_trial_end' 		=> $this->dateFormat( get_post_meta( $subscription->ID,'sumo_get_trial_end_date',true) ),
					'schedule_start' 			=> $this->dateFormat( $start ),
					'schedule_end' 				=> $this->dateFormat( $end ),
					'schedule_cancelled' 		=> $this->dateFormat( $cancelled ),
					'schedule_next_payment' 	=> $this->dateFormat( $next ),
					//	'schedule_payment_retry' 	=> $this->dateFormat( get_post_meta( $subscription->ID,'schedule_payment_retry') ),
					'billing_period'			=> $period,
					'billing_interval'			=> $interval,
					'sign_up_fee'				=> $plan['signup_fee'],
					'product_meta'				=> $this->get_product_meta($order,[ $plan['subscription_product_id'] ]),
		];
	}


	/**
	 * Get product meta data
	 *
	 * @param object $order WC_Subscription or WC_Order
	 * @param object $includeonly these item(s)
	 * @return array [id => type]
	 */
	private function get_product_meta( $order, $include=null )
	{
		$result = array();
		$items = $order->get_items();
		foreach ($items as $item)
		{
			if (is_array($include))
			{
				if (!in_array($item->get_product_id(),$include) && !in_array($item->get_variation_id(),$include)) continue;
			}

			$meta_data = $attributes = $categories = array();

			$product 	= wc_get_product( $item->get_product_id() );
			$product_id = $product->get_id();

			foreach ($product->get_meta_data() as $meta) {
				$meta_data[ $meta->key ] = $meta->value;
			}

			$attributes	= $product->get_attributes();

			if ($categories = get_the_terms( $product_id, 'product_cat' )) {
				$categories = array_combine(wp_list_pluck( $categories, 'slug' ),wp_list_pluck( $categories, 'name' ));
			}

			if ($variation_id = $item->get_variation_id())
			{
				$product = wc_get_product( $variation_id );
				foreach ($product->get_meta_data() as $meta) {
					if (!empty($meta->value)) {
						$meta_data[ $meta->key ] = $meta->value;
					}
				}

				$attributes	= array_merge($attributes,$product->get_attributes());

				if ($varcat = get_the_terms( $variation_id, 'product_cat' )) {
					$varcat = array_combine(wp_list_pluck( $varcat, 'slug' ),wp_list_pluck( $varcat, 'name' ));
					$categories = array_merge($categories,$varcat);
				}
			}

			// strip "pa_" prefix (product attribute)
			$_attributes = [];
			foreach ($attributes as $key => $value) {
				if (is_a($value,'WC_Product_Attribute')) {
					$value = $value->get_options()[0] ?? null;
				}
				$_attributes[ preg_replace('/^pa_(.+)/','$1',$key) ] = $value;
			}

			$result[ $product_id ] = array(
				'id'		=> $product_id,
				'name'		=> $product->get_name(),
				'slug'		=> $product->get_slug(),
				'sku'		=> $product->get_sku(),
				'attributes'=> $_attributes,
				'meta_data' => array_filter($meta_data, function($key){return ($key[0] != '_');}, ARRAY_FILTER_USE_KEY),
				'categories'=> $categories,
			);
		}

		return $result;
	}


	/**
	 * Get related orders and order type (wc)
	 *
	 * @param array $subscription WC_Subscription
	 * @return array [id => type]
	 */
	private function get_related_orders_wc( $subscription )
	{
		$relatedOrders = [];
		foreach ( ['parent','renewal','resubscribe','switch'] as $type )
		{
			foreach ($subscription->get_related_orders('ids',$type) as $id)
			{
				$relatedOrders[$id] = $type;
			}
		}
		krsort($relatedOrders,SORT_NUMERIC);
		return $relatedOrders;
	}


	/**
	 * Get related orders and order type (sumo)
	 *
	 * @param array $subscription sumo subscription
	 * @return array [id => type]
	 */
	private function get_related_orders_sumo( $subscription )
	{
		$relatedOrders 	= get_post_meta( $subscription->ID, 'sumo_get_every_renewal_ids', true );
		$relatedOrders 	= (empty($relatedOrders)) ? [] : array_fill_keys(maybe_unserialize($relatedOrders), 'renewal');
		$relatedOrders[ get_post_meta( $subscription->ID, 'sumo_get_parent_order_id', true ) ] = 'parent';

		krsort($relatedOrders,SORT_NUMERIC);
		return $relatedOrders;
	}


	/**
	 * Format date to ISO 8601 'Y-m-d\TH:i:s'
	 *
	 * @param string $date
	 * @return string 'Y-m-d\TH:i:s'
	 */
	private function dateFormat( $date )
	{
		if (empty($date)) return $date;

		if (is_a($date,'WC_DateTime'))
		{
			$date->setTimezone(new \DateTimeZone('UTC'));
			return $date->date('Y-m-d\TH:i:s');
		}

		try {
			$date 	= new \DateTimeImmutable($date);
			$date 	= $date->format('Y-m-d\TH:i:s');
		} catch (\Throwable $e) {$date = '';}

		return $date;
	}


	/**
	 * logging via eacDoojigger
	 *
	 * @param mixed $data
	 * @param string $label
	 */
	private function logDebug( $data, $label=null )
	{
		if ($this->debugging_enabled)
		{
			do_action('eacDoojigger_log_debug',$data,$label);
		}
	}
}
new \EarthAsylumConsulting\eacSoftwareRegistry_Subscription_Webhooks();
?>
