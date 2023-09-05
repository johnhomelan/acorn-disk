Acorn Disk
=========================

This package allows the reading of DFS and ADFS disk images.  

Features
--------

* PSR-4 autoloading compliant structure
* Unit-Testing with PHPUnit
* Disk Image can be read from files, or passed in as a string


Install
-------

composer requre homelan/acorn-disk

Overview
--------

The DfsReader class, allows files and metadata to be read from a DFS disk image stored in the .ssd format.  In DFS directorys are not hierarchical like modern fs, rather they can only exist as the top level, and dirnames can only be 1 char in length.  Another querk of DFS is that the default dir is '$', and while on later filing systems for the 8-bit Acorn range '$' was the root of the filing system in DFS directorys are more like a file name prefix with '$' being the default. 

The DfsReader class can working to two ways, in can directly read bits of the disk image as needed by openin a file handle on demand, or the entire disk can be read in as a string and passed to the DfsReader.  The former is more efficent for lots of file access, while the later is more memory efficent. 

The AdfsReader class, allows files and metadata to be read from a ADFS floppy disk images stored in the .adl format.  ADFS filing systems are like modern filing systems in that there is a hierarchical directory structure, with '$' being the root of the fs.  There are limits on the number of files per dir in ADFS 

The AdfsReaderHD class, does the same as the AdfsReader class, only is read hardisk images stored in the scsi.dat format used by Beebem.

DfsReader Usage
---------------

Creating a DfsReader Object to directly open a file on disk

$oDfs = new \HomeLan\Retro\Acorn\Disk\DfsReader('disk_image.ssd');


Creating a DfsReader Object to read from a disk image held in a binary string

$sDiskImage = file_get_contents('disk_image.ssd');

$oDfs = new  \HomeLan\Retro\Acorn\Disk\DfsReader(null,$sDiskImage);


Once the DfsReader object exists a few simple methods can be used to read data from it.

$oDfs->getTitle()

Reads the title of the disk

$aCatalogue = $oDfs->getCatalogue();

Gets the catalogue of what is on the disk *CAT

e.g. 

$aCatalogue = $oDfs->getCatalogue();
foreach($aCatalogue as $sDirectoy=>$aDir)
{
	echo $sDirectoy."\n==============\n";
	foreach($aDir as $sFileName=>$aEntryMetadata){
		echo $sFileName."  [".$aEntryMetadata['loadaddr'].' '.$aEntryMetadata['execaddr'].' '.$aEntryMetadata['size'].' '.$aEntryMetadata['startsector']."\n";
		
	}
}


$oDfs->getFile('$.!BOOT');

The the contents of a give file 

e.g.

$sFileContents = $oDfs->getFile('$.!BOOT');


$oDfs->getStat('$.!BOOT');

Stats a file 


$oDfs->isFile('$.!BOOT');

Test if a given path is a file or not

e.g.

$bFile = $oDfs->isFile('$.!BOOT');
if($bFile){
	echo "!BOOT is file.\n" 
}

$oDfs->isDir('A');

Test if a given path is a file or not

$bDir = $oDfs->isDir('D');
if($bDir){
	echo "D is a dir.\n" 
}

AdfsReader Usage
---------------

Creating a AdfsReader Object to directly open a file on disk

$oDfs = new \HomeLan\Retro\Acorn\Disk\AdfsReader('disk_image.adl');


Creating a AdfsReader Object to read from a disk image held in a binary string

$sDiskImage = file_get_contents('disk_image.adl');

$oDfs = new  \HomeLan\Retro\Acorn\Disk\AdfsReader(null,$sDiskImage);


Once the AdfsReader object exists a few simple methods can be used to read data from it.

$oAdfs->getTitle()

Reads the title of the disk

$aCatalogue = $oAdfs->getCatalogue();

Gets the catalogue of what is on the disk *CAT

e.g. 

$aCatalogue = $oAdfs->getCatalogue();
foreach($aCatalogue as $sDirectoy=>$aDir)
{
	echo $sDirectoy."\n==============\n";
	foreach($aDir as $sFileName=>$aEntryMetadata){
		echo $sFileName."  [".$aEntryMetadata['loadaddr'].' '.$aEntryMetadata['execaddr'].' '.$aEntryMetadata['size'].' '.$aEntryMetadata['startsector'].' '$aEntryMetadata['type']."\n";
		
	}
}


$oAdfs->getFile('$.!BOOT');

The the contents of a give file 

e.g.

$sFileContents = $oAdfs->getFile('$.!BOOT');


$oAdfs->getStat('$.!BOOT');

Stats a file 


$oAdfs->isFile('$.!BOOT');

Test if a given path is a file or not

e.g.

$bFile = $oAdfs->isFile('$.!BOOT');
if($bFile){
	echo "!BOOT is file.\n" 
}

$oDfs->isDir('A');

Test if a given path is a file or not

$bDir = $oAdfs->isDir('D');
if($bDir){
	echo "D is a dir.\n" 
}

AdfsReaderHD Usage
------------------

This operates exactly the same as the AdfsReader class only the constuctor is different, as it does not support reading the disk image from a binary string.  
As that is a some what negative feature with the significantly larger disk image sizes involved.

$oDfs = new \HomeLan\Retro\Acorn\Disk\AdfsReaderHD('scsi0.dat');

