<?php
/**
 * Reads adfs disk images 
 *
*/

namespace HomeLan\Retro\Acorn\Disk;

class AdfsReader {

	const TRACKS_PER_SIDE = 80;
	const SECTORS_PER_TRACK = 16;
	const SECTOR_SIZE = 256;
	const FILE_NAME_LEN = 10;	
	const METADATA_SIZE = 26;

	private $sDiskImageRaw = NULL;

	private $sImagePath = NULL;

	private $sTitle = NULL;

	private $aCatalogue = NULL;

	private $bInterleaved = true;

	/**
	 * Creates a new instance of the reader
	 *
	 * @param string $sPath The path to the disk image to read
	 * @param string $sDiskImage A binary string of the disk image (don't supply this and the path a the same time)
	*/
	public function __construct(string $sPath,?string $sDiskImage=NULL)
	{
		$this->sDiskImageRaw = $sDiskImage;
		$this->sImagePath = $sPath;
	}

	/**
	 * Gets a raw sector from the disk image
	 *
	 * This method calculates the location in the disk image of the sector and returns the bytes contained in that sector
	 * @param int $iSector The sector number 
	 * @return string Binary String 
	*/
	private function _getSectorRaw(int $iSector):string 
	{
		if($this->bInterleaved){
			$iTrack = floor($iSector/self::SECTORS_PER_TRACK);
			if ($iTrack < (self::TRACKS_PER_SIDE-1)){
				$iStart = (2 * $iTrack * self::SECTORS_PER_TRACK * self::SECTOR_SIZE) + (($iSector - (self::SECTORS_PER_TRACK*$iTrack)) * self::SECTOR_SIZE) ;
			}else{
				$iStart = (self::SECTOR_SIZE * self::SECTORS_PER_TRACK) + (2 * ($iTrack - self::TRACKS_PER_SIDE) * self::SECTORS_PER_TRACK * self::SECTOR_SIZE) + (($iSector - (self::SECTORS_PER_TRACK*$iTrack)) * self::SECTOR_SIZE) ;
			}
		}else{
			$iStart = self::SECTOR_SIZE * $iSector;
		}

		if(!is_null($this->sDiskImageRaw)){
			return substr($this->sDiskImageRaw,$iStart,self::SECTOR_SIZE);
		}else{
			$iFileHandle = fopen($this->sImagePath,'r');
			fseek($iFileHandle,$iStart);
			$sBytes = fread($iFileHandle,self::SECTOR_SIZE);
			fclose($iFileHandle);
			return $sBytes;
		}
		
	}

	/**
	 * Gets a sector from the disk as an array of bytes
	 *
	 * @param int $iSector The sector number
	 * @return array
	*/
	private function _getSectorAsByteArray(int $iSector):array
	{
		$iStart = ($iSector*self::SECTOR_SIZE);
		return unpack('C*',$this->_getSectorRaw($iSector));
	}

	/**
	 * Gets a number of sectors as a byte array
	 * 
	 * Returns a number of sectors inclusive of the start sector
	 * @param int $iStartSector
	 * @param int $iCount
	 * @return array
	*/
	private function _getSectorsAsByteArray(int $iStartSector,int $iCount):array
	{
		$iStart = ($iStartSector*self::SECTOR_SIZE);
		$sBlock = "";
		for($i=0;$i<$iCount;$i++){
			$sBlock .= $this->_getSectorRaw($iStartSector+$i);
		}
		return unpack('C*',$sBlock);
	}

	/**
	 * Gets a number of sectors as the raw binary 
	 *
	 * Returns a number of sectors inclusive of the start sector
	 * @param int $iStartSector
	 * @param int $iCount
	*/
	private function _getSectorsRaw(int $iStartSector,int $iCount):string
	{
		$iStart = ($iStartSector*self::SECTOR_SIZE);
		$sBlock = "";
		for($i=0;$i<$iCount;$i++){
			$sBlock .= $this->_getSectorRaw($iStartSector+$i);
		}
		return $sBlock;
	}

