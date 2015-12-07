<?php

class KAMFData
{
	public $pts;
	public $ts;

};

/**
 * @package plugins.cuePoint
 */
class kCuePointManager implements kBatchJobStatusEventConsumer, kObjectDeletedEventConsumer, kObjectChangedEventConsumer, kObjectAddedEventConsumer, kObjectReplacedEventConsumer
{
	const MAX_CUE_POINTS_TO_COPY_TO_VOD = 100;
	const MAX_CUE_POINTS_TO_COPY_TO_CLIP = 1000;
	const CUE_POINT_TIME_EPSILON = 1;

	/* (non-PHPdoc)
 	 * @see kBatchJobStatusEventConsumer::updatedJob()
 	 */
	public function updatedJob(BatchJob $dbBatchJob)
	{
		KalturaLog::debug('in kCuePointManager.updatedJob - jobID:' . $dbBatchJob->getId() . ' job type: ' . $dbBatchJob->getJobType()  . ' JobStatus= ' . $dbBatchJob->getStatus());

		if ($jobType = $dbBatchJob->getJobType() == BatchJobType::CONCAT){
			self::handleConcatJobFinished($dbBatchJob, $dbBatchJob->getData());
		}
		else if ($jobType = $dbBatchJob->getJobType() == BatchJobType::CONVERT_LIVE_SEGMENT) {
			self::handleConvertLiveSegmentJobFinished($dbBatchJob, $dbBatchJob->getData());
		}
		return true;
	}

	private function handleConvertLiveSegmentJobFinished(BatchJob $dbBatchJob, kConvertLiveSegmentJobData $data)
	{
		KalturaLog::debug('in kCuePointManager.handleConvertLiveSegmentJobFinished - file index is ' . $data->getFileIndex());
		if ($data->getFileIndex() == 0) {
			KalturaLog::debug('in kCuePointManager.handleConvertLiveSegmentJobFinished - running KMediaInfoMediaParser');
			$mediaInfoParser = new KMediaInfoMediaParser($data->getDestFilePath(), kConf::get('bin_path_mediainfo'));
			$recordedVODDurationInMS = $mediaInfoParser->getMediaInfo()->videoDuration;
			KalturaLog::debug('in kCuePointManager.handleConvertLiveSegmentJobFinished - running KMediaInfoMediaParser - finished ' .
				$dbBatchJob->getEntry()->getRecordedEntryId() . ' ' . $recordedVODDurationInMS . ' ' . $recordedVODDurationInMS . ' ' . print_r($data->getAMFs(), true));
			self::copyCuePointsFromLiveToVodEntry($dbBatchJob->getEntry()->getRecordedEntryId(), $recordedVODDurationInMS, $recordedVODDurationInMS, $data->getAMFs());
		}
	}

	private function handleConcatJobFinished(BatchJob $dbBatchJob, kConcatJobData $data)
	{
		KalturaLog::debug('in kCuePointManager.handleConcatJobFinished');

		$mediaInfoParser = new KMediaInfoMediaParser($data->getDestFilePath(), kConf::get('bin_path_mediainfo'));
		$recordedVODDurationInMS = $mediaInfoParser->getMediaInfo()->videoDuration;

		//kConvertLiveSegmentJobData tmp =
		$convertJobData = ($dbBatchJob->getParentJob()->getData());

		// get the duration of the new segment file
		$segmentFiles = $data->getSrcFiles();
		sort($segmentFiles);

		$mediaInfoParser2 = new KMediaInfoMediaParser($segmentFiles[$convertJobData->getFileIndex()], kConf::get('bin_path_mediainfo'));
		$lastSegmentDurationInMS = $mediaInfoParser2->getMediaInfo()->videoDuration;

		self::copyCuePointsFromLiveToVodEntry( $dbBatchJob->getParentJob()->getEntry()->getRecordedEntryId(), $recordedVODDurationInMS, $lastSegmentDurationInMS, $data->getAMFs());
	}

