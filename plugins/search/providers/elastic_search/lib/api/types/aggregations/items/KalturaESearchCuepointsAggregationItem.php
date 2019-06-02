<?php
/**
 * @package plugins.elasticSearch
 * @subpackage api.objects
 */

class KalturaESearchCuepointsAggregationItem extends KalturaESearchAggregationItem
{
	/**
	 *  @var KalturaESearchCuePointAggregateByFieldName
	 */
	public $fieldName;

	public function toObject($object_to_fill = null, $props_to_skip = array())
	{
		if (!$object_to_fill)
			$object_to_fill = new ESearchCuepointsAggregationItem();
		return parent::toObject($object_to_fill, $props_to_skip);
	}

	public function getFieldEnumMap()
	{
		return array(
			KalturaESearchCuePointAggregateByFieldName::TAGS => ESearchCuePointsAggregationFieldName::TAGS,
			KalturaESearchCuePointAggregateByFieldName::TYPE => ESearchCuePointsAggregationFieldName::TYPE);
	}

	public function coreToApiResponse($aggregation)
	{

	}

}