	/**
	 * Decodes the 7bit format used by adfs
	 *
	 * @param int $iByte (0-255)
	 * @return int 
	*/
	private function _decode7bit(int $iByte):int 
	{
		return $iByte & 127;
	}

	/**
	 * Decodes the 32bit int address form used by adfs
	 *
	 * @param int $iByte1
	 * @param int $iByte2
	 * @param int $iByte3
	 * @param int $iByte4
	 * @return int
	*/
	private function _decode32bitAddr(int $iByte1,int $iByte2,int $iByte3,int $iByte4):int
	{
		return ($iByte4 << 24) + ($iByte3 << 16) + ($iByte2 << 8) + $iByte1;
	}

	/**
	 * Decodes the 24bit int form used by adfs
	 *
	 * @param int $iByte1
	 * @param int $iByte2
	 * @param int $iByte3
	 * @return int
	*/
	private function _decode24bitAddr(int $iByte1,int $iByte2,int $iByte3):int
	{
		return ($iByte3 << 16) + ($iByte2 << 8) + $iByte1;
	}

	/**
	 * Reads block from the filing system 
	 * 
	 * Starts and the begining of a sector then reads a set number of bytes 
	 * @param int $iStartSector
	 * @param int $iLen
	 * @return string
	*/
	private function getBlocks(int $iStartSector,int $iLen): string
	{
		$iSectors = floor($iLen/self::SECTOR_SIZE);
		$iRemainder = $iLen - $iSectors*self::SECTOR_SIZE;
		$sBlock = $this->_getSectorsRaw($iStartSector,$iSectors);
		
		$sBlock .= substr($this->_getSectorRaw($iStartSector+$iSectors),0,$iRemainder);
		return $sBlock;
	}

	/**
	 * Extracts the disc catalogue 
	 *
	 * An adfs image is diveded up into sectors the root directory ($) is stored in 5 secotors starting on sector 2
	 *
	 * @return array The array is in the format array('dir'=>array('filename'=>array('loadaddr'=>,'execaddr'=>,'size'=>,'startsector'=>)));
	*/
	public function getCatalogue(int $iStartSector=2,bool $bUseCache=true):array
	{
		if(is_null($this->aCatalogue) OR !$bUseCache){
			$aCat = array();
			
			$aSectors = $this->_getSectorsAsByteArray($iStartSector,5);

			//ADFS has a fixed number of table entries per dir (47), so read them all
			//If not all 47 entries are used the table is 0 padded
			for($i=0;$i<47;$i++){
				//The filemeta data start 5 bytes in
				$iStart = 5+($i*self::METADATA_SIZE);
				
				//Grab a block of file metadata
				$aMeta = array_slice($aSectors,$iStart,self::METADATA_SIZE);

				//Decode the file name
				$sFileName = '';
				if($aMeta[0]==0){
					//We hit the last entry 
					break;
				}

				for($x=0;$x<self::FILE_NAME_LEN;$x++){
					if($this->_decode7bit($aMeta[$x])!==13 ){
						$sFileName .=chr($this->_decode7bit($aMeta[$x]));
					}else{
						//File name are terminated with the ascii char cr (13)
						break;
					}
				}

				//The high bit of the 4th byte of the file name is used to denote if an entry is a directory
				if($aMeta[3] & 128){
					$sType = "dir";
				}else{
					$sType = "file";
				}

				//Decode Load Addr
				$iLoad = $this->_decode32bitAddr($aMeta[0x0a],$aMeta[0x0b],$aMeta[0x0c],$aMeta[0x0d]);

				//Decode Exec Addr
				$iExec = $this->_decode32bitAddr($aMeta[0x0e],$aMeta[0x0f],$aMeta[0x10],$aMeta[0x11]);

				//Decode Size
				$iSize = $this->_decode32bitAddr($aMeta[0x12],$aMeta[0x13],$aMeta[0x14],$aMeta[0x15]);

				//Decode Start Sector
				$iSector = $this->_decode24bitAddr($aMeta[0x16],$aMeta[0x17],$aMeta[0x18]);
				$aCat[$sFileName]=array('load'=>$iLoad,'exec'=>$iExec,'size'=>$iSize,'startsector'=>$iSector,'type'=>$sType);

				//Recurse the directory tree
				if($sType=='dir'){
					$aCat[$sFileName]['dir']=$this->getCatalogue($iSector,false);
				}
			}
			if($bUseCache){
				$this->aCatalogue = $aCat;
			}else{
				return $aCat;
			}
		}
		return $this->aCatalogue;
	}

