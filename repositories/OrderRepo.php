<?php

namespace Kika\Repositories;

use WC_Order;
use WP_Query;


class OrderRepo
{

	const STATUS_KEY = 'fhb-api-status';
	const EXPORT_KEY = 'fhb-api-export';
	const EXPORT_ID_KEY = 'fhb-api-id';
	const TOKEN_KEY = 'fhb-api-token';
	const STATUS_SYNCED = 'synced';
	const STATUS_ERROR = 'error';


	public function fetch($args)
	{
		$loop = new WP_Query($args);
		$data = [];

		while ($loop->have_posts()) {
			$loop->the_post();
			$order = new WC_Order(get_the_ID());
			$data[] = $this->prepareData($order);
		};

		wp_reset_postdata();
		return $data;
	}


	public function fetchById($id)
	{
		$order = wc_get_order($id);
		return $this->prepareData($order);
	}


	public function fetchForExport($export, $limit = 5)
	{
		$args = [
			'post_type'   => 'shop_order',
			'posts_per_page' => $limit,
			'post_status' => ['wc-pending', 'wc-processing'],

			'meta_query' => [
				'relation' => 'AND',

				[
					'relation' => 'OR',
					[
						'key' => self::STATUS_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => self::STATUS_KEY,
						'compare' => '!=',
						'value' => self::STATUS_SYNCED,
					],
				],

				[
					'relation' => 'OR',
					[
						'key' => self::EXPORT_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => self::EXPORT_KEY,
						'compare' => '!=',
						'value' => $export,
					],
				],
			]
		];

		return $this->fetch($args);
	}


	public function count()
	{
		$args = [
			'post_type' => 'shop_order',
			'post_status' => ['wc-pending', 'wc-processing'],
		];

		$loop = new WP_Query($args);
		return $loop->found_posts;
	}


	public function countByStatus($status)
	{
		$args = [
			'post_type' => 'shop_order',
			'post_status' => ['wc-pending', 'wc-processing'],

			'meta_query' => [
				[
					[
						'key' => self::STATUS_KEY,
						'value' => $status,
					],
				],
			]
		];

		$loop = new WP_Query($args);
		return $loop->found_posts;
	}


	public function countSynced()
	{
		return $this->countByStatus(self::STATUS_SYNCED);
	}


	public function countError()
	{
		return $this->countByStatus(self::STATUS_ERROR);
	}


	public function prepareData(WC_Order $order)
	{

		$name = ($order->shipping_company) ? $order->shipping_company : $order->shipping_first_name . ' ' . $order->shipping_last_name;
		$street = $order->shipping_address_1;
		$street.= $order->shipping_address_2 ? ', ' . $order->shipping_address_2 : '';
		$street.= $order->shipping_state ? ', ' . $order->shipping_state : '';

		$data = [
			'id' => $order->id,
			'variableSymbol' => $order->id,
			'name' => $name,
			'email' => $order->billing_email,
			'street' => $street,
			'country' => mb_strtolower($order->shipping_country),
			'city' => $order->shipping_city,
			'psc' => $order->shipping_postcode,
			'phone' => $order->billing_phone ? $order->billing_phone : null,
			'invoiceLink' => '',
			'cod' => get_option('kika_method_' . $order->payment_method) ? $order->get_total() : 0,
			'parcelService' => get_option('kika_service', null),
		];

		$items = $order->get_items();
		foreach ($items as $item_id => $item) {
			$product = $order->get_product_from_item($item);
			$data['_embedded']['items'][] = [
				'id' => $product ? $product->get_sku() : null,
				'qty' => $item['qty'],
			];
		}

		return $data;
	}

}