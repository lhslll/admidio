<?php
/**
 ***********************************************************************************************
 * Diese Klasse verwaltet Bilder und bietet Methoden zum Anpassen dieser
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Image
 * Folgende Methoden stehen zur Verfuegung:
 *
 * setImageFromPath($pathAndFilename)
 *                  - setzt den Pfad zum Bild und liest Bildinformationen ein
 * setImageFromData($imageData)
 *                  - liest das Bild aus einem String ein und wird intern als PNG-Bild
 *                    weiter verarbeitet und ausgegeben
 * copyToFile($imageResource = null, $pathAndFilename = "", $quality = 95)
 *                  - kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der
 *                    hinterlegten Datei des Objekts
 * copyToBrowser($imageResource = null, $quality = 95)
 *                  - gibt das Bild direkt aus, so dass es im Browser dargestellt werden kann
 * getMimeType()    - gibt den Mime-Type (image/png) des Bildes zurueck
 * rotate($direction = "right")
 *                  - dreht das Bild um 90° in eine Richtung ("left"/"right")
 * scaleLargerSide($newMaxSize)
 *                  - skaliert die laengere Seite des Bildes auf den uebergebenen Pixelwert
 * scale($newXSize, $newYSize, $maintainAspectRatio = true)
 *                  - das Bild wird in einer vorgegebenen maximalen Laenge/Hoehe skaliert
 * delete()         - entfernt das Bild aus dem Speicher
 */
class Image
{
    const ROTATE_DIRECTION_LEFT  = 'left';
    const ROTATE_DIRECTION_RIGHT = 'right';
    const ROTATE_DIRECTION_FLIP  = 'flip';

    /**
     * @var string
     */
    private $imagePath = '';
    /**
     * @var resource|null
     */
    private $imageResource;
    /**
     * @var int
     */
    private $imageType;
    /**
     * @var int
     */
    private $imageWidth  = 0;
    /**
     * @var int
     */
    private $imageHeight = 0;

    /**
     * @param string $pathAndFilename
     */
    public function __construct($pathAndFilename = '')
    {
        if ($pathAndFilename !== '')
        {
            $this->setImageFromPath($pathAndFilename);
        }
    }

    /**
     * @return resource|null Returns the image resource
     */
    public function getImageResource()
    {
        return $this->imageResource;
    }

    /**
     * @return array<int,int> Returns an array of the image width and height
     */
    public function getImageSize()
    {
        return array($this->imageWidth, $this->imageHeight);
    }

    /**
     * Methode setzt den Pfad zum Bild und liest Bildinformationen ein
     * @param string $pathAndFilename
     * @return bool
     */
    public function setImageFromPath($pathAndFilename)
    {
        if (!is_file($pathAndFilename))
        {
            return false;
        }

        $this->imagePath = $pathAndFilename;
        $imageProperties = getimagesize($this->imagePath);

        if ($imageProperties === false)
        {
            return false;
        }

        $this->imageWidth  = $imageProperties[0];
        $this->imageHeight = $imageProperties[1];
        $this->imageType   = $imageProperties[2];

        return $this->createResource($pathAndFilename);
    }

    /**
     * Methode liest das Bild aus einem String ein und wird intern als PNG-Bild weiter verarbeitet und ausgegeben
     * @param string $imageData String with binary image data
     * @return bool
     */
    public function setImageFromData($imageData)
    {
        $imageResource = imagecreatefromstring($imageData);

        if ($imageResource === false)
        {
            return false;
        }

        $this->imageResource = $imageResource;

        $this->imageWidth  = imagesx($this->imageResource);
        $this->imageHeight = imagesy($this->imageResource);
        $this->imageType   = IMAGETYPE_PNG;

        return true;
    }

    /**
     * @param string $pathAndFilename
     * @return bool
     */
    private function createResource($pathAndFilename)
    {
        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                $imageResource = imagecreatefromjpeg($pathAndFilename);
                break;

            case IMAGETYPE_PNG:
                $imageResource = imagecreatefrompng($pathAndFilename);
                break;
            default:
                return false;
        }

        if ($imageResource === false)
        {
            return false;
        }

        $this->imageResource = $imageResource;

