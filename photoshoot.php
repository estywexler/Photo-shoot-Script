<?php

/**
 * @package   Photo-shoot Script.
 * @copyright 2018 Esther Wexler.
 */

/**
 * Class camera
 */
class camera {

    /**
     * @type string
     */
    protected $type;

    /**
     * @type float
     * Circle of confusion in mm.
     */
    protected $coc;

    /**
     * @type int
     * viewing distance in meter.
     */
    protected $distance;

    /**
     * @type int
     * desired final-image resolution in lp/mm for a 25 cm viewing distance.
     */
    protected $image_resolution_for_25;

    /**
     * @type int
     */
    protected $enlargement;

    /**
     * @type array
     * a camera does not have to have a type, but it does, if can only except a known type.
     */
    protected $valid_types = ['Canon_APS-C', 'Nikon_APS-C'];

    /**
     * @param stdClass|null $data
     */
    public function __construct($data) {

        if (!$this->missing_info($data)) {
            if (isset($data->type) && in_array($data->type, $this->valid_types)) {
                $this->type = $data->type;
            } else {
                $this->type = null;
            }
            $this->distance = isset($data->distance) ? $data->distance : null;
            $this->image_resolution_for_25 = isset($data->image_resolution_for_25) ? $data->image_resolution_for_25 : null;
            $this->enlargement = isset($data->enlargement) ? $data->enlargement : null;
            $this->coc = isset($data->coc) ? $data->coc : $this->calculate_coc();
        } else {
            throw new Exception("Missing parameters to calculate coc");
        }
    }

    /**
     * check no data is missing to to calculate coc.
	 *
     * @param stdClass|null
     * @return bool
     */
    protected function missing_info($data) {
        if (isset($data->type) && in_array($data->type, $this->valid_types)) {
            return false;
        } else if (isset($data->coc)) {
            return false;
        } else if (isset($data->distance) && isset($data->image_resolution_for_25) && isset($data->enlargement)) {
            return false;
        }
        return true;
    }

    /**
     * calculate Circle of confusion in mm.
     * @param null
     * @return float|null
     */
    protected function calculate_coc() {
        if (isset($this->type) && $this->type === "Canon_APS-C") {
            return 0.018;
        } else if (isset($this->type) && $this->type === "Nikon_APS-C") {
            return 0.019;
        } else if (isset($this->distance) && isset($this->image_resolution_for_25) && isset($this->enlargement)) {

            // Calculate CoC source from wikipedia (link above).
            // distance is in meter so we need to multiply it with 100.
            // CoC in mm = (distance cm / 25 cm ) / (desired final-image resolution in lp/mm for a 25 cm distance) / enlargement
            return ($this->distance * 100 / 25) / $this->image_resolution_for_25 / $this->enlargement;
        }
        return null;
    }

   /**
     * get Circle of confusion in mm.
     * @param null
     * @return float
     */
    public function get_coc() {
        return $this->coc;
    }
}

/**
 * Class lens
 */
class lens{

    /**
     * @type int
     * focal length in mm.
     */
    protected $focal_length;

    /**
     * @type float
     */
    protected $aperture;

	/**
     * @param stdClass|null $data
     */
    public function __construct($data) {
        if (isset($data->focal_length)) {
            $this->focal_length = $data->focal_length;
        }
        if(isset($data->aperture)){
            $this->aperture = $data->aperture;
        } else {
            throw new Exception("aperture must be set and sequence of the powers of the square root of 2");
        }
    }

    /**
     * get focal length.
     * @param null
     * @return float
     */
    public function get_focal_length() {
    	return $this->focal_length;
	}

    /**
     * get aperture.
     * @param null
     * @return int
     */
    public function get_aperture() {
        return $this->aperture;
    }
}

/**
 * Class photoshoot
 * has the photo shoot values
 * calculate for those photo shoot values DoF, FAR anf Near points
 */
class photoshoot {

    /**
     * @type Class
     * camera details.
     */
    protected $camera;

    /**
     * @type Class
     * lens details.
     */
    protected $lens;

