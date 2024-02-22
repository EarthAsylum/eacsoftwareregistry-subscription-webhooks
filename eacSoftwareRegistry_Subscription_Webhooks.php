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
 * @copyright	Copyright (c) 2022 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 *
 * @wordpress-plugin
 * Plugin Name:				{eac}SoftwareRegistry Subscription WebHooks
 * Description:				Software Registration Server Subscription Webhooks for WooCommerce - adds a custom Webhook topic for subscription updates to WooCommerce Webhooks.
 * Version:					1.0.9
 * Requires at least:		5.5.0
 * Tested up to:			6.4
 * Requires PHP:			7.2
 * WC requires at least: 	5.2
 * WC tested up to: 		8.2
 * Plugin URI:        		https://swregistry.earthasylum.com/subscriptions-for-woocommerce/
 * Author:					EarthAsylum Consulting
 * Author URI:				http://www.earthasylum.com
 * License: 				GPLv3 or later
 * License URI: 			https://www.gnu.org/licenses/gpl.html
 * Text Domain:				eacSoftwareRegistry
 * Domain Path:				/languages
 */

namespace EarthAsylumConsulting;

class eacSoftwareRegistry_Subscription_Webhooks
{
	/**
	 * constructor method
	 *
	 * Add filters and actions for our custom webhook
	 *
	 * @return	void
	 */
	public function __construct()
	{
		if (is_admin())
		{
		 	// on plugin_action_links_ filter, add 'Settings' link
	 		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
			 													array( $this, 'plugin_action_links' ), 20, 3 );

			// add our order webhook options
			add_action( 'woocommerce_webhook_options',			array( $this, 'add_webhook_options' ));

			// save our order webhook options
			add_action( 'woocommerce_webhook_options_save',		array( $this, 'save_webhook_options' ));
		}

		// add our custom webhook topic
		add_filter( 'woocommerce_webhook_topics',				array( $this, 'add_webhook_topic' ), 10, 1 );

		// trigger our webhook from subscription hook(s)
		add_action( 'woocommerce_subscription_status_updated',	array( $this, 'subscription_updated' ), 10, 3 );

		// filter the webhook payload for ours and for order webhooks
		add_filter( 'woocommerce_webhook_payload',				array( $this, 'get_webhook_payload' ), 10, 4 );