        return true;
    }

    /**
     * Methode kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der hinterlegten Datei des Objekts
     * @param resource|null $imageResource   eine andere Bild-Resource kann uebergeben werden
     * @param string        $pathAndFilename ein andere Datei kann zur Ausgabe angegeben werden
     * @param int           $quality         die Qualitaet kann fuer jpeg-Dateien veraendert werden
     * @return bool true, falls erfolgreich
     */
    public function copyToFile($imageResource = null, $pathAndFilename = '', $quality = 95)
    {
        if ($imageResource === null)
        {
            $imageResource = $this->imageResource;
        }

        if ($pathAndFilename === '')
        {
            $pathAndFilename = $this->imagePath;
        }

        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $pathAndFilename, $quality);

            case IMAGETYPE_PNG:
                return imagepng($imageResource, $pathAndFilename);

            default:
                return false;
        }
    }

    /**
     * Methode gibt das Bild direkt aus, so dass es im Browser dargestellt werden kann
     * @param resource|null $imageResource eine andere Bild-Resource kann uebergeben werden
     * @param int           $quality       die Qualitaet kann fuer jpeg-Dateien veraendert werden
     * @return bool
     */
    public function copyToBrowser($imageResource = null, $quality = 95)
    {
        if ($imageResource === null)
        {
            $imageResource = $this->imageResource;
        }

        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                echo imagejpeg($imageResource, null, $quality);
                break;

            case IMAGETYPE_PNG:
                echo imagepng($imageResource);
                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * gibt den Mime-Type (image/png) des Bildes zurueck
     * @return string
     */
    public function getMimeType()
    {
        return image_type_to_mime_type($this->imageType);
    }

    /**
     * setzt den Image-Type des Bildes neu
     * @param string $imageType
     * @return bool
     */
    public function setImageType($imageType)
    {
        switch ($imageType)
        {
            case 'jpeg':
                $this->imageType = IMAGETYPE_JPEG;
                break;

            case 'png':
                $this->imageType = IMAGETYPE_PNG;
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * Methode dreht das Bild um 90° in eine Richtung
     * @param string $direction 'right' o. 'left' Richtung, in die gedreht wird
     * @return bool
     */
    public function rotate($direction = self::ROTATE_DIRECTION_RIGHT)
    {
        switch ($direction)
        {
            case self::ROTATE_DIRECTION_LEFT:
                $angle = 90;
                break;
            case self::ROTATE_DIRECTION_RIGHT:
                $angle = -90;
                break;
            case self::ROTATE_DIRECTION_FLIP:
                $angle = 180;
                break;
            default:
                return false;
        }

        $imageRotated = imagerotate($this->imageResource, $angle, 0);

        // save
        $this->copyToFile($imageRotated);

        // Delete image from ram
        imagedestroy($imageRotated);

        return true;
    }

    /**
     * Scales the longer side of the image to the passed pixel value. The other side
     * is then calculated back according to the page ratio. If the image is already
     * smaller than the new max size nothing is done.
     * @param int $newMaxSize New maximum size in pixel to which the image should be scaled.
     * @return bool Return true if the image was scaled otherwise false.
     */
    public function scaleLargerSide($newMaxSize)
    {
        if($newMaxSize < $this->imageWidth || $newMaxSize < $this->imageHeight)
        {
            // calc aspect ratio
            $aspectRatio = $this->imageWidth / $this->imageHeight;

            if($this->imageWidth > $this->imageHeight)
            {
                // Scale the x-side
                $newXSize = $newMaxSize;
                $newYSize = (int) round($newMaxSize / $aspectRatio);
            }
            else
            {
                // Scale the y-side
                $newXSize = (int) round($newMaxSize * $aspectRatio);
                $newYSize = $newMaxSize;
            }

            return $this->scale($newXSize, $newYSize, false);
        }

        return false;
    }

    /**
     * Scale an image to the new size of the parameters. Therefore the PHP instance may need
     * some memory which should be set through the PHP setting memory_limit.
     * @param int  $newXSize            The new horizontal width in pixel. The image will be scaled to this size.
     * @param int  $newYSize            The new vertical height in pixel. The image will be scaled to this size.
     * @param bool $maintainAspectRatio If this is set to true, the image will be within the given size
     *                                  but maybe one side will be smaller than set with the parameters.
     * @return bool Return true if the image was scaled otherwise false.
     */
    public function scale($newXSize, $newYSize, $maintainAspectRatio = true)
    {
        if ($maintainAspectRatio)
        {
            if ($newXSize >= $this->imageWidth && $newYSize >= $this->imageHeight)
            {
                return false;
            }

            // calc aspect ratio
            $aspectRatio = $this->imageWidth / $this->imageHeight;

            if ($aspectRatio > $newXSize / $newYSize)
            {
                // scale to maximum width
                $newWidth = $newXSize;
                $newHeight = (int) round($newXSize / $aspectRatio);
            }
            else
            {
                // scale to maximum height
                $newWidth = (int) round($newYSize * $aspectRatio);
                $newHeight = $newYSize;
            }

            return $this->scale($newWidth, $newHeight, false);
        }

        // check current memory limit and set this to 50MB if the current value is lower
        if (PhpIniUtils::getMemoryLimit() < 50 * 1024 * 1024) // 50MB
        {
            @ini_set('memory_limit', '50M');
        }

        if (version_compare(PHP_VERSION, '5.5.0', '>='))
        {
            // create new resized image
            $resizedImageResource = imagescale($this->imageResource, $newXSize, $newYSize);
        }
        else // backwards compatibility for PHP-Version < 5.5
        {
            // create a new image
            $resizedImageResource = imagecreatetruecolor($newXSize, $newYSize);

            // copy image data to a new image with the new given size
            imagecopyresampled($resizedImageResource, $this->imageResource, 0, 0, 0, 0, $newXSize, $newYSize, $this->imageWidth, $this->imageHeight);
        }

        imagedestroy($this->imageResource);

        // update the class parameters to new image data
        $this->imageResource = $resizedImageResource;
        $this->imageWidth    = $newXSize;
        $this->imageHeight   = $newYSize;

        return true;
    }

    /**
     * Delete image from class and server memory
     */
    public function delete()
    {
        imagedestroy($this->imageResource);
        $this->imageResource = null;
        $this->imagePath = '';
    }
}