    /**
     * @type float
     * Subject distance in meter.
     */
    protected $distance;

    /**
     * @type float
     */
    protected $hyper_focal;

    /**
     * @type float
     */
    protected $far_point;
    /**
     * @type float
     */
    protected $near_point;

    /**
     * @type float
     * Depth of field
     */
    protected $dof;

    /**
     * @param stdClass|null $data
     * @throws Exception
     */
    public function __construct($data)
    {

        if (!$this->missing_info($data)) {
            $this->camera = new camera($data->camera);
            $this->lens = new lens($data->lens);

            // Convert from meter to mm
            $this->distance = ($data->distance)*1000;
            $this->hyper_focal = $this->calculate_hyper_focal($this->lens->get_focal_length(), $this->lens->get_aperture(), $this->camera->get_coc());
            $this->far_point = $this->calculate_far_point($this->hyper_focal, $this->distance, $this->lens->get_focal_length());
            $this->near_point = $this->calculate_near_point($this->hyper_focal, $this->distance, $this->lens->get_focal_length());
            $this->dof = $this->calculate_dof($this->far_point, $this->near_point);
        } else {
            throw new Exception("Missing data");
        }
    }

    /**
     * check no data is missing for this class.
     * @param $data
     * @return bool returns true if data is missing, otherwise returns false
     */
    protected function missing_info($data)
    {
        if (!isset($data->distance) && !isset($data->camera) && !isset($data->lens)) {
            return true;
        }
        return false;
    }

    /**
     * Calculate hyper focal.
     * @param $focal_length int
     * @param $aperture float
     * @param $coc float
     * @return float|int
     * @throws Exception
     */
    protected function calculate_hyper_focal($focal_length, $aperture, $coc)
    {

        // Make sure we are not try to divide by zero.
        if (($aperture * $coc)+ $focal_length == 0) {
            throw new Exception("Wrong values please check the values of aperture ,coc and focal_length");
        }

        //Based on http://www.dofmaster.com/equations.html
        return (($focal_length * $focal_length) / ($aperture * $coc))+ $focal_length;

    }

    /**
     * Calculate far_point.
     * @param $hyper_focal float
     * @param $distance float
     * @param $focal_length int
     * @return float|int
     * @throws Exception
     */
    protected function calculate_far_point($hyper_focal, $distance, $focal_length)
    {

        // Make sure we are not try to divide by zero.
        if (($hyper_focal-$distance) == 0) {
            throw new Exception("ERROR trying divide by zero");
        }

        // Based on http://www.dofmaster.com/equations.html
        return ($distance * ($hyper_focal - $focal_length)) / ($hyper_focal - $distance);
    }

    /**
     * Calculate near_point.
     * @param $hyper_focal float
     * @param $distance float
     * @param $focal_length int
     * @return float|int
     * @throws Exception
     */
    protected function calculate_near_point($hyper_focal, $distance, $focal_length)
    {

        // Make sure we are not try to divide by zero.
        if ($hyper_focal + $distance - (2 * $focal_length) == 0) {
            throw new Exception("ERROR trying divide by zero");
        }

        //Based on http://www.dofmaster.com/equations.html
        return  ($distance * ($hyper_focal - $focal_length)) / ($hyper_focal + $distance - (2 * $focal_length));
    }

    /**
     * Calculate the dof.
     * @param $far_point float
     * @param $near_point float
     * @return float
     */
    protected function calculate_dof($far_point, $near_point)
    {
        return  $far_point - $near_point;
    }

    /**
     * A helper function to convert meters to feet.
     * @param $meters float
     * @return float
     */
    protected function metersToFeet($meters) {
        return round(($meters) * 3.2808399 ,2);
    }

    /**
     * formatted string for printing DOF, FAR and NEAR point.
     * @return formatted string
     */
    public function __toString()
    {
        // Display values round with precision of 2.
        // Display FAR point and Far poit in m anf feet.
        $dof = round($this->dof, 2);
        $df = round($this->far_point / 1000, 2);
        $dn = round($this->near_point / 1000, 2);
        $df_feet = $this->metersToFeet($df);
        $dn_feet = $this->metersToFeet($dn);
        return "DOF is: $dof mm, FAR point is: $df m ($df_feet feet), NEAR point is: $dn m ($dn_feet feet) \n";
    }
}

