<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaLiveToVODJobData extends KalturaJobData
{
	/**
	 * $vod Entry Id
	 * @var string
	 */
	public $vodEntryId;

	/**
	 * live Entry Id
	 * @var string
	 */
	public $liveEntryId;

	/**
	 * total VOD Duration
	 * @var float
	 */
	public $totalVODDuration;

	/** 
	 * last Segment Duration
	 * @var float
	 */
	public $lastSegmentDuration;
	/**
	 * amf Array File Path
	 * @var string
	 */
	public $amfArray;


	private static $map_between_objects = array
	(
		'vodEntryId',
		'liveEntryId',
		'totalVODDuration',
		'lastSegmentDuration',
		'amfArray',
	);

	/* (non-PHPdoc)
	 * @see KalturaObject::getMapBetweenObjects()
	 */
	public function getMapBetweenObjects ( )
	{
		return array_merge ( parent::getMapBetweenObjects() , self::$map_between_objects );
	}
	
	/* (non-PHPdoc)
	 * @see KalturaObject::toObject()
	 */
	public function toObject($dbData = null, $props_to_skip = array()) 
	{
		if(is_null($dbData))
			$dbData = new kLiveToVODJobData();
			
		return parent::toObject($dbData, $props_to_skip);
	}
}