	/* (non-PHPdoc)
 	 * @see kBatchJobStatusEventConsumer::shouldConsumeJobStatusEvent()
 	 */
	public function shouldConsumeJobStatusEvent(BatchJob $dbBatchJob)
	{
		KalturaLog::debug('in kCuePointManager.shouldConsumeJobStatusEvent. JobType= ' . $dbBatchJob->getJobType() . ' JobStatus= ' . $dbBatchJob->getStatus());
		$jobType = $dbBatchJob->getJobType();
		if (($jobType == BatchJobType::CONVERT_LIVE_SEGMENT || $jobType == BatchJobType::CONCAT) && $dbBatchJob->getStatus() == BatchJob::BATCHJOB_STATUS_FINISHED){
			KalturaLog::debug('in kCuePointManager.shouldConsumeJobStatusEvent - returning true');
			return true;
		}
		KalturaLog::debug('in kCuePointManager.shouldConsumeJobStatusEvent - returning false');
		return false;
	}

	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::shouldConsumeAddedEvent()
	 */
	public function shouldConsumeAddedEvent(BaseObject $object)
	{
		if($object instanceof CuePoint)
			return true;
		return false;
	}

	/* (non-PHPdoc)
	 * @see kObjectDeletedEventConsumer::shouldConsumeDeletedEvent()
	 */
	public function shouldConsumeDeletedEvent(BaseObject $object)
	{
		if($object instanceof entry)
			return true;

		if($object instanceof CuePoint)
			return true;

		return false;
	}

	/* (non-PHPdoc)
	 * @see kObjectReplacedEventConsumer::shouldConsumeReplacedEvent()
	 */
	public function shouldConsumeReplacedEvent(BaseObject $object)
	{
		if($object instanceof entry) {
			return true;
		}
		return false;
	}

	/**
	 * Return a VOD entry (sourceType = RECORDED_LIVE) based on the flavorAsset that is
	 * associated with the given mediaInfo object
	 * @param mediaInfo $mediaInfo
	 * @return entry|null
	 */
	public static function getVodEntryBasedOnMediaInfoFlavorAsset( $mediaInfo )
	{
		if (! ($mediaInfo instanceof mediaInfo) )
		{
			return null;
		}
		$flavorAsset = $mediaInfo->getasset();
		if ( ! $flavorAsset || ! $flavorAsset->hasTag(assetParams::TAG_RECORDING_ANCHOR) )
		{
			return null;
		}
		$vodEntry = $flavorAsset->getentry();
		if ( ! $vodEntry || $vodEntry->getSourceType() != EntrySourceType::RECORDED_LIVE )
		{
			return null;
		}
		return $vodEntry;
	}

	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::objectAdded()
	 */
	public function objectAdded(BaseObject $object, BatchJob $raisedJob = null)
	{
		if($object instanceof CuePoint)
			$this->cuePointAdded($object);

		return true;
	}

	/* (non-PHPdoc)
	 * @see kObjectDeletedEventConsumer::objectDeleted()
	 */
	public function objectDeleted(BaseObject $object, BatchJob $raisedJob = null)
	{
		if($object instanceof entry)
			$this->entryDeleted($object->getId());

		if($object instanceof CuePoint)
			$this->cuePointDeleted($object);

		return true;
	}

	/* (non-PHPdoc)
	 * @see kObjectReplacedEventConsumer::objectReplaced()
	*/
	public function objectReplaced(BaseObject $object, BaseObject $replacingObject, BatchJob $raisedJob = null) {
		//replacement as a result of convertLiveSegmentFinished
		if ( !$replacingObject->getIsTemporary() ) {
			return true;
		}
		$c = new Criteria();
		$c->add(CuePointPeer::ENTRY_ID, $object->getId());
		if ( CuePointPeer::doCount($c) > self::MAX_CUE_POINTS_TO_COPY_TO_CLIP ) {
			KalturaLog::alert("Can't handle cuePoints after replacement for entry [{$object->getId()}] because cuePoints count exceeded max limit of [" . self::MAX_CUE_POINTS_TO_COPY_TO_CLIP . "]");
			return true;
		}
		$clipAttributes = self::getClipAttributesFromEntry( $replacingObject );
		//replacement as a result of trimming
		if ( !is_null($clipAttributes) ) {
			kEventsManager::setForceDeferredEvents( true );
			$this->deleteCuePoints($c);
			//copy cuepoints from replacement entry
			$replacementCuePoints = CuePointPeer::retrieveByEntryId($replacingObject->getId());
			foreach( $replacementCuePoints as $cuePoint ) {
				$newCuePoint = $cuePoint->copyToEntry($object);
				$newCuePoint->save();
			}
			kEventsManager::flushEvents();
		} else if (PermissionPeer::isValidForPartner(CuePointPermissionName::REMOVE_CUE_POINTS_WHEN_REPLACING_MEDIA, $object->getPartnerId())) {
			$this->deleteCuePoints($c);
		}
		return true;
	}