/**
 * Class recommends
 * this class does not save ant data
 * contains static functions that can be called without a without a class instance.
 */
class recommends {

    /**
     * calculate distance work with flash
     * @param $gn int GN number given from the flash
     * @param $aperture float aperture needed for the photoshot.
     * @return float round with precision of 2
     */
    public static function distance_with_flash($gn, $aperture)
    {
        if ($aperture != 0 && isset($gn)) {
            return round($gn / $aperture, 2);
        } else {
            echo "Error, aperture can not be zero and gn must be set";
        }
    }

    /**
     * This function returns an array of a recommendation for mode to set,
     * what should take priority? aperture or shutter,
     * based on parameters of environment and type of picture we want to get.
     * The first value returned is the mode, the second is the recommended setting for that mode.
     *
     * “A” is Nikon's version of aperture priority.
     * “S” is Nikon's version of shutter priority.
     * “Av” is Canon's version of aperture priority.
     * “Tv” is Canon's version of shutter priority.
     *
     * assuming aperture range is f2.8 - f22
     * @param  $type_wanted    string one of the following: 'portrait', 'subject_focus', 'landscape', 'group', 'panning'.
     * @param  $movement_level string one of the following: 'objects_in_high_speed', 'objects_in_medium_speed', 'objects_in_low_speed', 'no_movement_level'.
     * @param  $light          string one of the following: 'daylight_ouside','dark_ouside','flash_outside','flash_inside','no_flash_inside'
     * @param  $bokeh_level    string one of the following: 'max','mid','min';
     * @return array
     */
    public static function mode_and_settings($type_wanted, $movement_level, $light, $bokeh_level)
    {
        $mode = null;
        $settings = null;

        // first check if the movement_level is in speed,
        // if so, the shutter will get priority.
        // Otherwise, the aperture.
        switch ($movement_level) {
            case 'objects_in_high_speed':
            case 'objects_in_medium_speed':
            case 'objects_in_low_speed':
                $mode = 'shutter';
                break;

            default:
                $mode = 'aperture';
        }

        // Set the aperture.
        if ($mode === 'aperture') {

            switch ($type_wanted) {
                case 'portrait':
                case 'subject_focus':

                    // Range of aperture in portrait is f2.8 - f11.
                    if ($bokeh_level === 'max') {
                        $settings = 'f/2.8';
                    } else if ($bokeh_level === 'min') {
                        $settings = 'f/11';
                    } else {

                        // Bokeh level is mid or not set.
                        $settings = 'f/5.6';
                    }
                    break;

                case 'landscape':
                    if ($light === 'daylight_ouside') {
                        $settings = 'f/22';
                    } else {
                        $settings = 'f/11';
                    }
                    break;

                case 'group':
                    if ($light === 'dark_ouside' || $light === 'no_flash_inside') {
                        $settings = 'f/5.6';
                    } else {
                        $settings = 'f/11';
                    }
                    break;

                case 'panning':

                    // In this case the recommendation is shutter mode.
                    $mode = 'shutter';
                    break;
                default:

                    // For best quality we will use by default 2 stops above the lowest option.
                    $settings = 'f/5.6';
            }
        }

        // Set the shutter.
        if ($mode === 'shutter') {

            switch ($movement_level) {
                case 'objects_in_high_speed':

                    if ($type_wanted === 'panning') {
                        $settings = '1/5';
                    } else if ($light === 'flash_ouside' || $light === 'flash_inside' ) {

                        // Only with flash we can set the shutter so fast, otherwise as fast as it ca go without flash.
                        $settings = '1/600';
                    } else {
                        $settings = '1/250';
                    }
                    break;
                case 'objects_in_medium_speed':
                    $settings = $type_wanted === 'panning' ? '1/10' : '1/250';
                    break;
                case 'objects_in_low_speed':
                case 'no_movement_level':
                    $settings = $type_wanted === 'panning' ? '1/25' : '1/160';
                    break;
                default:
                    $settings = '1/200';
            }
        }
        return array('mode' => $mode, 'settings' => $settings);
    }
}

