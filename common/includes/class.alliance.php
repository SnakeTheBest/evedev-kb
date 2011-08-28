<?php
/**
 * $Date$
 * $Revision$
 * $HeadURL$
 * @package EDK
 */

/**
 * Creates a new Alliance or fetches an existing one from the database.
 * @package EDK
 */
class Alliance extends Entity
{
	/** @var integer */
	private $id = null;
	/** @var integer */
	private $externalid = null;
	/** @var boolean */
	private $executed = false;
	/** @var string */
	private $name = null;
	/** @var string */
	private $imgurl = null;

	/**
	 * Create a new Alliance object from the given $id.
	 *
	 * @param integer $id The alliance ID.
	 * @param boolean $external set true if the given id is external
	 */
	function Alliance($id = 0, $external = false)
	{
		if ($external) {
			$this->externalid = (int) $id;
		} else {
			$this->id = (int) $id;
		}
	}

	/**
	 * Return the alliance CCP ID.
	 *
	 * @return integer
	 */
	function getExternalID()
	{
		if ($this->externalid) {
			return $this->externalid;
		}

		$this->execQuery();

		if ($this->externalid) {
			return $this->externalid;
		}
		// If we still don't have an external ID then try to fetch it from CCP.
		$myID = new API_NametoID();
		$myID->setNames($this->getName());
		$myID->fetchXML();
		$myNames = $myID->getNameData();
		if ($this->setExternalID($myNames[0]['characterID'])) {
			return $this->externalid;
		} else {
			return 0;
		}
	}

	/**
	 * Return the alliance ID.
	 *
	 * @return integer
	 */
	function getID()
	{
		if ($this->id) {
			return $this->id;
		} elseif ($this->externalid) {
			$this->execQuery();
			return $this->id;
		} else {
			return 0;
		}
	}

	/**
	 * Return the alliance name stripped of all non-ASCII non-alphanumeric characters.
	 *
	 * @return string
	 */
	function getUnique()
	{
		if (is_null($this->name)) {
			$this->execQuery();
		}
		return preg_replace('/[^a-zA-Z0-9]/', '', $this->name);
	}

	/**
	 * Return the alliance name.
	 *
	 * @return string
	 */
	function getName()
	{
		if (is_null($this->name)) {
			$this->execQuery();
		}
		return $this->name;
	}

	/**
	 * Fetch the alliance details from the database using the id given on construction.
	 *
	 * If no record is found but we have an external ID then the result
	 * will be fetched from CCP.
	 */
	function execQuery()
	{
		if (!$this->executed) {
			if ($this->id && $this->isCached()) {
				$cache = $this->getCache($this->id);
				$this->externalid = $cache->externalid;
				$this->executed = $cache->executed;
				$this->name = $cache->name;
			} else {
				$qry = DBFactory::getDBQuery();
				$sql = "select all_id, all_name, all_external_id from kb3_alliances where ";
				if ($this->externalid) {
					$sql .= "all_external_id = ".$this->externalid;
				} else {
					$sql .= "all_id = ".$this->id;
				}
				$qry->execute($sql);
				if ($this->externalid && !$qry->recordCount()) {
					$this->fetchAlliance();
				} else if ($qry->recordCount()) {
					$row = $qry->getRow();
					$this->id = (int) $row['all_id'];
					$this->name = $row['all_name'];
					$this->externalid = (int) $row['all_external_id'];
					$this->executed = true;
					$this->putCache();
				}
			}

			$this->executed = true;
		}
	}

	/**
	 * Add a new alliance to the database or update the details of an
	 * existing alliance.
	 *
	 * @param string $name An alliance name for this object.
	 * @param integer $externalid External ID if known.
	 * @return type
	 */
	function add($name, $externalid = 0)
	{
		$qry = DBFactory::getDBQuery();
		$name = $qry->escape(stripslashes($name));
		$qry->execute("select all_id, all_name, all_external_id"
				." from kb3_alliances where all_name = '".$name."'");

		if ($qry->recordCount() == 0) {
			$externalid = (int) $externalid;
			if (!$externalid) {
				$allname = str_replace(" ", "%20", $name);
				$myID = new API_NametoID();
				$myID->setNames($allname);
				$myID->fetchXML();
				$myNames = $myID->getNameData();
				$externalid = (int) $myNames[0]['characterID'];
			}
			// If we have an external id then check it isn't already in use
			// If we find it then update the old alliance with the new name
			// then return.
			if ($externalid) {
				$qry->execute("SELECT * FROM kb3_alliances WHERE all_external_id = ".$externalid);
				if ($qry->recordCount() > 0) {
					$row = $qry->getRow();
					$qry->execute("UPDATE kb3_alliances SET all_name = '".$name
							."' WHERE all_external_id = ".$externalid);

					$this->id = (int) $row['all_id'];
					$this->name = $name;
					$this->externalid = (int) $row['all_external_id'];
					$this->executed = true;

					return;
				}
				$qry->execute("insert into kb3_alliances ".
						"(all_id, all_name, all_external_id) values ".
						"(null, '".$name."', ".$externalid.")");
			} else {
					$qry->execute("insert into kb3_alliances ".
						"(all_id, all_name) values ".
						"(null, '".$name."')");
			}
			$this->id = (int) $qry->getInsertID();
			$this->name = $name;
			$this->externalid = (int) $externalid;
			$this->executed = true;
		} else {
			$row = $qry->getRow();
			$this->id = (int) $row['all_id'];
			$this->name = $row['all_name'];
			$this->externalid = (int) $row['all_external_id'];
			$this->executed = true;
		}
	}

