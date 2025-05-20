<?php
/**
 * Reads adfs hard disk images 
 *
*/

namespace HomeLan\Retro\Acorn\Disk;

class AdfsReaderHD extends AdfsReader {

	/**
	 * Creates a new instance of the reader
	 *
	 * The difference between a hard disk image, and a floppy (apart from the size), is the hd images are not interleaved
	 * Thus this class just extends the floppy disk image reader, and turns off Interleaved access, it also drops the option
	 * to hold the disk image in ram, as they are too big tipically for it to be a good idea.
	 *
	 * @param string $sPath The path to the disk image to read
	 * @param string $sDiskImage A binary string of the disk image (don't supply this and the path a the same time)
	*/
	public function __construct(string $sPath)
	{
		parent::__construct($sPath,null);
		$this->bInterleaved = false;
	}

}
