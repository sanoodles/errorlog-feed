<?php
/*
<application>
    <name>
        error_log feed
    </name>
    <description>
        Valid and warning-free RSS feed with the lines of all error_log
        files nested from the REL_START_DIR directory
    </description>
    <keywords>
        apache, error_log, error.log, feed, rss, rdf
    </keywords>
    <updates>
        https://github.com/sanoodles/errorlog-feed/commits/master.atom
    </updates>
    <license>
        GNU GPL v3
    </license>
    <author>
        Samuel Gómez
    </author>
    <mail>
        samuelgomezcrespo@gmail.com
    </mail>
</application>
*/

/**
 * @section setings
 * 1. required settings
 * 2. optional settings
 * 3. expert settings
 */

// @subsection required settings

/* site url
 * not very useful for now
 * @example "http://www.example.com/"
 */
define("SITE_URL", "http://www.example.com/");

/* absolute error_log feed directory
 * this directory should be not public since error_log feed allows to delete error_log files
 * @example "https://www.example.com/admin/"
 */
define("ABS_ELF_DIR",
       "http://www.islabinaria.com/samu/errorlogfeed/demo/");

/* relative path from ABS_ELF_DIR to start the scan for error_log files
 * @example "./", "../", "a_subdir/", "../a_brotherdir", ...
 */
define("REL_START_DIR", "./");

/* name of the error_log files
 * @example "error_log" (linux default), "error.log" (windows default), ...
 */
define("LOG_NAME", "error_log");

/* date format used in the lines of error_log files
 * eg: "D M d H:i:s Y", "d-M-Y H:i:s", ..
 * default apache error_log date format is "D M d H:i:s Y",
 *   as in "Mon Jul 09 20:53:33 2007"
 * to determine your error_log format,
 * @see: http://www.php.net/manual/en/function.date.php
 * @example the format of "08-Jan-2007 17:09:55" is "d-M-Y H:i:s"
 */
define("LOG_DATE_FORMAT", "d-M-Y H:i:s");

/* required if PHP >= 5.1.0
 * one value among http://www.php.net/manual/en/timezones.php
 * @example "Europe/Madrid"
 * @see http://www.php.net/manual/en/timezones.europe.php
 */
define("TIMEZONE", "Europe/Madrid");

// @subsection optional settings
    define("FEED_TITLE", "error_log feed demo");
    define("FEED_DESCRIPTION", "This is a demo of error_log feed");
    define("STYLESHEET", ""); // stylesheet path, if any
    
/* @subsection expert settings 
 * don't change them unless you know what are you doing!
 */
    define("DEBUG_MODE", false);
    define("ELF_NAME", "errorlogfeed.php");
    define("ELF_URI", ABS_ELF_DIR . ELF_NAME);
    define("FILE_PREFIX", " in ");
    define("LINE_PREFIX", " on line ");
    define("READ_LIMIT", 256 * 1024); // in bytes. default: 256 KB

/**
 *   internal functions calls tree
 *   by being a function appears on the function browser of the editor
 *
 *   do_actions
 *       file_delete
 *   errors_get_all
 *       errors_get_from_file
 *   rdf_channel
 *       errors_get_last
 *       error_split
 *           error_get_date
 *               dates_interconv
 *                   Mtom
 *           error_get_script // not used
 *   rdf_items
 *       error_split
 *           error_get_date
 *               dates_interconv
 *                   Mtom
 */

function test($v)
{
    echo "<!-- \n";
    print_r($v);
    echo "\n -->";
}

/**
 * @pre $v is a date in "M" PHP date format
 * @return a date in "m" PHP date format
 * @see http://www.php.net/date
 */
function Mtom($v)
{
    $a = array(
      "Jan" => "01",
      "Feb" => "02",
      "Mar" => "03",
      "Apr" => "04",
      "May" => "05",
      "Jun" => "06",
      "Jul" => "07",
      "Aug" => "08",
      "Sep" => "09",
      "Oct" => "10",
      "Nov" => "11",
      "Dec" => "12",
    );
    return $a[$v];
}

/**
 * Converts a date and time string from one format to another
 * 
 * @param string $date_format1
 * @param string $date_format2
 * @param string $date_str
 * @return string
 * @example dates_interconv("d.m.Y", "Y/d/m", "31.12.1999")
 * @example dates_interconv("d/m/Y", "Y-m-d", "31/12/1999")
 * @see http://www.php.net/manual/en/function.date.php#71397
 */