	/**
	 * @param BaseObject $entry entry to check
	 * @return kClipAttributes|null
	 */
	protected static function getClipAttributesFromEntry( BaseObject $object ) {
		if ( $object instanceof entry ) {
			$operationAtts = $object->getOperationAttributes();
			if ( !is_null($operationAtts) && count($operationAtts) > 0 ) {
				$clipAtts = reset($operationAtts);
				if ($clipAtts instanceof kClipAttributes) {
					return $clipAtts;
				}
			}
		}
		return null;
	}

	/**
	 * @param CuePoint $cuePoint
	 */
	protected function cuePointAdded(CuePoint $cuePoint)
	{
		if($cuePoint->shouldReIndexEntry())
			$this->reIndexCuePointEntry($cuePoint);
	}

	/**
	 * @param CuePoint $cuePoint
	 */
	protected function cuePointDeleted(CuePoint $cuePoint)
	{
		$c = new Criteria();
		$c->add(CuePointPeer::PARENT_ID, $cuePoint->getId());

		$this->deleteCuePoints($c);

		//re-index cue point on entry
		if($cuePoint->shouldReIndexEntry())
			$this->reIndexCuePointEntry($cuePoint);
	}

	/**
	 * @param int $entryId
	 */
	protected function entryDeleted($entryId)
	{
		$c = new Criteria();
		$c->add(CuePointPeer::ENTRY_ID, $entryId);

		$this->deleteCuePoints($c);
	}

	protected function deleteCuePoints(Criteria $c)
	{
		CuePointPeer::setUseCriteriaFilter(false);
		$cuePoints = CuePointPeer::doSelect($c);
		$update = new Criteria();
		$update->add(CuePointPeer::STATUS, CuePointStatus::DELETED);

		$con = Propel::getConnection(myDbHelper::DB_HELPER_CONN_MASTER);
		BasePeer::doUpdate($c, $update, $con);
		CuePointPeer::setUseCriteriaFilter(true);
		foreach($cuePoints as $cuePoint)
		{
			$cuePoint->setStatus(CuePointStatus::DELETED);
			$cuePoint->indexToSearchIndex();
			kEventsManager::raiseEvent(new kObjectDeletedEvent($cuePoint));
		}
	}

	/**
	 * @param SimpleXMLElement $scene
	 * @param int $partnerId
	 * @param CuePoint $newCuePoint
	 * @return CuePoint
	 */
	public static function parseXml(SimpleXMLElement $scene, $partnerId, CuePoint $newCuePoint = null)
	{
		$cuePoint = null;

		$entryId = $scene['entryId'];
		$entry = entryPeer::retrieveByPK($entryId);
		if(!$entry)
			throw new kCoreException("Entry [$entryId] not found", kCoreException::INVALID_ENTRY_ID);

		if(isset($scene['sceneId']) && $scene['sceneId'])
			$cuePoint = CuePointPeer::retrieveByPK($scene['sceneId']);

		if(!$cuePoint && isset($scene['systemName']) && $scene['systemName'])
			$cuePoint = CuePointPeer::retrieveBySystemName($entryId, $scene['systemName']);

		if(!$cuePoint)
			$cuePoint = $newCuePoint;

		$cuePoint->setPartnerId($partnerId);
		$cuePoint->setStartTime(kXml::timeToInteger($scene->sceneStartTime));

		$tags = array();
		foreach ($scene->tags->children() as $tag)
		{
			$value = "$tag";
			if($value)
				$tags[] = $value;
		}
		$cuePoint->setTags(implode(',', $tags));

		$cuePoint->setEntryId($entryId);
		if(isset($scene['systemName']))
			$cuePoint->setSystemName($scene['systemName']);

		return $cuePoint;
	}

