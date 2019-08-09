<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
ob_start();
error_reporting(E_ALL);

$ProperInclusion = true;

require("include/php/info.php");
require("include/php/functions.php");

$sessionid = session_id();

$md5 = $_GET["hash"];

  $result = db_sql("SELECT * FROM files where filename_server = '$md5'");
  $numrow = mysqli_num_rows($result);
  $result = mysqli_fetch_assoc($result);

if($numrow != '1') {

echo "Filen findes ikke i DB";
exit();

}


$filenameserver = $result['filename_server'];
$filenameoriginal = $result['filename_original'];


//$file_path  = $_REQUEST['file'];
$file_path  = $filenameoriginal;
$path_parts = pathinfo($file_path);
$file_name  = $path_parts['basename'];
$file_ext   = $path_parts['extension'];
$file_path  = 'files/' . $file_name;
$file_path2  = 'files/' . $filenameserver;


// allow a file to be streamed instead of sent as an attachment
$is_attachment = isset($_REQUEST['stream']) ? false : true;

        @ini_set('zlib.output_compression', 'Off');
        @ini_set('output_buffering', 'Off');

//echo "1";
//exit();

// make sure the file exists
if (is_file($file_path2))
{
        $file_size  = filesize($file_path2);
        $file = @fopen($file_path2,"rb");
        if ($file)
        {
                // set the headers, prevent caching
                header("Pragma: public");
                header("Expires: -1");
                header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
                header("Content-Disposition: attachment; filename=\"$file_name\"");

        // set appropriate headers for attachment or streamed file
        if ($is_attachment) {
                header("Content-Disposition: attachment; filename=\"$file_name\"");
        }
        else {
                header('Content-Disposition: inline;');
                header('Content-Transfer-Encoding: binary');
        }

        // set the mime type based on extension, add yours if needed.

        $ctype_default = "application/octet-stream4";
        $content_types = array(
                "exe" => "application/octet-stream",
                "zip" => "application/zip",
                "mp3" => "audio/mpeg",
                "mpg" => "video/mpeg",
                "avi" => "video/x-msvideo",
        );
        $ctype = isset($content_types[$file_ext]) ? $content_types[$file_ext] : $ctype_default;
        header("Content-Type: " . $ctype);

                //check if http_range is sent by browser (or download manager)
                if(isset($_SERVER['HTTP_RANGE']))
                {
                        list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                        if ($size_unit == 'bytes')
                        {
                                //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                                //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                                list($range, $extra_ranges) = explode(',', $range_orig, 2);
                        }
                        else
                        {
                                $range = '';
                                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                                exit;
                        }
                }
                else
                {
                        $range = '';
                }

                //figure out download piece from range (if set)
                list($seek_start, $seek_end) = explode('-', $range, 2);

                //set start and end based on range (if set), else set defaults
                //also check for invalid ranges.
                $seek_end   = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)),($file_size - 1));
                $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);

                //Only send partial content header if downloading a piece of the file (IE workaround)
                if ($seek_start > 0 || $seek_end < ($file_size - 1))
                {
                        header('HTTP/1.1 206 Partial Content');
                        header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$file_size);
                        header('Content-Length: '.($seek_end - $seek_start + 1));
                }
                else
                  header("Content-Length: $file_size");

                header('Accept-Ranges: bytes');

                set_time_limit(0);
                fseek($file, $seek_start);
              while(!feof($file))
                {
                        print(@fread($file, 1024*8));
                        ob_flush();
                        flush();
                        if (connection_status()!=0)
                        {
                                @fclose($file);
                                exit;
                        }
                }

                // file save was a success
                @fclose($file);
                exit;
        }
        else
        {
                // file couldn't be opened
                header("HTTP/1.0 500 Internal Server Error");
                exit;
        }
}
else
{
        // file does not exist
        header("HTTP/1.0 404 Not Found");
        exit;
}


?>