	/**
	 * Gets a file from the filing system
	 *
	 * The file name supplied can be read from the root dir of the image or a subdir, the filename supplied
	 * may be a full file path to access files in a subdir
	 * @param string $sFileName
	 * @return string
	*/
	public function getFile(string $sFileName):string
	{
		$aCat = $this->getCatalogue();
		$aParts = explode('.',$sFileName);
		foreach($aParts as $sPart){
			$aKeys = array_keys($aCat);
			$bFound = false;
			foreach($aKeys as $sTestKey){
				if(strtolower($sTestKey)==strtolower($sPart)){
					$bFound=true;
					break;
				}
			}
			if($bFound){
				if($aCat[$sTestKey]['type']=='file'){
					return $this->getBlocks($aCat[$sTestKey]['startsector'],$aCat[$sTestKey]['size']);
				}
				if($aCat[$sTestKey]['type']=='dir'){
					$aCat = $aCat[$sTestKey];
				}
			}
		}
	}

	/**
	 * Gets the file stats for a given file 
	 *
	 * @param string $sFileName
	 * @return array
	*/
	public function getStat(string $sFileName):array
	{
		$aCat = $this->getCatalogue();
		$aParts = explode('.',$sFileName);
		foreach($aParts as $sPart){
			$aKeys = array_keys($aCat);
			$bFound = false;
			foreach($aKeys as $sTestKey){
				if(strtolower($sTestKey)==strtolower($sPart)){
					$bFound=true;
					break;
				}
			}
			if($bFound){
				if($aCat[$sTestKey]['type']=='file'){
					return array('size'=>$aCat[$sTestKey]['size'],'sector'=>$aCat[$sTestKey]['startsector']);
				}
				if($aCat[$sTestKey]['type']=='dir'){
					$aCat = $aCat[$sTestKey];
				}
			}
		}
	}

	/**
	 * Tests if a path is a file or not
	 *
	 * @param string $sFileName
	 * @return boolean Returns true if its a file false if not
	*/
	public function isFile(string $sFileName):bool
	{
		$aCat = $this->getCatalogue();
		$aParts = explode('.',$sFileName);
		$iParts = count($aParts);
		
		foreach($aParts as $iIndex => $sPart){
				$aKeys = array_keys($aCat);
				$bFound = false;
				foreach($aKeys as $sTestKey){
					if(strtolower($sTestKey)==strtolower($sPart)){
						$bFound=true;
						break;
					}
				}
				if($iIndex+1 == $iParts){
					//last entry 
				
					if($bFound){
						if($aCat[$sTestKey]['type']=='file'){
							return true;
						}
					}
				}
				if($bFound){
					if($aCat[$sPart]['type']=='dir'){
						$aCat = $aCat[$sTestKey];
					}else{
						return false;
					}
				}
		}
		return false;
	}

	/**
	 * Tests if a path is a directory or not
	 *
	 * @param string $sFileName
	 * @return boolean Returns true if its a directory false if not
	*/
	public function isDir(string $sFileName):bool
	{
		$aCat = $this->getCatalogue();
		$aParts = explode('.',$sFileName);
		$iParts = count($aParts);
		foreach($aParts as $iIndex => $sPart){
			$aKeys = array_keys($aCat);
			$bFound = false;
			foreach($aKeys as $sTestKey){
				if(strtolower($sTestKey)==strtolower($sPart)){
					$bFound=true;
					break;
				}
			}if($iIndex+1 == $iParts){
				//last entry 
				if($bFound){
					if($aCat[$sTestKey]['type']=='dir'){
						return true;
					}
				}
			}
			if($bFound){
				if($aCat[$sPart]['type']=='dir'){
					$aCat = $aCat[$sTestKey];
				}else{
					return false;
				}
			}
		}
		return false;
	}
}
