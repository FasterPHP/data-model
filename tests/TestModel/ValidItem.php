<?php
/**
 * Test Item class.
 */
namespace FasterPhp\DataModel\TestModel;

use Laminas\Validator;
use FasterPhp\DataModel\Item as BaseItem;
use FasterPhp\DataModel\Field;

/**
 * Test Item class.
 */
class ValidItem extends BaseItem
{
	public const ID_FIELD = 'userId';

	public const FIELDS = [
		'id' => Field\Integer::class,
		'name' => Field\Varchar::class,
		'age' => Field\Integer::class,
		'height' => Field\Double::class,
		'handsome' => Field\Boolean::class,
	];

	public const DEFAULTS = [
		'name' => '',
		'age' => 0,
		'height' => 0.0,
		'handsome' => false,
	];

	public const VALIDATORS = [
		'name' => [
			[
				'class' => Validator\StringLength::class,
				'options' => [
					'min' => 2,
					'max' => 60,
					'message' => 'Name must be between 2 and 60 characters',
				],
				'break' => false,
				'priority' => 1,
			],
			[
				'class' => Validator\Regex::class,
				'options' => [
					'pattern' => '/[^0-9]/',
					'message' => 'Name cannot contain numbers',
				],
			]
		],
		'age' => [
			[
				'class' => Validator\GreaterThan::class,
				'options' => [
					'min' => 18,
					'inclusive' => true,
					'messages' => [
						Validator\GreaterThan::NOT_GREATER => 'You must be over 17 to use this app',
						Validator\GreaterThan::NOT_GREATER_INCLUSIVE => 'You must be at least 18 to use this app',
					],
				],
			],
		],
	];
}