	/**
	 * @param CuePoint $cuePoint
	 * @param SimpleXMLElement $scene
	 * @return SimpleXMLElement the created scene
	 */
	public static function generateCuePointXml(CuePoint $cuePoint, SimpleXMLElement $scene)
	{
		$scene->addAttribute('sceneId', $cuePoint->getId());
		$scene->addAttribute('entryId', $cuePoint->getEntryId());
		if($cuePoint->getSystemName())
			$scene->addAttribute('systemName', kMrssManager::stringToSafeXml($cuePoint->getSystemName()));

		$scene->addChild('sceneStartTime', kXml::integerToTime($cuePoint->getStartTime()));
		if($cuePoint->getPuserId())
			$scene->addChild('userId', kMrssManager::stringToSafeXml($cuePoint->getPuserId()));

		if(trim($cuePoint->getTags(), " \r\n\t"))
		{
			$tags = $scene->addChild('tags');
			foreach(explode(',', $cuePoint->getTags()) as $tag)
				$tags->addChild('tag', kMrssManager::stringToSafeXml($tag));
		}

		return $scene;
	}

	/**
	 * @param CuePoint $cuePoint
	 * @param SimpleXMLElement $scene
	 * @return SimpleXMLElement the created scene
	 */
	public static function syndicateCuePointXml(CuePoint $cuePoint, SimpleXMLElement $scene)
	{
		$scene->addAttribute('sceneId', $cuePoint->getId());
		if($cuePoint->getSystemName())
			$scene->addAttribute('systemName', kMrssManager::stringToSafeXml($cuePoint->getSystemName()));

		$scene->addChild('sceneStartTime', kXml::integerToTime($cuePoint->getStartTime()));
		$scene->addChild('createdAt', ($cuePoint->getCreatedAt(kMrssManager::FORMAT_DATETIME)));
		$scene->addChild('updatedAt', ($cuePoint->getCreatedAt(kMrssManager::FORMAT_DATETIME)));
		if($cuePoint->getPuserId())
			$scene->addChild('userId', kMrssManager::stringToSafeXml($cuePoint->getPuserId()));

		if(trim($cuePoint->getTags(), " \r\n\t"))
		{
			$tags = $scene->addChild('tags');
			foreach(explode(',', $cuePoint->getTags()) as $tag)
				$tags->addChild('tag', kMrssManager::stringToSafeXml($tag));
		}

		return $scene;
	}

	/**
	 * @param string $xmlPath
	 * @param int $partnerId
	 * @return array<CuePoint>
	 */
	public static function addFromXml($xmlPath, $partnerId)
	{
		if(!file_exists($xmlPath))
			throw new kCuePointException("XML file [$xmlPath] not found", kCuePointException::XML_FILE_NOT_FOUND);

		$xml = new KDOMDocument();
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		if(!$xml->load($xmlPath))
		{
			$errorMessage = kXml::getLibXmlErrorDescription(file_get_contents($xmlPath));
			throw new kCuePointException("XML [$xmlPath] is invalid:\n{$errorMessage}", kCuePointException::XML_INVALID);
		}

		$xsdPath = SchemaService::getSchemaPath(CuePointPlugin::getApiValue(CuePointSchemaType::INGEST_API));
		libxml_clear_errors();
		if(!$xml->schemaValidate($xsdPath))
		{
			$errorMessage = kXml::getLibXmlErrorDescription(file_get_contents($xmlPath));
			throw new kCuePointException("XML [$xmlPath] is invalid:\n{$errorMessage}", kCuePointException::XML_INVALID);
		}

		$pluginInstances = KalturaPluginManager::getPluginInstances('IKalturaCuePointXmlParser');
		$scenes = new SimpleXMLElement(file_get_contents($xmlPath));
		$cuePoints = array();

		foreach($scenes as $scene)
		{
			$cuePoint = null;
			foreach($pluginInstances as $pluginInstance)
			{
				$cuePoint = $pluginInstance->parseXml($scene, $partnerId, $cuePoint);
				if($cuePoint)
					$cuePoint->save();
			}

			if($cuePoint && $cuePoint instanceof CuePoint)
			{
				$cuePoints[] = $cuePoint;
			}
		}

		return $cuePoints;
	}