function dates_interconv($date_format1, $date_format2, $date_str)
{
    $base_struc     = split('[:/.\ \-]', $date_format1);
    $date_str_parts = split('[:/.\ \-]', $date_str );

    $date_elements = array();

    $p_keys = array_keys($base_struc);
    foreach ($p_keys as $p_key) {
        if (!empty($date_str_parts[$p_key])) {
            $date_elements[$base_struc[$p_key]] = $date_str_parts[$p_key];
        } else {
            return false;
        }
    }

    if (array_key_exists('M', $date_elements)) {
        $date_elements['m'] = Mtom($date_elements['M']);
    }

    $dummy_ts = mktime(
        $date_elements['H'],
        $date_elements['i'],
        $date_elements['s'],
        $date_elements['m'],
        $date_elements['d'],
        $date_elements['Y']
    );

    return date($date_format2, $dummy_ts);
}

/**
 * @pre $error is a string which starts with a date between "[" and "]"
 * @return the date between "[" and "]" in valid RDF date format
 * @example error_get_date("[08-Jan-2007 17:09:55]") outputs "2007-01-08T17:09:55+01:00"
 * @see http://validator.w3.org/feed/docs/error/InvalidW3CDTFDate.html
 * @see http://www.w3.org/TR/NOTE-datetime
 */
function error_get_date($error)
{
    // read date span. eg: "08-Jan-2007 17:09:55"
    $openpos = strpos($error, "["); // eg: 0
    $closepos = strpos($error, "]"); // eg: 21
    $date = substr($error, $openpos+1, ($closepos - $openpos - 1));

    $res = dates_interconv(LOG_DATE_FORMAT, "Y-m-d\TH:i:sO", $date);
    $res = substr($res, 0, -2) . ":" . substr($res, -2, 2);

    return $res;
}

function error_get_script($error)
{
    $prefixpos = strpos($error, FILE_PREFIX);
    $sufixpos = strpos($error, LINE_PREFIX);
    $inipos = $prefixpos+strlen(FILE_PREFIX);
    $finpos = $sufixpos;
    $res = substr($error, $inipos, ($finpos-$inipos));
    return $res;
}

/**
 * @param string error
 * @pre error is a string which starts with a date between "[" and "]"
 *   eg: "[08-Jan-2007 17:09:55]"
 * @return array of two positions:
 *  "date": the date at the beginning of the line
 *  "description": the whole string
 */
function error_split($error)
{
    // date
    $date = error_get_date($error);

    // script
    // $script = error_get_script($error);
    $script = "";

    // description
    $description = $error;

    return array(
        "date" => $date,
        "script" => $script,
        "description" => $description
    );
}

/**
 * @param string dir
 * @pre the format of $dir is:
 *   "" for the own error_log feed directory
 *   "../" for the error_log feed parent directory
 *   "a/" for a subdirectory of the error_log feed directory called "a"
 * @return array of relative paths from $dir to its subfolders
 */
function get_folders($dir = "")
{
    $sub_dir = "";
    $res = array();
    $i = 0;

    // yes is a directory
    if (is_dir($dir)) {

        if ($dh = opendir("./".$dir)) { // directory handler

            // for each subitem (directory or not)
            while (($file = readdir($dh)) !== false) {

                // no is itself or its parent
                if ($file != "." && $file != "..") {

                    $sub_dir = $dir.$file;

                    // yes subitem is directory
                    if (is_dir($sub_dir)) {

                        $sub_dir .= "/";
                        $res[count($res)] = $sub_dir;
                        $res = array_merge($res, get_folders($sub_dir));

                        $i++;

                    // end subitem is directory
                    }

                // end is itself or its parent
                }

            // end while
            }

            closedir($dh);
        } // fi directory handler

    // no is a directory
    } else {

        echo "\"".$dir."\" is not a directory";

    // end is a directory
    }

    return $res;
}

/**
 * @param string dir
 * @return array with all the lines of the error_log file in $dir directory
 */
function errors_get_from_file($dir)
{
    $path = $dir.LOG_NAME;
    $errors = array();
    $i = 0;

    $fh = fopen($path, "r");

    // for each line of error_log
    while (!feof($fh)) {
        $error = "";
        $error = fgets($fh);
        if ($error != "") {

            // error_log line
            $errors[$i] = $error;

            // error_log file path
            $errors[$i] .= "<br />\n<br />\n @ ".$path;

            // error_log file   button
            $href = ELF_URI."?a=del&amp;path=".$dir;
            $text = "Delete ".$path;
            $link = "<a href=\"".$href."\">".$text."</a>";
            $errors[$i] .= " - ".$link;

        }
        $i++;
    }

    fclose($fh);

    return $errors;
}

function error_from_string($msg)
{
    $res = array("[" . date(LOG_DATE_FORMAT) . "] " . $msg);
    return $res;
}

/**
 * @return array of all the lines of the error_log files in the current folder and all its subfolders
 */
