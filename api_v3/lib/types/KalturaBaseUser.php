<?php
/*
* @package api
* @subpackage objects
*/

class KalturaBaseUser extends KalturaObject implements IRelatedFilterable
{
	const MAX_NAME_LEN = 40;

	/**
	 * @var string
	 * @filter order
	 */
	public $id;

	/**
	 * @var int
	 * @readonly
	 * @filter eq
	 */
	public $partnerId;

	/**
	 * @var string
	 * @filter like,likex
	 */
	public $screenName;

	/**
	 * @var string
	 * @deprecated
	 */
	public $fullName;

	/**
	 * @var string
	 * @filter like,likex
	 */
	public $email;

	/**
	 * @var string
	 */
	public $country;

	/**
	 * @var string
	 */
	public $state;

	/**
	 * @var string
	 */
	public $city;

	/**
	 * @var string
	 */
	public $zip;

	/**
	 * @var string
	 */
	public $thumbnailUrl;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * @var string
	 * @filter mlikeor,mlikeand
	 */
	public $tags;

	/**
	 * Admin tags can be updated only by using an admin session
	 * @deprecated Use "tags" field instead.
	 * @var string
	 */
	public $adminTags;

	/**
	 * @var KalturaUserStatus
	 * @filter eq,in
	 */
	public $status;

	/**
	 * Creation date as Unix timestamp (In seconds)
	 * @var time
	 * @readonly
	 * @filter gte,lte,order
	 */
	public $createdAt;

	/**
	 * Last update date as Unix timestamp (In seconds)
	 * @var time
	 * @readonly
	 */
	public $updatedAt;

	/**
	 * Can be used to store various partner related data as a string
	 * @var string
	 */
	public $partnerData;

	/**
	 * @var int
	 */
	public $indexedPartnerDataInt;

	/**
	 * @var string
	 */
	public $indexedPartnerDataString;

	/**
	 * @var int
	 * @readonly
	 */
	public $storageSize;


	/**
	 * @var KalturaLanguageCode
	 */
	public $language;

	/**
	 * @var int
	 * @readonly
	 */
	public $lastLoginTime;

	/**
	 *
	 * @var int
	 * @readonly
	 */
	public $statusUpdatedAt;

	/**
	 *
	 * @var time
	 * @readonly
	 */
	public $deletedAt;


	/**
	 * @var string
	 */
	public $allowedPartnerIds;

	/**
	 * @var string
	 */
	public $allowedPartnerPackages;

	/**
	 * @var KalturaUserMode
	 */
	public $userMode;

	private static $map_between_objects = array
	(
		"id" => "puserId",
		"partnerId",
		"screenName",
		"email",
		"country",
		"state",
		"city",
		"zip",
		"thumbnailUrl" => "picture",
		"description" => "aboutMe",
		"tags",
		"status",
		"createdAt",
		"updatedAt",
		"partnerData",
		"storageSize",
		"language",
		"lastLoginTime",
		"deletedAt",
		"allowedPartnerIds" => "allowedPartners",
		"allowedPartnerPackages",
		"statusUpdatedAt",
		"userMode",
	);

	public function getMapBetweenObjects ( )
	{
		return array_merge ( parent::getMapBetweenObjects() , self::$map_between_objects );
	}


	public function getExtraFilters()
	{
		return array();
	}

	public function getFilterDocs()
	{
		return array();
	}

	public function toInsertableObject($object_to_fill = null, $props_to_skip = array())
	{
		$this->verifyMaxLength();
		return parent::toInsertableObject($object_to_fill, $props_to_skip);
	}

	public function toUpdatableObject($object_to_fill, $props_to_skip = array())
	{
		$this->verifyMaxLength();
		return parent::toUpdatableObject($object_to_fill, $props_to_skip);
	}

	private function verifyMaxLength()
	{
		if (strlen($this->firstName) > self::MAX_NAME_LEN)
			$this->firstName = kString::alignUtf8String($this->firstName, self::MAX_NAME_LEN);
		if (strlen($this->lastName) > self::MAX_NAME_LEN)
			$this->lastName = kString::alignUtf8String($this->lastName, self::MAX_NAME_LEN);
		if (strlen($this->fullName) > self::MAX_NAME_LEN)
			$this->fullName = kString::alignUtf8String($this->fullName, self::MAX_NAME_LEN);
	}
}