		// get webhook delivery response
		// $http_args, $response, $duration, $arg, $this->get_id() );
		//add_action( 'woocommerce_webhook_delivery', 			array( $this, 'get_webhook_response' ), 10, 5 );
	}

	/**
	 * on plugin_action_links_ filter, add 'Settings' & 'Support' link
	 *
	 * @param  array $pluginLinks Default links
	 * @return array with added link
	 */
	public function plugin_action_links( $pluginLinks, $pluginFile, $pluginData )
	{
		if (empty($pluginData)) return $pluginLinks;

		$query = array(
			'page'    => 'wc-settings',
			'tab'     => 'advanced',
			'section' => 'webhooks',
		);
		$settings_url = sprintf(
				'<a href="%s" title="%s">%s</a>',
				 esc_url( add_query_arg( $query,self_admin_url( 'admin.php' ) ) ),
				 esc_attr( sprintf( __( "%s Webhooks", 'eacSoftwareRegistry' ),'WooCommerce' ) ),
				 __( 'Settings', 'eacSoftwareRegistry' )
        );
		$website_url = sprintf(
				'<a href="%s" title="%s">%s</a>',
		 		esc_url( "https://swregistry.earthasylum.com/{$pluginData['slug']}" ),
		 		esc_attr( sprintf( __( "%s Documentation", 'eacSoftwareRegistry' ), $pluginData['Name'] ) ),
		 		__( 'Docs', 'eacSoftwareRegistry' )
		);
		$support_url = sprintf(
				'<a href="%s" title="%s">%s</a>',
				 esc_url( "https://wordpress.org/support/plugin/{$pluginData['slug']}" ),
				 esc_attr( sprintf( __( "%s Support", 'eacSoftwareRegistry' ), $pluginData['Name'] ) ),
				 __( 'Support', 'eacSoftwareRegistry' )
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
				__('Append related subscriptions to orders','eacSoftwareRegistry'),
				in_array('NEWORDER',$sub_options) ? ' checked=checked' : '',
			],
			[
				'RENEWAL',
				__('Append related subscriptions to renewals','eacSoftwareRegistry'),
				in_array('RENEWAL',$sub_options) ? ' checked=checked' : '',
			],
			[
				'METADATA',
				__('Append product meta data to all orders','eacSoftwareRegistry'),
				in_array('METADATA',$sub_options) ? ' checked=checked' : '',
			],
		]
		?>
		<div id="webhook-subscription-options" style="display: none;"">
		<h4><?php esc_html_e( '{eac}SoftwareRegistry Subscription WebHooks', 'woocommerce' ); ?></h4>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="eacSoftwareRegistry_subscription_options">
							<?php esc_html_e( 'Order Options', 'eacSoftwareRegistry' ); ?>
							<?php echo wc_help_tip( __( 'Append subscription record(s) to orders with related subscriptions. Append custom fields and attributes from products ordered to order records;', 'eacSoftwareRegistry' ) ); ?>
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
					var current            = $( this ).val(),
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
	 * Add webhook topic to drop-down list
	 *
	 * @param array $topics array of current topics
	 * @return array $topics
	 */
	public function add_webhook_topic( $topics )
	{
		if ( function_exists('\wcs_get_subscription') )
		{
			$topics['action.wc_eacswregistry_subscription'] = '{eac}SoftwareRegistry Subscription updated';
		}
		return $topics;
	}


	/**
	 * subscription updated
	 *
	 * triggered by woocommerce_subscription_status_updated
	 * triggers custom webhook wc_eacswregistry_subscription
	 *
	 * @param object $subscription WC_Subscription
	 * @param string $newStatus new status
	 * @param string $oldStatus old status
	 * @return void
	 */
	public function subscription_updated( $subscription, $newStatus=null, $oldStatus=null )
	{
		do_action('wc_eacswregistry_subscription', $subscription->get_id() );
	}


	/**
	 * Get the payload data for our webhook
	 *
	 * WebHook topic header = 'X-Wc-Webhook-Topic: action.wc_eacswregistry_subscription',
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

		$sub_options = \get_option('eacSoftwareRegistry_subscription_options_'.$webhook_id,[]);

		$version = str_replace( 'wp_api_', '', $webhook->get_api_version() );

		if ( isset( $payload['action'] ) && $payload['action'] == 'wc_eacswregistry_subscription' && is_numeric($resource_id) )
		// subscription update - get subscription order and overlay additional subscription data
		{
			$subscription = new \WC_Subscription($resource_id);
			$payload = array_merge(
				wc()->api->get_endpoint_data( "/wc/{$version}/orders/{$resource_id}" ),
				$this->get_subscription_data($subscription)
			);
			$payload['related_orders'] 		= $this->get_related_orders($subscription);

		}
		else if ( $resource == 'order' && is_numeric($resource_id) )
		// order update - get the order and append subscription(s)
		{
			if (in_array('NEWORDER',$sub_options) && wcs_order_contains_subscription($resource_id))
			{
				$subscriptions = wcs_get_subscriptions_for_order($resource_id);
			}
			else if (in_array('RENEWAL',$sub_options) && wcs_order_contains_renewal($resource_id))
			{
				$subscriptions = wcs_get_subscriptions_for_renewal_order($resource_id);
			}
			if (isset($subscriptions))
			{
				$payload['subscriptions'] = array();
				foreach ($subscriptions as $sub)
				{
					$subscription = $this->get_subscription_data($sub);
					$payload['subscriptions'][ $sub->get_id() ] = array_merge(
						wc()->api->get_endpoint_data( "/wc/{$version}/orders/{$sub->get_id()}" ),
						$subscription
					);
				}
			}
			if (in_array('METADATA',$sub_options))
			{
				$payload['product_meta']		= $this->get_product_meta(new \WC_Order($resource_id));
			}
		}

		// Restore the current user.
		wp_set_current_user( $current_user );

		return $payload;
	}


	/**
	 * Get subscription data to add to the payload
	 *
	 * @param array $subscription WC_Subscription
	 * @return array subscription data array
	 */
	private function get_subscription_data( $subscription )
	{
		return	[	// dates are UTC/GMT
				//	'created_via' 				=> 'subscription',
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
	 * Get product meta data
	 *
	 * @param object $order WC_Subscription or WC_Order
	 * @return array [id => type]
	 */
	private function get_product_meta( $order )
	{
		$result = array();
		$items = $order->get_items();
		foreach ($items as $item)
		{
			$meta_data = $attributes = array();

			$product = wc_get_product( $item->get_product_id() );

			foreach ($product->get_meta_data() as $meta) {
				$meta_data[ $meta->key ] = $meta->value;
			}

			$attributes	= $product->get_attributes();

			if ($item->get_variation_id())
			{
				$product = wc_get_product( $item->get_variation_id() );
				foreach ($product->get_meta_data() as $meta) {
					if (!empty($meta->value)) {
						$meta_data[ $meta->key ] = $meta->value;
					}
				}

				$attributes	= array_merge($attributes,$product->get_attributes());
			}

			$product_id 	= $product->get_id();

			// strip "pa_" prefix (product attribute)
			$_attributes = [];
			foreach ($attributes as $key => $value) {
				$_attributes[ preg_replace('/^pa_(.+)/','$1',$key) ] = $value;
			}

			$result[ $product_id ] = array(
				'id'		=> $product_id,
				'name'		=> $product->get_name(),
				'slug'		=> $product->get_slug(),
				'sku'		=> $product->get_sku(),
				'attributes'=> $_attributes,
				'meta_data' => array_filter($meta_data, function($key){return ($key[0] != '_');}, ARRAY_FILTER_USE_KEY),
			);
		}

		return $result;
	}


	/**
	 * Get related orders and order type
	 *
	 * @param array $subscription WC_Subscription
	 * @return array [id => type]
	 */
	private function get_related_orders( $subscription )
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
	 * Format date to ISO 8601 'Y-m-d\TH:i:s'
	 *
	 * @param string $date
	 * @return string 'Y-m-d\TH:i:s'
	 */
	private function dateFormat( $date )
	{
		return (!empty($date)) ? date('Y-m-d\TH:i:s',strtotime($date)) : $date;
	}
}
new \EarthAsylumConsulting\eacSoftwareRegistry_Subscription_Webhooks();
?>