function errors_get_all()
{
    $bytes_read_c = 0; // bytes read currently
    $bytes_read_c_kb = 0; // kbytes read currently

    $all_errors = array();
    $el_path = ""; // error_log path

    // folders are the REL_START_DIR folder and its subfolders
    $folders = array_merge(
                           array(REL_START_DIR),
                           get_folders(REL_START_DIR)
                          );
    sort($folders);

    // for each folder
    foreach ($folders as $k => $v) {

        $el_path = $v.LOG_NAME;

        // yes error_log file exists
        if (file_exists($el_path)) {

            $fs = filesize($el_path);
            $fskb = ceil($fs / 1024);
            if (DEBUG_MODE) {
                test ($el_path.": ".$fskb." KB");
            }

            if (($bytes_read_c + $fs) <= READ_LIMIT) {

                $bytes_read_c += $fs;
                $bytes_read_c_kb += $fskb;

                // file errors
                $file_errors = errors_get_from_file($v);

                // all errors .= file errors
                $all_errors = array_merge($all_errors, $file_errors);

            } else {

                $msg = "<br />\n".
                    $fskb . " KB is the size of \"" . $el_path . "\".<br />\n".
                    $bytes_read_c_kb . " KB have been read yet from other files.<br />\n".
                    ceil(READ_LIMIT / 1024) . " KB is the limit to read.<br />\n".
                    "This file will not be read.<br />\n".
                    "You can change this limit by modifying the READ_LIMIT constant.";
                $all_errors = array_merge($all_errors,
                                          error_from_string($msg));

            }

        // end error_log file exists
        }
    }

    return $all_errors;

}

function errors_get_last($errors)
{
    return $errors[(count($errors))-1];
}

function rdf_channel($errors)
{
?>
    <channel rdf:about="<?php echo ELF_URI; ?>">
        <title><?php echo FEED_TITLE; ?></title>
        <link><?php echo ELF_URI; ?></link>
        <description><?php echo FEED_DESCRIPTION; ?></description>
        <dc:language>en</dc:language>
<?php
        // date
        // items
            // ...

        // last error date
        $last = errors_get_last($errors);
        $split = error_split($last);
        $date = $split["date"];
?>
        <dc:date><?php echo $date; ?></dc:date>
        <items>
            <rdf:Seq>
<?php
            // error list
            for ($i = 0; $i < count($errors); $i++) {

                $error = $errors[$i];
?>
<rdf:li rdf:resource="<?php echo SITE_URL."#".sprintf("%05s", $i); ?>" />
<?php
            // ffor
            }
?>
            </rdf:Seq>
        </items>
    </channel>
<?php
}

function rdf_items($errors)
{
    reset($errors);
    for ($i = 0; $i < count($errors); $i++) {

        $uri = SITE_URL."#".sprintf("%05s", $i);
        $title = "#".sprintf("%'_5s", $i);
        $split = error_split($errors[$i]);
        $date = $split["date"];
        $description = $split["description"];
?>
<item rdf:about="<?php echo $uri; ?>">
<title><?php echo $title; ?></title>
<link><?php echo $uri; ?></link>
<dc:date><?php echo $date; ?></dc:date>
<description><?php echo htmlspecialchars($description); ?></description>
</item>
<?php
    }
}

function file_delete()
{
    $path = $_GET["path"].LOG_NAME;
    if (file_exists($path)) {
        unlink($path);
    }
}

function do_actions()
{
    // delete
    if ($_GET["a"] == "del") {
        file_delete();
    }
}

function init()
{
    // to measure the script execution time
    $initime = explode(" ", microtime());
    $initime = $initime[0] + $initime[1];

    // to carry out with Strict Standards on PHP 5
    if (version_compare(phpversion(), "5.1.0", ">=")) {
        date_default_timezone_set(TIMEZONE);
    }

    return $initime;
}

function print_exec_time($initime)
{
    // display the script execution time with milliseconds resolution
    $fintime = explode(" ", microtime());
    $fintime = $fintime[0] + $fintime[1];
    echo "<!-- ".number_format($fintime-$initime, 3, ".", "")." -->\n";
}

function main()
{
    $initime=init();

    // HTTP HEADER
    header("Content-type: application/rss+xml; charset=iso-8859-1");

    // XML HEADER
    echo "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";
    if (STYLESHEET != "") {
        echo "<?xml-stylesheet".
             " href=\"".STYLESHEET."\" type=\"text/css\"?>\n";
    }

    do_actions();

    // RDF DATA
?>
    <rdf:RDF
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns="http://purl.org/rss/1.0/">
<?php
    // get lines
    $errors=errors_get_all();

    // print channel
    rdf_channel($errors);

    // print items
    if (!DEBUG_MODE) {
        rdf_items($errors);
    }

    // print execution time
    print_exec_time($initime);
?>
    </rdf:RDF>
<?php
}

main();
?>