// Set some values for testing, can be change to ane value we want to test/execute.
$data = ['coc' => 0.029, 'focal_length' => 50, 'aperture' => 1.4, 'distance' => 1];
$flash_data = ['GN' => 92, 'aperture' => 2.8];
$mode_data = ['type_wanted' => 'portrait', 'movement_level' => 'no_movement_level', 'light' => 'flash_inside', 'bokeh_level' => 'max'];

// Test for mode_and_settings function, all options.
$type_wanteds = array('portrait', 'subject_focus', 'landscape', 'group', 'panning');
$movement_levels = array('objects_in_high_speed', 'objects_in_medium_speed', 'objects_in_low_speed', 'no_movement_level');
$lights = array('daylight_ouside','dark_ouside','flash_outside','flash_inside','no_flash_inside');
$bokeh_levels = array('max','mid','min');

// Execute.
$camera = new stdClass();
$camera->coc = isset($data['coc']) ? $data['coc'] : null;

$lens = new stdClass();
$lens->focal_length = isset($data['focal_length']) ? $data['focal_length'] : null;
$lens->aperture = isset($data['aperture']) ? $data['aperture'] : null ;

$light = isset($data->light) ? $data->light : null;

$shoot_params = new stdClass();
$shoot_params->camera = $camera;
$shoot_params->lens = $lens;
$shoot_params->distance = isset($data['distance']) ? $data['distance'] : null;

$photoshoot = new photoshoot($shoot_params);

// Check if photoshoot was created.
if($photoshoot) {
	echo $photoshoot;
} else {
    echo "Error, something went wrong, photoshoot_one doesn't exist";
}

// Test distance_with_flash.
$distance_with_flash = \recommends::distance_with_flash($flash_data['GN'], $flash_data['aperture']);
echo 'Distance working with GN '.$flash_data['GN'].' and '.$flash_data['aperture'].' aperture is : '.$distance_with_flash."\n";

// Test an example for mode_and_settings:
echo "Running test with portrait, no_movement_level, flash_inside, bokeh_level max ";
$mode_and_settings = \recommends::mode_and_settings($mode_data['type_wanted'], $mode_data['movement_level'], $mode_data['light'], $mode_data['bokeh_level']);
echo $mode_and_settings['mode'].", ".$mode_and_settings['settings']."\n";

//More data for testing:
//Test all options for mode_and_settings.
/*
foreach ($type_wanteds as $type_wanted){
    foreach ($movement_levels as $movement_level){
        foreach ($lights as $light) {
            foreach ($bokeh_levels as $bokeh_level){
                echo "running test with ".$type_wanted.", ".$movement_level.", ".$light.", bokeh_level ".$bokeh_level;
                $mode_and_settings = \recommends::mode_and_settings($type_wanted, $movement_level, $light, $bokeh_level);
                echo $mode_and_settings['mode'].", ".$mode_and_settings['settings']."\n";
            }
        }
    }
}

Test examples for DoF
50mm lens @ f/1.4 on a full frame (coc=0.029) with a subject at 1m distance:  31mm
50mm lens @ f/1.4 on a full frame (coc=0.029) with a subject at 3m distance: 228mm
50mm lens @ f/2.8 on a full frame (coc=0.029) with a subject at 1m distance:  61mm
50mm lens @ f/2.8 on a full frame (coc=0.029) with a subject at 3m distance: 580mm

50mm lens @ f/1.4 on a Canon APS frame (coc=0.018) with a subject at 1m distance:  19mm
50mm lens @ f/1.4 on a Canon APS frame (coc=0.018) with a subject at 3m distance: 178mm
50mm lens @ f/2.8 on a Canon APS frame (coc=0.018) with a subject at 1m distance:  38mm
50mm lens @ f/2.8 on a Canon APS frame (coc=0.018) with a subject at 3m distance: 358mm
*/

?>