	/**
	 * Set the CCP external ID for this alliance.
	 *
	 * @param integer $externalid
	 * @param boolean $update If true and the ID exists, update the existing
	 * entry.
	 *
	 * @return integer
	 */
	function setExternalID($externalid, $update = true)
	{
		$externalid = (int) $externalid;
		if ($externalid && $this->id) {
			$this->execQuery();
			$qry = DBFactory::getDBQuery();
			// Check if an alliance already exists with this external id and
			// merge the two if so. i.e. the name has changed.
			$qry->execute("SELECT * FROM kb3_alliances WHERE all_external_id = "
					.$externalid);
			if ($qry->recordCount() > 0) {
				if (!$update) {
					return false;
				}

				$row = $qry->getRow();
				// The already existing alliance is this one.
				if ($row['all_id'] == $this->id) {
					return $this->id;
				}

				$newid = $row['all_id'];
				$qry->execute("UPDATE kb3_corps SET crp_all_id = $newid WHERE crp_all_id = ".$this->id);
				$qry->execute("UPDATE kb3_inv_detail SET ind_all_id = $newid WHERE ind_all_id = ".$this->id);
				$qry->execute("UPDATE kb3_inv_all SET ina_all_id = $newid WHERE ina_all_id = ".$this->id);
				$qry->execute("UPDATE kb3_kills SET kll_all_id = $newid WHERE kll_all_id = ".$this->id);
				$qry->execute("DELETE FROM kb3_alliances WHERE all_id = ".$this->id);
				$qry->execute("UPDATE kb3_alliances SET all_name = '".$qry->escape($this->name)."' WHERE all_external_id = ".$externalid);

				$this->id = $newid;
				$this->externalid = $externalid;
				$this->putCache();
				return $this->id;
			}
			else if ($qry->execute("UPDATE kb3_alliances SET all_external_id = ".$externalid." WHERE all_id = ".$this->id)) {
				$this->externalid = $externalid;
				$this->putCache();
				return $this->id;
			}
		}
		return false;
	}

	/**
	 * Check if this is a Faction.
	 *
	 * @return boolean
	 */
	function isFaction()
	{
		$factions = array("Amarr Empire", "Minmatar Republic", "Caldari State", "Gallente Federation");
		return (in_array($this->getName(), $factions));
	}

	/**
	 * Return the faction ID.
	 *
	 * @return integer The faction ID or 0 if this is not a faction.
	 */
	function getFactionID()
	{
		if (!$this->isFaction()) {
			return 0;
		}
		return $this->getExternalID();
	}

	/**
	 * Return the URL for the alliance's portrait.
	 *
	 * @param integer $size The desired portrait size.
	 * @return string URL for a portrait.
	 */
	function getPortraitURL($size = 128)
	{
		if (isset($this->imgurl)) {
			return $this->imgurl;
		}
		if (file_exists("img/alliances/".$this->getUnique().".png")) {
			if ($size == 128) {
				$this->imgurl = IMG_HOST."/img/alliances/"
						.$this->getUnique().".png";
			} else if (CacheHandler::exists(
					$this->getUnique()."_$size.png",'img')) {
				$this->imgurl = KB_HOST."/"
						.CacheHandler::getExternal($this->getUnique()
						."_$size.png", 'img');
			} else {
				$this->imgurl = KB_HOST.'/?a=thumb&amp;type=alliance&amp;id='
						.$this->getUnique().'&amp;size='.$size;
			}
			$this->putCache();
		} else if ($this->getExternalID()) {
			$this->imgurl = imageURL::getURL('Alliance', $this->getExternalID(),
					$size);
			$this->putCache();
		} else {
			$this->imgurl = imageURL::getURL('Alliance', 1, $size);
		}
		return $this->imgurl;
	}

	/**
	 * Return the URL for the alliance's details page.
	 *
	 * @return string URL for the details page.
	 */
	function getDetailsURL()
	{
		if ($this->getExternalID()) {
			return edkURI::page('alliance_detail', $this->externalid,
					'all_ext_id');
		} else {
			return edkURI::page('alliance_detail', $this->id, 'all_id');
		}
	}

	/**
	 * Fetch the alliance name from CCP using the stored external ID.
	 */
	private function fetchAlliance()
	{
		if (!$this->getExternalID()) {
			return false;
		}

		$myID = new API_IDtoName();
		$myID->setIDs($this->externalid);
		$myID->fetchXML();
		$myNames = $myID->getIDData();

		$this->add($myNames[0]['name'], (int) $myNames[0]['characterID']);
	}
}