	/**
	 * @param array<CuePoint> $cuePoints
	 * @param SimpleXMLElement $scenes
	 */
	public static function syndicate(array $cuePoints, SimpleXMLElement $scenes)
	{
		$pluginInstances = KalturaPluginManager::getPluginInstances('IKalturaCuePointXmlParser');
		foreach($cuePoints as $cuePoint)
		{
			$scene = null;
			foreach($pluginInstances as $pluginInstance)
				$scene = $pluginInstance->syndicate($cuePoint, $scenes, $scene);
		}
	}

	/**
	 * @param array<CuePoint> $cuePoints
	 * @return string xml
	 */
	public static function generateXml(array $cuePoints)
	{
		$schemaType = CuePointPlugin::getApiValue(CuePointSchemaType::SERVE_API);
		$xsdUrl = "http://" . kConf::get('cdn_host') . "/api_v3/service/schema/action/serve/type/$schemaType";

		$scenes = new SimpleXMLElement('<scenes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="' . $xsdUrl . '" />');

		$pluginInstances = KalturaPluginManager::getPluginInstances('IKalturaCuePointXmlParser');

		foreach($cuePoints as $cuePoint)
		{
			$scene = null;
			foreach($pluginInstances as $pluginInstance)
				$scene = $pluginInstance->generateXml($cuePoint, $scenes, $scene);
		}

		$xmlContent = $scenes->asXML();

		$xml = new KDOMDocument();
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		if(!$xml->loadXML($xmlContent))
		{
			$errorMessage = kXml::getLibXmlErrorDescription($xmlContent);
			throw new kCuePointException("XML is invalid:\n{$errorMessage}", kCuePointException::XML_INVALID);
		}

		$xsdPath = SchemaService::getSchemaPath($schemaType);
		libxml_clear_errors();
		if(!$xml->schemaValidate($xsdPath))
		{
			$errorMessage = kXml::getLibXmlErrorDescription($xmlContent);
			throw new kCuePointException("XML is invalid:\n{$errorMessage}", kCuePointException::XML_INVALID);
		}

		return $xmlContent;
	}

	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::objectChanged()
	 */
	public function objectChanged(BaseObject $object, array $modifiedColumns)
	{
		if ( self::isPostProcessCuePointsEvent( $object, $modifiedColumns ) )
		{
			self::postProcessCuePoints( $object );
		}

		if(self::shouldReIndexEntry($object, $modifiedColumns))
		{
			$this->reIndexCuePointEntry($object);
		}
		if ( self::wasEntryClipped($object, $modifiedColumns) )
		{
			self::copyCuePointsToClipEntry( $object );
		}
		return true;
	}

	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::shouldConsumeChangedEvent()
	 */
	public function shouldConsumeChangedEvent(BaseObject $object, array $modifiedColumns)
	{
		if ( self::isPostProcessCuePointsEvent($object, $modifiedColumns) )
		{
			return true;
		}
		if( self::shouldReIndexEntry($object, $modifiedColumns) )
		{
			return true;
		}
		if ( self::wasEntryClipped($object, $modifiedColumns) ) {
			return true;
		}
		return false;
	}

	public static function wasEntryClipped(BaseObject $object, array $modifiedColumns)
	{
		if ( ($object instanceof entry)
			&& in_array(entryPeer::CUSTOM_DATA, $modifiedColumns)
			&& $object->isCustomDataModified('operationAttributes')
			&& $object->isCustomDataModified('sourceEntryId') )
		{
			return true;
		}
		return false;
	}

