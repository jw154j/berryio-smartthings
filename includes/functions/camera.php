<?
/*------------------------------------------------------------------------------
  BerryIO Camera Functions
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
 Load the camera settings
------------------------------------------------------------------------------*/
settings('camera', '1');
require_once(CONFIGS.'camera.php');


/*----------------------------------------------------------------------------
  Check the camera is installed properly and create the image/video folders
----------------------------------------------------------------------------*/
function camera_setup()
{
  // Must be run in CLI mode
  if($GLOBALS['EXEC_MODE'] != 'cli')
    return FALSE;

  echo PHP_EOL;
  echo 'BerryIO Camera Setup'.PHP_EOL;
  echo '--------------------'.PHP_EOL;
  echo PHP_EOL;


  // First check Raspbian has the required pre-requisites
  echo 'Checking for camera support....'.PHP_EOL;
  if(!is_file('/usr/bin/raspistill') || !is_file('/usr/bin/raspivid'))
  {
    echo PHP_EOL;
    echo 'The raspistill and raspivid binaries are missing.'.PHP_EOL;
    echo 'This is most likely because your Raspbian install is out of date.'.PHP_EOL;
    echo PHP_EOL;
    echo 'Please run:'.PHP_EOL;
    echo 'sudo apt-get update'.PHP_EOL;
    echo 'sudo apt-get upgrade'.PHP_EOL;
    echo PHP_EOL;
    echo '...and try again.'.PHP_EOL;
    echo PHP_EOL;
    echo 'If that doesn\'t work try:'.PHP_EOL;
    echo 'sudo apt-get dist-upgrade'.PHP_EOL;
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Do a test photo
  echo 'Please wait, testing the camera can take images.... (no images are kept)'.PHP_EOL;
  exec('raspistill -t 0 -o /dev/null 2>&1', $output, $return_var);
  // Because raspistill doesn't return the correct exit code we have to manually test the output for content
  if(trim(implode('', $output)) != '')
  {
    echo PHP_EOL;
    echo 'An error occured when trying to take an image.'.PHP_EOL;
    echo 'This could be because your camera is not connected properly,'.PHP_EOL;
    echo 'or it may simply be that you have not yet turned on the camera with raspi-config.'.PHP_EOL;
    echo PHP_EOL;
    echo 'The error returned was as follows:'.PHP_EOL;
    echo implode(PHP_EOL, $output);
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Do a test video
  echo 'Please wait, testing the camera can take video.... (no video is kept)'.PHP_EOL;
  exec('raspivid -t 1000 -o /dev/null 2>&1', $output, $return_var);
  // Because raspistill doesn't return the correct exit code we have to manually test the output for content
  if($return_var || trim(implode('', $output)) != '')
  {
    echo PHP_EOL;
    echo 'An error occured when trying to take video.'.PHP_EOL;
    echo PHP_EOL;
    echo 'The error returned was as follows:'.PHP_EOL;
    echo implode(PHP_EOL, $output);
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Grant the webserver access to the camera
  echo 'Granting the webserver access to the camera....'.PHP_EOL;
  exec('adduser www-data video', $output, $return_var);
  if($return_var != 0)
  {
    echo PHP_EOL;
    echo 'An error occured when trying to give the webserver access to the camera.'.PHP_EOL;
    echo PHP_EOL;
    echo 'The error returned was as follows:'.PHP_EOL;
    echo implode(PHP_EOL, $output);
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Check GD-Lib is installed
  echo 'Checking support for PHP GD Libraries....'.PHP_EOL;
  $success = FALSE;
  if(!function_exists('imagetypes'))
  {
    echo 'Installing PHP GD Libraries.... (this may take a while)'.PHP_EOL;
    exec('apt-get -y install php5-gd 2>&1', $output, $return_var);
    if($return_var != 0)
    {
      echo PHP_EOL;
      echo 'An error occured when trying to install the package php5-gd.'.PHP_EOL;
      echo PHP_EOL;
      echo 'The error returned was as follows:'.PHP_EOL;
      echo implode(PHP_EOL, $output);
      echo PHP_EOL;
      echo PHP_EOL;
      echo 'Please install it manually and try again'.PHP_EOL;
      return FALSE;
    }
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Create the folders required to store the images, videos and thumbnails
  echo 'Creating the folders to store your images and videos....'.PHP_EOL;
  if(!_camera_setup_check_folder($GLOBALS['CAMERA_STORE']['IMAGES']['FILES'], 'images')) return FALSE;
  if(!_camera_setup_check_folder($GLOBALS['CAMERA_STORE']['IMAGES']['THUMBNAILS'], 'image thumbnails')) return FALSE;
  if(!_camera_setup_check_folder($GLOBALS['CAMERA_STORE']['VIDEOS']['FILES'], 'videos')) return FALSE;
  if(!_camera_setup_check_folder($GLOBALS['CAMERA_STORE']['VIDEOS']['THUMBNAILS'], 'video thumbnails')) return FALSE;

  echo PHP_EOL;
  echo 'All your new folders have been successfuly set up.'.PHP_EOL;
  echo 'You can change these at any time by editing '.SETTINGS.'camera.php'.PHP_EOL;
  echo 'and re-running this script.'.PHP_EOL;
  echo PHP_EOL;


  // Modify apache site file
  echo 'Modifying the Apache site configuration....'.PHP_EOL;
  $success = FALSE;
  if(is_file('/etc/apache2/sites-available/berryio'))
    if(($lines = @file('/etc/apache2/sites-available/berryio')) !== FALSE)
      foreach($lines as $line_number => $line)
        if(substr(trim($line), 0, 107) == 'php_admin_value open_basedir "/usr/share/berryio/:/etc/berryio/:/sys/class/gpio/:/sys/devices/virtual/gpio/')
        {
          $lines[$line_number] = '    php_admin_value open_basedir "/usr/share/berryio/:/etc/berryio/:/sys/class/gpio/:/sys/devices/virtual/gpio/';
          $lines[$line_number] .= ':'.$GLOBALS['CAMERA_STORE']['IMAGES']['FILES'].'/';
          $lines[$line_number] .= ':'.$GLOBALS['CAMERA_STORE']['IMAGES']['THUMBNAILS'].'/';
          $lines[$line_number] .= ':'.$GLOBALS['CAMERA_STORE']['VIDEOS']['FILES'].'/';
          $lines[$line_number] .= ':'.$GLOBALS['CAMERA_STORE']['VIDEOS']['THUMBNAILS'].'/';
          $lines[$line_number] .= '"'.PHP_EOL;

          $success = $success == FALSE ? TRUE : FALSE;
        }
  if($success)
    $success = file_put_contents('/etc/apache2/sites-available/berryio', $lines);
  if(!$success)
  {
    echo PHP_EOL;
    echo 'An error occured when trying to modify /etc/apache2/site-available/berryio.'.PHP_EOL;
    echo 'Please add the paths to your image and video folders into the'.PHP_EOL;
    echo 'php_admin_value open_basedir line manually and restart apache'.PHP_EOL;
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;

  echo PHP_EOL;


  // Restart apache
  echo 'Restarting Apache....'.PHP_EOL;
  exec('service apache2 restart 2>&1', $output, $return_var);
  if($return_var != 0)
  {
    echo PHP_EOL;
    echo 'Apache failed to restart!'.PHP_EOL;
    echo PHP_EOL;
    echo 'The output was as follows:'.PHP_EOL;
    echo implode(PHP_EOL, $output);
    return FALSE;
  }
  echo 'Success!'.PHP_EOL;
}


/*----------------------------------------------------------------------------
  Check a folder exists and if not create it
----------------------------------------------------------------------------*/
function _camera_setup_check_folder($folder, $purpose)
{
  if(is_dir($folder))
  {
    echo 'Using the existing directory '.$folder.' to store your '.$purpose.'.'.PHP_EOL;
    return TRUE;
  }

  // Check it starts at slash
  if(!isset($folder[0]) || $folder[0] != '/')
  {
    echo PHP_EOL;
    echo 'Your '.$purpose.' folder appears to be invalid?'.PHP_EOL;
    echo 'Please check '.SETTINGS.'camera.php for any mistakes and try again.'.PHP_EOL;
    return FALSE;
  }

  $path = '';
  foreach(explode('/', $folder) as $directory)
    if($directory != '')
    {
      $path .= '/'.$directory;

      // If the folder doesnt exist, create it
      if(!is_dir($path))
        if(!@mkdir($path, 0770) || !chmod($path, 0770)  || !chown($path, 'pi') || !chgrp($path, 'www-data'))
        {
          echo PHP_EOL;
          echo 'An error occured when trying to create the folder:'.PHP_EOL;
          echo $folder.PHP_EOL;
          echo PHP_EOL;
          echo 'Please check '.SETTINGS.'camera.php for any mistakes and try again.'.PHP_EOL;
          return FALSE;
        }
    }

  echo 'Successfuly created the folder: '.$folder.'.'.PHP_EOL;

  return TRUE;
}


/*----------------------------------------------------------------------------
  Get a list of images
  Returns FALSE on failure or
  array( [$thumb => $file] [, $thumb => $file] [, ...] )
----------------------------------------------------------------------------*/
function camera_images()
{
  return _camera_scan_directory($GLOBALS['CAMERA_STORE']['IMAGES']['FILES'], $GLOBALS['CAMERA_STORE']['IMAGES']['THUMBNAILS'], $GLOBALS['CAMERA_EXTENSIONS']['IMAGES']);
}


/*----------------------------------------------------------------------------
  Get a list of videos
  Returns FALSE on failure or
  array( [$thumb => $file] [, $thumb => $file] [, ...] )
----------------------------------------------------------------------------*/
function camera_videos()
{
  return _camera_scan_directory($GLOBALS['CAMERA_STORE']['VIDEOS']['FILES'], $GLOBALS['CAMERA_STORE']['VIDEOS']['THUMBNAILS'], $GLOBALS['CAMERA_EXTENSIONS']['VIDEOS']);
}


/*----------------------------------------------------------------------------
  Get a list of files from a directory and remove any duff thumbnails
  Returns FALSE on failure or
  array( [$thumb => $file] [, $thumb => $file] [, ...] )
----------------------------------------------------------------------------*/
function _camera_scan_directory($files_directory, $thumbnails_directory, $extensions)
{
  // Check the directories are set up
  if(!is_dir($files_directory) || !is_dir($thumbnails_directory)) return FALSE;

  // Scan the directory
  $files = array();
  if(($listing = @scandir($files_directory)) === FALSE) return FALSE;
  foreach($listing as $file)
    if($file != '..' && $file != '.' && is_file($files_directory.'/'.$file)) // Files only
    {
      $file_details = pathinfo($files_directory.'/'.$file);
      if(isset($file_details['extension']) && in_array($file_details['extension'], $extensions))
        $files[$file_details['filename'].'.png'] = $file;
    }

  // Scan the thumbnails directory and remove anything that isn't a thumbnail
  if(($listing = @scandir($thumbnails_directory)) !== FALSE)
    foreach($listing as $file)
      if($file != '..' && $file != '.' && is_file($thumbnails_directory.'/'.$file)) // Files only
      {
        $file_details = pathinfo($thumbnails_directory.'/'.$file);
        if(!isset($file_details['extension']) || $file_details['extension'] != 'png' || !isset($files[$file]))
          @unlink($thumbnails_directory.'/'.$file);
      }

  return $files;
}


/*----------------------------------------------------------------------------
  Checks an image thumbnail exists and if it doesn't creates it
  Returns FALSE on failure or TRUE on success
----------------------------------------------------------------------------*/
function _camera_create_image_thumb($file, $thumbnail, $thumb_size)
{
  // Check for bad resize
  if(!isset($thumb_size['X']) || !is_numeric($thumb_size['X']) || $thumb_size['X'] < 1 || !isset($thumb_size['Y']) || !is_numeric($thumb_size['Y']) || $thumb_size['Y'] < 1)
    return FALSE;

  // Source file cant be found
  if(!is_file($file))
    return FALSE;

  // Already exists
  if(is_file($thumbnail))
    return TRUE;

  // Create a zero byte thumbnail to stop race conditions where two people view the site at once and try to create thumbnails at once
  if(!@touch($thumbnail))
    return FALSE;

  // Get the file information
  list($source_x, $source_y, $type) = getimagesize($file);

  // Check gd support for PNG's (which we need for the thumbnail) and the image we are making into a thumb and get it
  $supported = imagetypes();
  if(!$supported & IMG_PNG) return FALSE;
  switch($type)
  {
    case IMAGETYPE_JPEG:
      if($supported & IMG_JPG)
        $img = imagecreatefromjpeg($file);
      break;

    case IMAGETYPE_PNG:
      if($supported & IMG_PNG)
        $img = imagecreatefrompng($file);
      break;

    case IMAGETYPE_GIF:
      if($supported & IMG_GIF)
        $img = imagecreatefromgif($file);
      break;
  }

  if(!isset($img) || !$img)
  {
    @unlink($thumbnail);
    return FALSE;
  }

  // Calculate scale
  $x_scale = $source_x/$thumb_size['X'];
  $y_scale = $source_y/$thumb_size['Y'];
  $scale   = $y_scale > $x_scale ? $y_scale : $x_scale;
  $thumb_x = round($source_x/$scale);
  $thumb_y = round($source_y/$scale);

  // Create new image
  if(($thumb_img = imagecreatetruecolor($thumb_x, $thumb_y)) === FALSE)
  {
    @unlink($thumbnail);
    return FALSE;
  }

  if(!($success = imagecopyresampled($thumb_img, $img, 0, 0, 0, 0, $thumb_x, $thumb_y, $source_x, $source_y) && imagepng($thumb_img, $thumbnail)))
    @unlink($thumbnail);

  @imagedestroy($img);
  @imagedestroy($thumb_img);

  return $success;
}


/*----------------------------------------------------------------------------
  Checks a video thumbnail exists and if it doesn't creates it
  Returns FALSE on failure or TRUE on success
----------------------------------------------------------------------------*/
function _camera_create_video_thumb($file, $thumbnail, $thumb_size)
{
  // NOT CURRENTLY SUPPORTED
  // TO DO
  return FALSE;
}


/*----------------------------------------------------------------------------
  Outputs an image or video file or thumbnail (creating the thumbnail if required)
  Returns FALSE on failure or exits on success
----------------------------------------------------------------------------*/
function camera_show($type, $file)
{
  switch($type)
  {
    case 'image_thumbnail':
      $thumbnail = TRUE;
    case 'image':
      $global_type = 'IMAGES';
      break;

    case 'video_thumbnail':
      $thumbnail = TRUE;
    case 'video':
      $global_type = 'VIDEOS';
      break;

    default:
      return FALSE;
  }

  // Split out the file from the extension
  $file_details = pathinfo($GLOBALS['CAMERA_STORE'][$global_type]['FILES'].'/'.$file);

  // If its a thumbnail, we don't have an extension (but it exists - tested in _camera_show_file) we are done
  if(isset($thumbnail) && !isset($file_details['extension']))
    _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file.'.png');

  // If its a thumbnail, the extension is .png (and it exists - tested in _camera_show_file) we are done
  if(isset($thumbnail) && isset($file_details['extension']) && $file_details['extension'] == 'png')
    _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file);

  // If its not a thumbnail but the extension is in the alowed types (and it exists - tested in _camera_show_file) we are done
  if(!isset($thumbnail) && isset($file_details['extension']) && in_array($file_details['extension'], $GLOBALS['CAMERA_EXTENSIONS'][$global_type]))
    _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['FILES'].'/'.$file);

  // Since we are here its likely we can't find the source so we will need to look for it in the sensible extensions
  foreach($GLOBALS['CAMERA_EXTENSIONS'][$global_type] as $extension)
  {
    $file = $file_details['filename'].'.'.$extension;

    // Otherwise if we are looking for the source, just try and output it using this filename
    if(!isset($thumbnail))
      _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['FILES'].'/'.$file);
    // If we are looking for a image thumbnail, try and make it from this filename
    elseif($global_type == 'IMAGES')
    {
      if(_camera_create_image_thumb($GLOBALS['CAMERA_STORE'][$global_type]['FILES'].'/'.$file, $GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file_details['filename'].'.png', $GLOBALS['CAMERA_THUMBNAIL_SIZE'][$global_type]))
        _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file_details['filename'].'.png');
    }
    // If we are looking for a video thumbnail, try and make it from this filename
    if($global_type == 'VIDEOS')
    {
      if(_camera_create_video_thumb($GLOBALS['CAMERA_STORE'][$global_type]['FILES'].'/'.$file, $GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file_details['filename'].'.png', $GLOBALS['CAMERA_THUMBNAIL_SIZE'][$global_type]))
        _camera_show_file($GLOBALS['CAMERA_STORE'][$global_type]['THUMBNAILS'].'/'.$file_details['filename'].'.png');
    }
  }

  // Looks like we ran out of options
  return FALSE;
}


/*----------------------------------------------------------------------------
  Outputs a file if it exists with the correct MIME type and exits
----------------------------------------------------------------------------*/
function _camera_show_file($file)
{
  // Check the file actually exists
  if(!is_file($file))
    return FALSE;

  // Get the MIME type
  if(!($finfo = finfo_open(FILEINFO_MIME_TYPE)) || !($mime = finfo_file($finfo, $file)))
    return FALSE;
  finfo_close($finfo);

  // Set a cache duration
  header('Cache-Control: max-age=3600');
  header('Expires: '.gmdate('D, d M Y H:i:s', time()+3600).' GMT');

  // Dump the file
  header('Content-type: '.$mime);
  echo file_get_contents($file);

  exit();
}


/*----------------------------------------------------------------------------
  Takes an image and returns the filename (without the extension)
----------------------------------------------------------------------------*/
function camera_take_image()
{
  if(!is_dir($GLOBALS['CAMERA_STORE']['IMAGES']['FILES']))
    return FALSE;

  // Temporary until we get the options working
  $extension = 'jpg';

  // Generate a filename
  $filename = date('Y-m-d_H-i-s_U');

  // Take the photo
  exec('/usr/bin/raspistill -t 0 -o '.$GLOBALS['CAMERA_STORE']['IMAGES']['FILES'].'/'.$filename.'.'.$extension, $output, $return_var);

  // Because raspistill doesn't return the correct exit code we have to manually test the output for content
  if($return_var || trim(implode('', $output)) != '')
    return FALSE;

  return $filename.'.'.$extension.PHP_EOL.$filename.'.png';
}