	public static function isPostProcessCuePointsEvent(BaseObject $object, array $modifiedColumns)
	{
		if(	$object instanceof LiveEntry
			&& $object->getRecordStatus() == RecordStatus::DISABLED // If ENABLED, it will be handled at the end of copyCuePointsFromLiveToVodEntry()
			&& !$object->hasMediaServer()
		)
		{
			// checking if the live-entry media-server was just unregistered
			$customDataOldValues = $object->getCustomDataOldValues();
			if(isset($customDataOldValues[LiveEntry::CUSTOM_DATA_NAMESPACE_MEDIA_SERVERS]))
			{
				return true;
			}
		}

		return false;
	}

	public static function shouldReIndexEntry(BaseObject $object, array $modifiedColumns)
	{
		if(!($object instanceof CuePoint))
			return false;

		/* @var $object CuePoint */
		return $object->shouldReIndexEntry($modifiedColumns);
	}

	public static function postProcessCuePoints( $liveEntry, $cuePointsIds = null )
	{
		$select = new Criteria();
		if ( $cuePointsIds )
		{
			$select->add(CuePointPeer::ID, $cuePointsIds, Criteria::IN);
		}
		else
		{
			/* @var $liveEntry LiveEntry */
			$select->add(CuePointPeer::ENTRY_ID, $liveEntry->getId());
			$select->add(CuePointPeer::STATUS, CuePointStatus::READY);
			$cuePoints = CuePointPeer::doSelect($select);
			$cuePointsIds = array();
			foreach($cuePoints as $cuePoint)
			{
				/* @var $cuePoint CuePoint */
				$cuePointsIds[] = $cuePoint->getId();
			}
		}
		$update = new Criteria();
		$update->add(CuePointPeer::STATUS, CuePointStatus::HANDLED);
		$con = Propel::getConnection(MetadataPeer::DATABASE_NAME);
		BasePeer::doUpdate($select, $update, $con);
		$cuePoints = CuePointPeer::retrieveByPKs($cuePointsIds);
		foreach($cuePoints as $cuePoint)
		{
			/* @var $cuePoint CuePoint */
			$cuePoint->indexToSearchIndex();
		}
	}

	/**
	 * @param string $vodEntryId
	 */
	public static function copyCuePointsFromLiveToVodEntry( $vodEntryId, $totalVODDuration, $lastSegmentDuration, $AMFs )
	{
		KalturaLog::debug("in copyCuePointsFromLiveToVodEntry with VOD entry ID: " . $vodEntryId .
			" totalVODDuration: " . $totalVODDuration .
			" lastSegmentDuration " . $lastSegmentDuration .
			" AMFs: " . print_r($AMFs, true));

		$vodEntry = entryPeer::retrieveByPK($vodEntryId);
		if ( ! $vodEntry )
		{
			return;
		}
		$liveEntryId = $vodEntry->getRootEntryId();
		/** @var $liveEntry KalturaLiveEntry */
		$liveEntry = entryPeer::retrieveByPK( $liveEntryId );
		if ( ! $liveEntry || ! $liveEntry instanceof LiveEntry )
		{
			KalturaLog::err("Can't find live entry with id [$liveEntryId]");
			return;
		}

		$currentSegmentEndTime = self::getSegmentEndTime($AMFs, $lastSegmentDuration);
		$currentSegmentStartTime = self::getSegmentStartTime($AMFs);

		$AMFs = self::parseAMFs($AMFs, $totalVODDuration, $lastSegmentDuration);

		KalturaLog::log("Saving the live entry [{$liveEntry->getId()}] cue points into the associated VOD entry [{$vodEntry->getId()}]");

		// select up to MAX_CUE_POINTS_TO_COPY_TO_VOD to handle
		$c = new KalturaCriteria();
		$c->add( CuePointPeer::ENTRY_ID, $liveEntry->getId() );
		$c->add( CuePointPeer::CREATED_AT, $currentSegmentEndTime, KalturaCriteria::LESS_EQUAL ); // Don't copy future cuepoints
		$c->addAnd( CuePointPeer::CREATED_AT, $currentSegmentStartTime - self::CUE_POINT_TIME_EPSILON, KalturaCriteria::GREATER_EQUAL ); // Don't copy cuepoints before segment begining
		$c->add( CuePointPeer::STATUS, CuePointStatus::READY ); // READY, but not yet HANDLED
		$c->addAscendingOrderByColumn(CuePointPeer::CREATED_AT);
		$c->setLimit( self::MAX_CUE_POINTS_TO_COPY_TO_VOD );
		$liveCuePointsToCopy = CuePointPeer::doSelect($c);

		$numLiveCuePointsToCopy = count($liveCuePointsToCopy);
		KalturaLog::info("About to copy $numLiveCuePointsToCopy cuepoints from live entry [{$liveEntry->getId()}] to VOD entry [{$vodEntry->getId()}]");
		$processedCuePointIds = array();
		if ( $numLiveCuePointsToCopy > 0 )
		{
			foreach ( $liveCuePointsToCopy as $liveCuePoint )
			{
				$processedCuePointIds[] = $liveCuePoint->getId();
				$cuePointCreationTime = $liveCuePoint->getCreatedAt(NULL)*1000;
				$offsetForTS = self::getOffsetForTimestamp($cuePointCreationTime, $AMFs);
				$copyMsg = "cuepoint [{$liveCuePoint->getId()}] from live entry [{$liveEntry->getId()}] to VOD entry [{$vodEntry->getId()}] cuePointCreationTime= $cuePointCreationTime offsetForTS= $offsetForTS";
				KalturaLog::debug("Preparing to copy $copyMsg");
				if ( ! is_null( $offsetForTS ) )
				{
					$liveCuePoint->copyFromLiveToVodEntry( $vodEntry, $offsetForTS );
				}
				else
				{
					KalturaLog::info("Not copying $copyMsg" );
				}
			}
		}
		KalturaLog::info("Post processing cuePointIds for live entry [{$liveEntry->getId()}]: " . print_r($processedCuePointIds,true) );
		if ( count($processedCuePointIds) )
		{
			self::postProcessCuePoints( $liveEntry, $processedCuePointIds );
		}
	}

	private static function getOffsetForTimestamp($timestamp, $AMFs){
		KalturaLog::debug('getOffsetForTimestamp ' . $timestamp);

		$minDistanceIndex = self::getClosestAMFIndex($timestamp, $AMFs);

		$ret = 0;
		if (is_null($minDistanceIndex)){
			KalturaLog::debug('minDistanceIndex is null - returning 0');
		}
		else if ($AMFs[$minDistanceIndex]->ts > $timestamp){
			KalturaLog::debug('timestamp is before index #' . $minDistanceIndex);
			$ret = $AMFs[$minDistanceIndex]->pts - ($AMFs[$minDistanceIndex]->ts - $timestamp);
		}
		else{
			KalturaLog::debug('timestamp is after index #' . $minDistanceIndex);
			$ret = $AMFs[$minDistanceIndex]->pts + ($timestamp - $AMFs[$minDistanceIndex]->ts);
		}

		KalturaLog::debug('AMFs array is:' . print_r($AMFs, true) . 'getOffsetForTimestamp returning ' . $ret);
		return $ret;
	}

	private static function getClosestAMFIndex($timestamp, $AMFs){
		$len = count($AMFs);
		$ret = null;

		if ($len == 1){
			$ret = 0;
		}
		else if ($timestamp >= $AMFs[$len-1]->ts){
			$ret = $len-1;
		}
		else if ($timestamp <= $AMFs[0]->ts){
			$ret = 0;
		}
		else if ($len > 1) {
			$lo = 0;
			$hi = $len - 1;

			while ($hi - $lo > 1) {
				$mid = round(($lo + $hi) / 2);
				if ($AMFs[$mid]->ts <= $timestamp) {
					$lo = $mid;
				} else {
					$hi = $mid;
				}
			}

			if (abs($AMFs[$hi]->ts - $timestamp) > abs($AMFs[$lo]->ts - $timestamp)) {
				return $lo;
			} else {
				return $hi;
			}
		}

		KalturaLog::debug('getClosestAMFIndex returning ' . $ret);
		return $ret;
	}

	// Get an array of strings of the form pts;ts and return an array of KAMFData
	private static function parseAMFs($AMFs, $totalVODDuration, $currentSegmentDuration){
		$retArr = array();

		for($i=0; $i < count($AMFs); ++$i){
			$amf = new KAMFData();
			$amfParts = explode(';', $AMFs[$i]);
			$amf->pts = $amfParts[0] + $totalVODDuration - $currentSegmentDuration;
			$amf->ts = $amfParts[1];

			KalturaLog::debug('adding AMF to AMFs: ' . print_r($amf, true) . ' extracted from ' . $AMFs[$i]);
			array_push($retArr, $amf);
		}

		return $retArr;
	}

	private static function getSegmentEndTime($AMFs, $segmentDuration){
		if (count($AMFs) == 0){
			KalturaLog::warning("getSegmentEndTime got an empty AMFs array - returning 0 as segment end time");
			return 0;
		}
		$amfParts = explode(';', $AMFs[0]);
		$ts = $amfParts[0];
		$pts = $amfParts[1];

		return ($pts - $ts + $segmentDuration)/1000;
	}

	private static function getSegmentStartTime($AMFs){
		if (count($AMFs) == 0){
			KalturaLog::warning("getSegmentStartTime got an empty AMFs array - returning 0 as segment end time");
			return 0;
		}
		$amfParts = explode(';', $AMFs[0]);
		$ts = $amfParts[0];
		$pts = $amfParts[1];

		return ($pts - $ts)/1000;
	}

	protected function reIndexCuePointEntry(CuePoint $cuePoint)
	{
		//index the entry after the cue point was added|deleted
		$entryId = $cuePoint->getEntryId();
		$entry = entryPeer::retrieveByPK($entryId);
		if($entry){
			$entry->setUpdatedAt(time());
			$entry->save();
			$entry->indexToSearchIndex();
		}
	}

	/**
	 *
	 * @param entry $clipEntry new entry to copy and adjust cue points from root entry to
	 */
	public static function copyCuePointsToClipEntry( entry $clipEntry ) {
		$clipAtts =  self::getClipAttributesFromEntry( $clipEntry );
		if ( !is_null($clipAtts) ) {
			$sourceEntry = entryPeer::retrieveByPK( $clipEntry->getSourceEntryId() );
			if ( is_null($sourceEntry) ) {
				KalturaLog::info("Didn't copy cuePoints for entry [{$clipEntry->getId()}] because source entry [" . $clipEntry->getSourceEntryId() . "] wasn't found");
				return;
			}
			$sourceEntryDuration = $sourceEntry->getLengthInMsecs();
			$clipStartTime = $clipAtts->getOffset();
			if ( is_null($clipStartTime) )
				$clipStartTime = 0;
			$clipDuration = $clipAtts->getDuration();
			if ( is_null($clipDuration) )
				$clipDuration = $sourceEntryDuration;
			$c = new KalturaCriteria();
			$c->add( CuePointPeer::ENTRY_ID, $clipEntry->getSourceEntryId() );
			if ( $clipDuration < $sourceEntryDuration ) {
				$c->addAnd( CuePointPeer::START_TIME, $clipStartTime + $clipDuration, KalturaCriteria::LESS_EQUAL );
			}
			if ( $clipStartTime > 0 ) {
				$c->addAnd( CuePointPeer::START_TIME, $clipStartTime, KalturaCriteria::GREATER_EQUAL );
				$c->addOr( CuePointPeer::START_TIME, 0, KalturaCriteria::EQUAL );
			}
			$c->addAscendingOrderByColumn(CuePointPeer::CREATED_AT);
			$rootEntryCuePointsToCopy = CuePointPeer::doSelect($c);
			if ( count( $rootEntryCuePointsToCopy ) <= self::MAX_CUE_POINTS_TO_COPY_TO_CLIP )
			{
				foreach( $rootEntryCuePointsToCopy as $cuePoint ) {
					$cuePoint->copyToClipEntry( $clipEntry, $clipStartTime, $clipDuration );
				}
			} else {
				KalturaLog::alert("Can't copy cuePoints for entry [{$clipEntry->getId()}] because cuePoints count exceeded max limit of [" . self::MAX_CUE_POINTS_TO_COPY_TO_CLIP . "]");
			}
		}
	}
}